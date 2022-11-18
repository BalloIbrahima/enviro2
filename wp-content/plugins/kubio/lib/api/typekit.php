<?php

function kubio_typekit_query( WP_REST_Request $data ) {

	$path = $data->get_param( 'path' );
	$key  = $data->get_param( 'key' );

	$url      = 'https://typekit.com/' . trim( $path, '/' );
	$response = wp_remote_get(
		$url,
		array(
			'headers' => array(
				'X-Typekit-Token' => $key,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( $response = json_decode( wp_remote_retrieve_body( $response ) ) ) {
		return $response;
	}

	return new WP_Error( 'Unable to decode' );
}


add_action(
	'rest_api_init',
	function () {
		$namespace = 'kubio/v1';

		register_rest_route(
			$namespace,
			'/typekit-query',
			array(
				'methods'             => 'GET',
				'callback'            => 'kubio_typekit_query',
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},

			)
		);
	}
);
