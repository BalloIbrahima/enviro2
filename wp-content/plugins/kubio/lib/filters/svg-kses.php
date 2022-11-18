<?php

function kubio_get_svg_kses_allowed_elements( $allowed_html = array() ) {

	$svg_elements = array(
		'svg'     =>
		array(
			'xmlns',
			'viewbox',
			'id',
			'data-name',
			'width',
			'height',
			'version',
			'xmlns:xlink',
			'x',
			'y',
			'enable-background',
			'xml:space',
		),
		'path'    =>
		array(
			'd',
			'id',
			'class',
			'data-name',
		),
		'g'       =>
		array(
			'id',
			'stroke',
			'stroke-width',
			'fill',
			'fill-rule',
			'transform',
		),
		'title'   =>
		array(),
		'polygon' =>
		array(
			'id',
			'points',
		),
		'rect'    =>
		array(
			'x',
			'y',
			'width',
			'height',
			'transform',
		),
		'circle'  =>
		array(
			'cx',
			'cy',
			'r',
		),
		'ellipse' =>
		array(
			'cx',
			'cy',
			'rx',
			'ry',
		),
	);

	$shared_attrs = array( 'data-*', 'id', 'class' );

	foreach ( $svg_elements as $element => $attrs ) {
		if ( ! isset( $allowed_html[ $element ] ) ) {
			$allowed_html[ $element ] = array();
		}

		$allowed_html[ $element ] = array_merge( $allowed_html[ $element ], array_fill_keys( array_merge( $attrs, $shared_attrs ), true ) );
	}

	return $allowed_html;

}

add_filter( 'wp_kses_allowed_html', 'kubio_get_svg_kses_allowed_elements' );
