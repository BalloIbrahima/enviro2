<?php

namespace Kubio\Core\GlobalElements;
use Kubio\Core\Element;
use Kubio\Core\LodashBasic;
class Icon   extends Element {
	public function __construct( $tag_name, $props = array(), $children = array(), $block = null ) {
		$defaultIcon = 'font-awesome/star';
		$icon        = LodashBasic::get( $props, 'name', $defaultIcon );
		if ( ! $icon ) {
			$icon = $defaultIcon;
		}
		$svg = '';
		if ( $icon && is_string( $icon ) ) {
			$icon_folder_name = explode( '/', $icon );
			$svg_file         = ( KUBIO_ROOT_DIR . 'static/icons/' . sanitize_file_name( $icon_folder_name[0] ) . '/' . $icon_folder_name[1] . '.svg' );
			if ( file_exists( $svg_file ) ) {
				$svg = file_get_contents( $svg_file );
			}
		}
		parent::__construct(
			Element::SPAN,
			LodashBasic::merge(
				$props,
				array(
					'className' => array( 'h-svg-icon' ),
				)
			),
			array( $svg ),
			$block
		);
	}

	public function __toString() {
		return parent::__toString();
	}
}

