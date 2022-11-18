<?php


function kubio_wpform_get_forms() {
	$wp_forms = wpforms()->form->get( '', array( 'order' => 'DESC' ) );
	$wp_forms = ! empty( $wp_forms ) ? $wp_forms : array();
	$wp_forms = array_map(
		function ( $form ) {
			return array(
				'label' => htmlspecialchars_decode( $form->post_title, ENT_QUOTES ),
				'value' => $form->ID,
			);
		},
		$wp_forms
	);
	return $wp_forms;
}
function kubio_contact_forms_by_type( WP_REST_Request $data ) {

	$forms = array();
	if ( class_exists( '\Forminator_GFBlock_Forms' ) ) {
		$forminator_forms = Forminator_GFBlock_Forms::get_instance()->get_forms();

		// remove the empty form
		array_shift( $forminator_forms );
		if ( count( $forminator_forms ) === 0 ) {
			kubio_forminator_create_sample_form();
			$forminator_forms = Forminator_GFBlock_Forms::get_instance()->get_forms();
			array_shift( $forminator_forms );
		}
		if ( count( $forminator_forms ) > 0 ) {
			$forms['forminator'] = $forminator_forms;
		}
	}
	if ( class_exists( '\WPCF7' ) ) {
		$contact_form7_items = array();
		$args                = array(
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => - 1,
		);
		if ( $data = get_posts( $args ) ) {
			foreach ( $data as $key ) {
				$contact_form7_items[] = array(
					'label' => $key->post_title,
					'value' => $key->ID,
				);
			}
		}
		if ( count( $contact_form7_items ) > 0 ) {
			$forms['contactForm7'] = $contact_form7_items;
		}
	}
	if ( class_exists( 'WPForms' ) ) {
		$wp_forms = kubio_wpform_get_forms();
		if ( count( $wp_forms ) > 0 ) {

			$forms['wpForms'] = $wp_forms;
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
			'/contact-form/forms_by_type',
			array(
				'methods'             => 'GET',
				'callback'            => 'kubio_contact_forms_by_type',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},

			)
		);
	}
);
