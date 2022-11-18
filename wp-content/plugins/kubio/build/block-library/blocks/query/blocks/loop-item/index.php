<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\Query\QueryLoopItemBase;
use Kubio\Core\Registry;


class LoopItemBlock extends QueryLoopItemBase {

	/**
	 * get current loop block name
	 * @return string;
	 */
	public function loopBlockName() {
		return 'kubio/query-loop';
	}

}

Registry::registerBlock(
	__DIR__,
	LoopItemBlock::class,
	array(
		'metadata'        => '../../../column/block.json',
		'metadata_mixins' => array( './block.json' ),
	)
);

