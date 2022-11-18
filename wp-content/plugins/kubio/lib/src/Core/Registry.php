<?php

namespace Kubio\Core;

use Exception;
use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Background\Background;
use Kubio\Core\Blocks\BlockElement;
use Kubio\Core\GlobalElements\Icon;
use Kubio\Core\GlobalElements\LinkWrapper;
use Kubio\Core\Separators\Separators;

class Registry {


	private static $instance;

	private $registered = array();

	private $elementsByType = array(
		'background'     => Background::class,
		'separators'     => Separators::class,
		'element'        => BlockElement::class,
		'wp:InnerBlocks' => InnerBlocks::class,
		'LinkWrapper'    => LinkWrapper::class,
		'icon'           => Icon::class,
	);

	private $blocksStack      = array();
	private $lastBlocksByName = array();
	private $fonts            = array();
	// normal and bold should be here by defauly for inline text
	private $window_font_weights = array( '400', '700', '400italic', '700italic' );

	/**
	 * @param $block_dir
	 * @param $handle_class
	 * @param array $args
	 *
	 * @throws Exception
	 */
	static function registerBlock( $block_dir, $handle_class, $args = array() ) {
		$block_json = wp_normalize_path( $block_dir . '/' . Arr::get( $args, 'metadata', 'block.json' ) );
		$metadata   = kubio_get_block_metadata_mixin( $block_json );

		if ( ! $metadata ) {
			throw new Exception( "Kubio register block missing metadata. Path: {$block_json}" );
		}

		$metadata_mixins = Arr::get( $args, 'metadata_mixins', array() );

		foreach ( $metadata_mixins as $mixin ) {
			$mixin_path = wp_normalize_path( "{$block_dir}/$mixin" );
			$mixin_data = kubio_get_block_metadata_mixin( $mixin_path );

			if ( ! $mixin_data ) {
				throw new Exception( "Kubio register block missing metadata mixin. Path: {$mixin_path}" );
			}

			$metadata = array_replace_recursive( $metadata, $mixin_data );

			$exact_replaces = Arr::get( $args, 'mixins_exact_replace', array() );

			if ( isset( $exact_replaces[ $mixin ] ) ) {
				foreach ( (array) $exact_replaces[ $mixin ] as $exact_replace ) {
					Arr::set( $metadata, $exact_replace, Arr::get( $mixin_data, $exact_replace ) );
				}
			}
		}
		$metadata = array_replace_recursive(
			$metadata,
			array(
				'supports' => array(
					'anchor'          => true,
					'customClassName' => true,
				),
			)
		);
		$metadata = apply_filters( 'kubio/blocks/register_block_type', $metadata );

		$block_name = Arr::get( $metadata, 'name', null );

		if ( ! $block_name ) {
			throw new Exception( "Kubio register block missing block name. Path: {$block_json}" );
		}

		self::getInstance()->registered[ $block_name ] = $handle_class;

		if ( kubio_can_register_block( $block_name ) ) {

			if ( did_action( 'init' ) ) {
				kubio_register_block_type_from_metadata_array(
					$metadata,
					array(
						'render_callback'   => 'kubio_render_block_callback',
						'skip_inner_blocks' => true,
						'editor_style'      => 'kubio-block-library-editor',
						'style'             => 'kubio-block-library',
					)
				);
			} else {
				add_action(
					'init',
					function () use ( $block_name, $metadata ) {
						kubio_register_block_type_from_metadata_array(
							$metadata,
							array(
								'render_callback'   => 'kubio_render_block_callback',
								'skip_inner_blocks' => true,
								'editor_style'      => 'kubio-block-library-editor',
								'style'             => 'kubio-block-library',
							)
						);
					},
					20
				);
			}
		}
	}

	static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	function getRenderedFonts() {
		$result = array();

		foreach ( $this->fonts as $font => $weights ) {
			$next_weights = LodashBasic::uniq( array_merge( $weights, $this->window_font_weights ) );
			$result[ $font ] = $next_weights;
		}

		return $result;
	}

	function registerFonts( $familiesStr, $weight, $style = 'normal' ) {
		$families = explode( ',', $familiesStr );
		foreach ( $families as $family ) {
			$family = trim( $family );
			if ( $family ) {
				if ( ! isset( $this->fonts[ $family ] ) ) {
					$this->fonts[ $family ] = array();
				}

				if ( empty( $weight ) ) {
					$weight = '400';
				}

				$next_weights = array( strval( $weight ) );

				if ( $style === 'italic' ) {
					$next_weights[] = $weight . 'italic';
				}

				$this->fonts[ $family ] = LodashBasic::uniq(
					LodashBasic::concat(
						$this->fonts[ $family ],
						$next_weights
					)
				);
			} else {
				if ( empty( $weight ) ) {
					$weight = '400';
				}

				$weight = strval( $weight );

				if ( $style === 'italic' ) {
					 $weight . 'italic';
				}

				if ( ! in_array( $weight, $this->window_font_weights ) ) {
					$this->window_font_weights[] = $weight;
				}
			}
		}
	}


	function getBlock( $block, $context ) {
		$blockName = $block['blockName'];
		if ( isset( $this->registered[ $blockName ] ) ) {
			$class = $this->registered[ $blockName ];
			$block = new $class( $block, true, $context );

			return $block;
		}
	}

	function getParentBlock() {
		$count = count( $this->blocksStack );

		return $count > 1 ? $this->blocksStack[ $count - 2 ] : null;
	}

	function getLastBlock() {
		return Arr::last( $this->blocksStack, null, null );
	}

	function addBlockToStack( $block ) {
		$name = $block->block_type->name;
		if ( ! isset( $this->lastBlocksByName[ $name ] ) ) {
			$this->lastBlocksByName[ $name ] = array();
		}
		$this->lastBlocksByName[ $name ][] = $block;
		$this->blocksStack[]               = $block;
	}

	function removeBlockFromStack( $block ) {
		$name = $block->block_type->name;
		if ( isset( $this->lastBlocksByName[ $name ] ) ) {
			array_pop( $this->lastBlocksByName[ $name ] );
		}

		array_pop( $this->blocksStack );
	}

	function getLastBlockOfName( $blockName ) {
		$block_names = array();
		if ( ! is_array( $blockName ) ) {
			$block_names = array( $blockName );
		} else {
			$block_names = $blockName;
		}

		foreach ( $block_names as $blockName ) {
			if ( isset( $this->lastBlocksByName[ $blockName ] ) ) {
				$length = count( $this->lastBlocksByName[ $blockName ] );

				if ( $length - 1 < 0 ) {
					continue;
				}

				return $this->lastBlocksByName[ $blockName ][ $length - 1 ];
			}
		}

		return null;
	}

	function createElement( $type, $props = array(), $children = array(), $block = null ) {
		// $typeParts = explode(".", $type);
		$class = $this->getClassForType( $type );
		$tag   = Element::DIV;
		if ( is_string( $type ) && ! isset( $this->elementsByType[ $type ] ) ) {
			$tag = $type;
		}

		return new $class( $tag, $props, $children, $block );
	}

	function getClassForType( $type ) {
		$elementsByType = apply_filters(
			'kubio/blocks/elements',
			$this->elementsByType
		);

		if ( ! isset( $elementsByType[ $type ] ) ) {
			return Element::class;
		}
		$constructor = $elementsByType[ $type ];
		if ( function_exists( $constructor ) ) {
			return call_user_func_array( $constructor, array() );
		} else {
			return $constructor;
		}
	}
}
