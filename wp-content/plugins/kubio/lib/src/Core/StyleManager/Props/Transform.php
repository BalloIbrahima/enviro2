<?php


namespace Kubio\Core\StyleManager\Props;


use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;
use function array_push;
use function is_array;
use function wp_is_numeric_array;

class Transform extends PropertyBase {


	function parse( $value, $options ) {
		if ( array_key_exists( 'none', $value ) && $value['none'] === true ) {
			return array(
				'transform' => 'none',
			);
		}
		$style            = array();
		$valueWithDefault = $this->valueWithDefault( $value );
		$perspective_     = $this->computePerspective( $valueWithDefault['perspective'] );
		$translateArr     = $this->computeTranslate( $valueWithDefault['translate'] );
		$scaleArr         = $this->computeScale( $valueWithDefault['scale'] );
		$rotateArr        = $this->computeRotate( $valueWithDefault['rotate'] );
		$skewArr          = $this->computeSkew( $valueWithDefault['skew'] );

		$transform = ParserUtils::joinNonEmpty(
			LodashBasic::concat( $perspective_, $translateArr, $scaleArr, $rotateArr, $skewArr )
		);

		if ( $transform ) {
			$style['transform'] = $transform;
		}

		if ( $transform && ParserUtils::isNotEmptyButCanBeZero( $style['transform'] ) ) {
			$xyz             = $this->computeOrigin( $valueWithDefault['origin'] );
			$transformOrigin = ParserUtils::joinNonEmpty( array( $xyz['x'], $xyz['y'], $xyz['z'] ) );
			if ( $transformOrigin ) {
				$style['transformOrigin'] = $transformOrigin;
			}
		}

		return $style;
	}

	public function valueWithDefault( $value ) {
		$defaultValue = $this->getDefaultValue();
		return LodashBasic::mergeSkipSeqArray( array(), $defaultValue, $value );
	}

	function computePerspective( $value ) {
		$results     = array();
		$perspective = ParserUtils::toValueUnitStringFunction( 'perspective', $value );
		if ( isset( $perspective ) ) {
			array_push( $results, $perspective );
		}

		return $results;
	}

	function computeTranslate( $XYZValues ) {
		return $this->computeXYZ(
			$XYZValues,
			array(
				'key'         => 'translate',
				'defaultUnit' => 'px',
			)
		);
	}

	function computeXYZ( $xyzArr, $options ) {
		$key         = $options['key'];
		$defaultUnit = LodashBasic::get( $options, 'defaultUnit', '' );
		$isUnitLess  = LodashBasic::get( $options, 'isUnitLess', false );
		$resultArr   = array();

		foreach ( $xyzArr as $item ) {
			if ( array_key_exists( 'axis', $item ) ) {
				$resultArr = LodashBasic::concat(
					$resultArr,
					$this->addDirectionValues(
						$key . strtoupper( $item['axis'] ),
						LodashBasic::get( $item, 'value' ),
						$defaultUnit,
						$isUnitLess
					)
				);
			}
		}

		return $resultArr;
	}

	function addDirectionValues( $key, $value, $defaultUnit = '', $isUnitLess = false ) {
		$translateArray = $value;
		if ( ! wp_is_numeric_array( $value ) ) {
			$translateArray = array( $value );
		}
		$result = array();
		foreach ( $translateArray as $translate ) {
			$str = ParserUtils::toValueUnitStringFunction(
				$key,
				$translate,
				null,
				$defaultUnit,
				$isUnitLess
			);
			array_push( $result, $str );
		}
		return $result;
	}

	function computeScale( $XYZValues = array() ) {
		return $this->computeXYZ(
			$XYZValues,
			array(
				'key'        => 'scale',
				'isUnitLess' => true,
			)
		);
	}

	function computeRotate( $rotateValues = array() ) {
		$rotates = array();

		$rotates = LodashBasic::concat(
			$rotates,
			$this->computeXYZ(
				$rotateValues,
				array(
					'key'         => 'rotate',
					'defaultUnit' => 'deg',
				)
			)
		);
		return $rotates;
	}

	function computeSkew( $skewValues = array() ) {
		$skews = $this->computeXYZ(
			$skewValues,
			array(
				'key'         => 'skew',
				'defaultUnit' => 'deg',
			)
		);
		return $skews;
	}

	function computeOrigin( $originOptions ) {
		$transformOrigin = array(
			'x' => $this->getOriginData( $originOptions, 'x.value', 'x.customValue' ),
			'y' => $this->getOriginData( $originOptions, 'y.value', 'y.customValue' ),
			'z' => $this->getOriginData( $originOptions, 'z.value', 'z.customValue' ),
		);
		return $transformOrigin;
	}

	function getOriginData( $originOptions, $valuePath, $customValuePath ) {
		if ( LodashBasic::get( $originOptions, $valuePath ) === 'custom' ) {
			$originX = LodashBasic::get( $originOptions, $customValuePath );
			$result  = ParserUtils::toValueUnitString( $originX );
		} else {
			$result = LodashBasic::get( $originOptions, $valuePath );
		}

		return $result;
	}
}
