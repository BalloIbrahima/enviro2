<?php

function kubio_template_locate( $template, $type, $templates ) {
	$template_fn = function_exists( 'gutenberg_override_query_template' ) ? 'gutenberg_override_query_template' : 'locate_block_template';

	$filtered_template = null;
	$filtered_template = apply_filters(
		'kubio/template/override-block-filter',
		$filtered_template,
		$type,
		$template,
		$templates
	);

	if ( ! is_null( $filtered_template ) ) {
		return $filtered_template;
	}

	if ( $type === 'frontpage' && get_option( 'show_on_front' ) !== 'page' ) {
		$type      = 'home';
		$templates = array( 'index.php', 'home.php' );

	}

	$template_fn = apply_filters( 'kubio/template/template-loader-callback', $template_fn );

	global $kubio_located_template_data;
	$kubio_located_template_data = array(
		'template'  => $template,
		'type'      => $type,
		'templates' => $templates,
	);

	return call_user_func( $template_fn, $template, $type, $templates );
}

/**
 * Adds necessary filters to use 'wp_template' posts instead of theme template files.
 */
function kubio_add_template_loader_filters() {
	foreach ( kubio_get_template_type_slugs() as $template_type ) {
		if ( 'embed' === $template_type ) { // Skip 'embed' for now because it is not a regular template type.
			continue;
		}

		$tag = str_replace( '-', '', $template_type ) . '_template';
		add_filter( $tag, 'kubio_template_locate', 20, 3 );
	}
}

add_action( 'wp_loaded', 'kubio_add_template_loader_filters' );

/*
 *  zip export
 *
 */
