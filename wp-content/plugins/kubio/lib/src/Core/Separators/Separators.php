<?php

namespace Kubio\Core\Separators;

use Kubio\Config;
use Kubio\Core\Element;
use Kubio\Core\LodashBasic;

class Separators extends Element {
	function __construct( $tag_name, $props, $children, $block ) {
		parent::__construct( Element::FRAGMENT, $props, $children, $block );

		$topEnabledByMedia    = array();
		$bottomEnabledByMedia = array();
		if ( $block ) {
			$this->value          = $this->block->separators();
			$topEnabledByMedia    = $this->block->separatorTopEnabledByMedia();
			$bottomEnabledByMedia = $this->block->separatorBottomEnabledByMedia();
		}

		$default = Config::value( 'definitions.separator.default' );

		$top    = $this->get( 'top', array() );
		$bottom = $this->get( 'bottom', array() );

		$shouldDisplayTopSeparator    = in_array( true, array_values( $topEnabledByMedia ) );
		$shouldDisplayBottomSeparator = in_array( true, array_values( $bottomEnabledByMedia ) );

		$separators = array();
		if ( $shouldDisplayTopSeparator ) {
			$separators[] = new Separator(
				Element::DIV,
				LodashBasic::merge(
					$default,
					$top,
					array(
						'position'       => 'top',
						'enabledByMedia' => $topEnabledByMedia,
					)
				),
				array(),
				$block
			);
		}

		if ( $shouldDisplayBottomSeparator ) {
			$separators[] = new Separator(
				Element::DIV,
				LodashBasic::merge(
					$default,
					$bottom,
					array(
						'position'       => 'bottom',
						'enabledByMedia' => $bottomEnabledByMedia,
					)
				),
				array(),
				$block
			);
		}

		$this->children = $separators;
	}
}

