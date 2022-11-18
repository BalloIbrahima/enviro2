<?php

use Kubio\Core\Utils;
use IlluminateAgnostic\Arr\Support\Arr;

add_filter(
	'template_include',
	function ( $template ) {
		if ( Arr::has( $_REQUEST, '__kubio-classic-page-template-slug' ) && Utils::canEdit() ) {
			$slug = str_replace( wp_normalize_path( get_stylesheet_directory() ), '', wp_normalize_path( $template ) );
			$slug = str_replace( wp_normalize_path( get_template_directory() ), '', wp_normalize_path( $slug ) );
			$slug = str_replace( '.php', '', trim( $slug, '/' ) );
			return wp_send_json_success( $slug );
		}

		return $template;
	},
	PHP_INT_MAX
);
