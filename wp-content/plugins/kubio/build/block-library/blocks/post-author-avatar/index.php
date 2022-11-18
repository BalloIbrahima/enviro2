<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Utils;
use Kubio\Core\Utils as GeneralUtils;

class PostAuthorAvatarBlock extends BlockBase {

	const OUTER = 'outer';
	const IMAGE = 'image';

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

		$avatarSize     = $this->getAttribute( 'avatarSize' );

		$defaultImg = GeneralUtils::getDefaultAssetsUrl( 'avatar-image-placeholder.png' );
		$src        = get_avatar_url( $authorId, array( 'size' => $avatarSize ) );
		$src        = $src ? $src : $defaultImg;
		$src2x      = get_avatar_url( $authorId, array( 'size' => $avatarSize * 2 ) );
		$src2x      = ( $src2x ? $src2x : $defaultImg ) . ' 2x';



		if ( $avatarSize > 0 ) {
			$map[ self::IMAGE ] = array(
				'src'    => esc_url( $src ),
				'srcset' => esc_attr( $src2x ),
			);
		}

		return $map;
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
	PostAuthorAvatarBlock::class
);
