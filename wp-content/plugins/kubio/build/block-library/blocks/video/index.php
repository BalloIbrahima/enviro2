<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Utils;

class VideoBlock extends BlockBase {

	const OUTER    = 'outer';
	const POSTER   = 'poster';
	const VIDEO    = 'video';
	const LIGHTBOX = 'lightbox';

	private $displayAsVideo            = false;
	private $displayAsPosterImage      = false;
	private $displayAsIconWithLightbox = false;

	public function computed() {
		$this->initInternalData();

		return array(
			'displayAsVideo'            => $this->displayAsVideo,
			'displayAsPoster'           => $this->displayAsPosterImage,
			'displayAsIconWithLightbox' => $this->displayAsIconWithLightbox,
		);
	}

	public function initInternalData() {
		$displayAs                       = $this->getAttribute( 'displayAs', 'video' );
		$this->displayAsVideo            = $displayAs === 'video';
		$this->displayAsPosterImage      = $displayAs === 'posterImage';
		$this->displayAsIconWithLightbox = $displayAs === 'iconWithLightbox';
	}

	public function mapPropsToElements() {
		$this->initInternalData();
		$params = $this->getVideoParameters();

		$shortcodeContent = $this->getShortcode( $params );

		$frontendAttributes = $this->getFrontendScriptAttributes();

		return array(
			self::VIDEO  => array(
				'innerHTML' => $shortcodeContent,
			),
			self::OUTER  => array_merge(
				array(
					'className'            => $this->getOuterClasses(),
					'data-kubio-component' => 'video',
				),
				$frontendAttributes
			),
			self::POSTER => array_merge(
				array(
					'style' => array(
						'background-image' => "url({$this->getAttribute( 'posterImage.url' )})",
					),
				),
				$frontendAttributes
			),
		);
	}

	public function getVideoParameters() {
		$paramList = array( 'internalUrl', 'youtubeUrl', 'vimeoUrl', 'videoCategory', 'displayAs', 'playerOptions' );
		$params    = array();

		foreach ( $paramList as $paramName ) {
			$params[ $paramName ] = $this->getAttribute( $paramName );
			if ( $paramName === 'internalUrl' && empty( $params[ $paramName ] ) ) {
				$params[ $paramName ] = 'https://static-assets.kubiobuilder.com/defaults/kubio-intro-video.mp4';
			}
		}

		$displayAs            = LodashBasic::get( $params, 'displayAs' );
		$displayAsVideo       = $displayAs === 'video';
		$displayAsPosterImage = $displayAs === 'posterImage';
		$displayAsLighbox     = $displayAs === 'iconWithLightbox';
		$videoCategory        = LodashBasic::get( $params, 'videoCategory' );
		$isInternal           = $videoCategory === 'internal';

		$params = array_merge(
			$params,
			array(
				'displayAsVideo'       => $displayAsVideo,
				'displayAsPosterImage' => $displayAsPosterImage,
				'displayAsLightbox'    => $displayAsLighbox,
				'isInternal'           => $isInternal,
			)
		);

		$url = $this->generateUrl( $params );

		$params['url'] = $url;

		return $params;

	}

	public function generateUrl( $params ) {
		$videoCategory = $params['videoCategory'];
		switch ( $videoCategory ) {
			case 'internal':
				return $this->generateInternalUrl( $params );
			case 'youtube':
				return $this->generateYoutubeUrl( $params );
			case 'vimeo':
				return $this->generateVimeoUrl( $params );
		}
	}

	public function generateInternalUrl( $params ) {
		$internalUrl   = LodashBasic::get( $params, 'internalUrl' );
		$playerOptions = LodashBasic::get( $params, 'playerOptions' );
		$startTime     = LodashBasic::get( $playerOptions, 'startTime' );
		$endTime       = LodashBasic::get( $playerOptions, 'endTime' );
		$startTime     = $startTime ? $startTime : 0;
		$endTime       = $endTime ? $endTime : 0;
		$time          = '';
		if ( ( $startTime && $endTime ) || ( $startTime === 0 && $endTime ) ) {
			$time = sprintf( '#t=%s,%s', $startTime, $endTime );
		} else {
			if ( $startTime && ! $endTime ) {
				$time = sprintf( '#t=%s', $startTime );
			}
		}

		return sprintf( '%s%s', $internalUrl, $time );
	}

