<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class Height extends PropertyBase {
	public function parse( $value, $options ) {

		if (is_string($value)) {
			return $value;
		}

		$defaultValue = Config::value( 'definitions.unitValuePx.default' );
		$style        = ParserUtils::addValueUnitString( $style, 'height', LodashBasic::merge( $defaultValue, $value ) );
		return $style;
	}
}

