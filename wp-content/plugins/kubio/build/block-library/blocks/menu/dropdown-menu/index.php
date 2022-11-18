<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;


class DropDownMenuBlock extends BlockBase {
	const BLOCK_NAME = 'kubio/dropdown-menu';

	public function mapPropsToElements() {

		$effect = $this->getProp( 'hoverEffect.type' ) === 'none' ? false : $this->getProp( 'hoverEffect.type' );

		$border_effect = $this->isLineEffect( $effect ) ? $this->getProp( 'hoverEffect.border.effect' ) : false;

		$background_effect = $this->isBackgroundEffect( $effect ) ? $this->getProp( 'hoverEffect.background.effect' ) : false;

		$effect_classes = array( $effect, $border_effect, $background_effect );
		$effect_classes = array_filter(
			$effect_classes,
			function ( $item ) {
				return ! ! $item;
			}
		);

		$jsProps = Utils::useJSComponentProps( 'dropdown-menu' );

		return array(
			'outer' => array_merge(
				$jsProps,
				array(
					'className' => array_merge(
						array(
							'kubio-dropdown-menu',
							$this->getAttribute( 'showOffscreenMenuOn', 'has-offcanvas-mobile' ),
						),
						$effect_classes
					),
				)
			),
		);
	}

	private function isLineEffect( $effect ) {
		return ( $effect && strpos( $effect, 'bordered-active-item' ) !== false );

	}

	private function isBackgroundEffect( $effect ) {
		return ( $effect && strpos( $effect, 'solid-active-item' ) !== false );

	}

}


Registry::registerBlock(
	__DIR__,
	DropDownMenuBlock::class,
	array(
		'metadata_mixins' => array( '../menu-items-block-json-partial.json' ),
	)
);
