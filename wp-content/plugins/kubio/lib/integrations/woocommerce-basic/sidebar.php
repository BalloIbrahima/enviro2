<?php

use Kubio\Flags;

add_filter(
	'kubio/instance-flags-default',
	function ( $flags ) {

		$flags['wc_sidebar_populated'] = false;

		return $flags;
	}
);

function kubio_get_woocommerce_sidebar_defaults() {
	return array(

		array(
			'id'   => 'woocommerce_widget_cart',
			'data' => array(
				'title'         => __( 'Cart', 'kubio' ),
				'hide_if_empty' => 1,
			),
		),

		array(
			'id'   => 'block',
			'data' => array(
				'content' => '<!-- wp:separator {"color":"kubio-color-1","className":"is-style-wide"} -->' .
							 '<hr class="wp-block-separator has-text-color has-background has-kubio-color-1-background-color has-kubio-color-1-color is-style-wide"/>' .
							 '<!-- /wp:separator -->',
			),
		),

		array(
			'id'   => 'woocommerce_price_filter',
			'data' => array(
				'title' => __( 'Filter by price', 'kubio' ),
			),
		),

		array(
			'id'   => 'woocommerce_recently_viewed_products',
			'data' => array(
				'title'  => __( 'Recently Viewed Products', 'kubio' ),
				'number' => 10,
			),
		),
		array(
			'id'   => 'woocommerce_top_rated_products',
			'data' => array(
				'title'  => __( 'Top rated products', 'kubio' ),
				'number' => 5,
			),
		),
		array(
			'id'   => 'woocommerce_layered_nav_filters',
			'data' => array(
				'title' => __( 'Active filters', 'kubio' ),
			),
		),
	);
}

function kubio_insert_widget_in_sidebar( $widget_id, $widget_data, $sidebar ) {
	// Retrieve sidebars, widgets and their instances
	$sidebars_widgets = get_option( 'sidebars_widgets', array() );
	$widget_instances = get_option( 'widget_' . $widget_id, array() );

	// Retrieve the key of the next widget instance
	$numeric_keys = array_filter( array_keys( $widget_instances ), 'is_int' );
	$next_key     = $numeric_keys ? max( $numeric_keys ) + 1 : 2;

	// Add this widget to the sidebar
	if ( ! isset( $sidebars_widgets[ $sidebar ] ) ) {
		$sidebars_widgets[ $sidebar ] = array();
	}
	$sidebars_widgets[ $sidebar ][] = $widget_id . '-' . $next_key;

	// Add the new widget instance
	$widget_instances[ $next_key ] = $widget_data;

	// Store updated sidebars, widgets and their instances
	update_option( 'sidebars_widgets', $sidebars_widgets );
	update_option( 'widget_' . $widget_id, $widget_instances );
}


function kubio_maybe_populate_woocommerce_sidebar() {
	$sidebars_widgets = get_option( 'sidebars_widgets', array() );

	$woocommerce_sidebar = isset( $sidebars_widgets['kubio-woocommerce'] ) ? $sidebars_widgets['kubio-woocommerce'] : array();

	if ( empty( $woocommerce_sidebar ) && ! Flags::get( 'wc_sidebar_populated' ) ) {
		Flags::set( 'wc_sidebar_populated', true );
		$kubio_woocommerce_sidebar_defaults = kubio_get_woocommerce_sidebar_defaults();

		$woocommerce_sidebar = array();

		foreach ( $kubio_woocommerce_sidebar_defaults as $widget ) {
			kubio_insert_widget_in_sidebar( $widget['id'], $widget['data'], 'kubio-woocommerce' );
		}
	}
}


function kubio_register_woocommerce_sidebar() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( kubio_has_kubio_woocommerce_support() ) {
		register_sidebar(
			array(
				'name'          => __( 'WooCommerce Widgets Area', 'kubio' ),
				'id'            => 'kubio-woocommerce',
				'description'   => __( 'Add widgets here to appear in the WooCommerce sidebar.', 'kubio' ),
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'before_title'  => '<h5 class="widgettitle">',
				'after_title'   => '</h5>',
				'after_widget'  => '</div>',
			)
		);
	}
}

add_action( 'widgets_init', 'kubio_register_woocommerce_sidebar' );
add_action( 'admin_init', 'kubio_maybe_populate_woocommerce_sidebar' );
