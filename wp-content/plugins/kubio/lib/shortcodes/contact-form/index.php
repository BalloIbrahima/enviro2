<?php

use Kubio\Core\Utils;

require __DIR__ . '/wpforms-filters.php';
require_once __DIR__ . '/forminator-filters.php';

add_shortcode( 'kubio_contact_form', 'kubio_contact_form_shortcode' );


function kubio_shortcode_is_kubio_contact_form( $shortcode ) {
	return strpos( $shortcode, 'kubio_contact_form' ) !== false;
}


function kubio_forminator_create_sample_form() {
	$createForminatorSampleForm = apply_filters( 'kubio_create_forminator_sample_form', true );
	if ( $createForminatorSampleForm && class_exists( '\Forminator_Template_Contact_Form' ) && class_exists( '\Forminator_API' ) ) {
		try {
			$template = new Forminator_Template_Contact_Form();
			Forminator_API::add_form( 'Kubio contact form', $template->fields(), $template->settings() );
		} catch ( \Exception $e ) {
		}
	}

}

function kubio_get_kubio_contact_form_shortcode( $shortcode ) {

	$matches_found = preg_match( '/shortcode="(.+)"/', $shortcode, $matches );
	if ( ! $matches_found ) {
		return null;
	}
	$inner_shortcode = $matches[1];

	return Utils::shortcodeDecode( $inner_shortcode );

}

function kubio_contact_form_shortcode( $atts ) {

	$atts = shortcode_atts(
		array(
			'shortcode'           => '',
			'use_shortcode_style' => '0',
			'decode_data'         => '1',
		),
		$atts
	);
	if ( $atts['decode_data'] == '1' ) {
		$atts['shortcode'] = Utils::shortcodeDecode( $atts['shortcode'] );
	}

	//stripslashes is fix for http://mantis.extendstudio.net/view.php?id=38520
	$shortcode     = stripslashes( $atts['shortcode'] );
	$shortcodeHtml = '';
	if ( shortcode_render_can_apply_forminator_filters( $shortcode ) ) {
		if ( kubio_forminator_is_auth_form( $shortcode ) ) {
			$shortcodeHtml = kubio_forminator_get_auth_placeholder();
		}
		if ( $atts['use_shortcode_style'] == '0' ) {
			$shortcodeHtml = kubio_forminator_form_shortcode( $shortcode );
		} else {
			$shortcodeHtml = do_shortcode( $shortcode );
		}
	} else {
		$shortcodeHtml = do_shortcode( $shortcode );
	}
	if ( ! $shortcodeHtml ) {
		$shortcodeHtml = Utils::getEmptyShortcodePlaceholder();
	}

	return $shortcodeHtml;

}


function kubio_forminator_get_auth_placeholder() {
	return '<p class="shortcode-placeholder-preview">Forminator\'s login and register forms are not visible if you are logged in</p>';
}

function kubio_forminator_is_auth_form( $shortcode ) {
	$id_found = preg_match( '/id="(\d+)"/', $shortcode, $matches );
	if ( ! $id_found ) {
		return false;
	}
	$form_id    = $matches[1];
	$form_class = null;

	//old
	if ( class_exists( '\Forminator_Custom_Form_Model' ) ) {
		$form_class = '\Forminator_Custom_Form_Model';
	}
	//new
	if ( class_exists( '\Forminator_Form_Model' ) ) {
		$form_class = '\Forminator_Form_Model';
	}

	if ( ! $form_class ) {
		return false;
	}
	try {
		$model = $form_class::model()->load( $form_id );
		if ( ! $model ) {
			return false;
		}

		return in_array( $model->settings['form-type'], array( 'login', 'registration' ) );
	} catch ( \Exception $e ) {
		return false;
	}
}

function kubio_forminator_form_shortcode( $shortcode ) {

	$html = do_shortcode( $shortcode );

	return $html;
}
