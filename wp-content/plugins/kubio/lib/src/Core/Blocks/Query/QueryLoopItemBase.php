<?php

namespace Kubio\Core\Blocks\Query;

use Kubio\Core\Blocks\BlockContainerBase;
use Kubio\Core\Layout\LayoutHelper;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\DynamicStyles;

abstract class QueryLoopItemBase extends BlockContainerBase {

	const CONTAINER          = 'container';
	const INNER              = 'inner';
	const ALIGN              = 'align';
	const VSPACE             = 'v-space';
	const TYPOGRAPHY_HOLDERS = 'typographyHolders';

	public function mapDynamicStyleToElements() {
		$dynamic_styles = array();
		$space_by_media = $this->getPropByMedia(
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

		$dynamic_styles[ self::TYPOGRAPHY_HOLDERS ] = DynamicStyles::typographyHolders( $typographyHoldersByMedia );
		$dynamic_styles[ self::VSPACE ]             = DynamicStyles::vSpace( $space_by_media );

		return $dynamic_styles;
	}

	public function mapPropsToElements() {
		$row_block = Registry::getInstance()->getLastBlockOfName( $this->loopBlockName() );

		$column_width_by_media = $this->getStyleByMedia(
			'columnWidth',
			array(),
			array(
				'styledComponent' => self::CONTAINER,
				'local'           => true,
			)
		);

		$layout_media        = $this->getPropByMedia( 'layout' );
		$row_layout_by_media = $row_block ? $row_block->getPropByMedia( 'layout' ) : array();

		$column_width  = $column_width_by_media['desktop'];
		$layout_helper = new LayoutHelper( $layout_media, $row_layout_by_media );

		$container_cls = LodashBasic::concat(
			$layout_helper->getColumnLayoutClasses( $column_width_by_media ),
			$layout_helper->getInheritedColumnVAlignClasses(),
			get_post_class()
		);

		$equal_width = LodashBasic::get( $row_layout_by_media, 'desktop.equalWidth', false );

		$align_cls = LodashBasic::concat(
			$layout_helper->getColumnContentFlexBasis( $equal_width, $column_width ),
			$layout_helper->getSelfVAlignClasses()
		);

		$inner = $layout_helper->getColumnInnerGapsClasses();

		$map                    = array();
		$map[ self::CONTAINER ] = array( 'className' => $container_cls );
		$map[ self::ALIGN ]     = array( 'className' => $align_cls );
		$map[ self::INNER ]     = array( 'className' => $inner );

		return $map;
	}

	/**
	 * @return string
	 */
	public abstract function loopBlockName();
}
