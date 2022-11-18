<?php

namespace Kubio\Core;

class ElementBase {

	protected $value   = array();
	protected $default = array();
	protected $_merged;

	function getDefault( $path ) {
		return LodashBasic::get( $this->default, $path );
	}

	function getMergedValue() {
		if ( ! $this->_merged ) {
			$this->_merged = LodashBasic::merge( $this->default, $this->value );
		}
		return $this->_merged;
	}

	function get( $path, $default = null ) {
		$value = LodashBasic::get( $this->getMergedValue(), $path, $default );
		return $value;
	}

	function __construct( $value, $default = array() ) {
		$this->value   = $value;
		$this->default = $default;
	}
}
