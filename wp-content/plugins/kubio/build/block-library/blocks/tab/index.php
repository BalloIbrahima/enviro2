<?php

namespace Kubio\Blocks;

use Kubio\Config;
use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\DynamicStyles;
use Kubio\Core\Utils;

class TabBlock extends BlockBase {

	const OUTER              = 'outer';
	const VSPACE             = 'v-space';
	const CONTENT            = 'content';
	const TYPOGRAPHY_HOLDERS = 'typographyHolders';

	public function mapDynamicStyleToElements() {
		$dynamicStyles            = array();
		$spaceByMedia             = $this->getPropByMedia(
			'vSpace',
			array()
		);
		$typographyHoldersByMedia = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => self::OUTER,
			)
		);

		$dynamicStyles[ self::TYPOGRAPHY_HOLDERS ] = DynamicStyles::typographyHolders( $typographyHoldersByMedia );
		$dynamicStyles[ self::VSPACE ]             = DynamicStyles::vSpace( $spaceByMedia );
		return $dynamicStyles;
	}

	public function mapPropsToElements() {
		$scriptData           = Utils::useJSComponentProps( 'tabs' );
		$layout               = $this->getProp( 'layout' );
		$tabItemsWidthByMedia = $this->getPropByMedia( 'tabItemsWidth' );
		$classes              = array_merge(
			$this->getTabItemsWidthClasses( $tabItemsWidthByMedia, $layout ),
			$this->getLayoutClasses( $layout )
		);
		return array(
			self::OUTER => array_merge(
				array( 'className' => $classes ),
				$scriptData
			),
		);
	}

	public function getTabItemsWidthClasses( $valueByMedia, $layout ) {
		$mediasById = Config::mediasById();
		$classes    = array();
		foreach ( $mediasById as $mediaId => $media ) {
			$itemWidth          = LodashBasic::get( $valueByMedia, $mediaId );
			$itemWidthForLayout = LodashBasic::get( $itemWidth, $layout );
			$gridPrefix         = LodashBasic::get( $media, 'gridPrefix' );
			$prefix             = $gridPrefix ? sprintf( '-%s', $gridPrefix ) : '';
			$widthClass         = sprintf( 'h-tabs--%s--%s%s', $layout, $itemWidthForLayout, $prefix );
			$classes[]          = $widthClass;
		}
		return $classes;
	}

	public function getLayoutClasses( $layout ) {
		$classes = array();
		switch ( $layout ) {
			case 'horizontal':
				$classes[] = 'h-tabs-horizontal';
				break;
			case 'vertical':
				$classes[] = 'h-tabs-vertical';
				break;
		}
		return $classes;
	}
}

class TabItemsBlock extends BlockBase {

	const OUTER = 'outer';


	public function mapPropsToElements() {

		return array();
	}
}

class TabItemBlock extends BlockBase {

	const OUTER = 'outer';
	const INNER = 'inner';

	public function mapPropsToElements() {
		$isFirst = LodashBasic::get( $this->parent_block_->block_data, 'innerBlocks.0.attrs.kubio.hash' )
			   === LodashBasic::get( $this->block_data, 'attrs.kubio.hash' );

		$slug = $this->getAttribute( 'slug' );
		return array(
			self::OUTER => array(),
			self::INNER => array(
				'id'        => $slug,
				'className' => $isFirst ? 'h-tabs-content-active' : '',
			),
		);
	}
}

class TabNavigationBlock extends BlockBase {

	const OUTER = 'outer';


	public function mapPropsToElements() {

		$tab_block = Registry::getInstance()->getLastBlockOfName( 'kubio/tab' );
		$tabItems  = LodashBasic::get( $tab_block->block_data, array( 'innerBlocks', 1, 'innerBlocks' ) );

		$content     = '';
		$tabWrapper  = new TabBlock( $tab_block->block_data );
		$iconEnabled = $tabWrapper->getProp( 'icons.show' );
		foreach ( $tabItems as $index => $tabItem ) {
			$clone              = LodashBasic::merge( array(), $tabItem );
			$blockWrapper       = new TabItemBlock( $clone );
			$clone['blockName'] = 'kubio/tabnavigationitem';
			$title              = $blockWrapper->getAttribute( 'title' );
			$slug               = $blockWrapper->getAttribute( 'slug' );
			$icon               = $blockWrapper->getAttribute( 'icon' );
			$context            = array(
				'title'       => esc_html( $title ),
				'slug'        => $slug,
				'iconName'    => $icon,
				'iconEnabled' => $iconEnabled,
				'arrayIndex'  => $index,
			);
			$content           .= (
			new \WP_Block(
				$clone,
				$context
			)
			)->render();
		}

		return array(
			self::OUTER => array(
				'innerHTML' => $content,
			),
		);
	}
}

class TabNavigationItemBlock extends BlockBase {

	const OUTER = 'outer';
	const ICON  = 'icon';
	const LINK  = 'link';
	const TEXT  = 'text';

	public function computed() {
		$iconEnabled      = LodashBasic::get( $this->block_context, 'iconEnabled' );
		$title            = LodashBasic::get( $this->block_context, 'title' );
		$shouldRenderText = ! ! $title;
		return array(
			'iconEnabled'      => $iconEnabled,
			'shouldRenderText' => $shouldRenderText,
		);
	}

	public function mapPropsToElements() {

		$title      = LodashBasic::get( $this->block_context, 'title' );
		$iconName   = LodashBasic::get( $this->block_context, 'iconName' );
		$arrayIndex = LodashBasic::get( $this->block_context, 'arrayIndex' );
		$link       = sprintf( '#%s', LodashBasic::get( $this->block_context, 'slug' ) );
		return array(
			self::LINK => array(
				'href'      => $link,
				'className' => $arrayIndex === 0 ? 'h-tabs-navigation-active-item h-custom-active-state' : '',
			),
			self::ICON => array(
				'name' => $iconName,
			),
			self::TEXT => array(
				'innerHTML' => $title,
			),
		);
	}
}

Registry::registerBlock(
	__DIR__,
	TabNavigationBlock::class,
	array(
		'metadata' => './blocks/tab-navigation/block.json',
	)
);

Registry::registerBlock(
	__DIR__,
	TabNavigationItemBlock::class,
	array(
		'metadata' => './blocks/tab-navigation-item/block.json',
	)
);

Registry::registerBlock(
	__DIR__,
	TabItemBlock::class,
	array(
		'metadata' => './blocks/tab-item/block.json',
	)
);
Registry::registerBlock(
	__DIR__,
	TabItemsBlock::class,
	array(
		'metadata' => './blocks/tab-items/block.json',
	)
);

Registry::registerBlock(
	__DIR__,
	TabBlock::class,
	array(
		'metadata' => './blocks/tab/block.json',
	)
);
