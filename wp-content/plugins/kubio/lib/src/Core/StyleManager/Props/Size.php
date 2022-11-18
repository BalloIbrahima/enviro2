<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class Size extends PropertyBase {
	public function parse( $value, $options ) {
		$defaultValue = Config::value( 'definitions.unitValuePx.default' );
		$style        = array();
		$style        = ParserUtils::addValueUnitString( $style, 'width', LodashBasic::merge( $defaultValue, $value ) );
		$style        = ParserUtils::addValueUnitString( $style, 'height', LodashBasic::merge( $defaultValue, $value ) );
		$style        = ParserUtils::addValueUnitString( $style, 'minWidth', LodashBasic::merge( $defaultValue, $value ) );
		$style        = ParserUtils::addValueUnitString( $style, 'minHeight', LodashBasic::merge( $defaultValue, $value ) );
		return $style;
	}
}

