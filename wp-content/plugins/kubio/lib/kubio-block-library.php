<?php

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Registry;
use Kubio\Core\Utils;

function kubio_prefix_block_category_title( $category_title ) {
	// translators: %s is the category name
	$prefix = __( 'Kubio - %s', 'kubio' );

	return sprintf( $prefix, $category_title );
}

function kubio_block_categories( $categories ) {

	$kubio_categories = array(
		array(
			'slug'  => 'kubio-basic',
			'title' => __( 'Basic Blocks', 'kubio' ),
		),
		array(
			'slug'  => 'kubio-components',
			'title' => __( 'Advanced Blocks', 'kubio' ),
		),
		array(
			'slug'  => 'kubio-site-data',
			'title' => __( 'Site Data Blocks', 'kubio' ),
		),
		array(
			'slug'  => 'kubio-blog-components',
			'title' => __( 'Blog Blocks', 'kubio' ),
		),

		array(
			'slug'  => 'kubio-layout',
			'title' => __( 'Layout', 'kubio' ),
		),

		array(
			'slug'  => 'kubio-template-parts',
			'title' => __( 'Template Parts', 'kubio' ),
		),

	);

	$prefixed_categories = array_map(
		function ( $category ) {
			$title = Arr::get( $category, 'title', '' );
			Arr::set( $category, 'title', kubio_prefix_block_category_title( $title ) );

			$category['isKubio'] = true;

			return $category;
		},
		$kubio_categories
	);

	return array_merge(
		$prefixed_categories,
		$categories
	);
}

add_filter( 'block_categories_all', 'kubio_block_categories', 10, 1 );


function kubio_get_block_metadata( $block_name ) {
	$blocks_dir    = __DIR__ . '/../build/block-library/blocks';
	$metadata_file = "{$blocks_dir}/{$block_name}/block.json";

	return kubio_get_block_metadata_mixin( $metadata_file );
}

function kubio_get_block_metadata_mixin( $mixin ) {
	if ( is_array( $mixin ) ) {
		return $mixin;
	}

	if ( file_exists( $mixin ) ) {
		$metadata = json_decode( file_get_contents( $mixin ), true );

		if ( ! is_array( $metadata ) ) {
			return null;
		}

		return $metadata;
	}

	return null;
}

function kubio_can_register_block( $block_name ) {
	$kubio_editor_only_blocks = array(
		'core/post-content',
	);

	if ( in_array( $block_name, $kubio_editor_only_blocks ) ) {
		return false;
	}

	return true;
}

function kubio_render_block_callback( $attributes, $content, $block ) {
	$context       = $block->context;
	$block_wrapper = Registry::getInstance()->getBlock( $block->parsed_block, $context );

	if ( ! $block_wrapper ) {
		return '';
	}

	$render_fn = 'render';

	/**
	 *  check if a custom serverSideRender function is defined
	 */
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		/** @var $wp WP */
		global $wp;
		$route = (string) Arr::get( $wp->query_vars, 'rest_route', '' );

		if ( strpos( $route, '/block-renderer/' ) !== false && method_exists( $block_wrapper, 'serverSideRender' ) ) {
			$render_fn = 'serverSideRender';
		}
	}

	Registry::getInstance()->addBlockToStack( $block_wrapper );
	$result = $block_wrapper->$render_fn( $block );
	Registry::getInstance()->removeBlockFromStack( $block_wrapper );

	return $result;

}

function kubio_no_block_manifest_notice() {

	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Kubio Error: Blocks manifest file (blocks-manifest.php) does not exists. Please recompile the plugin', 'kubio' ); ?> </p>
	</div>
	<?php

}

function kubio_register_block_types() {

	$library_dir   = __DIR__ . '/../build/block-library/';
	$manifest_file = $library_dir . '/blocks-manifest.php';

	if ( ! file_exists( $manifest_file ) ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', 'kubio_no_block_manifest_notice' );

		}

		return;
	}

	global $kubio_autoloader;

	$blocks_dir      = $library_dir . '/blocks';
	$blocks_manifest = require_once $manifest_file;

	$blocks_namespace = 'Kubio\\Blocks';
	$blocks_class_map = array();
	$block_files      = array();

	foreach ( $blocks_manifest as $block_data ) {
		$block_file = $block_data['rel'];
		$classes    = $block_data['classes'];

		array_push( $block_files, $block_file );

		foreach ( $classes as $class_name ) {
			$blocks_class_map[ "{$blocks_namespace}\\{$class_name}" ] = "{$blocks_dir}/{$block_file}";

		}
	}

	$kubio_autoloader->addClassMap( $blocks_class_map );

	foreach ( $block_files as $file ) {
		require_once "{$blocks_dir}/{$file}";
	}

}


add_action( 'init', 'kubio_register_block_types', 9 );


function kubio_enqueue_editor_assets() {
	wp_enqueue_script( 'kubio-block-library' );
	wp_enqueue_style( 'kubio-block-library-editor' );
	wp_enqueue_style( 'kubio-format-library' );
	wp_enqueue_style( 'kubio-controls' );

}

add_action( 'enqueue_block_editor_assets', 'kubio_enqueue_editor_assets' );


