<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\Query\QueryLoopBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;

class LoopBlock extends QueryLoopBase {

	function mapPropsToElements() {
		$js_props = Utils::useJSComponentProps(
			'masonry',
			array(
				'enabled'        => $this->getAttribute( 'masonry', false ),
				'targetSelector' => '.' . $this->elementClass( QueryLoopBase::INNER ),
			)
		);

		$map = parent::mapPropsToElements();

		$map[ QueryLoopBase::CONTAINER ] = array_merge(
			$map[ QueryLoopBase::CONTAINER ],
			$js_props
		);

		return $map;
	}
}

Registry::registerBlock(
	__DIR__,
	LoopBlock::class,
	array(
		'metadata'        => '../../../row/block.json',
		'metadata_mixins' => array( './block.json' ),
	)
);
