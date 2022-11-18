<?php


function kubio_has_kubio_woocommerce_support() {
	return apply_filters( 'kubio/woocommerce/has-kubio-specific-support', current_theme_supports( 'kubio-woocommerce' ) );
}


function kubio_get_woocommerce_content() {

	if ( is_single() ) {

		return WC_Shortcodes::product_page(
			array(
				'id'         => get_the_ID(),
				'show_title' => 0,
				'status'     => 'any',
			)
		);
	}

	ob_start();
	add_filter( 'woocommerce_show_page_title', '__return_false' );
	woocommerce_content();

	return ob_get_clean();
}