	public function generateYoutubeUrl( $params ) {
		$youtubeUrl    = LodashBasic::get( $params, 'youtubeUrl' );
		$playerOptions = LodashBasic::get( $params, 'playerOptions' );
		$url           = $youtubeUrl;
		if ( ! $url ) {
			return $url;
		}
		$youtubeRegex = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i';
		preg_match( $youtubeRegex, $url, $urlMatch );
		if ( count( $urlMatch ) === 0 ) {
			return $url;
		}
		$playList    = $urlMatch[1];
		$url         = sprintf( 'https://www.youtube.com/embed/%s?', $playList );
		$queryParams = array(
			'start'          => LodashBasic::get( $playerOptions, 'startTime' ),
			'end'            => LodashBasic::get( $playerOptions, 'endTime' ),
			'autoplay'       => LodashBasic::get( $playerOptions, 'autoPlay' ) && ! $this->displayAsPosterImage,
			'mute'           => LodashBasic::get( $playerOptions, 'mute' ),
			'loop'           => LodashBasic::get( $playerOptions, 'loop' ),
			'controls'       => LodashBasic::get( $playerOptions, 'playerControls' ),
			'modestBranding' => LodashBasic::get( $playerOptions, 'modestBranding' ),
			'rel'            => LodashBasic::get( $playerOptions, 'suggestedVideo' ),
			'enablejsapi'    => true,
		);

		$queryString = $this->convertExternalParamsToQueryString( $queryParams );

		if ( $queryParams['loop'] ) {
			$queryString[] = sprintf( 'playlist=%s', $playList );
		}

		if ( LodashBasic::get( $playerOptions, 'privacyMode' ) ) {
			$url = str_replace( 'youtube', 'youtube-nocookie', $url );
		}

		$url .= implode( '&', $queryString );

		return $url;

	}

	protected function convertExternalParamsToQueryString( $queryParams ) {
		$queryString = array();
		foreach ( $queryParams as $paramName => $paramValue ) {
			if ( gettype( $paramValue ) === 'boolean' ) {
				$paramValue = $paramValue ? 1 : 0;
			}
			$queryString[] = sprintf( '%s=%s', $paramName, urlencode( $paramValue ) );
		}

		return $queryString;
	}

	private function colorToHex( $color ) {

		if ( empty( $color ) ) {
			return '';
		}

		if ( strpos( $color, 'rgb' ) === 0 ) {
			preg_match( '/^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i', $color, $by_color );
			return sprintf( '#%02x%02x%02x', $by_color[1], $by_color[2], $by_color[3] );
		} else {
			return $color;
		}
	}

	public function generateVimeoUrl( $params ) {
		$vimeoUrl      = LodashBasic::get( $params, 'vimeoUrl' );
		$playerOptions = LodashBasic::get( $params, 'playerOptions' );
		$url           = $vimeoUrl;
		if ( ! $url ) {
			return $url;
		}
		$vimeoRegex = '/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|)(\d+)(?:[a-zA-Z0-9_\-]+)?/i';
		preg_match( $vimeoRegex, $url, $urlMatch );
		if ( count( $urlMatch ) === 0 ) {
			return $url;
		}
		$playList = $urlMatch[1];
		$url      = sprintf( 'https://player.vimeo.com/video/%s?', $playList );

		$autoplay = LodashBasic::get( $playerOptions, 'autoPlay' );

		$queryParams = array(
			'autoplay'  => $autoplay && ! $this->displayAsPosterImage,
			'autopause' => $autoplay,
			'muted'     => LodashBasic::get( $playerOptions, 'mute' ),
			'loop'      => LodashBasic::get( $playerOptions, 'loop' ),
			'title'     => LodashBasic::get( $playerOptions, 'introTitle' ),
			'portrait'  => LodashBasic::get( $playerOptions, 'introPortrait' ),
			'byline'    => LodashBasic::get( $playerOptions, 'introByLine' ),
			'color'     => str_replace( '#', '', $this->colorToHex( LodashBasic::get( $playerOptions, 'controlsColor' ) ) ),
			'api'       => true,
		);

		$queryString = $this->convertExternalParamsToQueryString( $queryParams );

		$url .= implode( '&', $queryString );

		$startTime = LodashBasic::get( $playerOptions, 'startTime' );
		if ( $startTime ) {
			$url .= sprintf( '#t=%s', $this->getVimeoStartTime( $startTime ) );
		}

		return $url;
	}

