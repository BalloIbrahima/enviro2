<?php

use IlluminateAgnostic\Arr\Support\Arr;

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/sidebar.php';

function kubio_enqueue_woocommerce_style() {

	wp_enqueue_style(
		'kubio-woocommerce',
		kubio_url( 'build/woocommerce-styles/style.css' ),
		array( 'woocommerce-general' ),
		KUBIO_VERSION
	);
}

function kubio_woocommerce_support_editor_assets() {
	wp_enqueue_style( 'kubio-woocommerce-styles' );
	wp_enqueue_script( 'kubio-woocommerce-styles' );
}


function kubio_add_woocommerce_support() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( kubio_has_kubio_woocommerce_support() ) {
		add_action( 'wp_enqueue_scripts', 'kubio_enqueue_woocommerce_style' );
		add_action( 'kubio/editor/enqueue_assets', 'kubio_woocommerce_support_editor_assets' );

		require_once __DIR__ . '/block-editor-settings.php';
		require_once __DIR__ . '/templates-filters.php';

		if ( kubio_is_hybdrid_theme_iframe_preview() ) {
			add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );
		}
	}
}


function kubio_woocommerce_products_page_edit( $args ) {
	if ( function_exists( 'is_shop' ) && is_shop() && wc_get_page_id( 'shop' ) ) {
			$args = array(
				'postId'   => wc_get_page_id( 'shop' ),
				'postType' => 'page',
			);
	}

	return $args;
}


function kubio_add_woocommerce_global_style_types( $types ) {
	$style_types = json_decode( file_get_contents( KUBIO_BUILD_DIR . '/woocommerce-styles/style-types.json' ), true );

	$global_style_root_path = 'definitions.globalStyle';
	$styles_enum            = array_merge(
		Arr::get( $types, "{$global_style_root_path}.elementsEnum", array() ),
		Arr::get( $style_types, 'elementsEnum', array() )
	);

	$styles_by_name = array_merge(
		Arr::get( $types, "{$global_style_root_path}.elementsByName", array() ),
		Arr::get( $style_types, 'elementsByName', array() )
	);

	Arr::set( $types, "{$global_style_root_path}.elementsEnum", $styles_enum );
	Arr::set( $types, "{$global_style_root_path}.elementsByName", $styles_by_name );

	return $types;
}

add_action( 'init', 'kubio_add_woocommerce_support' );

add_filter( 'kubio/frontend/edit-in-kubio-args', 'kubio_woocommerce_products_page_edit' );
add_filter( 'kubio/style-types', 'kubio_add_woocommerce_global_style_types' );
