<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Utils;
use Kubio\Core\Utils as GeneralUtils;

class PostAuthorNameBlock extends BlockBase {

	const TEXT = 'text';

	public function computed() {
		return array();
	}

	public function mapPropsToElements() {
		if ( ! ( $postId = LodashBasic::get( $this->block_context, 'postId' ) ) ) {
			return null;
		}

		$authorId = get_post_field( 'post_author', $postId );
		if ( empty( $authorId ) ) {
			return '';
		}

		$authorData = get_userdata( $authorId );

		return array(
			self::TEXT => array_merge(
				array(
					'innerHTML' => $authorData->display_name,
				)
			),
		);
	}

	public function getLinkAttribute() {
		if ( ! ( $postId = LodashBasic::get( $this->block_context, 'postId' ) ) ) {
			return null;
		}

		$authorId = get_post_field( 'post_author', $postId );
		if ( empty( $authorId ) ) {
			return '';
		}

		return  $this->getLinkAttributes( $authorId );
	}

	public function getLinkAttributes( $aAuthorId ) {
		if ( ! $this->getAttribute( 'addLink' ) ) {
			return array();
		}

		$authorLink = get_author_posts_url( $aAuthorId );
		$link       = array(
			'value' => $authorLink,
		);

		return $link;
	}
}

Registry::registerBlock(
	__DIR__,
	PostAuthorNameBlock::class
);
