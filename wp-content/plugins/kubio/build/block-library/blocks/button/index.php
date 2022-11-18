<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Utils;
use Kubio\Core\Registry;

class ButtonBlock extends BlockBase {


	const OUTER = 'outer';
	const LINK  = 'link';
	const TEXT  = 'text';
	const ICON  = 'icon';

	public function computed() {
		$iconEnabled    = $this->getProp( 'showIcon', false );
		$iconPosition   = $this->getProp( 'iconPosition', 'before' );
		$showBeforeIcon = $iconEnabled && $iconPosition == 'before';
		$showAfterIcon  = $iconEnabled && $iconPosition == 'after';
		return array(
			'showBeforeIcon' => $showBeforeIcon,
			'showAfterIcon'  => $showAfterIcon,
		);
	}

	public function mapPropsToElements() {
		$link           = $this->getAttribute( 'link' );
		$linkAttributes = Utils::getLinkAttributes( $link );
		$iconName       = $this->getAttribute( 'icon.name' );
		$text           = $this->getBlockInnerHtml();
		return array(
			self::LINK => $linkAttributes,

			self::ICON => array(
				'name' => $iconName,
			),

			self::TEXT => array(
				'innerHTML' => $text,
			),
		);
	}
}

Registry::registerBlock( __DIR__, ButtonBlock::class );
