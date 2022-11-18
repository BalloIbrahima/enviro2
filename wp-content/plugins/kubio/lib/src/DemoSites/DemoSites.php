<?php

namespace Kubio\DemoSites;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\PluginsManager;
use function _\parseInt;

class DemoSites {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'init' ) );
	}

	public static function load() {
		new DemoSites();
	}

	public static function exportDemoSiteContent() {
		$option_keys          = array( 'site_icon', 'site_logo', 'page_on_front', 'page_for_posts', 'show_on_front' );
		$dummy_fallback_value = uniqid( 'kubio-dummy-option-' );
		$options              = array();
		foreach ( $option_keys as $option_key ) {
			$option_value = get_option( $option_key, $dummy_fallback_value );

			if ( $option_value !== $dummy_fallback_value ) {
				$options[ $option_key ] = $option_value;
			}
		}

		$stylesheet     = get_stylesheet();
		$template       = get_template();
		$fse_base_query = array(
			'post_status'    => array( 'publish' ),
			'posts_per_page' => - 1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'name',
					'terms'    => array( $stylesheet, $template ),
				),
			),
		);

		$wp_templates         = get_posts( array_merge( $fse_base_query, array( 'post_type' => 'wp_template' ) ) );
		$wp_template_parts    = get_posts( array_merge( $fse_base_query, array( 'post_type' => 'wp_template_part' ) ) );
		$template_slugs       = array();
		$template_parts_slugs = array();

		foreach ( $wp_templates as $wp_template ) {
			$template_slugs[] = $wp_template->post_name;
		}

		foreach ( $wp_template_parts as $wp_template_part ) {
			$template_parts_slugs[] = $wp_template_part->post_name;
		}

		return array(
			'site_url'       => WXRExporter::getSiteURL(),
			'content'        => WXRExporter::export(),
			'customizer'     => get_theme_mods(),
			'options'        => $options,
			'templates'      => $template_slugs,
			'template_parts' => $template_parts_slugs,
		);
	}

	/**
	 * Return the first template part block found or return null. Also search in inner blocks
	 * @param $block
	 * @return mixed|null
	 */
	public static function findTemplatePartBlock($block) {
		$template_part_blocks_names = array( 'kubio/header', 'kubio/footer', 'kubio/sidebar');
		if ( in_array( $block['blockName'], $template_part_blocks_names ) ) {
			return $block;
		}

		foreach($block['innerBlocks'] as $innerBlock) {
			$resultBlock = static::findTemplatePartBlock($innerBlock);
			if(!!$resultBlock) {
				return $resultBlock;
			}
		}
		return null;
	}
	/*
	 * Exports demo sites splited per page, templates, tempalte parts, global data.
	 */
	public static function exportDemoSiteContentPerPage() {
		$stylesheet     = get_stylesheet();
		$template       = get_template();
		$fse_base_query = array(
			'post_status'    => array( 'publish' ),
			'posts_per_page' => - 1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'name',
					'terms'    => array( $stylesheet, $template ),
				),
			),
		);

		$menu_location = 'header-menu';
		$current_set_locations = get_nav_menu_locations();
		$primary_menu_id = Arr::get( $current_set_locations, $menu_location, null );
		$menu_items     = wp_get_nav_menu_items( $primary_menu_id );

		$menu_item_in_order = [];

		//Order menu items by their position in the menu. This also treats submenus
		static::getMenuItemsInOrder($menu_items, 0, $menu_item_in_order);

		$frontpage_id = intval(get_option( 'page_on_front' ));
		$blog_id      = intval(get_option( 'page_for_posts' ));

		$menu_items = $menu_item_in_order;

		$wp_templates      = get_posts( array_merge( $fse_base_query, array( 'post_type' => 'wp_template' ) ) );
		$wp_template_parts = get_posts( array_merge( $fse_base_query, array( 'post_type' => 'wp_template_part' ) ) );

		$blog_template_slugs = ['index', 'archive', 'home'];
		$single_template_slugs = ['single', 'singular'];
		foreach ( $wp_templates as $key => $template ) {

			if(in_array($template->post_name, $blog_template_slugs)) {
				$wp_templates[ $key ]->page_preview_url = get_permalink($blog_id);
			}

			if(in_array($template->post_name, $single_template_slugs)) {
				$sample_posts = get_posts(array('numberposts' => 1, 'post_type' => 'post'));
				if(count($sample_posts) > 0) {
					$wp_templates[ $key ]->page_preview_url = get_permalink($sample_posts[0]->ID);
				}
			}
			//TODO add permalinks for the other type of templates when they are needed

			$content = $template->post_content;
			$blocks  = parse_blocks( $content );

			if ( ! is_array( $blocks ) ) {
				continue;
			}



			$template_parts             = array();
			foreach ( $blocks as $block ) {
				$template_part_block = static::findTemplatePartBlock($block);
				if(!$template_part_block) {
					continue;
				}
				$template_parts[] = $template_part_block['attrs']['slug'];
			}

			$wp_templates[ $key ]->template_parts = $template_parts;
		}

		foreach($wp_template_parts as $template_part) {
			$template_part_areas = get_the_terms($template_part->ID, 'wp_template_part_area');
			if(count($template_part_areas) === 1) {
				$template_part_area = $template_part_areas[0]->slug;
				$template_part->area = $template_part_area;
			}
		}

		$pages_query = array(
			'post_status'    => array( 'publish' ),
			'post_type'      => 'page',
			'posts_per_page' => - 1,
		);
		$pages       = get_posts( $pages_query );

		foreach( $pages as $key => $page) {
			$permalink  = get_permalink($page->ID);
			$pages[$key]->permalink = static::removeTrailingSlashFromUrl($permalink);
		}


		$pages_order_by_id = [];
		$pages_title_by_id = [];
		$page_order_value = 0;
		if(is_array($menu_items)) {
			foreach ($menu_items as $key => $menu_item) {
				if($menu_item->type === 'custom') {
					$url = $menu_item->url;
					$diezPosition = strpos($url, '#');
					if($diezPosition !== false) {
						$url = substr($url, 0, $diezPosition);
					}
					$url = static::removeTrailingSlashFromUrl($url);
					foreach($pages as $pageIndex => $page) {
						if($page->permalink === $url && !array_key_exists($page->ID, $pages_order_by_id)) {
							$pages_order_by_id[$page->ID] = $page_order_value++;
						}
					}
				}
				if ($menu_item->type === 'post_type' && $menu_item->object === 'page') {
					$is_blog_page = intval($menu_item->object_id) === $blog_id;

					//we don't care about the blog page, because we'll use the blog template in the import per page logic
					if ($is_blog_page) {
						continue;
					}
					$id = intval($menu_item->object_id);
					if(!array_key_exists($id, $pages_order_by_id)) {

						//You can change the label of a page even if it's page menu item
						$pages_title_by_id[$id] = $menu_item->title;

						//Save the order found in the menu
						$pages_order_by_id[$id] = $page_order_value++;
					}

				}
			}
		}

		foreach ( $pages as $key => $page ) {
			$page_id = $page->ID;

			if(isset($pages_order_by_id[$page_id])) {
				$pages[$key]->order = $pages_order_by_id[$page_id];
			}
			if(isset($pages_title_by_id[$page_id])) {
				$pages[$key]->post_title = $pages_title_by_id[$page_id];
			}
			$pages[ $key ]->page_preview_url = get_permalink($page_id);
			if ( $frontpage_id == $page_id ) {
				$pages[ $key ]->template = 'front-page';
				continue;
			}

			//remove the blog page
			if ( $blog_id == $page_id ) {
				unset($pages[$key]);
				continue;
			}
			$template = get_post_meta( $page_id, '_wp_page_template', true );
			if ( $template === 'default' || !$template ) {
				$template = 'page';
			}

			$pages[ $key ]->template = $template;

		}

		$global_data = \kubio_get_global_data_content();

		function get_post_list_content( $post_list, $extra_columns = array() ) {
			return array_map(
				function( $post ) use ( $extra_columns ) {
					$extra_content = array();

					foreach ( $extra_columns as $key => $post_key ) {
						if(isset($post->$post_key)) {
							$extra_content[ $key ] = $post->$post_key;
						} else {
							$extra_content[ $key ] = null;
						}

					}
					return array_merge(
						array(
							'content' => $post->post_content,
							'slug'    => $post->post_name,
							'title'   => $post->post_title,
						),
						$extra_content
					);
				},
				$post_list
			);
		}

		return array(
			'pages'          => get_post_list_content( $pages, array(
				'template' => 'template',
				'order' => 'order',
				'page_preview_url' => 'page_preview_url'
			) ),
			'global_data'    => $global_data,
			'templates'      => get_post_list_content( $wp_templates, array(
				'template_parts' => 'template_parts',
				'page_preview_url' => 'page_preview_url'
			) ),
			'template_parts' => get_post_list_content( $wp_template_parts, array( 'area' => 'area' ) ),
		);
	}

	public static function getMenuItemsInOrder($menuItems, $parentId, &$output) {
		foreach($menuItems as $key => $menuItem) {
			if(intval($menuItem->menu_item_parent) === $parentId) {
				$output[] = $menuItem;
				static::getMenuItemsInOrder($menuItems, $menuItem->ID, $output);
			}
		}

		return $output;
	}

	public static function removeTrailingSlashFromUrl($url) {
		$last_character = substr($url, -1);
		if($last_character === '/') {
			$url = substr($url, 0, -1);
		}
		return $url;
	}


	public function init() {
		DemoSitesImporter::load();
		DemoSitesRepository::load();

		add_action( 'wp_ajax_kubio-demo-site-install-plugin', array( $this, 'installPlugin' ) );
		add_action( 'wp_ajax_kubio-demo-site-activate-plugin', array( $this, 'activatePlugin' ) );
	}

	public function installPlugin() {
		DemoSitesHelpers::verifyAjaxCall();

		$slug = sanitize_text_field( Arr::get( $_REQUEST, 'slug', null ) );

		if ( empty( $slug ) ) {
			DemoSitesHelpers::sendAjaxError( __( 'Slug not found', 'kubio' ) );
		}

		$result = PluginsManager::getInstance()->installPlugin( $slug );

		if ( is_wp_error( $result ) ) {
			DemoSitesHelpers::sendAjaxError( $result );
		} else {
			wp_send_json_success();
		}
	}

	public function activatePlugin() {
		DemoSitesHelpers::verifyAjaxCall();

		$slug = sanitize_text_field( Arr::get( $_REQUEST, 'slug', null ) );

		if ( empty( $slug ) ) {
			DemoSitesHelpers::sendAjaxError( __( 'Slug not found', 'kubio' ) );
		}

		$result = PluginsManager::getInstance()->activatePlugin( $slug, true );

		if ( is_wp_error( $result ) ) {
			DemoSitesHelpers::sendAjaxError( $result );
		} else {
			wp_send_json_success();
		}
	}

}
