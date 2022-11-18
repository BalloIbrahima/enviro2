<?php

namespace Kubio\Core\Background;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\Utils as GeneralUtils;

class BackgroundDefaults {


	const BASEURL = 'props.background';

	public static function getDefaultImage() {
		$background_url = static::BASEURL;
		$imageDefault   = LodashBasic::merge(
			array(),
			Config::value( "{$background_url}.image.default" ),
			array( array( 'source' => array( 'url' => GeneralUtils::getDefaultAssetsUrl( 'background-image-1.jpg' ) ) ) )
		);

		return $imageDefault;
	}

	public static function getDefaultVideo() {
		$background_url = static::BASEURL;
		$videoDefault   = LodashBasic::merge(
			array(),
			Config::value( "{$background_url}.video.default" ),
			array(
				'internal' => array(
					'url'  => 'https://static-assets.kubiobuilder.com/defaults/demo-video.mp4',
					'mime' => 'video/mp4',
				),
				'poster'   => array(
					'url' => 'https://static-assets.kubiobuilder.com/defaults/demo-video-cover.jpg',
				),
			)
		);
		return $videoDefault;
	}

	public static function getDefaultOverlay() {
		$background_url = static::BASEURL;
		$overlayDefault = Config::value( "{$background_url}.overlay.default" );
		return $overlayDefault;
	}

	public static function getDefaultSlideShow() {
		$background_url   = static::BASEURL;
		$slideshowDefault = LodashBasic::merge(
			array(),
			Config::value( "{$background_url}.slideshow.default" ),
			array(
				'slides' => array(
					array(
						'id'  => 1,
						'url' => GeneralUtils::getDefaultAssetsUrl( 'background-image-1.jpg' ),
					),
					array(
						'id'  => 2,
						'url' => GeneralUtils::getDefaultAssetsUrl( 'background-image-2.jpg' ),
					),
					array(
						'id'  => 3,
						'url' => GeneralUtils::getDefaultAssetsUrl( 'background-image-3.jpg' ),
					),
				),

			)
		);

		return $slideshowDefault;
	}

	public static function getDefaultBackground() {

		$imageDefault     = static::getDefaultImage();
		$videoDefault     = static::getDefaultVideo();
		$slideshowDefault = static::getDefaultSlideShow();
		$overlayDefault   = static::getDefaultOverlay();
		$defaultValue     = LodashBasic::merge(
			array(),
			Config::value( 'props.background.default' ),
			array(
				'type'      => '',
				'image'     => $imageDefault,
				'video'     => $videoDefault,
				'slideshow' => $slideshowDefault,
				'overlay'   => $overlayDefault,
			)
		);

		return $defaultValue;
	}
}
