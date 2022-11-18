<?php

namespace Kubio\Core\Blocks;

use Exception;
use Kubio\Config;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\BlockStyleRender;
use Kubio\Core\StyleManager\StyleManager;
use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Utils;
use WP_Block_Type_Registry;

/**
 * @package Kubio\Core\Blocks
 *
 * @method  serverSideRender( string $wp_block )
 *
 */
class BlockBase extends DataHelper {

	public $block_data    = null;
	public $block_type    = null;
	public $parent_block_ = null;
	public $block_context = null;

	public $elements             = array();
	public $styledElementsByName = array();
	private $defaultElement;

	private $local_id = null;

	public function __construct( $block, $autoload = true, $context = array() ) {
		$this->block_context = $context;
		$this->block_data    = $block;
		$this->block_type    = WP_Block_Type_Registry::get_instance()->get_registered(
			$block['blockName']
		);

		$this->styledElementsByName = $this->getBlockStyledElementsByName();
		$this->defaultElement       = $this->getDefaultElement();

		if ( ! $this->defaultElement ) {
			throw new Exception(
				"Kubio \"{$block['name']}\" has no default element defined"
			);
		}

		$attributesDefinitions = $this->block_type->attributes;
		$attributesDefaults    = LodashBasic::mapValues(
			$attributesDefinitions,
			'default'
		);
		$attributesValues      = LodashBasic::get( $this->block_data, 'attrs' );

		parent::__construct(
			$this->getBlockSupport( 'default' ),
			LodashBasic::mergeSkipSeqArray(
				$attributesDefaults,
				$attributesValues
			)
		);

		if ( $autoload ) {
			$this->create();
		}

		$this->local_id = Utils::uniqueId();
	}

	public function localId() {
		return $this->local_id;
	}

	public function getBlockStyledElementsByName() {
		$styledElementsByName = $this->getBlockSupport( Config::$elementsKey );
		// allow empty elements to be skiped in elementsEnum//
		$allElements = $this->getBlockSupport( Config::$elementsEnum );
		foreach ( $allElements as $elementName ) {
			if ( ! isset( $styledElementsByName[ $elementName ] ) ) {
				$styledElementsByName[ $elementName ] = array();
			}
		}

		return $styledElementsByName;
	}

	public function getBlockSupport( $path, $default = null ) {
		return LodashBasic::get(
			$this->block_type->supports,
			Config::$mainAttributeKey . '.' . $path,
			$default
		);
	}

	public function getDefaultElement() {
		if ( ! $this->defaultElement ) {
			$this->defaultElement = $this->findElementBy(
				'default',
				true,
				false
			);
		}

		return $this->defaultElement;
	}

	public function getLinkAttribute() {
		return $this->getAttribute( 'link', null );
	}

	public function findElementBy( $path, $value, $fallbackToDefault = true ) {
		$elements = $this->styledElementsByName;

		foreach ( (array) $elements as $name => $element ) {
			if ( LodashBasic::get( $element, $path ) === $value ) {
				return $name;
			}
		}

		if ( $fallbackToDefault ) {
			return $this->defaultElement;
		}
	}

	public function create() {
		$template = $this->getBlockSupport( 'template', null );
		if ( ! $template ) {
			$template = array(
				'type' => 'element',
			);
		}

		$this->createFromJson( $template );
	}

	public function createFromJson( $element ) {
		$wrapper_element   = $this->getWrapperElementName();
		$this->elements[0] = $this->toElement( $element, $wrapper_element );
	}


	public function getWrapperElementName() {
		return $this->findElementBy( 'wrapper', true, true );
	}
	public function toElement( $element, $wrapper_element, $level = 0 ) {
		$children = array();
		if ( isset( $element['children'] ) ) {
			foreach ( $element['children'] as $child ) {
				$children[] = $this->toElement(
					$child,
					$wrapper_element,
					$level + 1
				);
			}
		}

		$element_name = LodashBasic::get( $element, 'props.name', null );
		$props        = LodashBasic::get( $element, 'props', array() );

		$has_data_debug_attribute = apply_filters( 'kubio/blocks/element_add_data_debug_attribute', Utils::isDebug() );
		$is_wrapper               = $element_name === $wrapper_element;
		if ( $is_wrapper ) {
			$props = LodashBasic::merge(
				array_merge(
					array(
						'className'  => array(
							'wp-block',
							$this->blockClass(),
							implode( ' ', $this->getAppliedMigrationsClasses() ),
						),
						'data-kubio' => $this->block_data['blockName'],
					),
					$has_data_debug_attribute ? array( 'data-debug' => json_encode( $this->block_data ) ) : array() // add data-debug attribute
				),
				$props
			);

			$hidden = $this->getPropByMedia(
				'isHidden',
				false,
				array(
					'fromRoot' => true,
				)
			);

			foreach ( $hidden as $media => $is_hidden ) {
				if ( $is_hidden ) {
					$props['className'] = array_merge(
						isset( $props['className'] )
							? Arr::wrap( $props['className'] )
							: array(),
						array( "kubio-hide-on-{$media}" )
					);
				} else {
					$appearanceEffect = $this->getAttribute( 'appearanceEffect' );
					if ( $appearanceEffect ) {
						$props = LodashBasic::merge( array( 'data-kubio-aos' => $appearanceEffect ), $props );
					}
				}
			}
		}

		$element = $this->createElement( $element['type'], $props, $children );

		return $element;
	}

	public function getAppliedMigrations() {
		return  $this->getAttribute( 'kubio.migrations', array() );
	}

	public function getAppliedMigrationsClasses() {
		$result = array();

		$migrations = $this->getAppliedMigrations();
		foreach ( $migrations  as $migration_id ) {
			$result[] = "kubio-migration--{$migration_id}";
		}

		return $result;
	}

