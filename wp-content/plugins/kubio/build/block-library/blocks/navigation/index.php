<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockContainerBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Styles\FlexAlign;
use Kubio\Core\Utils;
use Kubio\Core\StyleManager\DynamicStyles;


class NavigationTopBarBlock extends SectionBlock {

}

class NavigationBlock extends BlockContainerBase {

	const NAVIGATION_CONTAINER = 'outer';
	const NAVIGATION_SECTION   = 'section';
	const TYPOGRAPHY_HOLDERS = 'typographyHolders';

	public function mapPropsToElements() {
		$overlap              = $this->getProp( 'overlap' );
		$verticalAlignByMedia = $this->getPropByMedia( 'verticalAlign' );
		$verticalAlignClasses = FlexAlign::getVAlignClasses( $verticalAlignByMedia );

		$map              = array();
		$containerClasses = array();
		if ( $overlap ) {
			$containerClasses[] = 'h-navigation_overlap';
		}

		$map[ self::NAVIGATION_CONTAINER ] = LodashBasic::merge(
			$overlap ? Utils::useJSComponentProps( 'overlap', $overlap ) : array(),
			array(
				'className' => $containerClasses,
			)
		);

		return $map;
	}
	public function mapDynamicStyleToElements() {
		$dynamicStyles            = array();

		$typographyHoldersByMedia = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => $this->getDefaultElement(),
			)
		);


		$dynamicStyles[ self::TYPOGRAPHY_HOLDERS ] = DynamicStyles::typographyHolders( $typographyHoldersByMedia );

		return $dynamicStyles;
	}

}


class NavigationSectionBlock extends BlockContainerBase {

	const NAVIGATION         = 'nav';
	const NAVIGATION_SECTION = 'nav-section';

	// move to json
	const IMMEDIATELY         = 'immediately';
	const AFTER_HERO          = 'afterHero';
	static $WidthTypesClasses = array(
		'full-width' => 'h-section-fluid-container',
		'boxed'      => 'h-section-boxed-container',
	);

	public function mapPropsToElements() {
		$navigation_block = Registry::getInstance()->getLastBlockOfName( 'kubio/navigation' );

		$width = $navigation_block->getProp( 'width', 'boxed' );
		$map   = array();

		$map[ self::NAVIGATION_SECTION ] = array( 'className' => self::$WidthTypesClasses[ $width ] );
		$map[ self::NAVIGATION ]         = LodashBasic::merge(
			Utils::useJSComponentProps( 'navigation', $this->navigationScriptExport( $navigation_block ) ),
			array()
		);

		return $map;
	}

	public function navigationScriptExport( $block ) {
		return array(
			'sticky'  => $this->stickyExport( $block ),
			'overlap' => $block->getProp( 'overlap' ),
		);
	}

	public function stickyExport( $block ) {
		$isSticky = $block->getProp( 'sticky', false );

		if ( ! $isSticky ) {
			return false;
		}
		$stickyStartAt   = $block->getProp( 'stickyStartAt' );
		$enableAnimation = $stickyStartAt === self::AFTER_HERO;

		$animationName     = $block->getProp( 'animation.name' );
		$animationDuration = $block->getStyle(
			'animation.duration.value',
			0,
			array(
				'styledComponent' => NavigationBlock::NAVIGATION_CONTAINER,
				'media'           => 'desktop',
				'ancestor'        => '',
			)
		);

		return array(
			'startAfterNode' => array(
				'enabled' => $enableAnimation,
			),
			'animations'     => array(
				'enabled'  => $enableAnimation,
				'duration' => $animationDuration,
				'name'     => $animationName,
			),
		);
	}
}


class NavigationItemsBlock extends BlockContainerBase {

	const NAVIGATION_CONTAINER = 'outer';

	public function mapPropsToElements() {
		$map                               = array();
		$map[ self::NAVIGATION_CONTAINER ] = array();

		return $map;
	}
}

class NavigationStickyItemsBlock extends BlockContainerBase {

	const NAVIGATION_CONTAINER = 'outer';

	public function mapPropsToElements() {
		$navigation_block                  = Registry::getInstance()->getLastBlockOfName( 'kubio/navigation' );
		$isSticky                          = $navigation_block->getProp( 'sticky', false );
		$map                               = array();
		$map[ self::NAVIGATION_CONTAINER ] = array(
			'shouldRender' => $isSticky,
			'style'        => array(
				'display' => 'none',
			),
		);

		return $map;
	}
}

Registry::registerBlock( __DIR__ . '/blocks/navigation', NavigationBlock::class );
Registry::registerBlock( __DIR__ . '/blocks/navigation-section', NavigationSectionBlock::class );
Registry::registerBlock( __DIR__ . '/blocks/navigation-normal', NavigationItemsBlock::class );
Registry::registerBlock( __DIR__ . '/blocks/navigation-sticky', NavigationStickyItemsBlock::class );
Registry::registerBlock( __DIR__ . '/blocks/navigation-top-bar', NavigationTopBarBlock::class );
