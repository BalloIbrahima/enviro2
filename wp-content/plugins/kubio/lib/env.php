<?php

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Utils;
use Symfony\Component\Dotenv\Dotenv;

function kubio_get_env( $key, $fallback = null ) {
	static $env_data;

	if ( ! $env_data ) {
		$dotenv = new Dotenv();
		$path   = KUBIO_ROOT_DIR . '/.env';

		if ( file_exists( $path ) ) {
			$env_data = $dotenv->parse( file_get_contents( $path ), $path );
		} else {
			$env_data = array();
		}
	}

	return Arr::get( $env_data, $key, $fallback );
}

if ( Utils::isDebug() ) {

	function kubio_print_live_reload_script() {
		if ( wp_validate_boolean( kubio_get_env( 'LIVE_RELOAD', false ) ) ) {
			$protocol = wp_validate_boolean( kubio_get_env( 'LIVE_RELOAD_SSL', false ) ) ? 'https' : 'http';
			$port     = kubio_get_env( 'LIVE_RELOAD_PORT', 9000 );
			$hostname = kubio_get_env( 'LIVE_RELOAD_HOSTNAME', 'localhost' );

			// phpcs:ignore WordPress.Security.EscapeOutput
			$url = sprintf( '%s://%s:%s/livereload.js', $protocol, $hostname, $port );

			// the url is escaped here
			printf( '<script src="%s"></script>', esc_url( $url ) );
		}

	}

	add_action( 'wp_footer', 'kubio_print_live_reload_script', 99 );
	add_action( 'admin_footer', 'kubio_print_live_reload_script', 99 );
}

function kubio_is_pro() {
	$kubio_root   = untrailingslashit( wp_normalize_path( KUBIO_ROOT_DIR ) );
	$folder_parts = explode( '/', $kubio_root );
	$folder       = array_pop( $folder_parts );
	$pro_flag_defined  = defined( 'KUBIO_IS_PRO' );
	$isPro = $folder === 'kubio-pro' || $pro_flag_defined;
	return apply_filters( 'kubio/is_pro', $isPro );
}
