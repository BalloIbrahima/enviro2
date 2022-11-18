<?php

use Kubio\Flags;

function kubio_mark_supported_themes_templates_fallback() {

	if ( ! kubio_theme_has_kubio_block_support() ) {
		return;
	}

	if ( ! is_admin() ) {
		return;
	}

	$kubio_default_templates      = array( '404', 'archive-product', 'front-page', 'full-width', 'index', 'page', 'search', 'single-product', 'single' );
	$kubio_default_template_parts = array( 'header', 'sidebar', 'footer', 'front-header' );

	if ( Flags::get( 'kubio_activation_time' ) !== null ) {

		if ( Flags::get( 'kubio_templates_imported' ) === null ) {

			$templates = get_posts(
				array(
					'post_type'      => 'wp_template',
					'post_name__in'  => $kubio_default_templates,
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( $templates as $template ) {
				update_post_meta( intval( $template ), '_kubio_template_source', 'kubio' );
			}

			Flags::touch( 'kubio_templates_imported' );

		}

		if ( Flags::get( 'kubio_template_parts_imported' ) === null ) {

			$template_parts = get_posts(
				array(
					'post_type'      => 'wp_template_parts',
					'post_name__in'  => $kubio_default_template_parts,
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( $template_parts as $part ) {
				update_post_meta( intval( $part ), '_kubio_template_source', 'kubio' );
			}

			Flags::touch( 'kubio_template_parts_imported' );
		}
	}
}

add_action( 'after_setup_theme', 'kubio_mark_supported_themes_templates_fallback' );
