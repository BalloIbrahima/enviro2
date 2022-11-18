<?php

namespace Kubio\Core;

use ColibriWP\Theme\Defaults;
use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\StyleManager\Utils;
use Kubio\Flags;

class CustomizerImporter {

	private static $current_data   = null;
	private $content               = '';
	private $type                  = '';
	private $slug                  = '';
	private $type_process_callback = array();


	public function __construct( $content, $type, $slug ) {
		$this->content = $content;
		$this->type    = $type;
		$this->slug    = $slug;

		$this->type_process_callback = array(
			'wp_template_part' => array(
				'footer'       => array( $this, 'processFooter' ),
				'front-header' => array( $this, 'processHeader' ),
				'header'       => array( $this, 'processHeader' ),
			),
			'wp_template'      => array(
				'*' => array( $this, 'processTemplate' ),
			),
		);
	}

	public function process() {

		$this->loadCurrentData();

		if ( $this->canProcessCurrent() ) {
			$this->processCurrent();
		}

		return $this->content;
	}

	private function loadCurrentData() {
		if ( static::$current_data === null ) {

			$options_data_map = array(
				'front-header.title.localProps.content'    => 'front-header.title.value',
				'front-header.subtitle.localProps.content' => 'front-header.subtitle.value',
				'front-header.header-menu.style.descendants.innerMenu.justifyContent' => array(
					'option'  => 'front-header.header-menu.style.descendants.main-menu-ul.justifyContent',
					'default' => 'center',
				),
				'header.header-menu.style.descendants.innerMenu.justifyContent' => array(
					'option'  => 'header.header-menu.style.descendants.main-menu-ul.justifyContent',
					'default' => 'center',
				),
			);

			$data = array();
			if ( class_exists( Defaults::class ) ) {
				$data = Defaults::getDefaults();

				// set default to lorem ipsum - inside the editor it is `Click to edit...`
				Arr::set( $data, 'front-header.subtitle.value', $data['lorem_ipsum'] );

				$options = get_theme_mods();

				foreach ( $options_data_map as $option_to_map => $map ) {
					$option_to_set = is_array( $map ) ? $map['option'] : $map;
					$default_value = is_array( $map ) ? $map['default'] : null;

					$value = Arr::get( $options, $option_to_map, $default_value );
					Arr::forget( $options, $option_to_map );

					if ( $value ) {
						$options[ $option_to_set ] = $value;
					}
				}

				foreach ( $options as $option => $value ) {

					// remove multiple dots in path - fixes bad formatting
					$option = preg_replace( '#\.\.+#', '.', $option );
					Arr::set( $data, $option, $value );
				}
			}

			// Copy layoutType for logo
			$front_header_logo_layout_type = Arr::get( $data, 'front-header.logo.props.layoutType' );
			$header_logo_layout_type       = Arr::get( $data, 'header.logo.props.layoutType' );

			if ( $front_header_logo_layout_type !== $header_logo_layout_type ) {
				Arr::set( $data, 'header.logo.props.layoutType', $front_header_logo_layout_type );
			}
			//

			// Copy top-bar icons from front header
			$front_header_icon_list    = Arr::get( $data, 'front-header.icon_list' );
			$front_header_social_icons = Arr::get( $data, 'front-header.social_icons' );

			Arr::forget( $data, array( 'header.icon_list', 'header.social_icons' ) );

			Arr::set( $data, 'header.icon_list', $front_header_icon_list );
			Arr::set( $data, 'header.social_icons', $front_header_social_icons );
			//

			// Set menu hover effect for front and inner header
			$menu_effect = Arr::get( $data, 'front-header.header-menu.props.hoverEffect.group.border.transition' );

			Arr::forget( $data, 'front-header.header-menu.props.hoverEffect.group' );
			Arr::forget( $data, 'header.header-menu.props.hoverEffect.group' );

			Arr::set( $data, 'front-header.header-menu.props.hoverEffect.border.effect', $menu_effect );
			Arr::set( $data, 'header.header-menu.props.hoverEffect.border.effect', $menu_effect );
			//

			static::$current_data = $data;
		}

	}

	private function canProcessCurrent() {

		if ( $this->type === 'wp_template' ) {
			return true;
		}

		return isset( $this->getCurrentData()[ $this->slug ] );
	}

	public function getCurrentData() {
		return static::$current_data;
	}

