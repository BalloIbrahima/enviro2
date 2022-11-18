<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Utils;
use Kubio\Core\Registry;

class SpacerBlock extends BlockBase {


	const CONTAINER = 'container';

}

Registry::registerBlock( __DIR__, SpacerBlock::class );
