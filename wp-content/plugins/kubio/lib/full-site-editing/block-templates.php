<?php

function kubio_get_block_template( $id, $template_type = 'wp_template' ) {
	if ( function_exists( 'gutenberg_get_block_template' ) ) {
		return gutenberg_get_block_template( $id, $template_type );
	}
	$parts = explode( '//', $id, 2 );
	if ( count( $parts ) < 2 ) {
		$slug  = $id;
		$theme = array( get_stylesheet() );
	} else {
		list( $theme, $slug ) = $parts;
	}

	$wp_query_args = array(
		'post_type'      => $template_type,
		'post_status'    => array( 'auto-draft', 'draft', 'publish', 'trash' ),
		'post_name__in'  => array( $slug ),
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'wp_theme',
				'field'    => 'name',
				'terms'    => $theme,
			),
		),
	);

	$template_query = new WP_Query( $wp_query_args );
	$posts          = $template_query->get_posts();

	if ( count( $posts ) > 0 ) {
		$template = _kubio_build_template_result_from_post( $posts[0] );

		if ( ! is_wp_error( $template ) ) {
			return $template;
		}
	}

	return kubio_get_block_file_template( $id, $template_type );
}

function kubio_has_block_template( $id, $template_type = 'wp_template' ) {

	$parts = explode( '//', $id, 2 );
	$slug  = array_pop( $parts );

	$block_template   = wp_get_theme()->get_file_path( "/templates/{$slug}.html" );
	$classic_template = wp_get_theme()->get_file_path( "/{$slug}.php" );

	if ( file_exists( $block_template ) || file_exists( $classic_template ) ) {
		return true;
	}

	return ! ! kubio_get_block_template( $id, $template_type );
}

/**
 * Build a unified template object based a post Object.
 *
 * @param WP_Post $post Template post.
 *
 * @return WP_Block_Template|WP_Error Template.
 */
function _kubio_build_template_result_from_post( $post ) {
	if ( function_exists( '_gutenberg_build_template_result_from_post' ) ) {
		return _gutenberg_build_template_result_from_post( $post );
	}
	$terms = get_the_terms( $post, 'wp_theme' );

	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	if ( ! $terms ) {
		return new WP_Error( 'template_missing_theme', __( 'No theme is defined for this template.', 'kubio' ) );
	}

	$theme          = $terms[0]->name;
	$has_theme_file = wp_get_theme()->get_stylesheet() === $theme &&
					  null !== _kubio_get_template_file( $post->post_type, $post->post_name );

	$template                 = new WP_Block_Template();
	$template->wp_id          = $post->ID;
	$template->id             = $theme . '//' . $post->post_name;
	$template->theme          = $theme;
	$template->content        = $post->post_content;
	$template->slug           = $post->post_name;
	$template->source         = 'custom';
	$template->type           = $post->post_type;
	$template->description    = $post->post_excerpt;
	$template->title          = $post->post_title;
	$template->status         = $post->post_status;
	$template->has_theme_file = $has_theme_file;

	if ( 'wp_template_part' === $post->post_type ) {
		$type_terms = get_the_terms( $post, 'wp_template_part_area' );
		if ( ! is_wp_error( $type_terms ) && false !== $type_terms ) {
			$template->area = $type_terms[0]->name;
		}
	}

	return $template;
}

function kubio_get_block_file_template( $id, $template_type = 'wp_template' ) {

	if ( function_exists( 'gutenberg_get_block_file_template' ) ) {
		return gutenberg_get_block_file_template( $id, $template_type );
	}
	$parts = explode( '//', $id, 2 );
	if ( count( $parts ) < 2 ) {
		return null;
	}
	list( $theme, $slug ) = $parts;

	if ( wp_get_theme()->get_stylesheet() === $theme ) {
		$template_file = _kubio_get_template_file( $template_type, $slug );
		if ( null !== $template_file ) {
			return _kubio_build_template_result_from_file( $template_file, $template_type );
		}
	}

	return null;
}

/**
 * Build a unified template object based on a theme file.
 *
 * @param array $template_file Theme file.
 * @param array $template_type wp_template or wp_template_part.
 *
 * @return WP_Block_Template Template.
 */
