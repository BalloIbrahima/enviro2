<?php

namespace Kubio\Blocks;

 use Kubio\Core\Registry;
use IlluminateAgnostic\Arr\Support\Arr;

class ReadMorebuttonBlock extends ButtonBlock {


	public function mapPropsToElements() {
		$current_map = parent::mapPropsToElements();

		$post_id                          = Arr::get( $this->block_context, 'postId', 0 );
		$current_map[ ButtonBlock::LINK ] = array(
			'href'          => get_permalink( $post_id ),
			'typeOpenLink'  => 'sameWindow',
			'noFollow'      => false,
			'lightboxMedia' => '',
		);

		return $current_map;
	}



}

Registry::registerBlock(
	__DIR__,
	ReadMorebuttonBlock::class,
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
