<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class Opacity extends PropertyBase {

	public function parse( $value, $options ) {
		$defaultValue = Config::value( 'definitions.unitValuePx.default' );
		$mergedData   = LodashBasic::merge( $defaultValue, $value );
		$value        = $mergedData['value'];
		if (
		ParserUtils::isNotEmptyButCanBeZero( $value )
		) {
			$style['opacity'] = $value;
		} else {
			$style['opacity'] = $defaultValue;
		}
		return $style;
	}
}

