<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;

class DividerBlock extends BlockBase {

	const OUTER = 'outer';
	const LINE  = 'line';
	const INNER = 'inner';

	public function computed() {
		$iconEnabled = false;
		if ( $this->getProp( 'type' ) === 'icon' ) {
			$iconEnabled = true;
		}
		return array(
			'iconEnabled' => $iconEnabled,
		);
	}

	public function mapPropsToElements() {
		if ( $this->getProp( 'type' ) === 'icon' ) {
			if ( ! $this->getAttribute( 'iconName' ) ) {
				$icon = '';
			} else {
				$icon = $this->getAttribute( 'iconName' );
			}
		} else {
			$icon = null;
		}
		return array(
			self::INNER => array( 'name' => $icon ),
		);
	}
}


Registry::registerBlock(
	__DIR__,
	DividerBlock::class
);