	private function processCurrent() {
		$process_fn = Arr::get( $this->type_process_callback, "{$this->type}.{$this->slug}", null );

		$process_fn_for_all = Arr::get( $this->type_process_callback, "{$this->type}.*", null );

		if ( $process_fn || $process_fn_for_all ) {
			$parsed_blocks = parse_blocks( $this->content );

			if ( $process_fn ) {
				$parsed_blocks = call_user_func( $process_fn, $parsed_blocks );
			}

			if ( $process_fn_for_all ) {
				$parsed_blocks = call_user_func( $process_fn_for_all, $parsed_blocks );
			}
			$this->content = kubio_serialize_blocks( $parsed_blocks );
		}
	}

	private function processTemplate( $parsed_blocks ) {

		$parsed_blocks = $this->postProcessBlocks( $parsed_blocks, $this->getCurrentData() );

		return $parsed_blocks;
	}

	private function postProcessBlocks( $parsed_blocks, $current_data ) {
		foreach ( $parsed_blocks as $index => $block ) {
			$parsed_blocks[ $index ] = $this->postProcessBlock( $block, $current_data );
			$inner_blocks            = $this->postProcessBlocks( $block['innerBlocks'], $current_data );
			$parsed_blocks           = $this->updateBlockInnerBlocks( $parsed_blocks, $index, $inner_blocks );
		}

		return $parsed_blocks;

	}

	private function postProcessBlock( $parsed_block, $current_data ) {
		$block_name = $parsed_block['blockName'];

		if ( $block_name === 'kubio/logo' ) {
			$data         = Arr::get( $current_data, 'logo' );
			$parsed_block = $this->normalizeLogo( $parsed_block, $data );
		}

		if ( $block_name === 'kubio/image' ) {
			$data         = Arr::get( $current_data, 'hero.image' );
			$parsed_block = $this->normalizeImage( $parsed_block, $data );
		}

		if ( $block_name === 'kubio/query-loop' ) {
			$items_per_row = Arr::get( $current_data, 'blog_posts_per_row', 2 );
			$masonry       = Arr::get( $current_data, 'blog_enable_masonry', true );

			Arr::set( $parsed_block, 'attrs.kubio.props.layout.itemsPerRow', intval( $items_per_row ) );

			Arr::set( $parsed_block, 'attrs.masonry', $masonry );
		}

		if ( $block_name === 'kubio/post-featured-image' ) {
			Arr::set(
				$parsed_block,
				'attrs.kubio.style.descendants.container.background.color',
				Arr::get( $current_data, 'blog_post_thumb_placeholder_color', 'rgba(var(--kubio-color-5-variant-2),1)' )
			);

			Arr::set(
				$parsed_block,
				'attrs.showPlaceholder',
				Arr::get( $current_data, 'blog_show_post_thumb_placeholder', true )
			);
		}
		if ( $block_name === 'kubio/footer' ) {
			Arr::set(
				$parsed_block,
				'attrs.kubio.props.useFooterParallax',
				Arr::get( $current_data, 'footer.footer.props.useFooterParallax' )
			);
		}

		if ( $block_name === 'kubio/dropdown-menu' && $this->slug === 'header' ) {
			$data        = $this->getCurrentData();
			$menu_effect = Arr::get( $data, 'front-header.header-menu.props.hoverEffect.border.effect' );
			Arr::set( $parsed_block, 'attrs.kubio.props.hoverEffect.border.effect', $menu_effect );
		}

		return $parsed_block;
	}

	private function normalizeLogo( $parsed_block, $data ) {

		$layout_type      = Arr::get( $data, 'props.layoutType', null );
		$current_data     = $this->getCurrentData();
		$menu_layout_type = Arr::get( $current_data, $this->slug . '.navigation.props.layoutType' );

		if ( $layout_type ) {
			Arr::set( $parsed_block, 'attrs.kubio.props.layoutType', $layout_type );

			if ( $menu_layout_type === 'logo-above-menu' ) {
				Arr::set( $parsed_block, 'attrs.kubio.style.descendants.container.alignItems', 'center' );
				Arr::set( $parsed_block, 'attrs.kubio.style.descendants.container.justifyContent', 'center' );
			}
		}

		if ( $alternate_logo = Arr::get( $current_data, 'alternate_logo', false ) ) {
			kubio_set_global_data( 'alternateLogo', wp_get_attachment_image_url( intval( $alternate_logo ), 'full' ) );
		}

		return $parsed_block;
	}

