<?php

use IlluminateAgnostic\Arr\Support\Arr;

add_action(
	'kubio/preview/handle_custom_entities',
	function ( $data ) {
		$kind = Arr::get( $data, 'kind' );
		$name = Arr::get( $data, 'name' );

		// map bloginfo( $show:string ) $show to root/site data
		$bloginfo_map = array(
			'name' => 'title',
		);

		$options_map = array(
			'title'     => 'option_blogname',
			'sitelogo'  => 'theme_mod_custom_logo',
			'site_logo' => 'theme_mod_custom_logo',
			'site_icon' => 'option_site_icon',
		);

		$dummy_value = uniqid( 'kubio-dummy-' );

		if ( $kind === 'root' && $name === 'site' ) {
			add_filter(
				'bloginfo',
				function( $output, $key ) use ( $data, $bloginfo_map ) {
					$key = Arr::get( $bloginfo_map, $key, $key );
					return Arr::get( $data, $key, $output );
				},
				10,
				2
			);

			foreach ( $options_map as $option_key => $filter_key ) {
				if ( Arr::has( $data, $option_key ) ) {
					$option_value = Arr::get( $data, $option_key, $dummy_value );
					add_filter(
						$filter_key,
						function() use ( $option_value, $option_key ) {
								return $option_value;
						}
					);
				}
			}
		}
	}
);
