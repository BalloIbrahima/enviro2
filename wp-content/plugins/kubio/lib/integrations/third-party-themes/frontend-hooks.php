<?php

use IlluminateAgnostic\Arr\Support\Arr;

function kubio_hybdrid_theme_classic_content_frontend_style( $content ) {

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return $content;
	}

	if ( is_admin() ) {
		return $content;
	}

	if ( kubio_is_hybdrid_theme_iframe_preview() || isset( $_REQUEST['__kubio-rendered-content'] ) ) {
		return $content;
	}
	if ( ! kubio_is_block_template() || ( kubio_is_page_preview() && kubio_3rd_party_theme_is_previewing_theme_template() ) ) {
		return sprintf(
			'<!-- content style : start -->%s<!-- content style : end -->%s',
			kubio_render_page_css(),
			$content
		);
	}

	return $content;
}

add_filter( 'the_content', 'kubio_hybdrid_theme_classic_content_frontend_style', PHP_INT_MAX );

add_filter(
	'language_attributes',
	function ( $html_attrs ) {
		$html_attrs .= ' id="kubio"';

		return $html_attrs;
	}
);


function kubio_3rd_party_theme_is_previewing_theme_template() {
	if ( kubio_is_page_preview() ) {
		global $kubio_preview_located_template_data;
		$template = Arr::get( $kubio_preview_located_template_data, 'template', null );
		if ( $template && strpos( $template, '.php' ) !== false && strpos( wp_normalize_path( $template ), '/template-canvas.php' ) === false ) {
			return true;
		}
	}

	return false;
}

function kubio_dequeue_theme_styles() {

	if ( kubio_theme_has_kubio_block_support() ) {
		return;
	}

	if ( kubio_is_hybdrid_theme_iframe_preview() && Arr::get( $_REQUEST, '__kubio-site-edit-iframe-classic-template' ) ) {
		return;
	}

	if ( kubio_3rd_party_theme_is_previewing_theme_template() ) {
		return;
	}

	$stylesheet_uri                     = get_stylesheet_directory_uri();
					$template_style_uri = get_template_directory_uri();
					$wp_styles          = wp_styles();
					$registered         = $wp_styles->registered;

					// add a normalize and reset style
					$wp_styles->add( 'kubio-3rd-party-theme-template-base', kubio_url( '/lib/integrations/third-party-themes/styles/base.css' ), array(), KUBIO_VERSION );
					// $wp_styles->enqueue( 'kubio-3rd-party-theme-template-base' );
					array_unshift( $wp_styles->queue, 'kubio-3rd-party-theme-template-base' );

	foreach ( $wp_styles->registered as $registered ) {
		$src    = $registered->src;
		$handle = $registered->handle;
		if ( $src && $handle ) {
			if ( strpos( $src, $stylesheet_uri ) === 0 || strpos( $src, $template_style_uri ) === 0 ) {
				$wp_styles->dequeue( $handle );
			}
		}
	}

	do_action( 'kubio/dequeue-theme-styles' );
}


function kubio_maybe_dequeue_theme_styles() {

	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	global $kubio_located_template_data;

	if ( is_array( $kubio_located_template_data ) ) {
		$template  = Arr::get( $kubio_located_template_data, 'template', null );
		$type      = Arr::get( $kubio_located_template_data, 'type', null );
		$templates = Arr::get( $kubio_located_template_data, 'templates', null );

		if ( $template !== null && $type !== null && $templates !== null ) {
			$block_template = resolve_block_template( $type, $templates, $template );
			if ( $block_template && $block_template->wp_id ) {
				$source = get_post_meta( $block_template->wp_id, '_kubio_template_source', true );

				if ( $source === 'kubio' || $source === 'kubio-custom' ) {
					kubio_dequeue_theme_styles();
				}
			}
		}
	}

}

// safari mobile do not transform phone noumbers to link automatically
add_action(
	'wp_head',
	function() {
		echo '<meta name="format-detection" content="telephone=no">';
	}
);

add_action( 'wp_print_styles', 'kubio_maybe_dequeue_theme_styles', PHP_INT_MAX );
