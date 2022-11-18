<?php


function kubio_save_entity_rest_endpoint( WP_REST_Request $request ) {
	$post_data  = (object) $request['postData'];
	$new_status = $request['status'];
	$type       = $request['type'];
	return apply_filters( "kubio/save-template-entity/{$post_data->type}/{$new_status}", $post_data, $type );
}

add_action(
	'rest_api_init',
	function () {
		$namespace = 'kubio/v1';

		register_rest_route(
			$namespace,
			'/save-entity',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'kubio_save_entity_rest_endpoint',
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},

			)
		);
	}
);
