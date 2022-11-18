<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;

class PostAuthorBioBlock extends BlockBase {

	const TEXT                = 'text';
	private $author_data_desc = null;

	public function computed() {
		$this->author_data_desc = $this->getAuthorData( $this->block_context )->description;
		return array(
			'showAuthorBio' => $this->author_data_desc ? true : false,
		);
	}

	public function mapPropsToElements() {
		return array(
			self::TEXT => array_merge(
				array(
					'innerHTML' => $this->author_data_desc,
				)
			),
		);
	}

	private function getAuthorData( $aBlockContext ) {
		if ( ! ( $postId = LodashBasic::get( $aBlockContext, 'postId' ) ) ) {
			return null;
		}

		$authorId = get_post_field( 'post_author', $postId );
		if ( empty( $authorId ) ) {
			return null;
		}

		return get_userdata( $authorId );
	}

}

Registry::registerBlock(
	__DIR__,
	PostAuthorBioBlock::class
);
