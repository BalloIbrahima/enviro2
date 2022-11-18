<?php

namespace Kubio\Core;

use function array_shift;
use function explode;
use function is_array;
use function is_string;
use function strpos;

class Element extends ElementBase {

	const DIV                = 'div';
	const SPAN               = 'span';
	const IMAGE              = 'img';
	const A                  = 'a';
	const FRAGMENT           = '<>';
	const ALLOWED_ATTRIBUTES = array(
		'hidden',
		'high',
		'href',
		'hreflang',
		'http-equiv',
		'icon',
		'id',
		'ismap',
		'itemprop',
		'keytype',
		'kind',
		'label',
		'lang',
		'language',
		'list',
		'loop',
		'low',
		'manifest',
		'max',
		'maxlength',
		'media',
		'method',
		'min',
		'multiple',
		'name',
		'novalidate',
		'open',
		'optimum',
		'pattern',
		'ping',
		'placeholder',
		'poster',
		'preload',
		'pubdate',
		'radiogroup',
		'readonly',
		'rel',
		'required',
		'reversed',
		'rows',
		'rowspan',
		'sandbox',
		'spellcheck',
		'scope',
		'scoped',
		'seamless',
		'selected',
		'shape',
		'size',
		'sizes',
		'span',
		'src',
		'srcdoc',
		'srclang',
		'srcset',
		'start',
		'step',
		'style',
		'summary',
		'tabindex',
		'target',
		'title',
		'type',
		'usemap',
		'value',
		'width',
		'wrap',
		'border',
		'buffered',
		'challenge',
		'charset',
		'checked',
		'cite',
		'class',
		'code',
		'codebase',
		'color',
		'cols',
		'colspan',
		'content',
		'contenteditable',
		'contextmenu',
		'controls',
		'coords',
		'data',
		'datetime',
		'default',
		'defer',
		'dir',
		'dirname',
		'disabled',
		'download',
		'draggable',
		'dropzone',
		'enctype',
		'for',
		'form',
		'formaction',
		'headers',
		'height',
		'accept',
		'accept-charset',
		'accesskey',
		'action',
		'align',
		'alt',
		'async',
		'autocomplete',
		'autofocus',
		'autoplay',
		'autosave',
		'bgcolor',
	);

	const SELF_CLOSING_TAGS = array(
		'area',
		'base',
		'br',
		'embed',
		'hr',
		//      "iframe", - self closing iframe break chrome
					'img',
		'input',
		'link',
		'meta',
		'param',
		'source',
		'track',

	);

	public static $allowedAttributesByName = true;
	public $block;
	protected $type;
	protected $props;
	protected $filters      = null;
	protected $children     = array();
	protected $innerHTML    = '';
	protected $shouldRender = true;


	function __construct( $type, $props = array(), $children = array(), $block = null ) {
		self::$allowedAttributesByName = array_fill_keys( self::ALLOWED_ATTRIBUTES, true );

		$this->type = $type;

		$this->children = $children;
		$this->block    = $block;

		$this->resolveComputed( $props );

		if ( isset( $props['innerHTML'] ) ) {
			$this->innerHTML = $props['innerHTML'];
			unset( $props['innerHTML'] );
		}

		if ( isset( $props['shouldRender'] ) ) {
			$this->shouldRender = $props['shouldRender'];
			unset( $props['shouldRender'] );
		}

		if ( ! empty( $props['filters'] ) ) {
			$this->filters = $props['filters'];
			unset( $props['filters'] );
		}

		if ( isset( $props['disableStyleClasses'] ) ) {
			$this->disableStyleClasses = $props['disableStyleClasses'];
			unset( $props['disableStyleClasses'] );
		}

		$this->props = $props;
	}

	function resolveComputed( &$props ) {
		foreach ( $props as $name => $value ) {
			if ( is_string( $value ) ) {
				if ( strpos( $value, 'computed.' ) === 0 ) {
					$props[ $name ] = $this->getComputed( $value );
				}
			}

			if ( is_array( $value ) ) {
				$this->resolveComputed( $value );
			}
		}
	}

	function getComputed( $path, $defaultValue = null ) {
		$paths = explode( '.', $path );
		array_shift( $paths );

		return LodashBasic::get( $this->block->computed(), $paths, $defaultValue );
	}

