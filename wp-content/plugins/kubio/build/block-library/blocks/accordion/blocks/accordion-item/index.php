<?php

namespace Kubio\Blocks;

use Kubio\Config;
use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\DynamicStyles;
use Kubio\Core\Utils;



class AccordionItemBlock extends BlockBase {

	const OUTER       = 'outer';
	const TITLE       = 'title';
	const ICON_NORMAL = 'iconNormal';
	const ICON_ACTIVE = 'iconActive';
	const TITLE_TEXT  = 'titleText';
	const CONTENT     = 'content';

	public function mapPropsToElements() {
		$accordionBlock = Registry::getInstance()->getLastBlockOfName( 'kubio/accordion' );

		$normalIcon   = $accordionBlock->getProp( 'accordionItems.normalIcon' );
		$activeIcon   = $accordionBlock->getProp( 'accordionItems.activeIcon' );
		$iconPosition = $accordionBlock->getProp( 'accordionItems.iconPosition' );
		$iconClasses  = array( $this->getIconPositionClass( $iconPosition ) );

		$slug          = uniqid( 'accordion-' );
		$title         = $this->getAttribute( 'title' );
		$openByDefault = $this->getAttribute( 'openByDefault', false );
		return array(

			self::TITLE       => array(
				'href'                 => sprintf( '#%s', $slug ),
				'data-open-by-default' => $openByDefault ? 'true' : 'false',
			),
			self::ICON_NORMAL => array(
				'name'      => $normalIcon,
				'className' => $iconClasses,
			),
			self::ICON_ACTIVE => array(
				'name'      => $activeIcon,
				'className' => $iconClasses,
			),
			self::TITLE_TEXT  => array(
				'innerHTML' => esc_html( $title ),
			),
			self::CONTENT     => array(
				'id' => $slug,
			),

		);
	}

	public function getIconPositionClass( $position ) {
		return sprintf( 'h-accordion-item-title-icon--%s', $position );
	}
}

Registry::registerBlock(
	__DIR__,
	AccordionItemBlock::class
);
