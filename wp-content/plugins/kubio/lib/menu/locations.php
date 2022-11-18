<?php

function kubio_register_menus_locations() {
	 $current_locations = array_keys( get_registered_nav_menus() );

	$kubio_locations = apply_filters(
		'kubio_menus_locations',
		array(
			'header-menu'           => kubio_theme_has_kubio_block_support()
				? esc_html__( 'Header menu', 'kubio' )
				: esc_html__( 'Kubio header menu', 'kubio' ),

			'header-menu-secondary' => kubio_theme_has_kubio_block_support()
				? esc_html__( 'Secondary header menu', 'kubio' )
				: esc_html__( 'Kubio secondary header menu', 'kubio' ),

			'footer-menu'           => kubio_theme_has_kubio_block_support()
				? esc_html__( 'Footer menu', 'kubio' )
				: esc_html__( 'Kubio footer menu', 'kubio' ),

			'footer-menu-secondary' => kubio_theme_has_kubio_block_support()
				? esc_html__( 'Secondary footer menu', 'kubio' )
				: esc_html__( 'Kubio secondary footer menu', 'kubio' ),
		)
	);

	foreach ( $kubio_locations as $location => $title ) {
		if ( in_array( $location, $current_locations ) ) {
			unset( $kubio_locations[ $location ] );
		}
	}

	register_nav_menus( $kubio_locations );
}

add_action( 'after_setup_theme', 'kubio_register_menus_locations', 100 );
