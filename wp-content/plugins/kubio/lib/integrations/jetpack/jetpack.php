<?php

// disable notices for iframe preview
function kubio_iframe_preview_disable_jetpack_notices_scripts()
{

	if (!kubio_is_hybdrid_theme_iframe_preview()) {
		return;
	}

	if (class_exists('Jetpack_Notifications')) {
		remove_action('wp_head', array(Jetpack_Notifications::init(), 'styles_and_scripts'), 120);
		remove_action('admin_head', array(Jetpack_Notifications::init(), 'styles_and_scripts'));
	}
}

add_action('init', 'kubio_iframe_preview_disable_jetpack_notices_scripts');
