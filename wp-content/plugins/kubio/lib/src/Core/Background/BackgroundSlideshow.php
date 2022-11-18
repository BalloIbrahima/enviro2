<?php

namespace Kubio\Core\Background;

use Kubio\Core\Element;
use Kubio\Core\ElementBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;
use Kubio\Core\Utils;

use function array_merge;

class BackgroundSlideshow extends ElementBase {

	function __construct( $value ) {
		parent::__construct( $value, BackgroundDefaults::getDefaultSlideShow() );
	}

	function getMergedValue() {
		if ( ! $this->_merged ) {
			$this->_merged = LodashBasic::mergeSkipSeqArray( $this->default, $this->value );
		}
		return $this->_merged;
	}


	function __toString() {
		$slides      = $this->get( 'slides' );
		$duration_str = ParserUtils::toValueUnitString( $this->get( 'duration' ) );
		$speed_str    = ParserUtils::toValueUnitString( $this->get( 'speed' ) );

		$slides_els = array();

		foreach ( $slides as $index => $slide ) {
			$slides_els[] = new Element(
				Element::DIV,
				array(
					'style'     => $this->getSlideStyle( $slide, $index ),
					'className' => array( 'slideshow-image' ),
				)
			);
		}

		$slideshow = Utils::useJSComponentProps(
			'slideshow',
			array(
				'duration' => $duration_str,
				'speed'    => $speed_str,
			)
		);
		return new Element(
			Element::DIV,
			array_merge(
				$slideshow,
				array(
					'className' => array(
						'background-layer',
						'kubio-slideshow',
					),
				)
			),
			$slides_els
		) . '';
	}

	function getSlideStyle( $slide, $index ) {
		$url   = $slide['url'];
		$style = array(
			'backgroundImage' => "url(\"$url\")",
			'zIndex'          => $index,
		);
		return $style;
	}
}
