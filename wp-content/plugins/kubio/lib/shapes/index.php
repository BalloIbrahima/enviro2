<?php

function kubio_get_shapes_css() {
	$shapes = array(
		'circles',
		'10degree-stripes',
		'rounded-squares-blue',
		'many-rounded-squares-blue',
		'two-circles',
		'circles-2',
		'circles-3',
		'circles-gradient',
		'circles-white-gradient',
		'waves',
		'waves-inverted',
		'dots',
		'left-tilted-lines',
		'right-tilted-lines',
		'right-tilted-strips',
	);
	$css    = '';
	$url    = plugin_dir_url( __FILE__ );

	foreach ( $shapes as $shape ) {
		$css .= ".kubio-shape-${shape}{background-image:url('${url}header-shapes/${shape}.png')}";
	}

	return $css;
}