function _kubio_build_template_result_from_file( $template_file, $template_type ) {
	if ( function_exists( '_gutenberg_build_template_result_from_file' ) ) {
		return _gutenberg_build_template_result_from_file( $template_file, $template_type );
	}
	$default_template_types = kubio_get_default_template_types();
	$template_content       = file_get_contents( $template_file['path'] );
	$theme                  = wp_get_theme()->get_stylesheet();

	$template                 = new WP_Block_Template();
	$template->id             = $theme . '//' . $template_file['slug'];
	$template->theme          = $theme;
	$template->content        = kubio_inject_theme_attribute_in_content( $template_content );
	$template->slug           = $template_file['slug'];
	$template->source         = 'theme';
	$template->type           = $template_type;
	$template->title          = $template_file['slug'];
	$template->status         = 'publish';
	$template->has_theme_file = true;

	if ( 'wp_template' === $template_type && isset( $default_template_types[ $template_file['slug'] ] ) ) {
		$template->description = $default_template_types[ $template_file['slug'] ]['description'];
		$template->title       = $default_template_types[ $template_file['slug'] ]['title'];
	}

	if ( 'wp_template_part' === $template_type && isset( $template_file['area'] ) ) {
		$template->area = $template_file['area'];
	}

	return $template;
}

/**
 * Parses wp_template content and injects the current theme's
 * stylesheet as a theme attribute into each wp_template_part
 *
 * @param string $template_content serialized wp_template content.
 *
 * @return string Updated wp_template content.
 */
function kubio_inject_theme_attribute_in_content( $template_content ) {
	if ( function_exists( '_inject_theme_attribute_in_content' ) ) {
		return _inject_theme_attribute_in_content( $template_content );
	}
	$has_updated_content = false;
	$new_content         = '';
	$template_blocks     = parse_blocks( $template_content );

	foreach ( $template_blocks as $key => $block ) {
		if (
			'core/template-part' === $block['blockName'] &&
			! isset( $block['attrs']['theme'] )
		) {
			$template_blocks[ $key ]['attrs']['theme'] = wp_get_theme()->get_stylesheet();
			$has_updated_content                       = true;
		}
	}

	if ( $has_updated_content ) {
		foreach ( $template_blocks as $block ) {
			$new_content .= serialize_block( $block );
		}

		return $new_content;
	}

	return $template_content;
}

/**
 * Retrieves the template file from the theme for a given slug.
 *
 * @access private
 *
 * @param string $template_type wp_template or wp_template_part.
 * @param string $slug template slug.
 *
 * @return array Template.
 * @internal
 *
 */
function _kubio_get_template_file( $template_type, $slug ) {

	if ( function_exists( 'gutenberg_get_block_file_template' ) ) {
		return _gutenberg_get_template_file( $template_type, $slug );
	}

	$template_base_paths = array(
		'wp_template'      => 'block-templates',
		'wp_template_part' => 'block-template-parts',
	);
	$themes              = array(
		get_stylesheet() => get_stylesheet_directory(),
		get_template()   => get_template_directory(),
	);
	foreach ( $themes as $theme_slug => $theme_dir ) {
		$file_path = $theme_dir . '/' . $template_base_paths[ $template_type ] . '/' . $slug . '.html';
		if ( file_exists( $file_path ) ) {
			$new_template_item = array(
				'slug'  => $slug,
				'path'  => $file_path,
				'theme' => $theme_slug,
				'type'  => $template_type,
			);

			if ( 'wp_template_part' === $template_type ) {
				return _kubio_add_template_part_area_info( $new_template_item );
			}

			return $new_template_item;
		}
	}

	return null;
}

/**
 * Attempts to add the template part's area information to the input template.
 *
 * @param array $template_info Template to add information to (requires 'type' and 'slug' fields).
 *
 * @return array Template.
 */
function _kubio_add_template_part_area_info( $template_info ) {
	if ( function_exists( '_gutenberg_add_template_part_area_info' ) ) {
		return _gutenberg_add_template_part_area_info( $template_info );
	}
	if ( WP_Theme_JSON_Resolver::theme_has_support() ) {
		$theme_data = WP_Theme_JSON_Resolver::get_theme_data()->get_template_parts();
	}

	if ( isset( $theme_data[ $template_info['slug'] ]['area'] ) ) {
		$template_info['area'] = kubio_filter_template_part_area( $theme_data[ $template_info['slug'] ]['area'] );
	} else {
		$template_info['area'] = WP_TEMPLATE_PART_AREA_UNCATEGORIZED;
	}

	return $template_info;
}
