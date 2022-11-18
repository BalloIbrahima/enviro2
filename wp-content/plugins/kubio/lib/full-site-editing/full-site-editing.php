<?php

function kubio_maybe_transform_slug_to_title( $slug ) {

	$slug_parts = explode(
		' ',
		trim(
			preg_replace(
				'/\s\s+/',
				' ',
				str_replace( array( '-', ' ' ), ' ', $slug )
			)
		)
	);
	$title      = implode(
		' ',
		array_map(
			function ( $item ) {
				return ucfirst( $item );
			},
			$slug_parts
		)
	);

	if ( empty( trim( $title ) ) ) {
		$title = $slug;
	}

	return $title;
}

function kubio_get_template_hierarchy( $template_type ) {
	if ( ! in_array( $template_type, kubio_get_template_type_slugs(), true ) ) {
		return array();
	}

	$get_template_function     = 'get_' . str_replace( '-', '_', $template_type ) . '_template'; // front-page -> get_front_page_template.
	$template_hierarchy_filter = str_replace( '-', '', $template_type ) . '_template_hierarchy'; // front-page -> frontpage_template_hierarchy.

	$result                             = array();
	$template_hierarchy_filter_function = function ( $templates ) use ( &$result ) {
		$result = $templates;

		return $templates;
	};

	add_filter( $template_hierarchy_filter, $template_hierarchy_filter_function, 20, 1 );
	call_user_func( $get_template_function ); // This invokes template_hierarchy_filter.
	remove_filter( $template_hierarchy_filter, $template_hierarchy_filter_function, 20 );

	return $result;
}


function kubio_get_template_paths() {

	$template_parts_rels  = array(
		'/full-site-editing/block-templates/*.html',
		'/block-templates/*.html',
	);
	$block_template_files = array();

	foreach ( $template_parts_rels as $template_parts_rel ) {
		$parent_block_template_files = glob( get_stylesheet_directory() . $template_parts_rel );
		$block_template_files        = is_array( $block_template_files ) ? array_merge( $block_template_files, $parent_block_template_files ) : array();

	}

	if ( is_child_theme() ) {
		foreach ( $template_parts_rels as $template_parts_rel ) {
			$child_block_template_files = glob( get_template_directory() . $template_parts_rel );
			$child_block_template_files = is_array( $child_block_template_files ) ? $child_block_template_files : array();
			$block_template_files       = array_merge( $block_template_files, $child_block_template_files );
		}
	}

	return $block_template_files;
}

function kubio_get_template_part_paths( $base_directory ) {
	$path_list = array();
	if ( file_exists( $base_directory . '/full-site-editing/block-template-parts' ) ) {
		$nested_files      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base_directory . '/full-site-editing/block-template-parts' ) );
		$nested_html_files = new RegexIterator( $nested_files, '/^.+\.html$/i', RecursiveRegexIterator::GET_MATCH );
		foreach ( $nested_html_files as $path => $file ) {
			$path_list[] = $path;
		}
	} else {
		if ( file_exists( $base_directory . '/block-template-parts' ) ) {
			$nested_files      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base_directory . '/block-template-parts' ) );
			$nested_html_files = new RegexIterator( $nested_files, '/^.+\.html$/i', RecursiveRegexIterator::GET_MATCH );
			foreach ( $nested_html_files as $path => $file ) {
				$path_list[] = $path;
			}
		}
	}

	return $path_list;
}


function kubio_add_page_top_div() {
	?>
	<div id="page-top" tabindex="-1"></div>
	<?php
}

add_action( 'wp_body_open', 'kubio_add_page_top_div' );
