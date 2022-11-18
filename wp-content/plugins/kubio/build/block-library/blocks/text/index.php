<?php

namespace Kubio\Blocks;
use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\DynamicStyles;

class TextBlock extends BlockBase {

	const TEXT               = 'text';


	public function mapDynamicStyleToElements() {
		$dynamicStyles = array();

		$typographyHoldersByMedia = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => $this->getDefaultElement(),
			)
		);

		$dynamicStyles[ self::TEXT ] = DynamicStyles::typographyHolders( $typographyHoldersByMedia );

		return $dynamicStyles;
	}


	public function mapPropsToElements() {
		$content = $this->getBlockInnerHtml();
		$isLead  = $this->getProp( 'isLead' );
		$dropCap = $this->getProp( 'dropCap' );
		$classes = array();
		if ( $isLead ) {
			$classes[] = 'h-lead';
		}
		if ( $dropCap ) {
			$classes[] = 'has-drop-cap';
		}
		return array(
			self::TEXT => array(
				'className' => $classes,
				'innerHTML' => $content,
			),
		);
	}
}


Registry::registerBlock( __DIR__, TextBlock::class );

