<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class Animation extends PropertyBase {
	public function parse( $value, $options ) {
		$defaultValue = Config::value( 'definitions.unitValueSeconds.default' );

		$delay    = LodashBasic::get( $value, 'delay', array() );
		$duration = LodashBasic::get( $value, 'duration', array() );

		$style = ParserUtils::addValueUnitString( $style, 'animationDuration', LodashBasic::merge( $defaultValue, $duration ) );
		$style = ParserUtils::addValueUnitString( $style, 'animationDelay', LodashBasic::merge( $defaultValue, $delay ) );
		return $style;
	}
}

