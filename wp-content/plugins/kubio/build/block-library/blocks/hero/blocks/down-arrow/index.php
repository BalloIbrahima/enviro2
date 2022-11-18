<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Blocks\BlockContainerBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;


/**
 * DownArrowBlock Main component
 * Down arrow code.
 */
class DownArrowBlock extends BlockBase {
	const OUTER = 'outer';
	const INNER = 'inner';

	public function __construct( $block, $autoload = true ) {
		parent::__construct( $block, $autoload );
	}

	public function mapPropsToElements() {
		$options = array(
			'arrowSelector'        => '.wp-block-kubio-downarrow__inner',
			'scrollTargetSelector' => '.wp-site-blocks > .wp-block-kubio-header + div',
		);

		$scriptData = Utils::useJSComponentProps(
			'downarrow',
			$options
		);

		$outerClasses = array();
		if ( $this->getProp( 'bounce' ) ) {
			array_push( $outerClasses, 'move-down-bounce' );
		}

		return array(
			self::OUTER => array_merge( array( 'className' => $outerClasses ), $scriptData ),
		);
	}
}

Registry::registerBlock( __DIR__, DownArrowBlock::class );
