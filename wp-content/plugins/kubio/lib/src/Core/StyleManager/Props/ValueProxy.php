<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Core\LodashBasic;

class ValueProxy {
	/**
	 * @var array
	 */
	public $mergedValue;

	public function __construct( $value, $default ) {
		$this->mergedValue = LodashBasic::merge( array(), $default, $value );
	}

	public function __get( $name ) {
		return isset( $this->mergedValue[ $name ] ) ? $this->mergedValue[ $name ] : null;
	}
}
