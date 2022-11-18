<?php

namespace Kubio\Core\Blocks;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\Utils;

class DataHelper {

	protected $_merged_data = null;
	protected $default      = null;
	protected $attributes   = null;

	public function __construct( $default, $attributes ) {
		$this->attributes = $attributes;
		$this->default    = $default;
	}

	public function getMainAttributeProp( $path, $default = null ) {
		$main_attr = $this->getMergedMainAttribute();
		return LodashBasic::get( $main_attr, $path, $default );
	}

	public function getMergedMainAttribute() {
		if ( ! $this->_merged_data ) {
			$this->_merged_data = $this->getMergedData();
		}
		return $this->_merged_data;
	}

	public function getMainAttributeData() {
		return LodashBasic::get( $this->getAttributes(), Config::$mainAttributeKey, array() );
	}

	public function getMergedData() {

		$data         = $this->getMainAttributeData();
		$defaultValue = $this->getDefaultValue();

		$style  = $this->mergeStyle( LodashBasic::get( $data, 'style' ), LodashBasic::get( $defaultValue, 'style' ), array() );
		$props  = $this->mergeProps( LodashBasic::get( $data, 'props' ), LodashBasic::get( $defaultValue, 'props' ) );
		$_style = $this->mergeStyle( LodashBasic::get( $data, '_style' ), LodashBasic::get( $defaultValue, '_style' ), array() );

		$mergedData = LodashBasic::merge(
			$data,
			array(
				'style'  => $style,
				'props'  => $props,
				'_style' => $_style,
			)
		);
		return $mergedData;
	}

	public function mergeProps( $props, $defaultProps ) {

		$mergedData  = LodashBasic::merge( array(), $defaultProps, $props );
		$dataByMedia = LodashBasic::get( $mergedData, 'media', array() );

		LodashBasic::unsetValue( $mergedData, 'media' );
		$desktopData = LodashBasic::merge( array(), $mergedData );
		$mediasById  = Config::mediasById();
		foreach ( $mediasById as $mediaId => $media ) {
			if ( $mediaId !== 'desktop' ) {
				LodashBasic::set( $mergedData, array( 'media', $mediaId ), LodashBasic::merge( array(), $desktopData, LodashBasic::get( $dataByMedia, $mediaId ) ) );
			}
		}
		return $mergedData;
	}

	public function mergeStyle( $style, $defaultStyle, $options ) {
		$mergedOptions = LodashBasic::merge(
			array(
				'states'            => array(),
				'statesByComponent' => array(),
			),
			$options
		);

		$mergedStyle            = LodashBasic::merge( array(), $defaultStyle, $style );
		$styledComponents       = Utils::normalizeStyle( $mergedStyle, array( 'skipClone' => true ) );
		$mergedStyledComponents = $this->mergeNormalizedComponents( $styledComponents, $mergedOptions );

		$denormalizedMergedStyle = Utils::denormalizeComponents(
			$mergedStyledComponents
		);
		return $denormalizedMergedStyle;
		//	return LodashBasic::merge($defaultStyle, $style);
	}


	public function mergeNormalizedComponents( $style, $data ) {
		$mergedData = LodashBasic::merge(
			array(
				'states'            => array(),
				'statesByComponent' => array(),
			),
			$data
		);
		$merged     = array();
		foreach ( array_keys( $style ) as $componentName ) {
			$componentStates = LodashBasic::get( $mergedData['statesByComponent'], $componentName, $mergedData['states'] );
			if ( $componentName === 'default' ) {
				$componentStates = $mergedData['states'];
			}
			$merged[ $componentName ] = $this->mergeNormalizedStyle( $style[ $componentName ], array( 'states' => $componentStates ) );
		}

		return $merged;
	}

	public function mergeNormalizedStyle( $style, $data ) {
		$mergedData = LodashBasic::merge(
			array(
				'states' => array(),
			),
			$data
		);
		$merged     = array();
		$this->addState( $style, 'normal', $merged );
		foreach ( $mergedData['states'] as $stateId ) {
			if ( $stateId !== 'normal' ) {
				$this->addState( $style, $stateId, $merged );
			}
		}

		return $merged;
	}

