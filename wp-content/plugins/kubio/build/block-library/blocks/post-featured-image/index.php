<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Styles\FlexAlign;
use Kubio\Core\Utils;
use Kubio\Core\Utils as GeneralUtils;

class PostFeaturedImageBlock extends Blockbase {


	const IMAGE     = 'image';
	const CONTAINER = 'container';
	const INNER     = 'inner';
	const ALIGN     = 'align';

	public function computed() {
		return array(
			'showImage' => $this->shouldRenderImage(),
		);
	}

	public function getFeaturedImageUrl() {
		if ( ! ( $post_ID = LodashBasic::get( $this->block_context, 'postId' ) ) ) {
			return null;
		}

		$url = get_the_post_thumbnail_url( $post_ID );
		$url = apply_filters( 'kubio/post-featured-image-url', $url );
		return $url;
	}

	public function getFeaturedImageId() {
		if ( ! ( $post_ID = LodashBasic::get( $this->block_context, 'postId' ) ) ) {
			return null;
		}

		$id = get_post_thumbnail_id( $post_ID );
		$id = apply_filters( 'kubio/post-featured-image-id', $id );
		return $id;
	}

	public function shouldRenderImage() {
		$featuredImageUrl = $this->getFeaturedImageUrl();
		$showPlaceholder  = $this->getAttribute( 'showPlaceholder' );
		return $featuredImageUrl || ( ! $featuredImageUrl && ! $showPlaceholder );
	}

	public function getImageUrl() {

		$featuredImage = $this->getFeaturedImageUrl();
		if ( $featuredImage ) {
			return $featuredImage;
		} else {
			return null;
		}
	}

	public function mapPropsToElements() {
		$imageSize        = $this->getStyle(
			'object.fit',
			null,
			array(
				'styledComponent' => 'image',
			)
		);
		$isNaturalSize    = $imageSize === 'fill';
		$containerClasses = array();
		if ( $isNaturalSize ) {
			$containerClasses[] = 'kubio-post-featured-image--natural-size';
		}
		if ( $this->shouldRenderImage() ) {
			$containerClasses[] = 'kubio-post-featured-image--has-image';
		}
		$imageUrl = $this->getImageUrl();
		$linkData = $this->getLinkData();

		$imageClasses = array();
		//only hide image in frontend. Show placeholder in editor
		$imageAttributes = array();
		if ( ! $imageUrl ) {
			$containerClasses[] = 'kubio-post-featured-image--image-missing';
		} else {

			//when it shows image
			$imageId      = $this->getFeaturedImageId();
			$imageClasses = array( 'wp-image-' . $imageId );
			$alt          = trim( strip_tags( get_post_meta( $imageId, '_wp_attachment_image_alt', true ) ) );
			if ( ! ! $alt ) {
				$imageAttributes['alt'] = $alt;
			}
			$imageAttributes['className'] = $imageClasses;
		}

		$verticalAlignByMedia = $this->getPropByMedia( 'verticalAlign' );
		$alignClasses         = FlexAlign::getVAlignClasses( $verticalAlignByMedia, array( 'self' => true ) );
		$aspectRatioClass     = $this->getAspectRatioClass( $this->getProp( 'aspectRatio' ) );
		$containerClasses[]   = $aspectRatioClass;
		return array(
			self::CONTAINER => array_merge(
				array( 'className' => $containerClasses ),
				$linkData
			),
			self::IMAGE     => array_merge(
				array(
					'src' => $imageUrl,
				),
				$imageAttributes
			),
			self::ALIGN     => array(
				'className' => $alignClasses,
			),
		);
	}

	public function getLinkData() {
		if ( ! $this->getAttribute( 'addLink' ) ) {
			return array();
		}
		$postId         = LodashBasic::get( $this->block_context, 'postId' );
		$postLink       = get_permalink( $postId );
		$link           = array(
			'value' => $postLink,
		);
		$linkAttributes = Utils::getLinkAttributes( $link );
		$scriptData     = Utils::useJSComponentProps( 'link', $linkAttributes );

		return $scriptData;
	}

	public function getAspectRatioClass( $aspectRatioValue ) {
		return sprintf( 'h-aspect-ratio--%s', $aspectRatioValue );
	}
}

Registry::registerBlock( __DIR__, PostFeaturedImageBlock::class );