	function getClassName() {
		return $this->getProp( 'className', array() );
	}

	function getProp( $name, $default = null ) {
		return LodashBasic::get( $this->props, $name, $default );
	}

	function extendProps( $extend ) {
		$this->props = LodashBasic::merge( $extend, $this->props );
	}

	function setChildren( $children ) {
		$this->children = $children;
	}

	function mergeProps( ...$arrays ) {
		$result = array();
		foreach ( $arrays as $props ) {
			if ( $props ) {
				foreach ( $props as $prop_name => $prop_value ) {
					$result_value = LodashBasic::get( $result, $prop_name, array() );
					if ( isset( $prop_value ) ) {
						switch ( $prop_name ) {
							case 'className':
								$result[ $prop_name ] = LodashBasic::concat( $result_value, $prop_value );
								break;
							case 'style':
								$result[ $prop_name ] = LodashBasic::merge( $result_value, $prop_value );
								break;
							default:
								$result[ $prop_name ] = $prop_value;
						}
					}
				}
			}
		}
		$result['className'] = LodashBasic::identity( LodashBasic::uniq( $result['className'] ) );

		return $result;
	}

	function __toString() {
		if ( ! $this->shouldRender ) {
			return '';
		}

		$output = '';
		$tags   = array();

		if ( $this->type !== self::FRAGMENT && in_array( $this->tagName(), self::SELF_CLOSING_TAGS ) ) {
			$output = "<{$this->tagName()} {$this->getAttributesAsString()} />";
		} else {
			if ( $this->type !== self::FRAGMENT ) {
				$tags[] = "<{$this->tagName()} {$this->getAttributesAsString()}>";
			}

			// check for non empty strings that return false on validation like. ex: '0'
			$non_empty_string = is_string( $this->innerHTML ) && strlen( $this->innerHTML );
			if ( $this->innerHTML || $non_empty_string ) {
				$tags[] = $this->innerHTML;
			} else {
				foreach ( $this->children as $child ) {
					if ( $child ) {
						$tags[] = $child;
					}
				}
			}

			if ( $this->type !== self::FRAGMENT ) {
				$tags[] = "</{$this->tagName()}>";
			}

			$output = implode( '', $tags );
		}

		if ( ! empty( $this->filters ) ) {
			foreach ( $this->filters as $filter ) {
				$output = apply_filters( $filter, $output );
			}
		}

		return $output;
	}

	function tagName() {
		return $this->type;
	}

	function getAttributesAsString() {
		$attrs = array();
		$props = $this->getProps();

		foreach ( $props as $prop_name => $prop_value ) {
			$attr_name  = $prop_name;
			$attr_value = $prop_value;
			switch ( $prop_name ) {
				case 'className':
					$attr_name  = 'class';
					$attr_value = $this->classAttribute( $prop_value );
					break;
				case 'style':
					$attr_value = $this->styleAttribute( $prop_value );
					break;
			}
			if ( is_string( $attr_value ) ) {
				if ( strpos( $attr_name, 'data-' ) === 0 || isset( self::$allowedAttributesByName[ $attr_name ] ) ) {
					$attrs[] = $attr_name . '="' . esc_attr( $attr_value ) . '"';
				}
			}
		}

		return implode( ' ', $attrs );
	}

	function getProps() {
		return $this->props;
	}

	function setProps( $props ) {
		$this->props = $props;
	}

	function classAttribute( $classes ) {
		$cls = array();
		if ( is_string( $classes ) ) {
			return $classes;
		}
		if ( is_array( $classes ) ) {
			foreach ( $classes as $class_name => $class_value ) {
				if ( ! is_numeric( $class_name ) ) {
					if ( ! ! $class_value ) {
						$cls[] = $class_name;
					}
				} else {
					$cls[] = $class_value;
				}
			}
		}

		return implode( ' ', $cls );
	}

	function styleAttribute( $style ) {
		$styles = array();
		foreach ( $style as $s_name => $s_value ) {
			$styles[] = LodashBasic::kebabCase( $s_name ) . ':' . $s_value;
		}

		return implode( ';', $styles );
	}
}

