<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\TemplatePartBlockBase;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\DynamicStyles;

class SidebarTemplatePart extends TemplatePartBlockBase {
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
}

Registry::registerBlock( __DIR__, SidebarTemplatePart::class );
