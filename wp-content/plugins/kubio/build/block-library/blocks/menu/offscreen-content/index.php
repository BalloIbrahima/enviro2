<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;



class MenuOffscreenContent extends BlockBase {
}

Registry::registerBlock(
	__DIR__,
	MenuOffscreenContent::class
);

