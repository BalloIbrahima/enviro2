<?php

namespace Kubio\Core;

use _;
use IlluminateAgnostic\Arr\Support\Arr;
use IlluminateAgnostic\Str\Support\Str;
use function array_map;
use function array_merge;
use function count;
use function is_array;

function array_get_value( &$array, $parents, $default = null, $glue = '.' ) {
	if ( ! $array || ! is_array( $array ) ) {
		return $default;
	}

	if ( ! is_array( $parents ) ) {
		$parents = explode( $glue, $parents );
	}

	$ref = &$array;

	foreach ( (array) $parents as $parent ) {
		if ( is_array( $ref ) && array_key_exists( $parent, $ref ) ) {
			$ref = &$ref[ $parent ];
			// walk inside object
		} elseif ( is_object( $ref ) && property_exists( $ref, $parent ) ) {
			$ref = &$ref->$parent;
		} else {
			return $default;
		}
	}

	return $ref;
}

function array_set_value( array &$array, $parents, $value, $glue = '.' ) {
	if ( ! is_array( $parents ) ) {
		$parents = explode( $glue, (string) $parents );
	}

	$ref = &$array;

	foreach ( $parents as $parent ) {
		if ( isset( $ref ) && ! is_array( $ref ) ) {
			$ref = array();
		}

		$ref = &$ref[ $parent ];
	}

	$ref = $value;
}

function array_unset_value( &$array, $parents, $glue = '.' ) {

	if ( ! is_array( $array ) ) {
		return;
	}

	if ( ! is_array( $parents ) ) {
		$parents = explode( $glue, $parents );
	}

	$key = array_shift( $parents );

	if ( empty( $parents ) ) {
		unset( $array[ $key ] );
	} else {
		array_unset_value( $array[ $key ], $parents );
	}
}

function array_map_by_key( $array, $key ) {
	$result = array();
	array_walk(
		$array,
		function ( $partial ) use ( $result, $key ) {
			$id = array_get_value( $partial, $key, null );
			if ( $id !== null ) {
				$result[ $id ] = $partial;
			}
		}
	);

	return $result;
}

class LodashBasic {
	static function array_get_value( &$array, $parents, $default = null, $glue = '.' ) {
		return array_get_value( $array, $parents, $default, $glue );
	}

	static function has( $array, $path ) {
		return Arr::has( $array, $path );
	}

	static function set( array &$array, $parents, $value ) {
		array_set_value( $array, $parents, $value );
	}

	static function unsetValue( &$array, $parents ) {
		array_unset_value( $array, $parents );
	}

	static function each( $collection, $iterateFn ) {
		_\each( $collection, $iterateFn );
	}

	static function keyBy( $collection, $iteratee ) {
		return _\keyBy( $collection, $iteratee );
	}

	static function map( $collection, $iteratee ) {
		return _\map( $collection, $iteratee );
	}

	static function mapValues( $array, $mapper ) {
		$closure = $mapper;
		if ( is_string( $mapper ) ) {
			$closure = function ( $value ) use ( $mapper ) {
				return LodashBasic::get( $value, $mapper );
			};
		}

		return array_map( $closure, $array );
	}

	static function get( $array, $parents, $default = null ) {
		if ( $array ) {
			return array_get_value( $array, $parents, $default );
		}

		return $default;
	}

	static function find( $array, $closure ) {
		return _\find( $array, $closure );
	}

	static function filter( $array, $closure ) {
		return _\filter( $array, $closure );
	}

	static function compactWithExceptions( $array, $exceptions = array() ) {
		return \array_values(
			\array_filter(
				$array,
				function ( $input ) use ( $exceptions ) {
					$isException = in_array( $input, $exceptions, true );

					return ! ! $input || $isException;
				}
			)
		);
	}

	static function isString( $value ) {
		return is_string( $value );
	}

	static function concat( $array, ...$values ) {
		$check = function ( $value ) {
			return is_array( $value ) ? $value : array( $value );
		};

		return array_merge( $check( $array ), ...array_map( $check, $values ) );
	}

	static function merge( ...$values ) {
		$not_null_values = LodashBasic::compact( $values );
		if ( count( $not_null_values ) > 0 ) {
			return array_replace_recursive( ...$not_null_values );
		}

		return array();
	}

	static function compact( $array ) {
		return _\compact( $array );
	}

	static function mergeSkipSeqArray( ...$values ) {
		$not_null_values = LodashBasic::compact( $values );
		if ( count( $not_null_values ) > 0 ) {
			if ( count( $not_null_values ) === 1 ) {
				return $not_null_values[0];
			} else {

				// get the first 2 arrays to merge from parameters
				$next_arr   = array_shift( $not_null_values );
				$second_arr = array_shift( $not_null_values );

				// if arrays are not assoc use the second array
				if (
					is_array( $next_arr ) && count( $next_arr ) && ! Arr::isAssoc( $second_arr ) &&
					is_array( $second_arr ) && count( $second_arr ) && ! Arr::isAssoc( $next_arr )
				) {
					return $second_arr;
				}

				foreach ( $second_arr as $key => $second_value ) {

					$first_value = Arr::get( $next_arr, $key, null );
					if ( is_array( $second_value ) && count( $second_value ) ) {
						if ( ! is_array( $first_value ) ) {
							$next_arr[ $key ] = $second_value;
						} else {
							$next_arr[ $key ] = LodashBasic::mergeSkipSeqArray( $first_value, $second_value );
						}
					} else {
						if (is_array($first_value) && is_array($second_value) && empty($second_value)){
							$next_arr[$key] = $first_value;
						} else {
							$next_arr[ $key ] = $second_value;
						}
					}
				}

				return $next_arr;
			}
		}

		return array();
	}

	static function omit( $object, $property ) {
		return Arr::except( $object, $property );
	}

	static function pick( $array, $paths ) {
		$paths_by_name = array_fill_keys( (array) $paths, true );

		return array_filter(
			$array,
			function ( $key ) use ( $paths_by_name ) {
				return isset( $paths_by_name[ $key ] );
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	static function kebabCase( $string ) {
		return Str::kebab( $string );
	}

	/**
	 * This method returns the first argument it receives.
	 *
	 * @param mixed $value Any value.
	 *
	 * @return mixed Returns `value`.
	 * @category Util
	 *
	 * @example
	 * <code>
	 * $object = ['a' => 1];
	 *
	 * identity($object) === $object;
	 * // => true
	 * </code>
	 */
	static function identity( $value ) {
		return _\identity( $value );
	}

	static function uniq( $values ) {
		return array_unique( $values );
	}

	static function diff( $a1, $a2 ) {
		$r = array();
		foreach ( $a1 as $k => $v ) {
			if ( array_key_exists( $k, (array)$a2 ) ) {
				if ( is_array( $v ) ) {
					$rad = self::diff( $v, $a2[ $k ] );
					if ( count( $rad ) ) {
						$r[ $k ] = $rad;
					}
				} else {
					if ( $v != $a2[ $k ] ) {
						$r[ $k ] = $v;
					}
				}
			} else {
				$r[ $k ] = $v;
			}
		}

		return $r;
	}

}
