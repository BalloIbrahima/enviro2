<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class Transition extends PropertyBase {
	public function parse( $value, $options ) {
		$style        = array();
		$defaultValue = Config::value( 'definitions.unitValueSeconds.default' );

		$delay    = LodashBasic::get( $value, 'delay', array() );
		$duration = LodashBasic::get( $value, 'duration', array() );

		$style = ParserUtils::addValueUnitString( $style, 'transitionDuration', LodashBasic::merge( $defaultValue, $duration ) );
		$style = ParserUtils::addValueUnitString( $style, 'transitionDelay', LodashBasic::merge( $defaultValue, $delay ) );

		return $style;
	}
}

