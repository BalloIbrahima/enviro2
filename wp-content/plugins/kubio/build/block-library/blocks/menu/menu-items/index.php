<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;

class MenuItemsBlock extends BlockBase {
	const BLOCK_NAME        = 'kubio/menu-items';
	const PARENT_MENU_ARROW = '<svg class="kubio-menu-item-icon" role="img" viewBox="0 0 320 512">' .
							  '	<path d="M143 352.3L7 216.3c-9.4-9.4-9.4-24.6 0-33.9l22.6-22.6c9.4-9.4 24.6-9.4 33.9 0l96.4 96.4 96.4-96.4c9.4-9.4 24.6-9.4 33.9 0l22.6 22.6c9.4 9.4 9.4 24.6 0 33.9l-136 136c-9.2 9.4-24.4 9.4-33.8 0z"></path>' .
							  '</svg>';


	private static $instances = array();


	public function __construct( $block, $autoload = true ) {
		parent::__construct( $block, $autoload );
	}

	public function addParentMenuItemsIcon( $args, $item, $depth ) {
		if ( in_array( 'menu-item-has-children', $item->classes, true ) ) {
			$args->link_before = '<span>';
			$args->link_after  = '</span>' . MenuItemsBlock::PARENT_MENU_ARROW;
		} else {
			$args->link_before = '';
			$args->link_after  = '';
		}

		return $args;
	}

	public function fixCSSClasses( $classes, $item, $args, $depth ) {
		$next_classes = array_diff( $classes, array( 'current-menu-item', 'current_page_item' ) );
		$url          = $item->url;

		if ( preg_replace( '/#(.*)/', '', $url ) !== $url ) {
			$classes = $next_classes;
		}

		return $classes;
	}

	public function mapPropsToElements() {
		return array(
			'outer' => array(
				'innerHTML' => $this->renderMenu(),
			),
		);
	}


	public function menuItemsAttrs( $atts, $item, $args, $depth ) {
		$style = isset( $atts['style'] ) ? $atts['style'] : '';

		$depth_value = min( array( $depth, 4 ) );

		$style .= ";--kubio-menu-item-depth:{$depth_value}";

		$atts['style'] = $style;

		return $atts;
	}

	public function renderMenu() {

		$is_first_level_only = $this->isForcedOnlyFirstLevel();

		add_filter( 'kubio/nav_menu_link_attributes', array( $this, 'menuItemsAttrs' ), 2, 4 );

		$location = $this->getAttribute( 'location', '' );
		$id       = $this->getAttribute( 'id', '' );

		$menu = empty( $location ) ? $id : $location;
		// Try to use a cached output for Menu Items based on this key.
		$instance_key = $menu . '-' . ( $is_first_level_only ? 'first_level' : 'full' );

		if ( isset( static::$instances[ $instance_key ] ) ) {
			return static::$instances[ $instance_key ];
		}

		if ( ! $this->isForcedOnlyFirstLevel() ) {
			add_filter( 'kubio/nav_menu_item_args', array( $this, 'addParentMenuItemsIcon' ), 2, 3 );
			add_filter( 'kubio/nav_menu_css_class', array( $this, 'fixCSSClasses' ), 2, 4 );
		}

		if ( ! class_exists( 'Kubio_Nav_Menu_Walker' ) ) {
			require_once KUBIO_ROOT_DIR . '/lib/menu/class-kubio-menu-wallker.php';
		}

		$args = array(
			'container'   => false,
			'depth'       => $is_first_level_only ? 1 : 0,
			'echo'        => false,
			'fallback_cb' => array( $this, 'fallback' ),
			'menu_class'  => 'menu kubio-has-gap-fallback',
			'walker'      => new \Kubio_Nav_Menu_Walker(),
		);

		if ( $location ) {
			$args['theme_location'] = $location;
		}

		if ( $menu ) {
			$args['menu'] = $menu;
		}

		// add a dummy location when the many has nothing assigned to it so we can trigger the fallback
		if ( empty( $location ) && empty( $id ) ) {
			$args['theme_location'] = uniqid( 'kubio-dummy-location-' );
		}

		$menu_content = wp_nav_menu( $args );
		if ( ! $this->isForcedOnlyFirstLevel() ) {
			remove_filter( 'kubio/nav_menu_item_args', array( $this, 'addParentMenuItemsIcon' ), 2 );
			remove_filter( 'kubio/nav_menu_css_class', array( $this, 'fixCSSClasses' ), 2 );
		}

		remove_filter( 'kubio/nav_menu_link_attributes', array( $this, 'menuItemsAttrs' ), 2, 4 );

		static::$instances[ $instance_key ] = $menu_content;

		return $menu_content;

	}

	public function fallback() {
		if ( is_user_logged_in() ) {
			return Utils::getFrontendPlaceHolder(
				sprintf(
					'%s<br/><div class="kubio-frontent-placeholder--small">%s</div>',
					__( 'This block has no menu assigned.', 'kubio' ),
					__( 'Edit this page to select a menu or delete this block.', 'kubio' )
				)
			);
		} else {
			return '';
		}
	}

	private function isForcedOnlyFirstLevel() {
		$menu_block = Registry::getInstance()->getLastBlockOfName( DropDownMenuBlock::BLOCK_NAME );

		if ( ! $menu_block ) {
			$menu_block = Registry::getInstance()->getLastBlockOfName( AccordionMenuBlock::BLOCK_NAME );
		}

		return $menu_block->getAttribute( 'hideSubmenu', false );
	}
}


Registry::registerBlock(
	__DIR__,
	MenuItemsBlock::class
);
