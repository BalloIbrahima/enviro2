<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class MaxWidth extends PropertyBase {

	public function parse( $value, $options ) {
		$defaultValue  = Config::value( 'props.maxWidth.default' );
		$mergedValue   = LodashBasic::merge( $defaultValue, $value );
		$maxWidthValue = ParserUtils::toValueUnitString( $mergedValue );
		if ( ! $maxWidthValue ) {
			return $maxWidthValue;
		}
		$style = array();

		$style['max-width'] = $maxWidthValue;
		return $style;
	}
}

