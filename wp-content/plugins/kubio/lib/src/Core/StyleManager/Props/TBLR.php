<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;
use Kubio\Core\Styles\Utils;
use function join;

class TBLR extends PropertyBase {
	public function computeTBLRCss( $prefix, $style, $obj ) {
		foreach ( $obj as $name => $value ) {
			ParserUtils::addValueUnitString( $style, join( '-', array( $prefix, $name ) ), $value );
		}
		return $style;
	}

	public function parse( $value, $options ) {
		$defaultValue = $this->config( 'default' );
		$obj          = new ValueProxy( $value, $defaultValue );
		return $this->computeTBLRCss( $this->name, array(), $obj->mergedValue );
	}
}
