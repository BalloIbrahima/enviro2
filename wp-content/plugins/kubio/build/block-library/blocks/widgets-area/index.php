<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;

class WidgetAreaBlock extends Blockbase {
	const CONTAINER = 'container';

	public function computed() {
		return array();
	}

	public function renderWidgetArea() {
		$id = $this->getAttribute( 'id', '' );
		if ( ! $id ) {
			return Utils::getFrontendPlaceHolder( __( 'Please choose a widget area to be displayed', 'kubio' ) );
		}

		if ( ! is_active_sidebar( $id ) ) {
			$content = Utils::getFrontendPlaceHolder( __( 'Widget area not found', 'kubio' ) );
		} else {
			ob_start();
			dynamic_sidebar( $id );
			$content = ob_get_clean();
		}

		return $content;
	}

	public function serverSideRender() {
		return $this->renderWidgetArea();
	}

	public function mapPropsToElements() {
		$containerClasses = array();
		return array(
			self::CONTAINER => array_merge(
				array(
					'className' => $containerClasses,
					'innerHTML' => $this->renderWidgetArea(),
				)
			),
		);
	}
}

Registry::registerBlock( __DIR__, WidgetAreaBlock::class );



