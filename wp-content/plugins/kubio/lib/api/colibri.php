<?php

use IlluminateAgnostic\Arr\Support\Arr;

add_action(
	'rest_api_init',
	function () {
		$namespace = 'kubio/v1';

		register_rest_route(
			$namespace,
			'/enable-theme',
			array(
				'methods'             => 'GET',
				'callback'            => 'kubio_enable_theme',
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);

		register_rest_route(
			$namespace,
			'/colibri-data-export',
			array(
				'methods'             => 'GET',
				'callback'            => 'kubio_colibri_data_export',
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);

		register_rest_route(
			$namespace,
			'/colibri-data-export-done',
			array(
				'methods'             => 'GET',
				'callback'            => 'kubio_colibri_data_export_done',
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);
	}
);

function kubio_get_installed_theme() {
	$themes       = wp_get_themes();
	$kubio_themes = array( 'kubio', 'elevate-wp' );
	$intersect    = array_values( array_intersect( array_keys( $themes ), $kubio_themes ) );
	$theme        = Arr::get( $intersect, 0, null );
	return $theme;
}

function kubio_enable_theme( WP_REST_Request $data ) {
	switch_theme( $data['name'] );
	return wp_send_json( array( 'switched' => $data['name'] ) );
}

function kubio_colibri_data_export_done() {

	// copy menus location from colibri to kubio;
	$colibri_options = get_option( 'theme_mods_colibri-wp', array() );
	$kubio_options   = get_option( 'theme_mods_kubio', array() );

	$colibri_locations = Arr::get( $colibri_options, 'nav_menu_locations', array() );
	$kubio_locations   = Arr::get( $kubio_options, 'nav_menu_locations', array() );

	$locations_map = array(
		'header-menu'   => 'header-menu',
		'footer-menu'   => 'footer-menu',
		'footer-menu-1' => 'footer-menu-secondary',
		'header-menu-1' => 'header-menu-secondary',
	);

	foreach ( $colibri_locations as $location => $menu ) {
		$location                     = Arr::get( $locations_map, $location, $location );
		$kubio_locations[ $location ] = $menu;
	}

	Arr::set( $kubio_options, 'nav_menu_locations', $kubio_locations );

	if ( $theme = kubio_get_installed_theme() ) {
		update_option( "theme_mods_{$theme}", $kubio_options );
		switch_theme( $theme );

		flush_rewrite_rules();
		update_option( 'theme_switched', false );
	}

	wp_send_json_success();
}

function kubio_colibri_data_export() {

	$theme = kubio_get_installed_theme();

	if ( $theme ) {
		ob_clean();

		// templates are created with colibri-wp theme on kubio activation //
		foreach ( get_block_templates( array(), 'wp_template' ) as $template ) {
			wp_set_post_terms( $template->wp_id, $theme, 'wp_theme' );
		}

		echo wp_json_encode(
			\ExtendBuilder\export_colibri_data(
				array( 'exclude_generated' => true ),
				false
			)
		);
		  switch_theme( $theme );
	} else {
		wp_send_json_error(
			array(
				'error' => 'kubio-not-installed',
			)
		);
	}
}

add_filter(
	'colibri_page_builder/get_partial_details',
	function ( $data ) {
		$post     = get_post( $data['id'] );
		$new_data = array(
			'title'         => $post->post_title,
			'page_template' => $post->page_template,
			'parent'        => $post->post_parent,
		);
		return array_merge( $data, $new_data );
	},
	100
);
