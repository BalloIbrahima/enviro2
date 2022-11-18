<?php

namespace Kubio\Blocks;

use Kubio\Core\Registry;

class QueryLayout extends SectionBlock {}

Registry::registerBlock(
	__DIR__,
	QueryLayout::class,
	array(
		'metadata'        => '../section/block.json',
		'metadata_mixins' => array( 'block.json' ),
	)
);
