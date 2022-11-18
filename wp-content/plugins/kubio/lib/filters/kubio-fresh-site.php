<?php

function _kubio_set_fresh_site() {
	update_option( 'kubio_is_fresh_site', 1 );
}

function kubio_is_fresh_site() {
	return ! ! intval( get_option( 'kubio_is_fresh_site', 0 ) );
}


function _kubio_remove_fresh_install_flag() {
	update_option( 'kubio_is_fresh_site', 0 );
}

function kubio_add_fresh_site_removal_hooks() {

	// do not automatically change the fresh_site flag while in cli
	if ( defined( 'WP_CLI' ) ) {
		return;
	}

	foreach (
		array(
			'publish_post',
			'publish_page',
			'wp_ajax_save-widget',
			'wp_ajax_widgets-order',
			'customize_save_after',
			'rest_after_save_widget',
			'rest_delete_widget',
			'rest_save_sidebar',
		) as $action
	) {
		add_action( $action, '_kubio_remove_fresh_install_flag', 0 );
	}
}

kubio_add_fresh_site_removal_hooks();
