<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class Width extends PropertyBase {
	public function parse( $value, $options ) {
		if (is_string($value)) {
			return $value;
		}

		$defaultValue = Config::value( 'definitions.unitValuePx.default' );

		$style        = ParserUtils::addValueUnitString( $style, 'width', LodashBasic::merge( $defaultValue, $value ) );
		return $style;
	}
}

