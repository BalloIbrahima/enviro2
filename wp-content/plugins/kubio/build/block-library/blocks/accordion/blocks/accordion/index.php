<?php

namespace Kubio\Blocks;

use Kubio\Config;
use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\DynamicStyles;
use Kubio\Core\Utils;


class AccordionBlock extends BlockBase {

	const OUTER              = 'outer';
	const CONTENT            = 'content';
	const VSPACE             = 'v-space';
	const TYPOGRAPHY_HOLDERS = 'typographyHolders';

	public function mapDynamicStyleToElements() {
		$dynamicStyles = array();
		$spaceByMedia  = $this->getPropByMedia(
			'vSpace',
			array()
		);

		$typographyHoldersByMedia = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => self::CONTENT,
			)
		);

		$dynamicStyles[ self::TYPOGRAPHY_HOLDERS ] = DynamicStyles::typographyHolders( $typographyHoldersByMedia );
		$dynamicStyles[ self::VSPACE ]             = DynamicStyles::vSpace( $spaceByMedia );
		return $dynamicStyles;
	}

	public function mapPropsToElements() {

		$openMultipleItems = $this->getProp( 'openMultipleItems', false );
		$scriptData        = Utils::useJSComponentProps(
			'accordion',
			array(
				'toggle' => ! $openMultipleItems,
			)
		);

		return array(
			self::OUTER => $scriptData,
		);
	}
}

Registry::registerBlock(
	__DIR__,
	AccordionBlock::class
);
