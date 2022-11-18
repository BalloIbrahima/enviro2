<?php


function kubio_subscribe_forms_by_type( WP_REST_Request $data ) {

	$forms = array();
	if ( class_exists( '\MC4WP_Plugin' ) ) {

		$mailchimp_items = array();
		$args            = array(
			'post_type'      => 'mc4wp-form',
			'posts_per_page' => -1,
		);
		$data            = get_posts( $args );

		if ( count( $data ) === 0 ) {
			kubio_mailchimp_create_sample_form();
			$data = get_posts( $args );
		}
		if ( $data ) {
			foreach ( $data as $key ) {
				$mailchimp_items[] = array(
					'label' => $key->post_title ?: __( 'Untitled form', 'kubio' ),
					'value' => $key->ID,
				);
			}
		}

		if ( count( $mailchimp_items ) > 0 ) {

			$forms['mailchimp'] = $mailchimp_items;
		}
	}

	return $forms;
}


add_action(
	'rest_api_init',
	function () {
		$namespace = 'kubio/v1';

		register_rest_route(
			$namespace,
			'/subscribe-form/forms_by_type',
			array(
				'methods'             => 'GET',
				'callback'            => 'kubio_subscribe_forms_by_type',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},

			)
		);
	}
);
