<?php


use Kubio\Core\EditInKubioCustomizerPanel;

function kubio_customizer_add_edit_page_in_kubio( $wp_customize ) {

	$wp_customize->add_panel(
		new EditInKubioCustomizerPanel(
			$wp_customize,
			'kubio-edit-in-kubio-section',
			array(
				'capability' => 'manage_options',
				'priority'   => 0,
			)
		)
	);

	return;

}

add_action( 'customize_register', 'kubio_customizer_add_edit_page_in_kubio' );
