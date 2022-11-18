<?php

namespace Kubio\Core\StyleManager\Props;
use Kubio\Core\LodashBasic;
use function is_string;

class Property {
	public $properties = array();
	public $default;
	public $value;

	public function __construct( $value, $default = array() ) {
		$this->value   = $value;
		$this->default = $default;
	}

	public function matchTypeOf( $types, $value ) {
		foreach ( $types as $type_value ) {
			$type = $type_value['type'];
			switch ( $type ) {
				case 'string':
					if ( is_string( $value ) ) {
						return $type;
					}
					break;
			}
		}
	}

	public function resolveProperties() {
		$resolved = array();
		foreach ( $this->properties as $key => $property ) {
			if ( $key == 'anyOf' ) {

			}
			if ( isset( $property['type'] ) ) {
				$resolved[ $key ] = $this->createProperty( $property, $this->value[] );
			}
		}
	}

	public function createProperty( $propery ) {
		if ( isset( $propery['type'] ) ) {
			$class = $propery['type'];

			return new $class();
		}
	}

	public function resolveMap( $resolvedProperties ) {
		$mapped = array();
		foreach ( $this->map as $key => $value ) {
			$mapped[ $key ] = $resolvedProperties[ $value ];
		}
	}

	public function get( $path, $default = null ) {
		return LodashBasic::get( $this->merged(), $path, $default );
	}

	public function merged() {
		return LodashBasic::merge( $this->default, $this->value );
	}

	public function __toString() {
		if ( is_string( $this->value ) ) {
			return $this->value;
		}
	}

}
