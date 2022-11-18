<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;

class MapBlock extends BlockBase {

	const OUTER   = 'outer';
	const WRAPPER = 'wrapper';
	const IFRAME  = 'iframe';

	public function __construct( $block, $autoload = true ) {
		parent::__construct( $block, $autoload );
	}

	public function computed() {
		return array(
			'address' => $this->getAttribute( 'address' ),
			'zoom'    => $this->getAttribute( 'zoom' ),
		);
	}

	public function mapPropsToElements() {
		$address = $this->getAttribute( 'address' );
		$zoom    = $this->getAttribute( 'zoom' );
		$key     = $this->getAttribute( 'apiKey' );
		if ( ! $address ) {
			$address = 'New York';
		}

		$base_url        = 'https://www.google.com/maps/embed/v1/place';
		$no_api_base_url = 'https://maps.google.com/maps';

		if ( $key ) {
			$zoomArg = 'zoom';
			$src     = add_query_arg(
				array(
					'key' => $key,
					'q'   => $address,
				),
				$base_url
			);
		} else {
			$zoomArg = 'z';
			$src     = add_query_arg(
				array(
					'q'      => $address,
					'output' => 'embed',
					'iwloc'  => 'near',
				),
				$no_api_base_url
			);
		}

		if ( ! empty( $zoom['value'] ) ) {
			$src = add_query_arg( array( $zoomArg => $zoom['value'] ), $src );
		}

		return array(
			self::OUTER   => array(),
			self::WRAPPER => array(
				'className' => 'frontend-wrapper',
			),
			self::IFRAME  => array(
				'src' => $src,
			),
		);
	}
}

Registry::registerBlock( __DIR__, MapBlock::class );
