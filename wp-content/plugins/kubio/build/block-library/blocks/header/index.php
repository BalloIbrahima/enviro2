<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\TemplatePartBlockBase;
use Kubio\Core\Registry;

class HeaderTemplatePart extends TemplatePartBlockBase {
}

Registry::registerBlock( __DIR__, HeaderTemplatePart::class );
