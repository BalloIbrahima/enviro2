<?php

namespace Kubio\Core\StyleManager;

use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\Props\Animation;
use Kubio\Core\StyleManager\Props\Background;
use Kubio\Core\StyleManager\Props\Border;
use Kubio\Core\StyleManager\Props\BoxShadow;
use Kubio\Core\StyleManager\Props\ColumnWidth;
use Kubio\Core\StyleManager\Props\CustomHeight;
use Kubio\Core\StyleManager\Props\Height;
use Kubio\Core\StyleManager\Props\MultipleImage;
use Kubio\Core\StyleManager\Props\Opacity;
use Kubio\Core\StyleManager\Props\Size;
use Kubio\Core\StyleManager\Props\Stroke;
use Kubio\Core\StyleManager\Props\TBLR;
use Kubio\Core\StyleManager\Props\TextShadow;
use Kubio\Core\StyleManager\Props\Transform;
use Kubio\Core\StyleManager\Props\Transition;
use Kubio\Core\StyleManager\Props\Typography;
use Kubio\Core\StyleManager\Props\Width;
use Kubio\Core\StyleManager\Props\Gap;
use Kubio\Core\StyleManager\Props\JustifyContent;
use Kubio\Core\StyleManager\Props\UnitValuePercentage;
use Kubio\Core\StyleManager\Props\UnitValuePx;
use Kubio\Core\StyleManager\Props\ObjectCss;
use Kubio\Core\StyleManager\Props\MaxWidth;

class StyleParser {

	public $groups;
	public static $instance = null;

	protected function __construct() {
		 $properties = array(
			 new Background( 'background' ),
			 new TBLR( 'padding' ),
			 new TBLR( 'margin' ),
			 new ColumnWidth( 'columnWidth' ),
			 new CustomHeight( 'customHeight' ),
			 new BoxShadow( 'boxShadow' ),
			 new TextShadow( 'textShadow' ),
			 new Border( 'border' ),
			 new Typography( 'typography' ),
			 new Size( 'size' ),
			 new Transform( 'transform' ),
			 new Opacity( 'opacity' ),
			 new Gap( 'gap' ),
			 new JustifyContent( 'justifyContent' ),
			 new Width( 'width' ),
			 new Height( 'height' ),
			 new Stroke( 'stroke' ),
			 new MultipleImage( 'multipleImage' ),
			 new UnitValuePercentage( 'top' ),
			 new UnitValuePercentage( 'right' ),
			 new UnitValuePercentage( 'bottom' ),
			 new UnitValuePercentage( 'left' ),
			 new MaxWidth( 'maxWidth' ),
			 new UnitValuePx( 'maxHeight' ),
			 new UnitValuePx( 'minHeight' ),
			 new ObjectCss( 'object' ),
			 new Animation( 'animation' ),
			 new Transition( 'transition' ),
		 );

		 $this->groups = array();
		 foreach ( $properties as $property ) {
			 $this->addProperty( $property->name, $property );
		 }
	}

	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new StyleParser();
		}
		return self::$instance;
	}

	public function evaluateString( $value ) {
		return $value;
	}

	public function addProperty( $name, $property ) {
		$this->groups[ $name ] = $property;
	}

	public function evaluate( $value ) {
		if ( LodashBasic::isString( $value ) ) {
			return $this->evaluateString( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $name => $val ) {
				$value[ $name ] = $this->evaluate( $val );
			}
		}
		return $value;
	}

	public function transform( $obj, $context, $skipNormal = false ) {
		if ( ! $obj ) {
			return;
		}
		$css = array();
		foreach ( $obj as $prop => $___ ) {
			if ( isset( $this->groups[ $prop ] ) ) {
				$newProps = $this->groups[ $prop ]->parse( $obj[ $prop ], $context );
				$newProps = $this->evaluate( $newProps );
				$css      = LodashBasic::merge( $css, (array)$newProps );
			} else {

				if ( strpos( $prop, '--' ) === 0 ) {
					$css[ $prop ] = ParserUtils::toValueUnitString( $obj[ $prop ] );
				} else {
					if ( ! $skipNormal ) {
						if ( is_numeric( $obj[ $prop ] ) || is_string( $obj[ $prop ] ) ) {
							$css[ $prop ] = $this->evaluate( $obj[ $prop ] );
						}
					}
				}
			}
		}
		return $css;
	}
}