	protected function getVimeoStartTime( $startTime ) {
		$time = gmdate( 'H\ms\s', $startTime );

		return $time;
	}

	public function getShortcode( $params ) {
		$url                   = LodashBasic::get( $params, 'url' );
		$isInternal            = LodashBasic::get( $params, 'isInternal' );
		$playerOptions         = LodashBasic::get( $params, 'playerOptions' );
		$internalUrlAttributes = $this->getInternalUrlAttributes( $params );

		$shortcodeAttributes = array(
			'url'        => $url,
			'type'       => $isInternal ? 'internal' : 'external',
			'autoplay'   => LodashBasic::get( $playerOptions, 'autoPlay' ) ? 1 : 0,
			'attributes' => $internalUrlAttributes,
		);

		return $this->kubio_video_shortcode( $shortcodeAttributes );
	}

	public function getInternalUrlAttributes( $params ) {
		$playerOptions = $params['playerOptions'];

		$attributesValues = array(
			'autoplay' => isset( $playerOptions['autoPlay'] ) && $playerOptions['autoPlay'] && ! $this->displayAsPosterImage,
			'muted'    => esc_attr( $playerOptions['mute'] ),
			'loop'     => esc_attr( $playerOptions['loop'] ),
			'controls' => esc_attr( $playerOptions['playerControls'] ),
		);
		$atts             = array();
		foreach ( $attributesValues as $attributeName => $attributeValue ) {
			if ( $attributeValue ) {
				$atts[] = $attributeName;
			}
		}

		return implode( ' ', $atts );
	}

	public function getFrontendScriptAttributes() {
		$useLightbox = $this->getAttribute( 'posterImage.lightbox' ) && $this->displayAsPosterImage || $this->displayAsIconWithLightbox;
		$autoPlay    = $this->getAttribute( 'playerOptions.autoPlay' ) && $this->displayAsVideo;

		return array(
			'data-display-as'     => $this->getAttribute( 'displayAs' ),
			'data-light-box'      => $useLightbox ? '1' : '0',
			'data-video-category' => $this->getAttribute( 'videoCategory' ),
			'data-autoplay'       => $autoPlay ? '1' : '0',
			'data-start-time'     => $this->getAttribute( 'playerOptions.startTime' ),
			'data-end-time'       => $this->getAttribute( 'playerOptions.endTime' ),
		);
	}

	public function getOuterClasses() {
		$classes = array();
		if ( ! $this->displayAsIconWithLightbox ) {
			$aspectRatio = $this->getAttribute( 'aspectRatio', '16-9' );
			$classes[]   = sprintf( 'h-aspect-ratio--%s', $aspectRatio );
		}

		return implode( ' ', $classes );
	}

	function kubio_video_shortcode( $atts ) {
		$atts = array_merge(
			array(
				'url'        => '',
				'autoplay'   => '0',
				'type'       => 'internal',
				'attributes' => '',
			),
			$atts
		);

		if ( $atts['type'] === 'external' ) {
			$content = $this->doIframe( $atts['url'], $atts['autoplay'] );
		} else {
			$content = $this->doVideo( $atts['url'], $atts['attributes'] );
		}
		return $content;
	}

	function doIframe( $url, $autoplay ) {
		$autoplay = "{$autoplay}" === '0' ? 'allow="autoplay"' : '';
		return sprintf(
			'<iframe src="%s" class="h-video-main" allowfullscreen %s ></iframe>',
			esc_url( $url ),
			$autoplay
		);
	}

	function doVideo( $url, $attributes ) {
		$poster_url = $this->getAttribute( 'posterImage.url' );

		if ( $poster_url ) {
			$attributes .= ' poster="' . esc_url( $poster_url ) . '"';
		}

		return sprintf(
			'<video class="h-video-main" playsinline poster="%s" %s>' .
			' <source src="%s" type="video/mp4" />' .
			'</video>',
			$poster_url,
			$attributes,
			$url
		);
	}
}

Registry::registerBlock( __DIR__, VideoBlock::class );