	private function normalizeImage( $parsed_block, $data ) {

		if ( ! $data ) {
			return $parsed_block;
		}

		$style_ref = Arr::get( $data, 'styleRef' );

		if ( $style_ref === Arr::get( $parsed_block, 'attrs.kubio.styleRef' ) ) {
			$url = Arr::get( $data, 'localProps.url', '' );
			Arr::set(
				$parsed_block,
				'attrs.url',
				$url
			);
			Arr::set(
				$parsed_block,
				'attrs.id',
				attachment_url_to_postid( $url )
			);
			Arr::set(
				$parsed_block,
				'attrs.alt',
				__( 'Image', 'kubio' )
			);
		}

		$style   = $data['style'];
		$x_value = Arr::get( $style, 'descendants.frameImage.transform.translate.x_value' );
		$width   = Arr::get( $style, 'descendants.frameImage.width' );
		$height  = Arr::get( $style, 'descendants.frameImage.height' );
		$y_value = Arr::get( $style, 'descendants.frameImage.transform.translate.y_value' );

		Arr::set(
			$style,
			'descendants.frameImage.width',
			array(
				'value' => $width,
				'unit'  => '%',
			)
		);
		Arr::set(
			$style,
			'descendants.frameImage.height',
			array(
				'value' => $height,
				'unit'  => '%',
			)
		);

		Arr::set(
			$style,
			'descendants.frameImage.transform.translate',
			array(
				array(
					'axis'  => 'x',
					'value' => array(
						'value' => $x_value,
						'unit'  => '%',
					),
				),
				array(
					'axis'  => 'y',
					'value' => array(
						'value' => $y_value,
						'unit'  => '%',
					),
				),
				array(
					'axis' => 'y',

				),
			)
		);

		$color     = Arr::get( $style, 'descendants.frameImage.backgroundColor' );
		$thickness = Arr::get( $style, 'descendants.frameImage.thickness' );
		$props     = Arr::get( $data, 'props' );

		Arr::forget( $style, 'descendants.frameImage.thickness' );

		if ( Arr::get( $props, 'frame.type' ) === 'border' ) {
			Arr::forget( $style, 'descendants.frameImage.backgroundColor' );
			Arr::set(
				$style,
				'descendants.frameImage.border',
				array(
					'left'   => array(
						'style' => 'solid',
						'color' => $color,
						'width' => array(
							'value' => $thickness,
							'unit'  => 'px',
						),
					),

					'right'  => array(
						'style' => 'solid',
						'color' => $color,
						'width' => array(
							'value' => $thickness,
							'unit'  => 'px',
						),
					),

					'top'    => array(
						'style' => 'solid',
						'color' => $color,
						'width' => array(
							'value' => $thickness,
							'unit'  => 'px',
						),
					),

					'bottom' => array(
						'style' => 'solid',
						'color' => $color,
						'width' => array(
							'value' => $thickness,
							'unit'  => 'px',
						),
					),
				)
			);
		}

		if ( Arr::get( $props, 'showFrameOverImage' ) ) {
			Arr::set( $style, 'descendants.frameImage.zIndex', 1 );
		} else {
			Arr::forget( $style, 'descendants.frameImage.zIndex' );
		}

		Arr::set( $parsed_block, 'attrs.kubio.style', $style );
		Arr::set(
			$parsed_block,
			'attrs.kubio.props.frame',
			array(
				'type'               => Arr::get( $props, 'frame.type' ),
				'enabled'            => Arr::get( $props, 'enabledFrameOption' ),
				'showFrameOverImage' => Arr::get( $props, 'showFrameOverImage' ),
			)
		);

		return $parsed_block;
	}

	/**
	 * @param array $parsed_blocks
	 * @param $block_index
	 * @param $next_inner_blocks
	 *
	 * @return array
	 */
	private function updateBlockInnerBlocks( array $parsed_blocks, $block_index, $next_inner_blocks ) {
		$parsed_blocks[ $block_index ]['innerContent'] = array_fill( 0, count( $next_inner_blocks ), null );

		if ( count( $parsed_blocks[ $block_index ]['innerContent'] ) === 0 ) {
			$parsed_blocks[ $block_index ]['innerContent'] = array( $parsed_blocks[ $block_index ]['innerHTML'] );
		}

		$parsed_blocks[ $block_index ]['innerBlocks'] = $next_inner_blocks;

		return $parsed_blocks;
	}

	private function processFooter( $parsed_blocks ) {

		$current_part_data = $this->getCurrentPartData();

		foreach ( $current_part_data as $item_data ) {
			$parsed_blocks = $this->updateBlocks( $parsed_blocks, $item_data );
		}

		return $parsed_blocks;
	}

	private function getCurrentPartData() {
		return $this->getCurrentData()[ $this->slug ];
	}

