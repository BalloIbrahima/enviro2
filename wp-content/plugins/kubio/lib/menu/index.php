<?php



if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

require_once __DIR__ . '/locations.php';


function kubio_register_rest_menu_location() {
	$nav_menu_location = new WP_REST_Menu_Locations_Controller();
	$nav_menu_location->register_routes();
}

function kubio_api_nav_menus_taxonomy_args( $args, $taxonomy ) {
	if ( 'nav_menu' === $taxonomy ) {
		$args['show_in_rest']          = true;
		$args['rest_base']             = 'menus';
		$args['rest_controller_class'] = 'WP_REST_Menus_Controller';
	}

	return $args;
}

if ( class_exists( 'WP_REST_Controller' ) ) {
	require_once __DIR__ . '/menu-rest-controller.php';

	if ( ! class_exists( 'WP_REST_Menus_Controller' ) ) {
		require_once __DIR__ . '/class-wp-rest-menus-controller.php';



		add_filter( 'register_taxonomy_args', 'kubio_api_nav_menus_taxonomy_args', 10, 2 );
	}


	if ( ! class_exists( 'WP_REST_Menu_Locations_Controller' ) ) {
		require_once __DIR__ . '/class-wp-rest-menu-locations-controller.php';
		add_action( 'rest_api_init', 'kubio_register_rest_menu_location' );
	}

	function kubio_register_rest_menu_controller() {
		$nav_menu_location = new Kubio_Menu_Rest_Controller();
		$nav_menu_location->register_routes();
	}

	add_action( 'rest_api_init', 'kubio_register_rest_menu_controller' );
}