	public function blockClass() {
		return 'wp-block-' . $this->kebabBlockName();
	}

	public function kebabBlockName() {
		return LodashBasic::kebabCase( str_replace( '/', '-', $this->name() ) );
	}

	public function name() {
		return $this->block_type->name;
	}

	public function createElement( $type, $props, $children ) {
		return Registry::getInstance()->createElement(
			$type,
			$props,
			$children,
			$this
		);
	}


	public function elementClass( $element ) {
		return 'wp-block-' . $this->kebabBlockName() . '__' . $element;
	}

	public function getStyledElementConfig( $name, $path = '', $defaultValue = null ) {
		return LodashBasic::get( $this->styledElementsByName, array( $name, $path ), $defaultValue );
	}

	public function getLocalIdClass( $styledComponentName ) {
		$localId         = $this->localId();
		$stylePrefix     = apply_filters( 'kubio/element-style-class-prefix', 'style-' );
		$stylePrefix     = "{$stylePrefix}local-";
		$localStyleClass = "{$stylePrefix}{$localId}-{$styledComponentName}";
		return $localStyleClass;
	}

	public function render( $wp_block ) {
		$this->parent_block_ = Registry::getInstance()->getParentBlock();
		$content             = '';
		if ( $this->canRender() ) {

			if ( $this->canRegisterStyle() ) {
				$this->registerStyle();
			}

			$content             = $this->elements[0] . '';
			$inner_block_content = $this->renderInnerBlocks( $wp_block );
			$content             = str_replace( '<InnerBlocks/>', $inner_block_content, $content );
		}

		return $content;
	}

	public function wrapperStyledComponent() {
		$wrapperElement       = null;
		$styledElementsByName = $this->styledElementsByName;
		foreach ( $styledElementsByName as $name => $styledElement ) {
			$wrapper = LodashBasic::get( $styledElement, 'wrapper', false );
			if ( $wrapper ) {
				$wrapperElement = $name;
			}
		}

		return $wrapperElement;
	}

	public function canRender() {
		global $wp;

		$is_rest_call = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$route        = Arr::get( $wp->query_vars, 'rest_route', '' );

		if ( strpos( $route, '/block-renderer/' ) !== false ) {
			return true;
		}

		$can_render = apply_filters( 'kubio/can_render_block', ! $is_rest_call && ! is_admin(), $this );

		return $can_render;
	}

	public function canRegisterStyle() {
		$is_rest_call = defined( 'REST_REQUEST' ) && REST_REQUEST;

		return ! $is_rest_call;
	}

	public function registerStyle() {
		$style       = new BlockStyleRender( $this, $this->parent_block_ );
		$styleByType = $style->export( $this->getDynamicStyle() );
		StyleManager::getInstance()->registerBlockStyle( $styleByType );
	}

	public function getDynamicStyle() {
		return $this->mapDynamicStyleToElements();
	}

	public function mapDynamicStyleToElements() {
		return array();
	}

	public function renderInnerBlocks( $wp_block ) {
		$block_content = '';
		foreach ( $wp_block->inner_blocks as $inner_block ) {
			$block_content .= $inner_block->render();
		}

		return $block_content;
	}

	public function mapPropsToElements() {
		return array();
	}

	public function mapPropsToElementsWithDefaults() {
		$mapPropsToElements = $this->mapPropsToElements();

		if ( ! $mapPropsToElements ) {
			$mapPropsToElements = array();
		}
		$basicAttributes = \WP_Block_Supports::get_instance()->apply_block_supports();

		if ( ! $basicAttributes ) {
			$basicAttributes = array();
		}
		//rename class to className to make it compatible with kubio classes
		if ( isset( $basicAttributes['class'] ) ) {
			$classList = array( $basicAttributes['class'] );
			LodashBasic::set( $basicAttributes, 'className', $classList );
			LodashBasic::unsetValue( $basicAttributes, 'class' );
		}

		/**
		 * Set anchor. Kubio sets anchor without the source and attribute parameters in the gutenberg attribute. If you
		 * set source attribute the anchor will not work anymore. When you publish any changes the anchor gets removed.
		 * Because of this when we register the anchor we only set the type: "string" paramter and we set the attribute from
		 * here
		 */
		$anchor = LodashBasic::get( $this->block_data, array( 'attrs', 'anchor' ) );
		if ( ! ! $anchor ) {
			$basicAttributes['id'] = $anchor;
		}

		$wrapperStyledComponentName = $this->getWrapperElementName();

		$wrapperData = LodashBasic::get( $mapPropsToElements, $wrapperStyledComponentName, array() );

		foreach ( $basicAttributes as $attributeName => $attributeValue ) {
			if ( is_array( $attributeValue ) ) {
				$propValue = LodashBasic::get( $wrapperData, $attributeName, array() );

				//if block value is string convert it to array
				if ( ! is_array( $propValue ) ) {
					$propValue = array( $propValue );
				}
				$mergedValue = array_merge( $attributeValue, $propValue );
				LodashBasic::set( $wrapperData, $attributeName, $mergedValue );
			}
			if ( ! isset( $wrapperData[ $attributeName ] ) ) {
				LodashBasic::set( $wrapperData, $attributeName, $attributeValue );
			}
		}

		LodashBasic::set( $mapPropsToElements, $wrapperStyledComponentName, $wrapperData );

		return $mapPropsToElements;
	}

	public function getParentColibriContext() {
		return Arr::get( $this->block_context, 'kubio/parentKubio', array() );
	}


	public function getBlockInnerHtml() {

		$content = trim( $this->block_data['innerHTML'] );

		return $content;
	}

	public function isSandboxRender() {
		return apply_filters( 'kubio/sandboxed_render', false );
	}
}
