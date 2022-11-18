<?php

use IlluminateAgnostic\Arr\Support\Arr;

function kubio_get_started_page_tabs() {
	$default_tabs = array(
		'get-started' => array(
			'type'        => 'page',
			'label'       => __( 'Get started with Kubio', 'kubio' ),
			'tab-partial' => 'get-started.php',
			'subtitle'    => __( 'The supercharged block-based WordPress builder', 'kubio' ),
		),
		'demo-sites'  => array(
			'type'        => 'page',
			'label'       => __( 'Starter sites', 'kubio' ),
			'tab-partial' => 'demo-sites.php',
			'subtitle'    => __( 'Beautiful starter sites with 1-click import', 'kubio' ),
		),
	);

	return apply_filters(
		'info_page_tabs',
		$default_tabs
	);
}


/**
 * Renders the kubio Welcome Page
 */

function kubio_get_started_page() {
	kubio_print_admin_page_start();
	$kubio_get_started_page_tabs = kubio_get_started_page_tabs();

	$current_tab = sanitize_key( Arr::get( $_REQUEST, 'tab', 'get-started' ) );

	if ( ! isset( $kubio_get_started_page_tabs[ $current_tab ] ) ) {
		$current_tab = 'get-started';
	}

	$subtitle = $kubio_get_started_page_tabs[ $current_tab ]['subtitle'];

	kubio_print_admin_page_header(
		$subtitle,
		$kubio_get_started_page_tabs
	);

	$tab_path         = Arr::get( $kubio_get_started_page_tabs, "{$current_tab}.tab-partial", null );
	$tab_partial_file = __DIR__ . "/main-page/$tab_path";

	//content
	if ( $tab_path && file_exists( $tab_partial_file ) ) {
		require_once $tab_partial_file;
	} else {
		wp_die( esc_html__( 'Unknown tab partial', 'kubio' ) );
	}

	kubio_print_admin_page_end();
}

/**
 * Registers the new WP Admin Menu
 *
 * @return void
 */
function kubio_get_started_add_menu_page() {
	add_submenu_page(
		'kubio',
		__( 'Kubio - Get Started', 'kubio' ),
		__( 'Get Started', 'kubio' ),
		'edit_posts',
		'kubio-get-started',
		'kubio_get_started_page',
		20
	);

	add_submenu_page(
		'kubio',
		__( 'Kubio - Starter Sites', 'kubio' ),
		__( 'Starter Sites', 'kubio' ),
		'edit_posts',
		'kubio-get-started-starter-sites',
		'kubio_get_started_page__starter_sites',
		20
	);

	if ( ! kubio_is_pro() ) {
		add_submenu_page(
			'kubio',
			__( 'Kubio - Upgrade to Pro', 'kubio' ),
			__( 'Upgrade to Pro', 'kubio' ),
			'edit_posts',
			'kubio-get-started-pro-upgrade',
			'kubio_get_started_page__starter_sites',
			20
		);
	}

	global $submenu;
	foreach ( $submenu['kubio'] as $index => $submenu_item ) {
		if ( $submenu_item[2] === 'kubio-get-started-starter-sites' ) {
			$submenu['kubio'][ $index ][2] = add_query_arg(
				array(
					'tab'  => 'demo-sites',
					'page' => 'kubio-get-started',
				),
				admin_url( 'admin.php' )
			);
		}
		if ( $submenu_item[2] === 'kubio-get-started-pro-upgrade' ) {
			$submenu['kubio'][ $index ][2] = add_query_arg(
				array(
					'tab'  => 'pro-upgrade',
					'page' => 'kubio-get-started',
				),
				admin_url( 'admin.php' )
			);
		}
	}
}

add_action( 'admin_menu', 'kubio_get_started_add_menu_page', 20 );