	private function updateBlocks( $parsed_blocks, $item_data ) {

		foreach ( $parsed_blocks as $index => &$block ) {

			if ( ! isset( $item_data['styleRef'] ) ) {
				continue;
			}

			$styleRef = Arr::get( $block, 'attrs.kubio.styleRef', null );

			if ( $styleRef === $item_data['styleRef'] ) {
				$kubio_attr  = Arr::get( $block, 'attrs.kubio' );
				$style       = Arr::get( $item_data, 'style', array() );
				$props       = Arr::get( $item_data, 'props', array() );
				$local_props = Arr::get( $item_data, 'localProps', array() );

				list( $block, $kubio_attr_replacement ) = $this->normalizeBlockData(
					$block,
					array(
						'style' => $style,
						'props' => $props,
					),
					$item_data
				);

				// if some prop in normalization nullified the block
				if ( $block === null ) {
					array_splice( $parsed_blocks, $index, 1 );
					continue;
				}

				foreach ( $local_props as $attr => $value ) {
					Arr::set( $block, "attrs.{$attr}", $value );
				}

				$kubio_attr = array_replace_recursive( $kubio_attr, $kubio_attr_replacement );

				Arr::set( $block, 'attrs.kubio', $kubio_attr );
			} else {
				$next_inner_blocks = $this->updateBlocks( $block['innerBlocks'], $item_data );

				// let the innerContent placeholders - null means inner block
				$block['innerContent'] = array_fill( 0, count( $next_inner_blocks ), null );

				if ( count( $block['innerContent'] ) === 0 ) {
					$block['innerContent'] = array( $block['innerHTML'] );
				}

				$block['innerBlocks'] = $next_inner_blocks;
			}

			if ( $block ) {
				$parsed_blocks[ $index ] = $block;
			}
		}

		return $parsed_blocks;
	}

	private function normalizeBlockData( $parsed_block, $data, $item_data ) {
		$block_name = $parsed_block['blockName'];

		if ( $block_name === 'kubio/buttongroup' ) {
			list( $parsed_block, $data ) = $this->normalizeButtonGroup( $parsed_block, $data, $item_data );
		}

		if ( in_array( $block_name, array( 'kubio/text', 'kubio/heading' ) ) ) {
			list( $parsed_block, $data ) = $this->normalizeTexts( $parsed_block, $data, $item_data );
		}

		if ( $block_name === 'kubio/navigation' ) {
			list( $parsed_block, $data ) = $this->normalizeNavigation( $parsed_block, $data, $item_data );
		}
		if ( $block_name === 'kubio/iconlist' ) {
			list( $parsed_block, $data ) = $this->normalizeIconsLists( $parsed_block, $data, $item_data );
		}
		if ( $block_name === 'kubio/social-icons' ) {
			list( $parsed_block, $data ) = $this->normalizeIconsLists( $parsed_block, $data, $item_data );
		}

		if ( $block_name === 'kubio/hero' ) {
			list( $parsed_block, $data ) = $this->normalizeHero( $parsed_block, $data, $item_data );
		}

		return array( $parsed_block, $data );
	}

	private function normalizeButtonGroup( $parsed_block, $data, $item_data ) {
		$text_align = Arr::get( $data, 'style.textAlign', 'center' );
		Arr::set( $data, 'style.descendants.spacing.textAlign', $text_align );
		Arr::forget( $data, 'style.textAlign' );

		$buttons = Arr::get( $item_data, 'value', array() );
		$buttons = $this->maybeDecodeArray( $buttons );

		if ( is_array( $buttons ) ) {
			$buttons_order     = array();
			$next_inner_blocks = array();
			foreach ( $buttons as $button ) {
				$buttons_order[] = intval( $button['button_type'] );
			}

			foreach ( $buttons_order as $index => $button_index ) {
				$next_inner_blocks[ $index ] = $parsed_block['innerBlocks'] [ $button_index ];
			}

			foreach ( $next_inner_blocks as $index => $inner_block ) {
				$button = $buttons[ $index ];

				$inner_block['innerHTML'] = $button['label'];
				Arr::set( $inner_block, 'attrs.link.value', $button['url'] );

				$next_inner_blocks[ $index ] = $inner_block;
			}

			$parsed_block['innerContent'] = array_fill( 0, count( $next_inner_blocks ), null );
			$parsed_block['innerBlocks']  = $next_inner_blocks;
		}

		return array( $parsed_block, $data );
	}

	private function maybeDecodeArray( $data ) {
		if ( is_array( $data ) ) {
			return $data;
		}

		$decoded = json_decode( $data, true );

		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $decoded;
		}