function kubio_find_template_post_and_parts( $template_type, $template_hierarchy = array() ) {
	if ( ! $template_type ) {
		return null;
	}

	if ( empty( $template_hierarchy ) ) {

		if ( 'index' === $template_type ) {
			$template_hierarchy = kubio_get_template_hierarchy( 'index' );
		} else {
			$template_hierarchy = array_merge( array( $template_type ), kubio_get_template_hierarchy( 'index' ) );
		}
	}

	$slugs = array_map(
		function ( $template_file ) {
			return preg_replace( '/\.(php|html)$/', '', $template_file );
		},
		$template_hierarchy
	);

	// Find most specific 'wp_template' post matching the hierarchy.
	$template_query = new WP_Query(
		array(
			'post_type'      => 'wp_template',
			'post_status'    => 'publish',
			'post_name__in'  => $slugs,
			'orderby'        => 'post_name__in',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);

	$current_template_post = $template_query->have_posts() ? $template_query->next_post() : null;

	// Build map of template slugs to their priority in the current hierarchy.
	$slug_priorities = array_flip( $slugs );

	// See if there is a theme block template with higher priority than the resolved template post.
	$higher_priority_block_template_path     = null;
	$higher_priority_block_template_priority = PHP_INT_MAX;
	$block_template_files                    = kubio_get_template_paths();
	foreach ( $block_template_files as $path ) {
		if ( ! isset( $slug_priorities[ basename( $path, '.html' ) ] ) ) {
			continue;
		}
		$theme_block_template_priority = $slug_priorities[ basename( $path, '.html' ) ];
		if (
			$theme_block_template_priority < $higher_priority_block_template_priority &&
			( empty( $current_template_post ) || $theme_block_template_priority < $slug_priorities[ $current_template_post->post_name ] )
		) {
			$higher_priority_block_template_path     = $path;
			$higher_priority_block_template_priority = $theme_block_template_priority;
		}
	}

	// If there is, use it instead.
	if ( isset( $higher_priority_block_template_path ) ) {
		$post_name             = basename( $higher_priority_block_template_path, '.html' );
		$file_contents         = file_get_contents( $higher_priority_block_template_path );
		$current_template_post = array(
			'post_content' => $file_contents,
			'post_title'   => $post_name,
			'post_status'  => 'publish',
			'post_type'    => 'wp_template',
			'post_name'    => $post_name,
		);
		if ( is_admin() || defined( 'REST_REQUEST' ) ) {
			$template_query        = new WP_Query(
				array(
					'post_type'      => 'wp_template',
					'post_status'    => 'publish',
					'name'           => $post_name,
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
			$current_template_post = $template_query->have_posts() ? $template_query->next_post() : $current_template_post;

			// Only create auto-draft of block template for editing
			// in admin screens, when necessary, because the underlying
			// file has changed.
			if ( is_array( $current_template_post ) || $current_template_post->post_content !== $file_contents ) {
				if ( ! is_array( $current_template_post ) ) {
					$current_template_post->post_content = $file_contents;
				}
				$current_template_post = get_post(
					wp_insert_post( $current_template_post )
				);
			}
		} else {
			$current_template_post = new WP_Post(
				(object) $current_template_post
			);
		}
	}

	// If we haven't found any template post by here, it means that this theme doesn't even come with a fallback
	// `index.html` block template. We create one so that people that are trying to access the editor are greeted
	// with a blank page rather than an error.
	if ( ! $current_template_post && ( is_admin() || defined( 'REST_REQUEST' ) ) ) {
		$current_template_post = array(
			'post_title'  => 'index',
			'post_status' => 'publish',
			'post_type'   => 'wp_template',
			'post_name'   => 'index',
		);
		$current_template_post = get_post(
			wp_insert_post( $current_template_post )
		);
	}

	if ( $current_template_post ) {
		$template_part_ids = array();
		if ( is_admin() || defined( 'REST_REQUEST' ) ) {
			foreach ( parse_blocks( $current_template_post->post_content ) as $block ) {
				$template_part_ids = array_merge( $template_part_ids, kubio_create_entity_for_template_part_block( $block ) );
			}
		}

		return array(
			'template_post'     => $current_template_post,
			'template_part_ids' => $template_part_ids,
		);
	}

	return null;
}


function kubio_create_entity_for_template_part_block( $block ) {
	$template_part_ids = array();

	$block_types = apply_filters( 'kubio/preview/template_part_blocks', array() );

	if ( in_array( $block['blockName'], $block_types ) && isset( $block['attrs']['slug'] ) ) {
		if ( isset( $block['attrs']['postId'] ) ) {
			// Template part is customized.
			$template_part_id = $block['attrs']['postId'];
		} else {
			// A published post might already exist if this template part
			// was customized elsewhere or if it's part of a customized
			// template. We also check if an auto-draft was already created
			// because preloading can make this run twice, so, different code
			// paths can end up with different posts for the same template part.
			// E.g. The server could send back post ID 1 to the client, preload,
			// and create another auto-draft. So, if the client tries to resolve the
			// post ID from the slug and theme, it won't match with what the server sent.

			$template_part_query = new WP_Query(
				array(
					'post_type'      => 'wp_template_part',
					'post_status'    => array( 'publish' ),
					'name'           => $block['attrs']['slug'],
					'meta_key'       => 'theme',
					'meta_value'     => $block['attrs']['theme'],
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'tax_query'      => array(
						array(
							'taxonomy' => 'wp_theme',
							'field'    => 'slug',
							'terms'    => $block['attrs']['theme'],
						),
					),
				)
			);
			$template_part_post  = $template_part_query->have_posts() ? $template_part_query->next_post() : null;
			if ( $template_part_post && 'auto-draft' !== $template_part_post->post_status ) {
				$template_part_id = $template_part_post->ID;
			} else {
				// Template part is not customized, get it from a file and make an auto-draft for it, unless one already exists
				// and the underlying file hasn't changed.
				$template_part_file_paths = array(
					get_stylesheet_directory() . '/full-site-editing/block-template-parts/' . $block['attrs']['slug'] . '.html',
					get_stylesheet_directory() . '/block-template-parts/' . $block['attrs']['slug'] . '.html',
				);

				$template_part_file_path = false;

				foreach ( $template_part_file_paths as $tpl_part_path ) {
					if ( file_exists( $tpl_part_path ) ) {
						$template_part_file_path = $tpl_part_path;
						break;
					}
				}

				if ( $template_part_file_path ) {
					$file_contents = file_get_contents( $template_part_file_path );
					if ( $template_part_post && $template_part_post->post_content === $file_contents ) {
						$template_part_id = $template_part_post->ID;
					} else {

						$slug  = $block['attrs']['slug'];
						$title = kubio_maybe_transform_slug_to_title( $slug );
						$area  = WP_TEMPLATE_PART_AREA_UNCATEGORIZED;

						if ( strpos( $slug, 'header' ) !== false ) {
							$area = WP_TEMPLATE_PART_AREA_HEADER;
						} else {
							if ( strpos( $slug, 'footer' ) !== false ) {
								$area = WP_TEMPLATE_PART_AREA_FOOTER;
							} else {
								if ( strpos( $slug, 'sidebar' ) !== false ) {
									$area = WP_TEMPLATE_PART_AREA_SIDEBAR;
								}
							}
						}

						$template_part_id = wp_insert_post(
							array(
								'post_content' => $file_contents,
								'post_title'   => $title,
								'post_status'  => 'publish',
								'post_type'    => 'wp_template_part',
								'post_name'    => $slug,
								'meta_input'   => array(
									'theme' => $block['attrs']['theme'],
								),
								'tax_input'    => array(
									'wp_theme' => $block['attrs']['theme'],
									'wp_template_part_area' => array(
										$area,
									),
								),
							),
							true
						);
					}
				}
			}
		}
		$template_part_ids[ $block['attrs']['slug'] ] = $template_part_id;
	}

	foreach ( $block['innerBlocks'] as $inner_block ) {
		$template_part_ids = array_merge( $template_part_ids, kubio_create_entity_for_template_part_block( $inner_block ) );
	}

	return $template_part_ids;
}
