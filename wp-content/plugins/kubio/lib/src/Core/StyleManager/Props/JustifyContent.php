<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Core\StyleManager\ParserUtils;

class JustifyContent extends PropertyBase {
	public function parse( $value, $options ) {
		if ( $value === 'right' ) {
			$value = 'flex-end';
		}

		if ( $value === 'left' ) {
			$value = 'flex-start';
		}

		$style = array();
		$style = ParserUtils::addValueUnitString( $style, 'justify-content', $value );
		return $style;
	}
}

