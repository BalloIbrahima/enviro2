<?php

use Kubio\Flags;

function kubio_api_update_ui_version( WP_REST_Request $request ) {
	$next_version       = intval( $request['version'] );
	$available_versions = array( 1, 2 );
	if ( in_array( $next_version, $available_versions ) ) {
		Flags::setSetting( 'editorUIVersion', $next_version );
	} else {
		return new WP_Error( 'kubio_invalid_ui_version' );
	}

	return array( 'version' => $next_version );
}


add_action(
	'rest_api_init',
	function () {
		$namespace = 'kubio/v1';

		register_rest_route(
			$namespace,
			'/update-ui-version',
			array(
				'methods'             => 'GET',
				'callback'            => 'kubio_api_update_ui_version',
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},

			)
		);
	}
);