		$decoded = json_decode( urldecode( $data ), true );

		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $decoded;
		}

		return array();

	}

	private function normalizeTexts( $parsed_block, $data, $item_data ) {
		if ( Arr::get( $item_data, 'show' ) === false ) {
			return array( null, $data );
		}

		$block_name = $parsed_block['blockName'];
		if ( $block_name === 'kubio/heading' ) {
			$value                     = Arr::get( $item_data, 'value', '' );
			$parsed_block['innerHTML'] = $value;
		}

		// modify block innerContent to the new value
		if ( $block_name === 'kubio/text' ) {
			$value                     = Arr::get( $item_data, 'value', '' );
			$parsed_block['innerHTML'] = $value;
		}

		return array( $parsed_block, $data );
	}

	private function normalizeNavigation( $parsed_block, $data, $item_data ) {
		$show_top_bar = Arr::get( $item_data, 'props.showTopBar', false );
		if ( ! $show_top_bar ) {
			foreach ( $parsed_block['innerBlocks'] as $index => $inner_block ) {
				if ( $inner_block['blockName'] === 'kubio/navigation-top-bar' ) {
					array_splice( $parsed_block['innerBlocks'], $index, 1 );
				}
			}
			$parsed_block['innerContent'] = array_fill( 0, count( $parsed_block['innerBlocks'] ), null );
		} else {
			foreach ( $parsed_block['innerBlocks'] as $index => $inner_block ) {
				if ( $inner_block['blockName'] === 'kubio/navigation-top-bar' ) {
					$nav_width = Arr::get( $data, 'props.width' );
					Arr::set( $parsed_block, "innerBlocks.{$index}.attrs.kubio.props.width", $nav_width );
					break;
				}
			}
		}

		$layout_type = Arr::get( $data, 'props.layoutType' );
		Arr::forget( $data, 'props.layoutType' );

		if ( $layout_type === 'logo-above-menu' ) {
			$navigation_section_index = count( $parsed_block['innerBlocks'] ) - 1;
			$navigation_row_path      = "innerBlocks.{$navigation_section_index}.innerBlocks.0.innerBlocks.0";
			$row                      = Arr::get( $parsed_block, $navigation_row_path );

			$next_columns = array();

			foreach ( $row['innerBlocks'] as $index => $column ) {
				$column_type = Arr::get( $column, 'attrs.kubio.props.internal.navContent.type' );

				if ( in_array( $column_type, array( 'logo', 'menu' ) ) ) {
					Arr::set(
						$column,
						'attrs.kubio._style.descendants.container.columnWidth',
						array(
							'type'   => 'custom',
							'custom' => array(
								'value' => 100,
								'unit'  => '%',
							),
						)
					);

					$next_columns[] = $column;
				}
			}

			$row['innerBlocks']  = $next_columns;
			$row['innerContent'] = array_fill( 0, count( $next_columns ), null );

			Arr::set( $parsed_block, $navigation_row_path, $row );
		}

		$padding = Arr::get( $data, 'style.padding.top' );
		Arr::forget( $data, 'style.padding' );
		Arr::forget( $data, 'style.nav' );

		Arr::set( $data, 'style.descendants.section.padding.top', $padding );
		Arr::set( $data, 'style.descendants.section.padding.bottom', $padding );

		return array( $parsed_block, $data );
	}

	private function normalizeIconsLists( $parsed_block, $data, $item_data ) {
		$show       = Arr::get( $item_data, 'show', false );
		$block_name = $parsed_block['blockName'];

		if ( ! $show ) {
			return null;
		}

		$first_icon = Arr::get( $parsed_block, 'innerBlocks.0', null );

		if ( ! $first_icon ) {
			return $parsed_block;
		}

		$icons_data        = $this->maybeDecodeArray( Arr::get( $item_data, 'localProps.iconList', array() ) );
		$next_inner_blocks = array();

		foreach ( $icons_data as $icon ) {
			$next_inner = $first_icon;

			if ( $block_name === 'kubio/iconlist' ) {
				Arr::set( $next_inner, 'attrs.text', Arr::get( $icon, 'text', '' ) );
				Arr::set( $next_inner, 'innerHTML', Arr::get( $icon, 'text', '' ) );
				Arr::set( $next_inner, 'innerContent.0', Arr::get( $icon, 'text', '' ) );
				Arr::set( $next_inner, 'attrs.icon', Arr::get( $icon, 'icon.name' ) );
			} else {
				Arr::set(
					$next_inner,
					'attrs.icon',
					array(
						'name' => Arr::get( $icon, 'icon.name' ),
					)
				);
			}

			Arr::set(
				$next_inner,
				'attrs.link',
				array(
					'typeOpenLink'  => 'sameWindow',
					'value'         => Arr::get( $icon, 'link_value', '' ),
					'noFollow'      => false,
					'lightboxMedia' => '',
				)
			);

			$next_inner_blocks[] = $next_inner;

		}

		if ( ! count( $next_inner_blocks ) ) {
			return null;
		}

		$parsed_block['innerBlocks']  = $next_inner_blocks;
		$parsed_block['innerContent'] = array_fill( 0, count( $next_inner_blocks ), null );

		return array( $parsed_block, $data );
	}

	private function normalizeHero( $parsed_block, $data, $item_data ) {

		$bottom_separator         = Arr::get( $data, 'style.descendants.outer.separators.separatorBottom' );
		$bg_type                  = Arr::get( $data, 'style.descendants.outer.background.type' );
		$bg_slides                = Arr::get( $data, 'style.descendants.outer.background.slideshow.slides' );
		$gradient_bg              = Arr::get( $data, 'style.descendants.outer.background.image.0.source.gradient' );
		$overlay_gradient         = Arr::get( $data, 'style.descendants.outer.background.overlay.gradient' );
		$overlay_color            = Arr::get( $data, 'style.descendants.outer.background.overlay.color' );
		$overlay_gradient_opacity = Arr::get( $data, 'style.descendants.outer.background.overlay.gradient_opacity', 50 );
		$internal_video           = Arr::get( $data, 'style.descendants.outer.background.video.internalUrl', '' );
		$external_video           = Arr::get( $data, 'style.descendants.outer.background.video.externalUrl', '' );
		$video_type               = Arr::get( $data, 'style.descendants.outer.background.video.videoType', 'external' );

		Arr::forget( $data, 'style.descendants.outer.background.slideshow.slides' );
		Arr::forget( $data, 'style.descendants.outer.background.overlay.gradient_opacity' );
		Arr::forget( $data, 'style.descendants.outer.separators.separatorBottom' );
		Arr::forget( $data, 'style.descendants.outer.background.video.internalUrl' );
		Arr::forget( $data, 'style.descendants.outer.background.video.externalUrl' );
		Arr::forget( $data, 'style.descendants.outer.background.video.videoType' );

		$bg_slides = static::prepareBackgroundSlides( $bg_slides );

		list($r,$g,$b,$a) = sscanf( $overlay_color['value'], 'rgba(%d,%d,%d,%f)' );
		list($r,$g,$b,$a) = sscanf( $overlay_color['value'], 'rgba(%d,%d,%d,%f)' );
		if ( is_numeric( $r ) && is_numeric( $g ) && is_numeric( $b ) && is_numeric( $a ) ) {
			$overlay_color = array(
				'opacity' => $a,
				'value'   => "rgb($r,$g,$b)",
			);
		}

		if ( is_numeric( $internal_video ) ) {
			$internal_video = wp_get_attachment_url( (int) $internal_video );
		}

		Arr::set( $data, 'style.descendants.outer.background.slideshow.slides', $bg_slides );
		Arr::set( $data, 'style.descendants.outer.background.overlay.color', $overlay_color );
		Arr::set( $data, 'style.descendants.outer.separators.bottom', $bottom_separator );
		Arr::set( $data, 'style.descendants.outer.background.video.internal.url', $internal_video );
		Arr::set( $data, 'style.descendants.outer.background.video.external.url', $external_video );
		Arr::set( $data, 'style.descendants.outer.background.video.type', $video_type );
		Arr::set( $data, 'style.descendants.outer.separators.bottom', $bottom_separator );
		Arr::set( $data, 'style.descendants.outer.textAlign', 'center' );

		if ( $gradient_bg ) {
			Arr::set( $data, 'style.descendants.outer.background.image.0.source.gradient', $this->composeGradient( $gradient_bg ) );
		}

		if ( $bg_type === 'image' ) {
			Arr::set( $data, 'style.descendants.outer.background.image.0.source.type', $bg_type );
		}

		if ( $bg_type === 'gradient' ) {
			Arr::set( $data, 'style.descendants.outer.background.image.0.source.type', $bg_type );
		}

		if ( $overlay_gradient ) {
			Arr::set( $data, 'style.descendants.outer.background.overlay.gradient', $this->composeGradient( $overlay_gradient, $overlay_gradient_opacity ) );
		}

		$overlay_shape = Arr::get( $data, 'style.descendants.outer.background.overlay.shape.value' );

		$titled_shapes = array( 'dots', 'left-tilted-lines', 'right-tilted-lines' );
		Arr::set( $data, 'style.descendants.outer.background.overlay.shape.isTile', in_array( $overlay_shape, $titled_shapes ) );

		$padding_style_path  = 'style.descendants.outer.padding';
		$hero_padding_top    = Arr::get( $item_data, 'style.padding.top.value' );
		$hero_padding_bottom = Arr::get( $item_data, 'style.padding.bottom.value' );
		$full_height         = Arr::get( $item_data, 'full_height' );

		if ( Flags::get( 'import_design', false ) === true ) {
			$hero_padding_top    = Arr::get( $item_data, 'style.descendants.outer.padding.top.value' );
			$hero_padding_bottom = Arr::get( $item_data, 'style.descendants.outer.padding.bottom.value' );
		}

		if ( $full_height ) {
			Arr::set( $data, 'style.descendants.outer.customHeight.type', 'full-screen' );
		} else {
			Arr::set( $data, 'style.descendants.outer.customHeight.type', 'fit-to-content' );
		}

		if ( $this->slug === 'header' ) {
			$show_title = Arr::get( static::$current_data, 'header.title.show' );

			if ( ! $show_title ) { // Title hidden
				$parsed_block['innerBlocks'] = $this->removePageTitleBlocks( $parsed_block['innerBlocks'] );
			} else {
				//Align inner title
				if ( class_exists( Defaults::class ) ) {
					$default_inner_title_text_align = Defaults::get( 'header.title.style.descendants.text.textAlign', 'center' );
				} else {
					$default_inner_title_text_align = 'center';
				}
				$parsed_block = static::alignInnerTitle( $parsed_block, $default_inner_title_text_align );
			}

			$customizer_hero_padding = array(
				'top'    => array(
					'value' => $hero_padding_top,
					'unit'  => 'px',
				),
				'bottom' => array(
					'value' => $hero_padding_bottom,
					'unit'  => 'px',
				),
				'left'   => array(
					'value' => 20,
					'unit'  => 'px',
				),
				'right'  => array(
					'value' => 20,
					'unit'  => 'px',
				),
			);
		}

		if ( $this->slug === 'front-header' ) {
			$show_buttons = $this->getHeroShowButtons();

			if ( ! $show_buttons && isset( $parsed_block['innerBlocks'] ) ) {
				$parsed_block['innerBlocks'] = $this->removeHeroButtons( $parsed_block['innerBlocks'] );
			}

			$customizer_hero_padding = array(
				'top'    => array(
					'value' => $hero_padding_top,
					'unit'  => 'px',
				),
				'bottom' => array(
					'value' => $hero_padding_bottom,
					'unit'  => 'px',
				),
				'left'   => array(
					'value' => 0,
					'unit'  => 'px',
				),
				'right'  => array(
					'value' => 0,
					'unit'  => 'px',
				),
			);
		}

		Arr::forget( $data, $padding_style_path );
		Arr::set( $data, $padding_style_path, $customizer_hero_padding );

		return array( $parsed_block, $data );
	}

	public function getHeroShowButtons() {
		$show_buttons = Arr::get( static::$current_data, 'front-header.buttons.show' );

		return $show_buttons;
	}

	/**
	 * Recursively removes kubio/buttongroup blocks from $blocks. Returns what remains after remove.
	 * @param $blocks
	 * @return array
	 */
	public function removeHeroButtons( $blocks ) {
		$blocks = array_filter(
			$blocks,
			function ( $block ) {
				if ( $this->blockIsTypeOf( $block, 'kubio/buttongroup' ) ) {
					return false;
				}
				return true;
			}
		);

		foreach ( $blocks as &$block ) {
			if ( isset( $block['innerBlocks'] ) && count( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->removeHeroButtons( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * Recursively removes kubio/page-title blocks from $blocks. Returns what remains after remove.
	 * @param $blocks
	 * @return array
	 */
	public function removePageTitleBlocks( $blocks ) {
		$blocks = array_filter(
			$blocks,
			function ( $block ) {
				if ( $this->blockIsTypeOf( $block, 'kubio/page-title' ) ) {
					return false;
				}
				return true;
			}
		);

		foreach ( $blocks as &$block ) {
			if ( isset( $block['innerBlocks'] ) && count( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->removePageTitleBlocks( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * Checks if block is of a specific type
	 * @param $block
	 * @param $type
	 * @return bool
	 */
	public function blockIsTypeOf( $block, $type ) {
		if ( isset( $block['blockName'] ) ) {
			if ( $block['blockName'] === $type ) {
				return true;
			}
		}
		return false;
	}

	private function composeGradient( $value, $opacity = 100 ) {
		$steps          = $value['steps'];
		$stepts_strings = array();

		foreach ( $steps as $step ) {
			$stepts_strings[] = $this->gradientStepToString( $step, $opacity );
		}

		$steps = implode( ', ', $stepts_strings );

		return "linear-gradient({$value['angle']}deg, {$steps} )";
	}

	private function gradientStepToString( $step, $opacity = 100 ) {
		if ( strpos( $step['color'], 'rgba' ) !== false || strpos( $step['color'], 'RGBA' ) !== false ) {
			$color = $step['color'];
		} else {
			$color = Utils::hex2rgba( $step['color'], intval( $opacity ) / 100 );
		}

		return "{$color} {$step['position']}%";
	}


	private function processHeader( $parsed_blocks ) {
		$current_part_data = $this->getCurrentPartData();

		foreach ( $current_part_data as $index => $item_data ) {
			$parsed_blocks = $this->updateBlocks( $parsed_blocks, $item_data );
		}

		$current_hero_layout = Arr::get( $current_part_data, 'hero.props.heroSection.layout' );

		$hero_column_width = Arr::get( $current_part_data, 'hero.hero_column_width' );

		if ( $current_hero_layout ) {
			switch ( $current_hero_layout ) {
				case 'textOnly':
					$parsed_blocks = $this->removeHeroMediaColumn( $parsed_blocks );
					break;
				case 'textWithMediaOnLeft':
					$parsed_blocks = $this->swapHeroColumns( $parsed_blocks );
					break;
			}
		}

		$parsed_blocks = $this->setColumnsWidth( $parsed_blocks, $hero_column_width );
		$parsed_blocks = $this->postProcessBlocks( $parsed_blocks, $current_part_data );

		return $parsed_blocks;
	}

	private function removeHeroMediaColumn( $parsed_blocks ) {

		foreach ( $parsed_blocks as $index => $block ) {
			if ( Arr::get( $block, 'attrs.kubio.props.internal.heroSection.type' ) === 'media' ) {
				array_splice( $parsed_blocks, $index, 1 );
				break;
			} else {
				$next_inner_blocks = $this->removeHeroMediaColumn( $block['innerBlocks'] );

				// let the innerContent placeholders - null means inner block
				$parsed_blocks = $this->updateBlockInnerBlocks( $parsed_blocks, $index, $next_inner_blocks );
			}
		}

		return $parsed_blocks;
	}

	private function swapHeroColumns( $parsed_blocks ) {

		foreach ( $parsed_blocks as $index => $block ) {
			$inner_blocks = $block['innerBlocks'];

			$inner_blocks_are_hero_columns = false;

			// check if inner blocks are hero columns, otherwise check each child children's
			foreach ( $inner_blocks as $inner_block ) {
				if ( Arr::get( $inner_block, 'attrs.kubio.props.internal.heroSection.type' ) === 'media' ) {
					$inner_blocks_are_hero_columns = true;
					break;
				}
			}

			if ( $inner_blocks_are_hero_columns ) {
				$parsed_blocks[ $index ]['innerBlocks'] = array_reverse( $inner_blocks );
				break;
			} else {
				$parsed_blocks[ $index ]['innerBlocks'] = $this->swapHeroColumns( $inner_blocks );
			}
		}

		return $parsed_blocks;

	}

	private function setColumnsWidth( $parsed_blocks, $text_column_width ) {
		foreach ( $parsed_blocks as $index => $block ) {
			$column_type = Arr::get( $block, 'attrs.kubio.props.internal.heroSection.type' );
			if ( $column_type ) {
				$value = $column_type === 'text' ? intval( $text_column_width ) : 100 - $text_column_width;
				Arr::set(
					$block,
					'attrs.kubio._style.descendants.container.columnWidth',
					array(
						'type'   => 'custom',
						'custom' => array(
							'value' => $value,
							'unit'  => '%',
						),
					)
				);

				$parsed_blocks[ $index ] = $block;
			} else {
				$parsed_blocks[ $index ]['innerBlocks'] = $this->setColumnsWidth( $block['innerBlocks'], $text_column_width );
			}
		}

		return $parsed_blocks;
	}

	public static function prepareBackgroundSlides( $slides ) {
		foreach ( $slides as $index => &$slide ) {
			$slide['id']   = $index + 1;
			$slide['icon'] = false;
		}

		return $slides;
	}

	public static function alignInnerTitle( $parsed_block, $textAlign ) {
		if ( isset( $parsed_block['blockName'] ) && $parsed_block['blockName'] === 'kubio/page-title' ) {
			Arr::set( $parsed_block, 'attrs.kubio.style.descendants.container.textAlign', $textAlign );
		} else {
			if ( isset( $parsed_block['innerBlocks'] ) && count( $parsed_block['innerBlocks'] ) ) {
				foreach ( $parsed_block['innerBlocks'] as &$block ) {
					$block = static::alignInnerTitle( $block, $textAlign );
				}
			}
		}

		return $parsed_block;
	}

	public static function themeHasModifiedOptions() {
		$keys = array_keys( get_theme_mods() );

		foreach ( $keys as $key ) {
			if ( strpos( $key, 'header.' ) === 0 ||
				strpos( $key, 'front-header.' ) === 0 ||
				strpos( $key, 'blog_' ) === 0
			) {
				return true;
			}
		}
		return false;
	}
}
