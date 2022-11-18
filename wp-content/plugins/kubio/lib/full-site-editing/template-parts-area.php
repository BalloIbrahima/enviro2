<?php


// Define constants for supported wp_template_part_area taxonomy.
if ( ! defined( 'WP_TEMPLATE_PART_AREA_HEADER' ) ) {
	define( 'WP_TEMPLATE_PART_AREA_HEADER', 'header' );
}
if ( ! defined( 'WP_TEMPLATE_PART_AREA_FOOTER' ) ) {
	define( 'WP_TEMPLATE_PART_AREA_FOOTER', 'footer' );
}
if ( ! defined( 'WP_TEMPLATE_PART_AREA_SIDEBAR' ) ) {
	define( 'WP_TEMPLATE_PART_AREA_SIDEBAR', 'sidebar' );
}
if ( ! defined( 'WP_TEMPLATE_PART_AREA_UNCATEGORIZED' ) ) {
	define( 'WP_TEMPLATE_PART_AREA_UNCATEGORIZED', 'uncategorized' );
}


function kubio_default_wp_template_areas( $defined_areas ) {

	$available_areas = array_column( $defined_areas, 'area' );

	$kubio_areas = array(
		array(
			'area'        => WP_TEMPLATE_PART_AREA_UNCATEGORIZED,
			'label'       => __( 'General', 'kubio' ),
			'description' => __(
				'General templates often perform a specific role like displaying post content, and are not tied to any particular area.',
				'kubio'
			),
			'icon'        => 'layout',
			'area_tag'    => 'div',
		),
		array(
			'area'        => WP_TEMPLATE_PART_AREA_HEADER,
			'label'       => __( 'Header', 'kubio' ),
			'description' => __(
				'The Header template defines a page area that typically contains a title, logo, and main navigation.',
				'kubio'
			),
			'icon'        => 'header',
			'area_tag'    => 'header',
		),
		array(
			'area'        => WP_TEMPLATE_PART_AREA_FOOTER,
			'label'       => __( 'Footer', 'kubio' ),
			'description' => __(
				'The Footer template defines a page area that typically contains site credits, social links, or any other combination of blocks.',
				'kubio'
			),
			'icon'        => 'footer',
			'area_tag'    => 'footer',
		),
		array(
			'area'        => WP_TEMPLATE_PART_AREA_SIDEBAR,
			'label'       => __( 'Sidebar', 'kubio' ),
			'description' => __(
				'The Sidebar template defines a page area that typically contains widgets.',
				'kubio'
			),
			'icon'        => 'sidebar',
			'area_tag'    => 'sidebar',
		),
	);

	foreach ( $kubio_areas as $area ) {
		if ( ! in_array( $area['area'], $available_areas ) ) {
			$defined_areas[] = $area;
		}
	}

	return $defined_areas;
}

add_filter( 'default_wp_template_part_areas', 'kubio_default_wp_template_areas' );


function kubio_get_allowed_template_part_areas() {

	if ( function_exists( 'gutenberg_get_allowed_template_part_areas' ) ) {
		return gutenberg_get_allowed_template_part_areas();
	}

	/**
	 * Filters the list of allowed template part area values.
	 *
	 * @param array $default_areas An array of supported area objects.
	 */
	return apply_filters( 'default_wp_template_part_areas', array() );
}

/**
 * Checks whether the input 'area' is a supported value.
 * Returns the input if supported, otherwise returns the 'uncategorized' value.
 *
 * @param string $type Template part area name.
 *
 * @return string Input if supported, else the uncategorized value.
 */
function kubio_filter_template_part_area( $type ) {

	if ( function_exists( 'gutenberg_filter_template_part_area' ) ) {
		return gutenberg_filter_template_part_area();
	}

	$allowed_areas = array_map(
		function ( $item ) {
			return $item['area'];
		},
		kubio_get_allowed_template_part_areas()
	);
	if ( in_array( $type, $allowed_areas, true ) ) {
		return $type;
	}

	$warning_message = sprintf(
		/* translators: %1$s: Template area type, %2$s: the uncategorized template area value. */
		__( '"%1$s" is not a supported wp_template_part area value and has been added as "%2$s".', 'kubio' ),
		$type,
		WP_TEMPLATE_PART_AREA_UNCATEGORIZED
	);
	trigger_error( wp_kses_post( $warning_message ), E_USER_NOTICE );
	return WP_TEMPLATE_PART_AREA_UNCATEGORIZED;
}


/**
 * Registers the 'wp_template_part_area' taxonomy.
 */
function kubio_register_wp_template_part_area_taxonomy() {
	if ( taxonomy_exists( 'wp_template_part_area' ) ) {
		return;
	}

	register_taxonomy(
		'wp_template_part_area',
		array( 'wp_template_part' ),
		array(
			'public'            => false,
			'hierarchical'      => false,
			'labels'            => array(
				'name'          => __( 'Template Part Areas', 'kubio' ),
				'singular_name' => __( 'Template Part Area', 'kubio' ),
			),
			'query_var'         => false,
			'rewrite'           => false,
			'show_ui'           => false,
			'_builtin'          => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'kubio_register_wp_template_part_area_taxonomy', 100 );