function kubio_register_block_type_from_metadata_array( $metadata, $args = array() ) {
	if ( ! is_array( $metadata ) ) {
		return false;
	}

	$settings          = array();
	$property_mappings = array(
		'title'           => 'title',
		'category'        => 'category',
		'parent'          => 'parent',
		'icon'            => 'icon',
		'description'     => 'description',
		'keywords'        => 'keywords',
		'attributes'      => 'attributes',
		'providesContext' => 'provides_context',
		'usesContext'     => 'uses_context',
		'supports'        => 'supports',
		'styles'          => 'styles',
		'example'         => 'example',
		'apiVersion'      => 'api_version',
	);

	foreach ( $property_mappings as $key => $mapped_key ) {
		if ( isset( $metadata[ $key ] ) ) {
			$value = $metadata[ $key ];
			if ( empty( $metadata['textdomain'] ) ) {
				$settings[ $mapped_key ] = $value;
				continue;
			}
			$textdomain = $metadata['textdomain'];
			switch ( $key ) {
				case 'title':
				case 'description':
					// phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralContext,WordPress.WP.I18n.NonSingularStringLiteralDomain
					$settings[ $mapped_key ] = translate_with_gettext_context( $value, sprintf( 'block %s', $key ), $textdomain );
					break;
				case 'keywords':
					$settings[ $mapped_key ] = array();
					if ( ! is_array( $value ) ) {
						continue 2;
					}

					foreach ( $value as $keyword ) {
						// phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain
						$settings[ $mapped_key ][] = translate_with_gettext_context( $keyword, 'block keyword', $textdomain );
					}

					break;
				case 'styles':
					$settings[ $mapped_key ] = array();
					if ( ! is_array( $value ) ) {
						continue 2;
					}

					foreach ( $value as $style ) {
						if ( ! empty( $style['label'] ) ) {
							// phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain
							$style['label'] = translate_with_gettext_context( $style['label'], 'block style label', $textdomain );
						}
						$settings[ $mapped_key ][] = $style;
					}

					break;
				default:
					$settings[ $mapped_key ] = $value;
			}
		}
	}

	if ( ! empty( $metadata['editorScript'] ) ) {
		$settings['editor_script'] = register_block_script_handle(
			$metadata,
			'editorScript'
		);
	}

	if ( ! empty( $metadata['script'] ) ) {
		$settings['script'] = register_block_script_handle(
			$metadata,
			'script'
		);
	}

	if ( ! empty( $metadata['editorStyle'] ) ) {
		$settings['editor_style'] = register_block_style_handle(
			$metadata,
			'editorStyle'
		);
	}

	if ( ! empty( $metadata['style'] ) ) {
		$settings['style'] = register_block_style_handle(
			$metadata,
			'style'
		);
	}

	/**
	 * Filters the settings determined from the block type metadata.
	 *
	 * @param array $settings Array of determined settings for registering a block type.
	 * @param array $metadata Metadata provided for registering a block type.
	 *
	 * @since 5.7.0
	 *
	 */
	$settings = apply_filters(
		'block_type_metadata_settings',
		array_merge(
			$settings,
			$args
		),
		$metadata
	);

	return WP_Block_Type_Registry::get_instance()->register(
		$metadata['name'],
		$settings
	);
}

/**
 * Returns a joined string of the aggregate serialization of the given parsed
 * blocks.
 *
 * @param WP_Block_Parser_Block[] $blocks Parsed block objects.
 *
 * @return string String of rendered HTML.
 * @since 5.3.1
 *
 */
function kubio_serialize_blocks( $blocks ) {
	$blocks = is_array( $blocks ) ? $blocks : array( $blocks );

	return implode( '', array_map( 'kubio_serialize_block', $blocks ) );
}

/**
 * Returns the content of a block, including comment delimiters, serializing all
 * attributes from the given parsed block.
 *
 * This should be used when preparing a block to be saved to post content.
 * Prefer `render_block` when preparing a block for display. Unlike
 * `render_block`, this does not evaluate a block's `render_callback`, and will
 * instead preserve the markup as parsed.
 *
 * @param WP_Block_Parser_Block $block A single parsed block object.
 *
 * @return string String of rendered HTML.
 * @since 5.3.1
 *
 */
function kubio_serialize_block( $block ) {
	$block_content = '';

	$kubio_attr = Arr::get( $block, 'attrs.kubio', false );

	if ( $kubio_attr ) {
		$kubio_attr = Utils::arrayRecursiveRemoveEmptyBranches( $kubio_attr );
		Arr::set( $block, 'attrs.kubio', $kubio_attr );
	}

	$index = 0;

	if( isset($block['innerContent']) ){
		foreach ( $block['innerContent'] as $chunk ) {
			$block_content .= is_string( $chunk ) ? $chunk : kubio_serialize_block( $block['innerBlocks'][ $index ++ ] );
		}
	}

	if ( ! is_array( $block['attrs'] ) ) {
		$block['attrs'] = array();
	}

	$blockName = $block['blockName'] ?? null;

	return kubio_get_comment_delimited_block_content(
		$blockName,
		$block['attrs'],
		$block_content
	);
}


/**
 * Returns the content of a block, including comment delimiters.
 *
 * @param string|null $block_name Block name. Null if the block name is unknown,
 *                                      e.g. Classic blocks have their name set to null.
 * @param array $block_attributes Block attributes.
 * @param string $block_content Block save content.
 *
 * @return string Comment-delimited block content.
 * @since 5.3.1
 *
 */
function kubio_get_comment_delimited_block_content( $block_name, $block_attributes, $block_content ) {
	if ( is_null( $block_name ) ) {
		return $block_content;
	}

	$serialized_block_name = strip_core_block_namespace( $block_name );
	$serialized_attributes = empty( $block_attributes ) ? '' : wp_json_encode( ( $block_attributes ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' ';

	if ( empty( $block_content ) ) {
		return sprintf( '<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes );
	}

	$serialized_block = sprintf(
		'<!-- wp:%s %s-->%s<!-- /wp:%s -->',
		$serialized_block_name,
		$serialized_attributes,
		$block_content,
		$serialized_block_name
	);

	return $serialized_block;
}
