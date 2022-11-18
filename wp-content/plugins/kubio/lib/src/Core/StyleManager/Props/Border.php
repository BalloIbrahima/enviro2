<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;

class Border extends PropertyBase {

	public function parse( $value, $options ) {
		$borderWithRadius = $this->valueWithDefault( $value );
		$style            = array();
		foreach ( $borderWithRadius as $side => $border_side ) {
			$border_width = LodashBasic::get( $border_side, 'width.value' );

			$side_props = array( 'color', 'width', 'style' );

			if ( ! is_numeric( $border_width ) ) {
				$side_props = array( 'color');
			}

			foreach ( $side_props as $prop ) {
				if ( isset( $border_side[ $prop ] ) ) {
					ParserUtils::addValueUnitString(
						$style,
						'border-' . $side . '-' . $prop,
						$border_side[ $prop ]
					);
				}
			}
		}

		$radiuses = Config::value( 'props.border.radiusMap' );
		foreach ( $radiuses as $path => $___ ) {
			$radius = LodashBasic::get(
				$borderWithRadius,
				$radiuses[ $path ],
				null
			);
			if ( $radius !== null ) {
				ParserUtils::addValueUnitString( $style, $path, $radius );
			}
		}
		return $style;
	}
}
