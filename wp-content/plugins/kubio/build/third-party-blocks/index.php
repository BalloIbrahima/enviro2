<?php

namespace Kubio\Core;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\StyleManager\StyleManager;
use Kubio\Core\StyleManager\StyleRender;


class ThirdPartySupportRegistry {
	const KUBIO_STYLE_SUPPORT = 'kubio-style';
	private static $instance;
	private $supported_blocks = array();

	public function __construct() {
		add_filter( 'register_block_type_args', array( $this, 'addBlockAttrsAndStyleSupport' ), 10, 2 );
		add_filter( 'plugins_loaded', array( $this, 'loadSupportedBlocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'loadEditorAssets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'loadFrontendAssets' ) );
		$this->registerKubioStyleSupport();
	}

	private function registerKubioStyleSupport() {
		\WP_Block_Supports::get_instance()->register(
			ThirdPartySupportRegistry::KUBIO_STYLE_SUPPORT,
			array(
				'apply' => array( $this, 'applyKubioStyleSupport' ),
			)
		);

		add_filter( 'pre_render_block', array( $this, 'apply_style_on_renderless_blocks' ), 3, 10 );
	}

	/**
	 * This method is meant to be used in `pre_render_block` where we need to apply our Kubio style on blocks that
	 * don't have a render in php.
	 *
	 * @param $null
	 * @param $parsed_block
	 */
	function apply_style_on_renderless_blocks( $pre_render, $parsed_block ) {
		foreach ( $parsed_block as $key => $block ) {
			if ( ! is_array( $block ) || empty( $block ) ) {
				continue;
			}

			if ( empty( $block['blockName'] ) ) {
				$this->apply_style_on_renderless_blocks( null, $block );
				continue;
			}

			$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );

			if ( ! isset( $block_type->render_callback ) && is_array( $block ) ) {
				$this->applyKubioStyleSupport( $block_type, Arr::get( $block, 'attrs', array() ) );
			}

			if ( empty( $block['innerBlocks'] ) ) {
				continue;
			}

			$this->apply_style_on_renderless_blocks( null, $block['innerBlocks'] );
		}

		return $pre_render;
	}

	public static function load() {
		static::getInstance();
	}

	public static function getInstance() {
		if ( ! static::$instance ) {
			static::$instance = new ThirdPartySupportRegistry();
		}

		return static::$instance;
	}

	public function loadEditorAssets() {
		$this->loadFrontendAssets();
		wp_enqueue_style( 'kubio-third-party-blocks-editor' );
	}

	public function loadFrontendAssets() {
		wp_enqueue_style( 'kubio-third-party-blocks' );
	}

	public function applyKubioStyleSupport( $block_type, $block_attributes ) {
		if ( ! block_has_support( $block_type, array( ThirdPartySupportRegistry::KUBIO_STYLE_SUPPORT ), false ) ) {
			return array();
		}

		if ( $this->isRestRerender() ) {
			return array();
		}

		$name          = $block_type->name;
		$kubio_support = $this->supported_blocks[ $name ];

		$main_attr = Arr::get( $block_attributes, 'kubio', null );
		$style_ref = Arr::get( $main_attr, 'styleRef', '' );
		$style_id  = Arr::get( $main_attr, 'id', '' );

		$elements_by_name = Arr::get( $kubio_support, 'elementsByName', array() );
		$elements_enum    = Arr::get( $kubio_support, 'elementsEnum', array() );

		$wrapper_element = '';
		foreach ( $elements_by_name as $elementName => $props ) {
			if ( Arr::get( $props, 'wrapper', false ) ) {
				$wrapper_element = $elementName;
				break;
			}
		}

		$style_prefix = apply_filters( 'kubio/element-style-class-prefix', 'style-' );
		$classes      = array(
			"{$style_prefix}{$style_ref}-{$wrapper_element}",
			"{$style_prefix}local-${style_id}-{$wrapper_element}",
			'wp-block-kubio-' . str_replace( '/', '-', $name ) . '__' . $wrapper_element, // bem class
		);

		// don't register style on rest requests ( reduce the load time )
		$normalized = StyleRender::normalizeData( $main_attr, $elements_by_name, $elements_enum );

		$style_render = new StyleRender(
			array(
				'styledElementsByName' => Arr::get( $normalized, 'styledElementsByName', array() ),
				'styledElementsEnum'   => Arr::get( $normalized, 'styledElementsEnum', array() ),
				'wrapperElement'       => $wrapper_element,
				'prefixParents'        => array(),
				'useParentPrefix'      => false,
				'model'                => (object) Arr::get( $normalized, 'model', array() ),
			)
		);

		$styleByType = $style_render->export();
		StyleManager::getInstance()->registerBlockStyle( $styleByType );

		$hidden = array(
			'desktop' => Arr::get( $main_attr, 'props.isHidden', false ),
			'tablet'  => Arr::get( $main_attr, 'props.media.tablet.isHidden', false ),
			'mobile'  => Arr::get( $main_attr, 'props.media.mobile.isHidden', false ),
		);

		foreach ( $hidden as $media => $is_hidden ) {
			if ( $is_hidden ) {
				$classes[] = "kubio-hide-on-{$media}";
			}
		}

		return array(
			'class' => implode( ' ', $classes ),
		);
	}

	private function isRestRerender() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			/** @var $wp \WP */
			global $wp;
			$route = (string) Arr::get( $wp->query_vars, 'rest_route', '' );

			if ( strpos( $route, '/block-renderer/' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	public function loadSupportedBlocks() {
		$manifest_file = __DIR__ . '/manifest.php'; // manifest file is autogenerated by webpack at build;
		if ( file_exists( $manifest_file ) ) {
			$supported_blocks = require_once $manifest_file;

			foreach ( $supported_blocks as $meta_file ) {
				if ( file_exists( __DIR__ . '/' . $meta_file ) ) {
					$settings = json_decode( file_get_contents( __DIR__ . '/' . $meta_file ), true );

					if ( is_array( $settings ) ) {
						$name                            = Arr::get( $settings, 'name', null );
						$kubio_support                   = Arr::get( $settings, 'kubioSupport', array() );
						$this->supported_blocks[ $name ] = $kubio_support;
					}
				}
			}
		}

		$this->supported_blocks = apply_filters( 'kubio/third-party-style/register-support', $this->supported_blocks );
	}

	public function addBlockAttrsAndStyleSupport( $args, $block_name ) {

		$has_support = ! ! Arr::get( $this->supported_blocks, $block_name, null );

		if ( $has_support ) {

			// register the kubio attribute for supported blocks
			$attributes          = Arr::get( $args, 'attributes', array() );
			$attributes          = is_array( $attributes ) ? $attributes : array();
			$attributes['kubio'] = array( 'type' => 'object' );

			// enable the kubio-style support to add the block classes and rennder the style
			$supports = Arr::get( $args, 'supports', array() );
			$supports = is_array( $supports ) ? $supports : array();
			$supports[ ThirdPartySupportRegistry::KUBIO_STYLE_SUPPORT ] = true;

			Arr::set( $args, 'attributes', $attributes );
			Arr::set( $args, 'supports', $supports );
		}

		return $args;
	}

}

ThirdPartySupportRegistry::load();
