<?php


namespace Kubio\Core\StyleManager;


use Kubio\Core\LodashBasic;
use IlluminateAgnostic\Arr\Support\Arr;
use function implode;
use function is_array;
use function is_string;

class ParserUtils {
	static function areAllNonEmpty( $values ) {
		$found = LodashBasic::find(
			$values,
			function ( $item ) {
				return ! self::isNotEmptyButCanBeZero( $item );
			}
		);

		return ! $found;
	}

	public static function isNotEmptyButCanBeZero( $value ) {
		return $value !== '' && $value !== null && $value !== 'undefined' && $value !== false;
	}

	public static function joinNonEmpty( $values, $join = ' ' ) {
		$nonEmpty = LodashBasic::filter(
			$values,
			function ( $item ) {
				return self::isNotEmptyButCanBeZero( $item );
			}
		);

		return implode( $join, $nonEmpty );
	}

	public static function toValueUnitStringFunction(
		$functionName,
		$valueUnit,
		$defaultValue = '',
		$defaultUnit = '',
		$isUnitLess = false
	) {
		$value = self::toValueUnitString( $valueUnit, null, $defaultUnit, $isUnitLess );
		if ( $value ) {
			return "$functionName($value)";
		}

		return $defaultValue;
	}

	public static function toValueUnitString(
		$value_unit,
		$defaultValue = '',
		$defaultUnit = '',
		$isUnitLess = false
	) {
		if ( is_string( $value_unit ) || is_numeric( $value_unit ) ) {

			if ( $value_unit && $defaultUnit ) {
				return "{$value_unit}{$defaultUnit}";
			}

			return $value_unit;
		}

		$value        = Arr::get( (array) $value_unit, 'value', $defaultValue );
		$unit         = Arr::get( (array) $value_unit, 'unit', $defaultUnit );
		$importantStr = isset( $value_unit['important'] ) && $value_unit['important'] ? ' !important' : '';
		if (
			self::isNotEmptyButCanBeZero( $value ) &&
			( $defaultUnit || self::isNotEmptyButCanBeZero( $unit ) || $isUnitLess )
		) {

			// in colmun spacing custom there seems to be an issue where the unit is sent as array of label, value
			if ( is_array( $unit ) && isset( $unit['value'] ) ) {
				$unit = $unit['value'];
			}

			return "${value}${unit}${importantStr}";
		}

		return $defaultValue;
	}

	public static function toJoinedValueUnitString( $values, $glue = ' ' ) {
		$vals = array();
		foreach ( $values as $value ) {
			$vals[] = self::toValueUnitString( $value );
		}

		return join( $glue, $vals );
	}

	public static function addValueUnitString( &$style, $key, $obj ) {
		$value = self::toValueUnitString( $obj );
		if ( $value ) {
			$style[ $key ] = $value;
		}

		return $style;
	}

	public static function addPrimitiveValues( &$style, $value, $propertiesMap, $unitLessProperties = array() ) {
		LodashBasic::each(
			$propertiesMap,
			function ( $cssName, $jsonName ) use ( &$style, $value, $unitLessProperties ) {
				if ( isset( $value[ $jsonName ] ) && self::isNotEmptyButCanBeZero( $value[ $jsonName ] ) ) {
					$isUnitLess    = in_array( $jsonName, $unitLessProperties );
					$propertyValue = self::toPrimitiveValue( $value[ $jsonName ], $isUnitLess );
					if ( self::isNotEmptyButCanBeZero( $propertyValue ) ) {
						$style[ $cssName ] = $propertyValue;
					}
				}
			}
		);

		return $style;
	}

	public static function toPrimitiveValue( $value, $isUnitLess ) {
		if ( is_array( $value ) ) {
			if (
				isset( $value['value'] ) ||
				isset( $value['unit'] )
			) {
				return self::toValueUnitString( $value, '', '', $isUnitLess );
			}
		} else {
			return $value;
		}
	}

}
