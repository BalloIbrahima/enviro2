<?php

use IlluminateAgnostic\Arr\Support\Arr;



function kubio_get_classic_theme_primary_templates() {

	$slugs = array(
		'index',
		'singular',
		'archive',
		'single',
		'page',
		'home',
		'404',
		'search',
	);

	$existing = array();

	foreach ( $slugs as $slug ) {
		$existing[ $slug ] = file_exists( wp_get_theme()->get_file_path( "$slug.php" ) );
	}

	return $existing;
}

function kubio_get_classic_theme_templates( $template_type = 'page' ) {
	$theme = wp_get_theme();
	$files = (array) $theme->get_files( 'php', 1, true );

	$post_templates = array();

	foreach ( $files as $file => $full_path ) {
		if ( ! preg_match( '|Template Name:(.*)$|mi', file_get_contents( $full_path ), $header ) ) {

			if ( $file === 'page.php' && $template_type === 'page' ) {
				$post_templates['page']['page.php'] = __( 'Page', 'kubio' );
			}

			if ( $file === 'single.php' && $template_type === 'post' ) {
				$post_templates['post']['single.php'] = __( 'Single', 'kubio' );
			}

			continue;
		}

		$types = array( 'page' );
		if ( preg_match( '|Template Post Type:(.*)$|mi', file_get_contents( $full_path ), $type ) ) {
			$types = explode( ',', _cleanup_header_comment( $type[1] ) );
		}

		foreach ( $types as $type ) {
			$type = sanitize_key( $type );
			if ( ! isset( $post_templates[ $type ] ) ) {
				$post_templates[ $type ] = array();
			}

			$post_templates[ $type ][ $file ] = _cleanup_header_comment( $header[1] );
		}
	}

	return Arr::get( $post_templates, $template_type, array() );
}


function kubio_third_party_theme_has_front_page_template() {
	$theme = wp_get_theme();
	$files = (array) $theme->get_files( 'php', 1, true );
	return array_key_exists( 'front-page.php', $files );
}


function kubio_third_party_themes_default_block_templates( $templates ) {

	if ( ! kubio_theme_has_kubio_block_support() ) {
		$kubio_default_templates = glob( KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . '/templates/*.html' );

		foreach ( $kubio_default_templates as $template ) {
			$slug               = preg_replace( '#(.*)/templates/(.*).html#', '$2', wp_normalize_path( $template ) );
			$templates[ $slug ] = $template;
		}
	}

	return $templates;
}

add_filter( 'kubio/importer/available_templates', 'kubio_third_party_themes_default_block_templates', 20 );


function kubio_third_party_themes_default_block_template_parts( $templates ) {

	if ( ! kubio_theme_has_kubio_block_support() ) {
		$kubio_default_templates = glob( KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . '/parts/*.html' );

		foreach ( $kubio_default_templates as $template ) {
			$slug               = preg_replace( '#(.*)/parts/(.*).html#', '$2', wp_normalize_path( $template ) );
			$templates[ $slug ] = $template;
		}
	}

	return $templates;

}


add_filter( 'kubio/importer/available_template_parts', 'kubio_third_party_themes_default_block_template_parts', 20 );


function kubio_third_party_themes_is_importing_kubio_template( $current_value, $template ) {
		return ( $current_value || strpos( $template, 'kubio-' ) === 0 );
}

add_filter( 'kubio/template/is_importing_kubio_template', 'kubio_third_party_themes_is_importing_kubio_template', 10, 2 );




function kubio_retrieve_template_source( $object ) {
	$post_id = is_object( $object ) ? $object->wp_id : $object['wp_id'];
	$source  = get_post_meta( $post_id, '_kubio_template_source', true );
	if ( $source === false ) {
		$source = 'custom';
	}
	return $source;
}

function kubio_update_template_source( $value, $object ) {
	$post_id  = $object->wp_id;
	$original = get_post_meta( $post_id, '_kubio_template_source', true );
	update_post_meta( $post_id, '_kubio_template_source', $value, $original );
}


function kubio_register_template_source_rest_field() {

	register_rest_field(
		'wp_template',
		'kubio_template_source',
		array(
			'get_callback'    => 'kubio_retrieve_template_source',
			'update_callback' => 'kubio_update_template_source',
		)
	);

	register_rest_field(
		'wp_template_part',
		'kubio_template_source',
		array(
			'get_callback'    => 'kubio_retrieve_template_source',
			'update_callback' => 'kubio_update_template_source',
		)
	);

}

add_action( 'rest_api_init', 'kubio_register_template_source_rest_field' );
