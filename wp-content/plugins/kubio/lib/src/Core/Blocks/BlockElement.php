<?php

namespace Kubio\Core\Blocks;

use Kubio\Core\Element;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use function str_replace;

class BlockElement extends Element {

	public $config;
	public $name;

	function __construct( $type, $props = array(), $children = array(), $block = null ) {
		if ( isset( $props['name'] ) ) {
			$this->name = $props['name'];
			unset( $props['name'] );
		}
		parent::__construct( $type, $props, $children, $block );
	}

	function __toString() {
		$props = $this->getFinalProps();
		if ( ! $this->shouldRender ) {
			return '';
		}

		return Registry::getInstance()->createElement( $this->tagName( $props ), $props, $this->children ) . '';
	}

	function getFinalProps() {
		return $this->mergeProps(
			parent::getProps(),
			array( 'className' => $this->getClassName() ),
			$this->mappedProps()
		);
	}

	function getClassName() {
		$classes = parent::getClassName();

		return $this->getClasses( $classes );
	}

	function getClasses( $extraClasses = array() ) {

		$classes             = LodashBasic::concat(
			array(
				'position-relative',

				$this->getBemClass( $this->block->name() ),
			),
			$extraClasses
		);
		$blockElement        = $this->block->elements[0];
		$disableStyleClasses = property_exists( $blockElement, 'disableStyleClasses' ) && $blockElement->disableStyleClasses;

		if ( ! $disableStyleClasses ) {
			$classes = LodashBasic::concat(
				$classes,
				array(
					$this->getSharedClass( $this->block->getMainAttributeProp( 'styleRef' ) ),
					$this->getLocalClasses( $this->block->localId() ),
				)
			);
		}

		return $classes;
	}

	function getBemClass( $blockName ) {
		return 'wp-block-' . str_replace( '/', '-', $blockName ) . '__' . $this->name;
	}

	function getSharedClass( $styleRef ) {
		$style_prefix = apply_filters( 'kubio/element-style-class-prefix', 'style-' );

		return $style_prefix . $styleRef . '-' . $this->name;
	}

	function getLocalClasses( $localId = null ) {
		$style_prefix = apply_filters( 'kubio/element-style-class-prefix', 'style-' );
		$style_prefix = "{$style_prefix}local-";
		return $localId ? $style_prefix . $localId . '-' . $this->name : false;
	}

	function mappedProps() {
		$mappedProps = array();
		if ( $this->block ) {
			$props       = $this->getConfig( 'props', array() );
			$mappedProps = array( 'className' => $this->getConfig( 'classes', array() ) );
			$mapped      = LodashBasic::get(
				apply_filters( 'kubio/blocks/element_props_map', $this->block->mapPropsToElementsWithDefaults(), $this->block ),
				$this->name
			);
			$mappedProps = $this->mergeProps( $props, $mappedProps, $mapped );
		}

		return $mappedProps;
	}

	function getConfig( $path, $defaultValue = null ) {
		return $this->block->getStyledElementConfig( $this->name, $path, $defaultValue );
	}

	function tagName( $props = null ) {
		if ( $props && isset( $props['tag'] ) ) {
			return $props['tag'];
		}

		return $this->getConfig( 'tag', $this->type );
	}
}
