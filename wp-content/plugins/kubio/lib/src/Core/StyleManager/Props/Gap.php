<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class Gap extends PropertyBase {
	public function parse( $value, $options ) {
		$defaultValue = Config::value( 'definitions.unitValuePx.default' );
		$style        = array();
		$style        = ParserUtils::addValueUnitString( $style, 'gap', LodashBasic::merge( $defaultValue, $value ) );
		$style        = ParserUtils::addValueUnitString( $style, '--kubio-gap-fallback', LodashBasic::merge( $defaultValue, $value ) );
		return $style;
	}
}

