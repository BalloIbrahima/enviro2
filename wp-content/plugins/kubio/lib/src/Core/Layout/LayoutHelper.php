<?php

namespace Kubio\Core\Layout;

use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\Styles\FlexAlign;
use Kubio\Core\Styles\Utils;


class LayoutHelper {

	public static $prefixes;
	public static $gridColumns = 12;

	public $rowLayoutByMedia;
	public $layoutByMedia;

	const ROW_CLASS_PREFIX    = 'h-row';
	const COLUMN_CLASS_PREFIX = 'h-col';

	public static $ColumnWidthTypes;
	public static function init() {
		LayoutHelper::$prefixes = Config::value( 'definitions.layout.prefixes' );
	}

	public function __construct( $layoutByMedia = array(), $rowLayoutByMedia = array() ) {
		$this->layoutByMedia    = $layoutByMedia;
		$this->rowLayoutByMedia = $rowLayoutByMedia;

		self::$ColumnWidthTypes = Config::value( 'props.columnWidth.enums.types' );
	}

	public function prefix( $path ) {
		return LodashBasic::get( self::$prefixes, $path );
	}


	public function getSelfVAlignClasses() {
		$verticalAlignByMedia = LodashBasic::mapValues( $this->layoutByMedia, 'verticalAlign' );
		$verticalAlignClasses = FlexAlign::getVAlignClasses( $verticalAlignByMedia, array( 'self' => true ) );
		return $verticalAlignClasses;
	}

	public function getColumnLayoutClasses( $columnWidthByMedia ) {
		 $equalWidth = LodashBasic::get( $this->rowLayoutByMedia, array( 'desktop', 'equalWidth' ), false );
		if ( $equalWidth ) {
			return $this->getColumnGridClasses();
		}
		return $this->getColumnWidthClasses( $columnWidthByMedia );
	}

	public function getColumnGridClasses() {
		$noColumnsByMedia = LodashBasic::mapValues(
			$this->rowLayoutByMedia,
			function ( $layout ) {
				return round( self::$gridColumns / $layout['itemsPerRow'] );
			}
		);
		return Utils::composeClassesByMedia( $noColumnsByMedia, self::COLUMN_CLASS_PREFIX );
	}

	public function getColumnWidthClasses( $columnWidthByMedia, $canUseHtml = true ) {
		if ( ! $canUseHtml ) {
			return array( 'h-col-none' );
		}
		$classes = $this->computeColumnWidthClasses( $columnWidthByMedia );
		return $classes;
	}

	public function computeColumnWidthClasses( $columnWidthByMedia ) {
		$columnTypeToClass   = Config::value( 'props.columnWidth.enums.typeToClass' );
		$widthClassesByMedia = LodashBasic::mapValues(
			$columnWidthByMedia,
			function ( $width ) use ( $columnTypeToClass ) {
				$type = LodashBasic::get( $width, 'type' );
				return LodashBasic::get( $columnTypeToClass, $type, '' );
			}
		);
		return Utils::composeClassesByMedia( $widthClassesByMedia, self::COLUMN_CLASS_PREFIX, true );
	}

	public function getInheritedColumnVAlignClasses() {
		$verticalAlignByMedia_ = LodashBasic::mapValues( $this->rowLayoutByMedia, 'verticalAlign' );
		$equalHeightByMedia    = LodashBasic::mapValues( $this->rowLayoutByMedia, 'equalHeight' );
		$verticalAlignByMedia  = array();
		foreach ( $equalHeightByMedia as $media => $stretch ) {
			if ( ! $stretch ) {
				$verticalAlignByMedia[ $media ] = $verticalAlignByMedia_[ $media ];
			}
		}
		$verticalAlignClasses = FlexAlign::getVAlignClasses( $verticalAlignByMedia, array( 'self' => true ) );
		return $verticalAlignClasses;
	}

	public function getRowAlignClasses() {
		$verticalAlignByMedia_ = LodashBasic::mapValues( $this->layoutByMedia, 'verticalAlign' );
		$equalHeightByMedia    = LodashBasic::mapValues( $this->layoutByMedia, 'equalHeight' );
		$verticalAlignByMedia  = $verticalAlignByMedia_;
		foreach ( $equalHeightByMedia as $media => $stretch ) {
			if ( $stretch ) {
				$verticalAlignByMedia[ $media ] = 'stretch';
			}
		}
		$hAlignByMedia = LodashBasic::mapValues( $this->layoutByMedia, 'horizontalAlign' );
		$classes       = LodashBasic::concat( FlexAlign::getVAlignClasses( $verticalAlignByMedia ), FlexAlign::getHAlignClasses( $hAlignByMedia ) );
		return $classes;
	}

	public function getRowGapClasses() {
		return $this->mapGapClasses( $this->prefix( 'row.outer' ), $this->layoutByMedia );
	}

	public function mapGapClasses( $propsToSuffix, $layoutByMedia, $inheritedLayoutByMedia = array() ) {
		$classes             = array();
		$mergedLayoutByMedia = LodashBasic::merge( $inheritedLayoutByMedia, $layoutByMedia );
		foreach ( $mergedLayoutByMedia as $media => $layout ) {
			foreach ( $propsToSuffix as $path => $gapSuffix ) {
				$value = LodashBasic::get( $layout, $path, null );
				if ( $value === 'inherit' ) {
					$inheritedLayoutInMedia = LodashBasic::get( $inheritedLayoutByMedia, $media );
					$inheritedValue         = LodashBasic::get( $inheritedLayoutInMedia, $path );
					$value                  = $inheritedValue;
				}
				if ( $value !== null ) {
					$classes = LodashBasic::concat( $classes, Utils::composeClassForMedia( $media, $value, $gapSuffix ) );
				}
			}
		}
		return $classes;
	}
	public function getRowGapInnerClasses() {
		return $this->mapGapClasses( $this->prefix( 'row.inner' ), $this->layoutByMedia );
	}

	public function getColumnInnerGapsClasses() {
		return $this->mapGapClasses( $this->prefix( 'column' ), $this->layoutByMedia, $this->rowLayoutByMedia );
	}

	public function getColumnWidthType( $equalWidth, $columnWidth ) {
		return $equalWidth ? self::$ColumnWidthTypes['EQUAL_WIDTH_COLUMNS'] : $columnWidth['type'];
	}

	public function getColumnContentFlexBasis( $equalWidth, $columnWidth ) {
		switch ( $this->getColumnWidthType( $equalWidth, $columnWidth ) ) {
			case self::$ColumnWidthTypes['FIT_TO_CONTENT']:
				return 'flex-basis-auto';
			default:
				return 'flex-basis-100';
		}
	}

}
LayoutHelper::init();
