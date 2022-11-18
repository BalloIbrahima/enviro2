<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class Stroke extends PropertyBase {
	public function parse( $value, $options ) {
		$style        = array();
		$defaultValue = Config::value( 'props.stroke.default' );
		$style        = ParserUtils::addPrimitiveValues( $style, LodashBasic::merge( $defaultValue, $value ), Config::value( 'props.stroke.map' ) );
		return $style;
	}
}

