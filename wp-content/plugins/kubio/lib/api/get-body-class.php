<?php

use Kubio\Core\Utils;
use IlluminateAgnostic\Arr\Support\Arr;

add_action(
	'template_redirect',
	function () {
		if ( Arr::has( $_REQUEST, '__kubio-body-class' ) && Utils::canEdit() ) {

			$omit_classes = array( 'logged-in', 'admin-bar', 'no-customize-support', 'wp-custom-logo' );

			$classes = array_diff( get_body_class(), $omit_classes );

			remove_all_filters( 'body_class' );

			return wp_send_json_success(
				array(
					'bodyClass' => array_values( $classes ),
				)
			);
		}
	},
	PHP_INT_MAX
);