	public function addState( $normalizedStyle, $state, &$merged = array() ) {
		$isNormal           = $state === 'normal';
		$desktopNormalStyle = $isNormal ? array() : LodashBasic::get( $normalizedStyle, 'desktop.normal' );
		$desktopStateStyle  = LodashBasic::get( $normalizedStyle, 'desktop.' . $state );
		LodashBasic::set( $merged, 'desktop.' . $state, LodashBasic::merge( array(), $desktopNormalStyle, $desktopStateStyle ) );
		foreach ( array_keys( $normalizedStyle ) as $mediaName ) {
			if ( $mediaName === 'desktop' ) {
				continue;
			}
			$deviceNormalMergedStyle = $isNormal ? array() : LodashBasic::get( $merged, $mediaName . '.normal' );
			$statePath               = $mediaName . '.' . $state;
			$deviceStateStyle        = LodashBasic::get( $normalizedStyle, $statePath, array() );
			$tempValue               = LodashBasic::merge( array(), $desktopNormalStyle, $deviceNormalMergedStyle, $desktopStateStyle, $deviceStateStyle );
			LodashBasic::set( $merged, $statePath, $tempValue );
		}
	}
	public function getDefaultValue() {
		return $this->default;
	}

	public function getAttributes() {
		return $this->attributes;
	}

	public function getStyle( $relPath, $default_value = null, $options = array() ) {
		$absPath = $this->getPathRoot( 'style', $options, $relPath );
		return $this->getPathValue( $absPath, $default_value, $options );
	}

	public function getPathRoot( $type, $options, $relPath ) {
		$local     = LodashBasic::get( $options, 'local', false );
		$type_root = LodashBasic::get( $this->rootPaths( $local ), $type, '' );
		return $this->getRootPropertyPath( $type_root, $options ) . '.' . $relPath;
	}

	public function rootPaths( $local ) {
		$prefix = $local ? '_' : '';
		return array(
			'props' => $prefix . 'props',
			'style' => $prefix . 'style',
		);
	}

	public function getRootPropertyPath( $root, $options ) {
		$paths         = LodashBasic::concat( array(), $root );
		$paths_ordered = array(
			'ancestor'        => 'ancestor',
			'styledComponent' => 'descendants',
			'state'           => 'states',
		);

		foreach ( $paths_ordered as $key => $name ) {
			if ( isset( $options[ $key ] ) && $options[ $key ] ) {
				$paths = LodashBasic::concat( $paths, array( $name, $options[ $key ] ) );
			}
		}

		if ( isset( $options['media'] ) && $options['media'] !== 'desktop' ) {
			$paths = LodashBasic::concat( $paths, array( 'media', $options['media'] ) );
		}

		return implode( '.', $paths );
	}

	public function getPathValue( $abs_path, $default_value = null, $options = array() ) {
		$fromRoot = LodashBasic::get( $options, 'fromRoot', false );
		$attr     = LodashBasic::get( $options, 'attr', false );
		$source   = $attr ? $this->getAttributes() : ( $fromRoot ? $this->getMainAttributeData() : $this->getMergedMainAttribute() );
		$value    = LodashBasic::get( $source, $abs_path, $default_value );
		return $value;
	}

	public function getProp( $relPath, $default_value = null, $options = array() ) {
		$absPath = $this->getPathRoot( 'props', $options, $relPath );
		return $this->getPathValue( $absPath, $default_value, $options );
	}

	public function getPropByMedia( $path, $default_value = null, $options = array() ) {
		return $this->getPathByMedia( $path, $default_value, $options, 'props' );
	}

	function getPathByMedia( $relPath, $defaultValue, $options, $type = 'style' ) {
		 $byMedia   = array();
		$mediasById = Config::mediasById();
		foreach ( $mediasById as $id => $media ) {
			$mediaOptions   = LodashBasic::merge(
				$options,
				array(
					'media' => $id,
				)
			);
			$absPath        = $this->getPathRoot( $type, $mediaOptions, $relPath );
			$byMedia[ $id ] = $this->getPathValue( $absPath, $defaultValue, $mediaOptions );
		}

		// temporary
		foreach ( $mediasById as $id => $media ) {
			if ( $byMedia[ $id ] === null ) {
				$byMedia[ $id ] = $byMedia['desktop'];
			}
		}
		return $byMedia;
	}

	public function getStyleByMedia( $path, $default_value = null, $options = array() ) {
		return $this->getPathByMedia( $path, $default_value, $options, 'style' );
	}

	public function getAttribute( $relPath, $defaultValue = null, $options = array() ) {
		$defaultOptions = array( 'attr' => true );
		$mergedOptions  = LodashBasic::merge( $defaultOptions, $options );
		return $this->getPathValue( $relPath, $defaultValue, $mergedOptions );
	}

}
