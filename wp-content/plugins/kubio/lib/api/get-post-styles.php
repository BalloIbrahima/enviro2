<?php

use Kubio\Core\Utils;
use IlluminateAgnostic\Arr\Support\Arr;

add_action(
	'template_redirect',
	function () {
		if ( Arr::has( $_REQUEST, '__kubio-rendered-styles' ) && Utils::canEdit() ) {
			wp_enqueue_scripts();
			$content = ob_start();
			wp_print_styles();
			$content = ob_get_clean();
			return wp_send_json_success(
				array(
					'content' => apply_filters( 'kubio/editor-rendered-styles', $content ),
				)
			);
		}
	},
	PHP_INT_MAX
);


// force display gallery ( no script required )
function kubio_editor_rendered_styles_woocommerce_extra_style( $content ) {
	$content .= '<style>.woocommerce-product-gallery {opacity: 1 !important;}</style>';
	return $content;
}

add_filter( 'kubio/editor-rendered-styles', 'kubio_editor_rendered_styles_woocommerce_extra_style' );
