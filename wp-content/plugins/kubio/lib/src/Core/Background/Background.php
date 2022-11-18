<?php

namespace Kubio\Core\Background;

use Kubio\Config;
use Kubio\Core\Element;
use Kubio\Core\LodashBasic;
use Kubio\Core\Styles\Utils;

class Background extends Element {

	private $backgroundByType = array(
		'video'     => BackgroundVideo::class,
		'slideshow' => BackgroundSlideshow::class,
		'image'     => BackgroundImage::class,
	);

	function __construct( $tag_name, $props, $children, $block = null ) {
		$this->default = BackgroundDefaults::getDefaultBackground();

		if ( $block ) {
			$this->value = $block->backgroundByMedia();
		}

		$bgs = array();

		$default = LodashBasic::get( $this->value, 'desktop', array() );
		foreach ( $this->value as $media => $value_on_media ) {
			if ( $media !== 'desktop' ) {
				$value_on_media = LodashBasic::merge( $default, $value_on_media );
			}
			$bgs[] = $this->backgroundOnMedia( $media, $value_on_media );
		}

		parent::__construct(
			Element::DIV,
			LodashBasic::merge(
				array(
					'className' => 'background-wrapper',
				),
				$props
			),
			$bgs,
			$block
		);
	}


	function backgroundOnMedia( $media, $value ) {
		$mergedValue = LodashBasic::merge( $this->default, $value );
		$types       = Config::value( 'props.background.enums.types' );

		$children = array();
		if ( isset( $value['overlay'] ) ) {
			$enabled = LodashBasic::get( $value, 'overlay.enabled', false );
			if ( $enabled ) {
				$children[] = new BackgroundOverlay( $value['overlay'] );
			}
		}

		$type = LodashBasic::get( $mergedValue, 'type', $types['NONE'] );
		if ( $type !== $types['NONE'] ) {
			if ( isset( $this->backgroundByType[ $type ] ) && $this->backgroundLayerIsEnabled( $mergedValue ) ) {
				$BackgroundClass = $this->backgroundByType[ $type ];
				$children[]      = new $BackgroundClass( LodashBasic::get( $value, $type ) );
			}
		}

		return new Element(
			Element::DIV,
			array(
				'className' => $this->backgroundOnMediaClass( $media ),
			),
			$children,
			$this->block
		);
	}

	function backgroundOnMediaClass( $media ) {
		$className = Utils::composeClassForMedia(
			$media,
			'',
			'background-layer-media-container',
			true
		);

		return array( 'background-layer', $className );
	}

	function backgroundLayerIsEnabled( $background ) {
		$type = LodashBasic::get( $background, 'type' );
		switch ( $type ) {
			case 'image':
				$useParallax          = LodashBasic::get( $background, 'image.0.useParallax', false );
				$useFeaturedImage     = LodashBasic::get( $background, 'image.0.useFeaturedImage', false );
				$forceBackgroundLayer = LodashBasic::get( $background, 'image.0.forceBackgroundLayer', false );
				return ! ! $useParallax || ! ! $useFeaturedImage || ! ! $forceBackgroundLayer;
			default:
				return true;
		}
	}
}

