<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;

class QueryBlock extends BlockBase {}

Registry::registerBlock(
	__DIR__,
	QueryBlock::class,
	array(
		'metadata' => './block.json',
	)
);

