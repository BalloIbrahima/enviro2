<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class UnitValuePercentage extends PropertyBase {
	public function parse( $value, $options ) {
		$defaultValue = Config::value( 'definitions.unitValuePercent.default' );
		$style        = ParserUtils::addValueUnitString( $style, $this->name, LodashBasic::merge( $defaultValue, $value ) );
		return $style;
	}
}

