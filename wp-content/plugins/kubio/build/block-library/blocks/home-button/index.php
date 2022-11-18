<?php

namespace Kubio\Blocks;

use Kubio\Core\Registry;

class HomeButtonBlock extends ButtonBlock {

	public function mapPropsToElements() {
		$current_map = parent::mapPropsToElements();

		$current_map[ ButtonBlock::LINK ] = array(
			'href'          => home_url(),
			'typeOpenLink'  => 'sameWindow',
			'noFollow'      => false,
			'lightboxMedia' => '',
		);

		return $current_map;
	}
}

Registry::registerBlock(
	__DIR__,
	HomeButtonBlock::class,
	array(
		'metadata'             => '../button/block.json',
		'metadata_mixins'      => array( './block.json' ),
		'mixins_exact_replace' => array(
			'./block.json' => array(
				'supports.kubio.template',
			),
		),
	)
);
