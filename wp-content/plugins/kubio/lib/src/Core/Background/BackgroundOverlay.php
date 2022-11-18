<?php

namespace Kubio\Core\Background;
use Kubio\Config;
use Kubio\Core\Element;
use Kubio\Core\ElementBase;
use function array_push;

class BackgroundOverlay extends ElementBase {
	const SHAPE_NONE = 'none';

	function __construct( $value ) {
		parent::__construct( $value, BackgroundDefaults::getDefaultOverlay() );
	}

	function getOverlayShapeStyle() {
		$tile_style = array(
			'backgroundPosition' => 'top left',
			'backgroundRepeat'   => 'repeat',
		);
		$non_tile   = array(
			'backgroundPosition' => 'center center',
			'backgroundRepeat'   => 'no-repeat',
			'backgroundSize'     => 'cover',
		);
		$style      = $this->get( 'shape.isTile' ) ? $tile_style : $non_tile;

		$style['filter'] = 'invert(' . $this->get( 'shape.light' ) . '%)';
		return $style;
	}

	function getOverlayLayerComputedStyle() {
		$style = array();
		switch ( $this->get( 'type' ) ) {
			case 'color':
				$style = array(
					'backgroundColor' => $this->get( 'color.value' ),
					'opacity'         => $this->get( 'color.opacity' ),
				);
				break;
			case 'gradient':
				$style = array(
					'backgroundImage' => $this->get( 'gradient' ),
				);
				break;
		}
		return $style;
	}

	function showShape() {
		return $this->get( 'shape.value' ) !== self::SHAPE_NONE;
	}

	function getShapeLayerClasses() {
		$classes = array( 'shape-layer' );
		if ( $this->showShape() ) {
			array_push( $classes, 'kubio-shape-' . $this->get( 'shape.value' ) );
		}
		return $classes;
	}

	function __toString() {
		if ( ! $this->get( 'enabled' ) ) {
			return '';
		}

		$shape = $this->showShape() ? new Element(
			Element::DIV,
			array(
				'style'     => $this->getOverlayShapeStyle(),
				'className' => $this->getShapeLayerClasses(),
			),
			array()
		) : null;

		return new Element(
			Element::DIV,
			array(
				'className' => 'overlay-layer',
			),
			array(
				new Element(
					Element::DIV,
					array(
						'className' => 'overlay-image-layer',
						'style'     => $this->getOverlayLayerComputedStyle(),
					)
				),
				$shape,
			)
		) . '';
	}
}

