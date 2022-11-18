<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;

class IconBlock extends BlockBase {

	const OUTER = 'outer';
	const INNER = 'inner';

	public function mapPropsToElements() {
		$link = $this->getAttribute( 'link' );
		$name = $this->getAttribute( 'name' );
		return array(
			self::OUTER => array( 'link' => $link ),
			self::INNER => array( 'name' => $name ),
		);
	}
}


Registry::registerBlock(
	__DIR__,
	IconBlock::class
);
