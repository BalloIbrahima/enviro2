<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Utils;
use Kubio\Core\Registry;

class LogoBlock extends BlockBase {
	const CONTAINER       = 'container';
	const IMAGE           = 'image';
	const ALTERNATE_IMAGE = 'alternateImage';
	const TEXT            = 'text';

	private $direction_classes = array(
		'image'      => 'kubio-logo-direction-row kubio-logo-without-text',
		'text'       => 'kubio-logo-direction-row',
		'imageLeft'  => 'kubio-logo-direction-row',
		'imageRight' => 'kubio-logo-direction-row-reverse',
		'imageBelow' => 'kubio-logo-direction-column-reverse',
		'imageAbove' => 'kubio-logo-direction-column',
	);

	private $imageShowData = array(
		'image'      => array(
			'showImage'          => true,
			'showAlternateImage' => true,
		),
		'text'       => array(
			'showImage'          => false,
			'showAlternateImage' => false,
		),
		'imageBelow' => array(
			'showImage'          => true,
			'showAlternateImage' => true,
		),
		'imageAbove' => array(
			'showImage'          => true,
			'showAlternateImage' => true,
		),
		'imageRight' => array(
			'showImage'          => true,
			'showAlternateImage' => true,
		),
		'imageLeft'  => array(
			'showImage'          => true,
			'showAlternateImage' => true,
		),
	);

	public function computed() {
		$iconEnabled    = $this->getProp( 'showIcon', false );
		$iconPosition   = $this->getProp( 'iconPosition', 'before' );
		$layout_type    = $this->getProp( 'layoutType', 'image' );
		$showBeforeIcon = $iconEnabled && $iconPosition === 'before';
		$showAfterIcon  = $iconEnabled && $iconPosition === 'after';

		return array(
			'showBeforeIcon'     => $showBeforeIcon,
			'showAfterIcon'      => $showAfterIcon,
			'layout_type'        => $layout_type,
			'showNormalImage'    => $this->imageShowData[ $layout_type ]['showImage'],
			'showAlternateImage' => $this->imageShowData[ $layout_type ]['showAlternateImage'],
		);
	}

	public function mapPropsToElements() {
		$computed = $this->computed();
		if ( $computed['layout_type'] === 'image' ) {
			$text = '';
		} else {
			$text = \get_bloginfo( 'name' );
		}

		if ( $computed['layout_type'] === 'text' ) {
			$image_src           = '';
			$alternate_image_src = '';
		} else {
			$image_src           = $this->getImageLogoUrl();
			$alternate_image_src = $this->getAlternateImageLogoUrl();
		}

		$map[ self::CONTAINER ] = array_merge(
			array(
				'className' => array(
					$this->direction_classes[ $computed['layout_type'] ],
					$this->getAttribute( 'mode', 'default' ),
				),
			),
			$this->getAttribute( 'linkTo' ) === 'homePage' ? array(
				'href' => site_url(),
			) : Utils::getLinkAttributes( $this->getAttribute( 'link' ) )
		);

		if ( $this->imageShowData[ $computed['layout_type'] ]['showImage'] ) {
			$map[ self::IMAGE ] = array(
				'alt' => $this->getAttribute( 'alt', '' ),
				'src' => $image_src,
			);
		}

		if ( $this->imageShowData[ $computed['layout_type'] ]['showImage'] ) {
			$map[ self::ALTERNATE_IMAGE ] = array(
				'alt' => $this->getAttribute( 'alt', '' ),
				'src' => $alternate_image_src,
			);
		}

		$map[ self::TEXT ] = array(
			'innerHTML' => $text,
		);
		return $map;

	}

	private function getImageLogoUrl() {
		$custom_logo_id = get_theme_mod( 'custom_logo', false );
		if ( ! $custom_logo_id ) {
			$placeholder = kubio_url( '/static/default-assets/logo-fallback.png' );
			return $placeholder;
		}
		return wp_get_attachment_image_url( $custom_logo_id, 'full' );
	}

	private function getAlternateImageLogoUrl() {
		$alternateImage = kubio_get_global_data( 'alternateLogo' );
		if ( $alternateImage ) {
			return $alternateImage;
		}
		return $this->getImageLogoUrl();
	}
}

Registry::registerBlock( __DIR__, LogoBlock::class );
