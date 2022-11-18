<?php


function kubio_woocommerce_basic_settings( $settings ) {

	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return $settings;
	}

	$wc_pages = array(
		'pagesIds' => array(
			'myAccount' => wc_get_page_id( 'myaccount' ),
			'shopPage'  => wc_get_page_id( 'shop' ),
			'cart'      => wc_get_page_id( 'cart' ),
			'checkout'  => wc_get_page_id( 'checkout' ),
			'viewOrder' => wc_get_page_id( 'view_order' ),
			'terms'     => wc_get_page_id( 'terms' ),
		),
	);

	$wc_pages['pagesUrls'] = array();

	foreach ( $wc_pages['pagesIds'] as $page => $page_id ) {
		$wc_pages['pagesUrls'][ $page ] = get_permalink( $page_id );
	}

	$settings['kubioBasicWooCommerce'] = isset( $settings['kubioBasicWooCommerce'] ) ? $settings['kubioBasicWooCommerce'] : array();
	$settings['kubioBasicWooCommerce'] = array_merge( $settings['kubioBasicWooCommerce'], $wc_pages );

	return $settings;
}


add_filter( 'block_editor_settings_all', 'kubio_woocommerce_basic_settings' );
