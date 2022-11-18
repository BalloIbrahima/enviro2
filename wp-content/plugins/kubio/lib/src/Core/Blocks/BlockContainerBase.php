<?php

namespace Kubio\Core\Blocks;

use Kubio\Config;

class BlockContainerBase extends BlockBase {


	public $backgroundElement;
	public $separatorElement;

	public function __construct( $block, $autoload, $context ) {
		 parent::__construct( $block, $autoload, $context );
		$this->backgroundElement = $this->findElementBy( 'supports.background', true );
		$this->separatorElement  = $this->findElementBy( 'supports.separator', true );
		parent::create();
	}

	public function backgroundByMedia() {
		return $this->getStyleByMedia(
			'background',
			Config::value( 'props.background.default' ),
			array(
				'styledComponent' => $this->backgroundElement,
			)
		);
	}

	public function separators() {
		return $this->getStyle(
			'separators',
			array(),
			array(
				'styledComponent' => $this->separatorElement,
			)
		);
	}

	public function separatorTopEnabledByMedia() {
		return $this->getStyleByMedia(
			'separators.top.enabled',
			false,
			array(
				'styledComponent' => $this->separatorElement,
			)
		);
	}
	public function separatorBottomEnabledByMedia() {
		return $this->getStyleByMedia(
			'separators.bottom.enabled',
			false,
			array(
				'styledComponent' => $this->separatorElement,
			)
		);
	}
}
