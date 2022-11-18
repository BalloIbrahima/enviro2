<?php

function kubio_add_3rd_party_theme_support() {
	$stylesheet = get_stylesheet();
	$template   = get_template();

	if ( file_exists( __DIR__ . "/{$template}.php" ) ) {
		require_once __DIR__ . "/{$template}.php";
	}

	if ( file_exists( __DIR__ . "/{$stylesheet}.php" ) ) {
		require_once __DIR__ . "/{$stylesheet}.php";
	}

	$general = glob( __DIR__ . '/general/*.php' );

	foreach ( $general as $file ) {
		require_once $file;
	}
}

add_action( 'init', 'kubio_add_3rd_party_theme_support' );
