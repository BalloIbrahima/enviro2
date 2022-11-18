<?php

namespace Kubio\Core\StyleManager\Generics;

use Kubio\Core\StyleManager\ParserUtils;
use Kubio\Core\StyleManager\Props\Property;
use Kubio\Core\Styles\Utils;
use function is_string;

class UnitValue extends Property {
	public function __toString() {
		if ( is_string( $this->value ) ) {
			return $this->value;
		}

		return ParserUtils::toValueUnitString( $this->value, $this->default );
	}
}
