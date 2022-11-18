<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;


class ObjectCss extends PropertyBase {

	public function parse( $value, $options ) {
		$defaultValue = Config::value( 'props.object.default' );
		$mergedValue  = LodashBasic::merge( $defaultValue, $value );

		$style          = array();
		$objectPosition = ParserUtils::toValueUnitString( LodashBasic::get( $mergedValue, 'position' ) );
		if ( ParserUtils::isNotEmptyButCanBeZero( $objectPosition ) ) {
			$style['objectPosition'] = $objectPosition;
		}
		$objectFit = ParserUtils::toValueUnitString( LodashBasic::get( $mergedValue, 'fit' ) );
		if ( ParserUtils::isNotEmptyButCanBeZero( $objectFit ) ) {
			$style['objectFit'] = $objectFit;
		}
		return $style;
	}
}

