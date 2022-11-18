<?php

namespace Kubio\Blocks;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Utils;

class ImageGalleryBlock extends BlockBase {
	const GALLERY = 'gallery';
	const STYLE   = 'style';

	public $blockNamespace = 'image-gallery';

	public function mapPropsToElements() {

		$hoverEffect     = $this->getProp( 'hoverEffect' );
		$captionSettings = $this->getProp( 'caption', array( 'enabled' => false ) );

		$jsProps = Utils::useJSComponentProps(
			'image-gallery',
			array(
				'showMasonry' => $this->getProp( 'showMasonry' ),
				'gridGap'     => $this->getProp( 'gridGap' ),
				'columns'     => $this->getProp( 'columns' ),
			)
		);

		$masonry           = $this->getProp( 'showMasonry' );
		$gallery_classname = array( 'wp-block-kubio-image-gallery_classic' );

		if ( $masonry ) {
			wp_enqueue_script( 'jquery-masonry' );
			$gallery_classname = array( 'wp-block-kubio-image-gallery_masonry' );
		}

		if ( $hoverEffect['enabled'] === true ) {
			$gallery_classname[] = 'hover-effect--' . $hoverEffect['type'];
		}

		if ( $hoverEffect['enabled'] === true ) {
			$gallery_classname[] = 'hover-effect--' . $hoverEffect['type'];
		}

		if ( Arr::get( $captionSettings, 'enabled', false ) ) {
			$gallery_classname[] = 'caption--' . $captionSettings['verticalAlign'];
			$gallery_classname[] = 'caption-position--' . $captionSettings['position'];
		}

		return array(
			self::GALLERY => array_merge(
				$jsProps,
				array(
					'className' => $gallery_classname,
				)
			),
			self::STYLE   => array(
				'innerHTML' => $this->renderStyle(),
			),
		);
	}

	public function renderStyle() {
		$columns_per_media = $this->getPropByMedia( 'columns' );

		$columns_media_sizes = array(
			'desktop' => "\n@media (min-width: 1023px) {\n",
			'tablet'  => "\n@media (min-width: 768px) and (max-width: 1023px) {\n",
			'mobile'  => "\n@media (max-width: 767px) {\n",
		);

		$style = '';


		$local_style_class = $this->getLocalIdClass('container');

		foreach ( $columns_per_media as $key => $value ) {
			$nr_columns_per_media = $value;

			$style .= $columns_media_sizes[ $key ];

			$style .= "\t.{$local_style_class} figure.wp-block-kubio-" . $this->blockNamespace . "-item{\n";
			$style .= "\t\twidth: " . ( 100 / $nr_columns_per_media ) . "% !important;\n";
			$style .= "\t\tmax-width: " . ( 100 / $nr_columns_per_media ) . "% !important;\n";
			$style .= "\t}\n"; // closing style selector.
			$style .= "}\n"; // closing media query.
		}

		return $style;
	}

	public function computed() {

		return array();
	}
}

class ImageGalleryItemBlock extends BlockBase {
	const IMAGE_CONTAINER = 'image-container';
	const LINK            = 'link';
	const IMAGE           = 'image';
	const IMAGE_OVERLAY   = 'image-overlay';
	const CAPTION         = 'caption';

	private $parent_block = null;

	public function mapPropsToElements() {
		$link_type = $this->parent_block->getProp( 'clickBehaviour' );
		$columns   = $this->parent_block->getProp( 'columns' );
		$size      = $this->parent_block->getAttribute( 'size' );
		$alt       = $this->getAttribute( 'alt' );
		$caption   = $this->getAttribute( 'caption' );
		$id        = $this->getAttribute( 'id' );
		$url       = $this->getAttribute( 'url', '' );

		$image_classnames = array(
			'image-gallery-grid-item',
		);

		$link_classname = null;
		$link           = null;
		$src            = wp_get_attachment_image_url( $id, $size );

		switch ( $link_type ) {
			case 'lightbox':
				$link_classname = 'lightbox';
				$link           = wp_get_attachment_image_url( $id, 'full' );
				break;

			case 'link':
				$link = get_attachment_link( $id );
				break;

			case 'media':
				// get the link to the media, not to the attachment page.
				$link = wp_get_attachment_url( $id );
				break;

			case 'none':
			default:
				break;
		}

		//if we add predefined sections we'll have images url that are not in media.
		if ( ! $src && ! empty( $url ) ) {
			$src  = $url;
			$link = $url;
		}

		return array(
			self::IMAGE_CONTAINER => array(
				'className' => $image_classnames,
			),
			self::LINK            => array(
				'href'      => $link,
				'className' => $link_classname,
			),
			self::IMAGE           => array(
				'src'       => $src,
				'alt'       => $alt,
				'className' => array( 'wp-image-' . $id ),
			),
			self::IMAGE_OVERLAY   => array(),
			self::CAPTION         => array(
				'innerHTML' => $caption,
			),
		);
	}

	function getParentBlock() {
		 return Registry::getInstance()->getLastBlockOfName( 'kubio/image-gallery' );
	}

	public function computed() {

		$this->parent_block = $this->getParentBlock();

		$captionSettings  = $this->parent_block->getProp( 'caption', array( 'enabled' => false ) );
		$hoverEffect      = $this->parent_block->getProp( 'hoverEffect' );
		$link_type        = $this->parent_block->getProp( 'clickBehaviour' );
		$id               = $this->getAttribute( 'id' );
		$captionAttribute = $this->getAttribute( 'caption' );
		$showCaption      = Arr::get( $captionSettings, 'enabled', false );
		$caption          = wp_get_attachment_caption( $id );

		//for images from predefined section we dont have an attachment so we look at the attribute for caption
		$caption     = $caption ? $caption : $captionAttribute;
		$linkEnabled = in_array( $link_type, array( 'lightbox', 'link', 'media' ) );

		return array(
			'showCaption'            => $showCaption && ! empty( $caption ),
			'showCaptionWithoutLink' => $showCaption && ! empty( $caption ) && ! $linkEnabled,
			'showOverlay'            => $hoverEffect['enabled'] === true && in_array( $hoverEffect['type'], array( 'addOverlay', 'removeOverlay' ) ),
			'showOverlayWithoutLink' => $hoverEffect['enabled'] === true && in_array(
				$hoverEffect['type'],
				array(
					'addOverlay',
					'removeOverlay',
				)
			) && ! $linkEnabled,
			'linkEnabled'            => $linkEnabled,
			'linkDisabled'           => ! $linkEnabled,
		);
	}
}

Registry::registerBlock(
	__DIR__,
	ImageGalleryBlock::class,
	array(
		'metadata' => './blocks/gallery/block.json',
	)
);
Registry::registerBlock(
	__DIR__,
	ImageGalleryItemBlock::class,
	array(
		'metadata' => './blocks/image/block.json',
	)
);
