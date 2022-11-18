<?php

use IlluminateAgnostic\Arr\Support\Arr;

function kubio_hybrid_theme_post_content_placeholder( $content ) {
	global $post;
	if ( $post && $post->post_type === 'page' ) {
		return '<div id="kubio-site-edit-content-holder"></div>';
	}

	return $content;
}

function kubio_hybrid_theme_assets_holder() {
	?>
	<meta style="display:none" id="kubio-site-edit-assets-holder"></meta>
	<?php
}


function kubio_hybrid_theme_iframe_hide_admin_bar() {
	show_admin_bar( false );
}

if ( kubio_is_hybdrid_theme_iframe_preview() ) {
	add_filter( 'the_content', 'kubio_hybrid_theme_post_content_placeholder', 0, 1 );
	add_action( 'wp_head', 'kubio_hybrid_theme_assets_holder', 0 );
	add_action( 'after_setup_theme', 'kubio_hybrid_theme_iframe_hide_admin_bar' );
	add_action( 'template_include', 'kubio_hybrid_theme_load_template' );
}

function kubio_hybrid_theme_load_template( $template ) {
	$classicTemplateId = Arr::get( $_REQUEST, '__kubio-site-edit-iframe-classic-template', false );
	if ( ! $classicTemplateId ) {
		return $template;
	}

	$new_template = locate_template( array( $classicTemplateId ) );
	if ( '' != $new_template ) {
		return $new_template;
	}
	return $template;
}



function kubio_wp_redirect_maybe_add_hybrid_theme_query_param( $location ) {

	if ( kubio_is_hybdrid_theme_iframe_preview() ) {
		$location = add_query_arg( KUBIO_3RD_PARTY_THEME_EDITOR_QUERY_PARAM, 'true', $location );
	}

	return $location;

}

add_filter( 'wp_redirect', 'kubio_wp_redirect_maybe_add_hybrid_theme_query_param' );
