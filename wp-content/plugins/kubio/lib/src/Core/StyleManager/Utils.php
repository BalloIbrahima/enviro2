<?php

namespace Kubio\Core\StyleManager;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use function array_keys;
use function array_merge_recursive;

class Utils {

	public static function normalizeProps( $props ) {
		return Utils::getMedias( $props );
	}

	public static function getMedias( $style ) {
		return Utils::extractProperty( $style, 'media', 'desktop' );
	}

	public static function extractProperty( $style, $property, $newName, $defaultValue = array() ) {
		$descendents = LodashBasic::get( $style, $property, $defaultValue );
		$defaultDesc = $style;
		LodashBasic::unsetValue( $defaultDesc, $property );
		LodashBasic::set( $descendents, $newName, $defaultDesc );
		return $descendents;
	}

	public static function denormalizeComponents( $style ) {
		$componentStates = array();
		self::walkStyle(
			$style,
			function( $data ) use ( &$componentStates ) {
				$component = LodashBasic::get( $data, 'element' );
				$media     = LodashBasic::get( $data, 'media' );
				$state     = LodashBasic::get( $data, 'state' );
				$style     = LodashBasic::get( $data, 'style' );
				$path      = array( $component, 'states', $state, 'media', $media );
				if ( $media === 'desktop' ) {
					if ( $state === 'normal' ) {
						$path = array( $component );
					} else {
						$path = array( $component, 'states', $state );
					}
				} elseif ( $state == 'normal' ) {
					$path = array( $component, 'media', $media );
				}

				$oldVal = LodashBasic::get( $componentStates, $path );
				LodashBasic::set( $componentStates, $path, LodashBasic::merge( array(), $oldVal, $style ) );
			}
		);
		return LodashBasic::merge( LodashBasic::get( $componentStates, 'default' ), array( 'descendants' => LodashBasic::omit( $componentStates, 'default' ) ) );
	}
	public static function normalizeStyle( $style, $settings_ ) {
		$settings = (object) LodashBasic::merge(
			array(
				'allowedElements' => false,
				'skipEmpty'       => false,
				'skipClone'       => false,
			),
			$settings_
		);

		$components = Utils::getElements( $style );
		if ( $settings->allowedElements ) {
			$components = LodashBasic::pick( $components, $settings->allowedElements );
		}

		$normalizedComponents = array();

		foreach ( $components as $elementName => $component ) {
			$component  = is_array( $component ) ? $component : (array) $component;
			$normalized = ( $settings->skipEmpty ) ? array() : Utils::normalizedDefault();
			$states     = Utils::getStates( $component );
			foreach ( $states as $stateName => $state ) {
				$medias = Utils::getMedias( $state );
				foreach ( $medias as $mediaName => $media ) {
					LodashBasic::set( $normalized, array( $mediaName, $stateName ), $media );
				}
			}
			$normalizedComponents[ $elementName ] = $normalized;
		}

		return $normalizedComponents;
	}

	public static function getElements( $style ) {
		return Utils::extractProperty( $style, 'descendants', 'default' );
	}

	public static function normalizedDefault() {
		$normalized = array();
		$mediasById = Config::mediasById();
		foreach ( $mediasById as $mediaId => $media ) {
			$normalized[ $mediaId ] = Utils::normalizedStates();
		}
		return $normalized;
	}

	public static function normalizedStates() {
		$normalized = array();
		$statesById = Config::statesById();
		foreach ( $statesById as $stateId => $state ) {
			$normalized[ $stateId ] = array();
		}
		return $normalized;
	}

	public static function getStates( $style ) {
		return Utils::extractProperty( $style, 'states', 'normal' );
	}

	public static function walkStyle( $style, $callback ) {
		$styleKeys = array_keys( $style );
		usort(
			$styleKeys,
			function ( $name ) {
				return ( $name === 'default' ) ? - 1 : 1;
			}
		);

		foreach ( $styleKeys as $elementName ) {
			$mediaKeys = array_keys( $style[ $elementName ] );
			usort(
				$mediaKeys,
				function ( $name ) {
					return ( $name === 'desktop' ) ? - 1 : 1;
				}
			);
			foreach ( $mediaKeys as $mediaName ) {
				$states    = $style[ $elementName ][ $mediaName ];
				$stateKeys = array();
				$allStates = Config::value( 'states' );
				foreach ( $allStates as $stateValue ) {
					$stateId = $stateValue['id'];
					if ( isset( $states[ $stateId ] ) ) {
						array_push( $stateKeys, $stateId );
					}
				}
				foreach ( $stateKeys as $stateName ) {
					$callback(
						array(
							'element' => $elementName,
							'media'   => $mediaName,
							'state'   => $stateName,
							'style'   => $states[ $stateName ],
						)
					);
				}
			}
		}
	}


	public static function hex2rgba( $color, $opacity = false, $values_only_string = false ) {
		$default = 'rgb(0,0,0)';

		if ( empty( $color ) ) {
			return $default;
		}

		if ( $color[0] == '#' ) {
			$color = substr( $color, 1 );
		}

		if ( strlen( $color ) == 6 ) {
			$hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
		} elseif ( strlen( $color ) == 3 ) {
			$hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
		} else {
			return $default;
		}

		$rgb = array_map( 'hexdec', $hex );

		if ( $opacity ) {
			if ( abs( $opacity ) > 1 ) {
				$opacity = 1.0;
			}

			$output = 'rgba(' . implode( ',', $rgb ) . ',' . $opacity . ')';
		} else {
			$output = 'rgb(' . implode( ',', $rgb ) . ')';
		}

		if ( $values_only_string ) {
			if ( $opacity ) {
				if ( abs( $opacity ) > 1 ) {
					$opacity = 1.0;
				}
				$output = implode( ',', $rgb ) . ',' . $opacity;
			} else {
				$output = implode( ',', $rgb );
			}
		}

		return $output;
	}


	public static function normalizeFontWeights( $weights ) {
		if ( empty( $weights ) ) {
			return array( '400' );
		}

		foreach ( $weights as $index => $weight ) {
			if ( $weight === 'italic' ) {
				$weights[ $index ] = '400italic';
			}

			if ( $weight === 'regular' ) {
				$weights[ $index ] = '400';
			}
		}

		$weights = array_unique( $weights );
		array_map( 'strval', $weights );
		asort( $weights );

		return $weights;
	}
}
