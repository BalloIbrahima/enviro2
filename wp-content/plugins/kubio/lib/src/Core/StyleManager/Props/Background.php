<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Core\Background\BackgroundDefaults;
use Kubio\Core\LodashBasic;

class Background extends PropertyBase {
	public $types;

	public function parse( $value, $options ) {

		$this->types = $this->config( 'enums.types' );

		$unsetColorOnEmpty = true;
		$defaultValue      = BackgroundDefaults::getDefaultBackground();
		$background        = new ValueProxy( $value, $defaultValue );

		$style      = array();
		$colorIsSet = false;

		if (
			$background->color
			//&& $background->type !== $this->types['GRADIENT']
		) {
			$style['backgroundColor'] = $background->color;
			$colorIsSet               = true;
		}

		if (
			! $unsetColorOnEmpty &&
			! $colorIsSet &&
			$background->type !== $this->types['GRADIENT']
		) {
			$colorIsSet = true;
		}

		switch ( $background->type ) {
			case $this->types['IMAGE']:
			case $this->types['GRADIENT']:
				// add source to image object directly, to be compatible with multiple backgrounds in the feature//
				$image   = LodashBasic::merge(
					array( 'source' => array( 'type' => $background->type ) ),
					$background->image[0]
				);
				$bgImage = new BackgroundImage( $image, $this->config( 'default' ) );
				$style   = LodashBasic::merge( $style, $bgImage->toStyle() );
				break;
			case $this->types['NONE']:
				$style = LodashBasic::merge( $style, $this->getBackgroundNoneCss( $colorIsSet ) );
				break;
		}

		return $style;
	}

	public function getBackgroundNoneCss( $colorIsSet ) {
		$computedStyle = array();
		if ( ! $colorIsSet ) {
			$computedStyle['backgroundColor'] = 'unset';
		}
		$computedStyle['backgroundImage'] = 'none';

		return $computedStyle;
	}
}
