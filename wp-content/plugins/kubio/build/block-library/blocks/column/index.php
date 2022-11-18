<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockContainerBase;
use Kubio\Core\Layout\LayoutHelper;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\DynamicStyles;


class ColumnBlock extends BlockContainerBase {


	const CONTAINER          = 'container';
	const INNER              = 'inner';
	const ALIGN              = 'align';
	const VSPACE             = 'v-space';
	const TYPOGRAPHY_HOLDERS = 'typographyHolders';


	public function mapDynamicStyleToElements() {
		$dynamicStyles            = array();
		$spaceByMedia             = $this->getPropByMedia(
			'layout.vSpace',
			array()
		);
		$typographyHoldersByMedia = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => $this->getDefaultElement(),
			)
		);


		$dynamicStyles[ self::VSPACE ] = DynamicStyles::vSpace( $spaceByMedia );

		$dynamicStyles[ self::TYPOGRAPHY_HOLDERS ] = DynamicStyles::typographyHolders( $typographyHoldersByMedia );

		return $dynamicStyles;
	}

	public function mapPropsToElements() {
		$row_block = Registry::getInstance()->getLastBlockOfName( 'kubio/row' );

		$columnWidthByMedia = $this->getStyleByMedia(
			'columnWidth',
			array(),
			array(
				'styledComponent' => self::CONTAINER,
				'local'           => true,
			)
		);

		$layoutByMedia    = $this->getPropByMedia( 'layout' );
		$rowLayoutByMedia = $row_block->getPropByMedia( 'layout' );

		$columnWidth  = $columnWidthByMedia['desktop'];
		$layoutHelper = new LayoutHelper( $layoutByMedia, $rowLayoutByMedia );

		$container_cls = LodashBasic::concat(
			$layoutHelper->getColumnLayoutClasses( $columnWidthByMedia ),
			$layoutHelper->getInheritedColumnVAlignClasses()
		);

		$equalWidth = LodashBasic::get( $rowLayoutByMedia, 'desktop.equalWidth', false );

		$align_cls = LodashBasic::concat(
			$layoutHelper->getColumnContentFlexBasis( $equalWidth, $columnWidth ),
			$layoutHelper->getSelfVAlignClasses()
		);

		$inner = $layoutHelper->getColumnInnerGapsClasses();

		$map                    = array();
		$map[ self::CONTAINER ] = array( 'className' => $container_cls );
		$map[ self::INNER ]     = array( 'className' => $inner );
		$map[ self::ALIGN ]     = array( 'className' => $align_cls );
		return $map;
	}
}

Registry::registerBlock(
	__DIR__,
	ColumnBlock::class
);

