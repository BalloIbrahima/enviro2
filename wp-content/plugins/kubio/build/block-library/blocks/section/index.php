<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockContainerBase;
use Kubio\Core\Registry;
use Kubio\Core\Styles\FlexAlign;
use Kubio\Core\StyleManager\DynamicStyles;

class SectionBlock extends BlockContainerBase {

	const TYPOGRAPHY_HOLDERS = 'typographyHolders';

	// move to json
	static $WidthTypesClasses = array(
		'full-width' => 'h-section-fluid-container',
		'boxed'      => 'h-section-boxed-container',
	);

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
		$verticalAlignByMedia = $this->getPropByMedia( 'verticalAlign' );
		$verticalAlignClasses = FlexAlign::getVAlignClasses( $verticalAlignByMedia );
		$width                = $this->getProp( 'width', 'boxed' );
		$map                  = array();
		$map['outer']         = array(
			'className' => $verticalAlignClasses,
		);
		$map['inner']         = array( 'className' => isset( self::$WidthTypesClasses[ $width ] ) ? self::$WidthTypesClasses[ $width ] : '' );
		return $map;
	}
}

Registry::registerBlock( __DIR__, SectionBlock::class );
