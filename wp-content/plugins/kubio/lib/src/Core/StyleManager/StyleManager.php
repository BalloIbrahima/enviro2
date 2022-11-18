<?php

namespace Kubio\Core\StyleManager;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\Utils;

use function array_merge;

class StyleManager {

	private static $instance;
	private $styles = array(
		'shared'  => array(),
		'local'   => array(),
		'dynamic' => array(),
		'global'  => array(),
	);

	private $style_join = array(
		'new_line' => '',
		'tab'      => '',
	);

	public function __construct() {
		if ( Utils::isDebug() ) {
			$this->style_join = array(
				'new_line' => "\n",
				'tab'      => "\t",
			);

		}
	}

	static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	function registerBlockStyle( $styleByType ) {
		foreach ( $styleByType as $type => $styleByMedia ) {
			foreach ( $styleByMedia as $media => $styles ) {
				$path = array( $type, $media );
				LodashBasic::set( $this->styles, $path, array_merge( LodashBasic::get( $this->styles, $path, array() ), $styles ) );
			}
		}
	}

	public function render() {
		$renderByMedia = array();
		foreach ( $this->styles as $styleByMedia ) {
			foreach ( $styleByMedia as $media => $styles ) {
				if ( ! isset( $renderByMedia[ $media ] ) ) {
					$renderByMedia[ $media ] = array();
				}
				$renderByMedia[ $media ] = array_merge( $renderByMedia[ $media ], $styles );
			}
		}

		$render = '';

		$devices    = LodashBasic::mapValues( Config::value( 'medias' ), 'id' );
		$mediasById = Config::mediasById();

		$device_rules = array();

		foreach ( $devices as $device ) {
			if ( isset( $renderByMedia[ $device ] ) ) {
				$query = LodashBasic::get( $mediasById, array( $device, 'query' ), false );
				$rules = join( $this->style_join['new_line'], $renderByMedia[ $device ] );
				if ( $query ) {
					$device_rules[] = $query . "{$this->style_join['tab']}{{$this->style_join['new_line']}{$rules}{$this->style_join['new_line']}}";
				} else {
					$device_rules[] = $rules;
				}
			}
		}

		$render .= join( '', $device_rules );

		return $render;
	}
}
