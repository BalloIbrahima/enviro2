<?php


namespace Kubio\Core\StyleManager\Props;

use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;
use Kubio\Core\Styles\Utils;
use function array_keys;

class CustomHeight extends PropertyBase {
	public function parse( $value, $options ) {
		$mergedValue = (object) $this->valueWithDefault( $value );

		$types       = $this->config( 'enums.types' );
		$typeToConst = array_flip( $types );

		$typeConstName = $typeToConst[ $mergedValue->type ];

		$cssByType  = $this->config( 'config.cssByType' );
		$minHeights = $this->config( 'config.minHeightByType' );

		if ( $typeConstName == 'MIN_HEIGHT' ) {
			$minHeights['MIN_HEIGHT'] = $mergedValue->{$types['MIN_HEIGHT']};
		}

		$style = LodashBasic::get( $cssByType, $typeConstName, array() );

		if ( isset( $minHeights[ $typeConstName ] ) ) {
			ParserUtils::addValueUnitString( $style, 'min-height', $minHeights[ $typeConstName ] );
		}

		return $style;
	}
}
