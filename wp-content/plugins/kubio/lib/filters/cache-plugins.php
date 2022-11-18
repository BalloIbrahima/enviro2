<?php

use Kubio\Core\Utils;

function kubio_clean_cache_plugins( $post_id, $post, $update ) {

	$post_types = array( 'page', 'post', 'wp_template', 'wp_template_part', kubio_global_data_post_type() );

	if ( ! $update || $post->post_status !== 'publish' || ! in_array( $post->post_type, $post_types ) ) {
		return;
	}

	try {

		// WP Super Cache
		if ( Utils::hasEnoughRemainingTime( 15 ) ) {
			if ( function_exists( 'wp_cache_clean_cache' ) ) {
				global $file_prefix;
				$prefix = '';
				if ( $file_prefix ) {
					$prefix = $file_prefix;
				}
				wp_cache_clean_cache( $prefix, true );
			}
		}

		// Autoptimize
		if ( Utils::hasEnoughRemainingTime( 15 ) ) {
			if ( class_exists( 'autoptimizeCache' ) && method_exists( autoptimizeCache::class, 'clearall' ) ) {
				autoptimizeCache::clearall();
			}
		}

		//  W3 Total Cache
		if ( Utils::hasEnoughRemainingTime( 15 ) ) {
			if ( function_exists( 'w3tc_flush_all' ) ) {
				w3tc_flush_all();
			}
		}
	} catch ( \Exception $e ) {

	}
}

add_action( 'wp_after_insert_post', 'kubio_clean_cache_plugins', 10, 3 );
