<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockContainerBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Layout\LayoutHelper;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\DynamicStyles;

class RowBlock extends BlockContainerBase {

	const CONTAINER  = 'container';
	const INNER      = 'inner';
	const CENTER     = 'center';
	const OUTER_GAPS = 'outerGaps';
	const INNER_GAPS = 'innerGaps';
	const TYPOGRAPHY_HOLDERS = 'typographyHolders';

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

	public function mapPropsToElements() {
		$layoutByMedia          = $this->getPropByMedia( 'layout' );
		$layoutHelper           = new LayoutHelper( $layoutByMedia );
		$map                    = array();
		$map[ self::CONTAINER ] = array( 'className' => $layoutHelper->getRowGapClasses() );
		$map[ self::INNER ]     = array( 'className' => LodashBasic::concat( $layoutHelper->getRowAlignClasses(), $layoutHelper->getRowGapInnerClasses() ) );
		return $map;
	}
}

Registry::registerBlock( __DIR__, RowBlock::class );
