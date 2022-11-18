<?php

/**
 * Retrieves a list of unified template objects based on a query.
 *
 * @since 5.8.0
 *
 * @param array  $query {
 *     Optional. Arguments to retrieve templates.
 *
 *     @type array  $slug__in  List of slugs to include.
 *     @type int    $wp_id     Post ID of customized template.
 *     @type string $theme     Theme slug
 *     @type string $area      A 'wp_template_part_area' taxonomy value to filter by (for wp_template_part template type only).
 *     @type string $post_type Post type to get the templates for.
 * }
 * @param string $template_type 'wp_template' or 'wp_template_part'.
 *
 * @return array Templates.
 */

function kubio_get_block_templates( $query = array(), $template_type = 'wp_template' ) {
	$post_type     = isset( $query['post_type'] ) ? $query['post_type'] : '';
	$wp_query_args = array(
		'post_status'    => array( 'auto-draft', 'draft', 'publish' ),
		'post_type'      => $template_type,
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'wp_theme',
				'field'    => 'name',
				'terms'    => isset( $query['theme'] ) ? $query['theme'] : wp_get_theme()->get_stylesheet(),
			),
		),
	);

	if ( 'wp_template_part' === $template_type && isset( $query['area'] ) ) {
		$wp_query_args['tax_query'][]           = array(
			'taxonomy' => 'wp_template_part_area',
			'field'    => 'name',
			'terms'    => $query['area'],
		);
		$wp_query_args['tax_query']['relation'] = 'AND';
	}

	if ( isset( $query['slug__in'] ) ) {
		$wp_query_args['post_name__in'] = $query['slug__in'];
	}

	// This is only needed for the regular templates/template parts post type listing and editor.
	if ( isset( $query['wp_id'] ) ) {
		$wp_query_args['p'] = $query['wp_id'];
	} else {
		$wp_query_args['post_status'] = 'publish';
	}

	$template_query = new WP_Query( $wp_query_args );
	$query_result   = array();
	foreach ( $template_query->posts as $post ) {
		$template = _build_block_template_result_from_post( $post );

		if ( is_wp_error( $template ) ) {
			continue;
		}

		if ( $post_type && ! $template->is_custom ) {
			continue;
		}

		$query_result[] = $template;
	}

	if ( ! isset( $query['wp_id'] ) ) {
		$template_files = _get_block_templates_files( $template_type );
		foreach ( $template_files as $template_file ) {
			$template = _build_block_template_result_from_file( $template_file, $template_type );

			if ( $post_type && ! $template->is_custom ) {
				continue;
			}

			if ( $post_type &&
				isset( $template->post_types ) &&
				! in_array( $post_type, $template->post_types, true )
			) {
				continue;
			}

			$is_not_custom   = false === array_search(
				wp_get_theme()->get_stylesheet() . '//' . $template_file['slug'],
				array_column( $query_result, 'id' ),
				true
			);
			$fits_slug_query =
				! isset( $query['slug__in'] ) || in_array( $template_file['slug'], $query['slug__in'], true );
			$fits_area_query =
				! isset( $query['area'] ) || $template_file['area'] === $query['area'];
			$should_include  = $is_not_custom && $fits_slug_query && $fits_area_query;
			if ( $should_include ) {
				$query_result[] = $template;
			}
		}
	}

	/**
	 * Filters the array of queried block templates array after they've been fetched.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_Block_Template[] $query_result Array of found block templates.
	 * @param array  $query {
	 *     Optional. Arguments to retrieve templates.
	 *
	 *     @type array  $slug__in List of slugs to include.
	 *     @type int    $wp_id Post ID of customized template.
	 * }
	 * @param string $template_type wp_template or wp_template_part.
	 */
	return apply_filters( 'get_block_templates', $query_result, $query, $template_type );
}
