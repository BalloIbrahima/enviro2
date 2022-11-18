<?php

namespace Kubio\Core\Background;

use Kubio\Config;
use Kubio\Core\Element;
use Kubio\Core\ElementBase;
use Kubio\Core\Utils;
use Kubio\Core\StyleManager\Props\BackgroundImage as BackgroundImageProp;
use Kubio\Core\StyleManager\Props\Background;

class BackgroundImage extends ElementBase {
	function __construct( $value ) {
		parent::__construct( $value, BackgroundDefaults::getDefaultImage() );
	}

	function wrapperComputedStyle() {

		if ( $this->useParallaxScript() ) {
			$url                      = $this->get( '0.source.url' );
			$style['backgroundImage'] = "url(\"$url\")";
		} else {
			$image   = $this->getMergedValue();
			$image   = $image[0];
			$bg      = new Background( 'background' );
			$bgImage = new BackgroundImageProp( $image, $bg->config( 'default' ) );
			$style   = $bgImage->toStyle();
		}
		if ( $this->useFeaturedImage() ) {
			$url                      = get_the_post_thumbnail_url( null, 'full' );
			$style['backgroundImage'] = "url(\"$url\")";
		}

		return $style;
	}

	function useParallaxScript() {
		return $this->get( '0.useParallax' );
	}
	function useFeaturedImage() {
		return $this->get( '0.useFeaturedImage' );
	}
	function useBackgroundLayer() {
		return $this->get( '0.forceBackgroundLayer' );
	}
	function getClasses() {
		$classes = array( 'background-layer' );
		if ( $this->useParallaxScript() ) {
			$classes[] = 'paraxify';
		}
		if ( $this->useBackgroundLayer() ) {
			$classes[] = 'forceBackgroundLayer';
		}

		return $classes;
	}

	function __toString() {
		$classes = $this->getClasses();

		$scriptData = Utils::useJSComponentProps(
			'parallax',
			array(
				'enabled' => $this->useParallaxScript(),
				'test'    => 'temp',
			)
		);

		return new Element(
			Element::DIV,
			array_merge(
				$scriptData,
				array(
					'style'     => $this->wrapperComputedStyle(),
					'className' => $classes,
				)
			)
		) . '';

	}
}
