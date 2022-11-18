<?php

namespace Kubio\Core\Separators;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Config;
use Kubio\Core\Element;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;
use Kubio\Core\Styles\Utils;
use function file_exists;
use function file_get_contents;
use function sanitize_file_name;
use const KUBIO_ROOT_DIR;

class Separator extends Element {

	function __construct( $tag_name, $props, $children, $block ) {
		parent::__construct( $tag_name, $props, $children, $block );

		$position = $this->getProp( 'position' );
		$negative = $this->getProp( 'negative' );

		$enabledByMedia = $this->getProp( 'enabledByMedia' );

		$visibilityPerMedia = $this->getVisibilityPerMedia( $enabledByMedia );

		$children = [];

		$height = ParserUtils::toValueUnitString( $this->getProp( 'height' ));
		$style = array(
			'fill'   => $this->getProp( 'color' ),
			'height' =>  $height,
		);

		if ( ! $this->getProp( 'overlap' ) ) {
			$style['position'] = 'relative';
		}

		$style[ $position ] = 'calc(0px)';

		$type = sanitize_file_name( $this->getProp( 'type' ) );

		$top    = $position === 'top';
		$shouldUseNegative = $negative && file_exists( KUBIO_ROOT_DIR . "lib/shapes/separators/${type}-negative.svg" );

		$supportsNegative = file_exists( KUBIO_ROOT_DIR . "lib/shapes/separators/${type}-negative.svg" );

		if ( ( $shouldUseNegative && $top ) || ( ! $shouldUseNegative && ! $top ) ) {
			$style['transform'] = 'rotateX(180deg)';
		}

		if ( $negative && $supportsNegative ) {
			$type = $type . '-negative';
		}

		$html = file_get_contents( KUBIO_ROOT_DIR . 'lib/shapes/separators/' . $type . '.svg' );
		$this->extendProps(
			array(
				'className' => array_merge( array( 'h-separator', "h-separator--${position}" ), $visibilityPerMedia ),
				'style'     => $style,
			)
		);

		if(in_array( true, array_values( $enabledByMedia ))){
			$medias = $this->getMediaProps($block);

			$media_style = "<style>";
			foreach ( $enabledByMedia as $media => $enabled ) {
				if($media === 'desktop') {
					continue;
				}
				if($enabled){
					$parent_class = $this->getBlockStyleRefAsClass($block);
					$media_height = ParserUtils::toValueUnitString($this->getHeightForMedia($medias, $media, $position));
					if(empty($media_height)) {
						continue;
					}

					$media_style .= $this->getStyleForMedia($parent_class, $media, $position, $media_height);
				}
			}
			$media_style .= "</style>";
			$children[] = $media_style;
		}

		$children[] = $html;

		$this->setChildren( $children );
	}

	public function getVisibilityPerMedia( $enabledByMedia = array() ) {
		$classes = array();
		$prefix  = 'h-separator--display';
		foreach ( $enabledByMedia as $media => $enabled ) {
			$value         = $enabled ? 'flex' : 'none';
			$mediaPrefix   = utils::getMediaPrefix( $media );
			$values        = LodashBasic::compactWithExceptions( array( $prefix, $value, $mediaPrefix ), array( '0', 0 ) );
			$prefixedClass = implode( '-', $values );

			$classes[] = $prefixedClass;
		}
		return $classes;
	}

	public function getMediaProps($block){
		$separator_element = $block->separatorElement;
		$key = "attrs.kubio.style.descendants.${separator_element}.media";

		return Arr::get($block->block_data, $key);
	}

	public function getHeightForMedia($array, $media, $position){
		return Arr::get($array, "${media}.separators.${position}.height");
	}

	public function getStyleForMedia($parent_class, $media = 'desktop', $position = 'bottom', $height = '100px' ){
		if($media === 'desktop') {
			return '';
		}
		$height = str_replace('%', '%%', $height);
		$style = "";

		if($media === 'tablet'){
			$style = __(
				"@media (min-width: 768px) and (max-width: 1023px){
					.%s > .h-separator.h-separator--%s {
						height: ${height} !important;
					}
				}\n",
				"kubio"
			);
		}
		else if($media === 'mobile'){
			$style = __(
				"@media (max-width: 767px){
					.%s > .h-separator.h-separator--%s {
						height: ${height} !important;
					}
				}\n",
				"kubio"
			);
		}

		return sprintf($style, $parent_class, $position);
	}

	public function getBlockStyleRefAsClass($block){
		$style_ref = Arr::get($block->block_data, 'attrs.kubio.styleRef');
		$separator_element = $block->separatorElement;

		return implode('-', ["style", $style_ref, $separator_element] );
	}
}

