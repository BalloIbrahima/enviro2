<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;
use IlluminateAgnostic\Arr\Support\Arr;

class AccordionMenuBlock extends BlockBase {
	const BLOCK_NAME = 'kubio/accordion-menu';

	public function mapPropsToElements() {
		$jsProps = Utils::useJSComponentProps( 'accordion-menu' );

		return array(
			'outer' => $jsProps,
		);
	}
}


Registry::registerBlock(
	__DIR__,
	AccordionMenuBlock::class,
	array(
		'metadata'        => './block.json',
		'metadata_mixins' => array( '../menu-items-block-json-partial.json' ),
	)
);
