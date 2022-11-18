<?php

use IlluminateAgnostic\Arr\Support\Arr;
use IlluminateAgnostic\Str\Support\Str;
use Kubio\Core\Utils;

function & _kubio_find_template_part( &$block, $availableTemplateParts ) {
	if ( in_array( $block['blockName'], $availableTemplateParts ) ) {
		return $block;
	}
	$templatePart = null;
	if ( count( $block['innerBlocks'] ) > 0 ) {
		foreach ( $block['innerBlocks'] as &$innerBlock ) {
			$found_template_part = & _kubio_find_template_part( $innerBlock, $availableTemplateParts );
			if ( ! ! $found_template_part ) {
				$templatePart = & $found_template_part;
			}
		}
	}

	return $templatePart;
}

function kubio_handle_autosaved_posts_and_templates() {
	$autosaved_posts = kubio_get_current_changeset_data( 'autosaves', array() );

	// remap templates to page is needed becaue in editor a page can be changed on the fly
	$page_templates_remap = kubio_get_current_changeset_data( 'pageTemplatesMap', array() );

	$template_part_blocks = apply_filters( 'kubio/preview/template_part_blocks', array() );
	$context_based_blocks = apply_filters(
		'kubio/preview/template_part_blocks',
		array(
			'core/post-content',

		)
	);

	add_filter(
		'render_block_data',
		function ( $parsed_block ) use ( $autosaved_posts, $template_part_blocks ) {
			$isTemplatePart    = in_array( $parsed_block['blockName'], $template_part_blocks );
			$templatePartBlock = & $parsed_block;

			//search inside inner blocks for the template part. This is needed for the sidebar template part
			if ( ! $isTemplatePart ) {
				$nestedTemplatePartBlock = & _kubio_find_template_part( $templatePartBlock, $template_part_blocks );
				$isTemplatePart          = ! ! $nestedTemplatePartBlock && in_array(
					$nestedTemplatePartBlock['blockName'],
					$template_part_blocks
				);
				if ( $isTemplatePart ) {
					$templatePartBlock = & $nestedTemplatePartBlock;
				}
			}
			if ( $isTemplatePart ) {
				$block_template_id = kubio_get_template_part_block_id( $templatePartBlock );
				foreach ( $autosaved_posts as $autosaved_post ) {
					$autosaved_parent = intval( Arr::get( $autosaved_post, 'parent', 0 ) );
					if ( $autosaved_parent === $block_template_id ) {
						$templatePartBlock['attrs']['postId'] = $autosaved_post['id'];
					}
				}
			}
			return $parsed_block;
		},
		10,
		1
	);

	add_filter(
		'render_block_context',
		function ( $context, $parsed_block ) use ( $autosaved_posts, $context_based_blocks ) {
			if ( in_array( $parsed_block['blockName'], $context_based_blocks ) ) {
				foreach ( $autosaved_posts as $autosaved_post ) {
					$autosaved_parent = intval( Arr::get( $autosaved_post, 'parent', 0 ) );
					if ( $autosaved_parent === Arr::get( $context, 'postId', -1 ) ) {
						$post = get_post( $autosaved_post['id'] );

						//check if revision post exists
						if ( $post ) {
							$context['postId'] = $autosaved_post['id'];
							global $wp_query;
							//wordpress updated the core/post-content block in 5.9 to ignore the postId from context
							if ( is_array( $wp_query->posts ) && count( $wp_query->posts ) > 0 ) {
								$firstPost = $wp_query->posts[0];
								if ( $firstPost->ID === $autosaved_post['parent'] ) {
									$wp_query->posts[0] = $post;
								}
							}
						}
					}
				}
			}

			return $context;
		},
		10,
		2
	);

	// filter template output content to use the autosaved data
	add_filter(
		'kubio/template/template-loader-callback',
		function ( $callback ) use ( $autosaved_posts, $page_templates_remap ) {
			return function ( $template, $type, $templates ) use ( $autosaved_posts, $callback, $page_templates_remap ) {

				if ( is_page() || is_single() ) {
					$remapped_template = Arr::get( $page_templates_remap, get_the_ID(), null );

					if ( $remapped_template ) {
						$templates = array( $remapped_template );
					}
				}

				$template_file = call_user_func( $callback, $template, $type, $templates );
				global $kubio_preview_located_template_data;

				$kubio_preview_located_template_data = array();

				if ( Str::endsWith( $template_file, 'template-canvas.php' ) ) {
					if ( Utils::wpVersionCompare( '5.9', '>=' ) ) {
						// use fallback parameter added in 5.9
						/** @var WP_Block_Template $template_data */
						$template_data = resolve_block_template( $template, $templates, $templates[0] );
					} else {
						/** @var WP_Block_Template $template_data */
						$template_data = resolve_block_template( $template, $templates );
					}

					//for third party non fse templates get classic template
					if ( $template_data === null ) {
						$new_template                        = locate_template( $templates );
						$kubio_preview_located_template_data = array( 'template' => $new_template );
						return $new_template;
					}

					$kubio_template_source = get_post_meta( $template_data->wp_id, '_kubio_template_source', true );

					if ( ( $kubio_template_source === 'kubio' || $kubio_template_source === 'kubio-custom' ) && function_exists( 'kubio_dequeue_theme_styles' ) ) {
						add_action( 'wp_print_styles', 'kubio_dequeue_theme_styles', PHP_INT_MAX );
					}

					foreach ( $autosaved_posts as $autosaved_post ) {
						$autosaved_parent = intval( Arr::get( $autosaved_post, 'parent', 0 ) );

						if ( intval( $autosaved_parent ) === intval( $template_data->wp_id ) ) {
							global $_wp_current_template_content;
							$post                         = get_post( $autosaved_post['id'] );
							$_wp_current_template_content = $post->post_content;
							break;
						}
					}
				}

				$kubio_preview_located_template_data = array( 'template' => $template_file );
				return $template_file;
			};

		}
	);
}
