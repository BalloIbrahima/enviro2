<?php

namespace Kubio\Core;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Flags;
use WP_Error;

class Activation {


	private static $instance = null;

	private $remote_content = array();


	public function __construct() {
		add_action(
			'activated_plugin',
			function ( $plugin ) {
				if ( $plugin == plugin_basename( KUBIO_ENTRY_FILE ) ) {
					$hash = uniqid( 'activate-' );
					Flags::set( 'activation-hash', $hash );
					$url = add_query_arg(
						array(
							'page'                  => 'kubio-get-started',
							'kubio-activation-hash' => $hash,
						),
						admin_url( 'admin.php' )
					);
					if ( ! $this->isCLI() && ! Arr::has( $_REQUEST, 'tgmpa-activate' ) ) {
						wp_redirect( $url );
						exit();
					} else {
						if ( Arr::has( $_REQUEST, 'tgmpa-activate' ) ) {
							Flags::set( 'activated_from_tgmpa', true );
						}
						Flags::set( 'import_design', false );

						if ( $this->isCLI() ) {
							add_filter( 'user_has_cap', array( Importer::class, 'allowImportCaps' ), 10, 2 );
							$this->activate();
						}
					}
				}
			}
		);

		$self = $this;

		// handle direct tgmpa activation
		add_action(
			'init',
			function () use ( $self ) {
				if ( Flags::get( 'activated_from_tgmpa' ) ) {
					Flags::delete( 'activated_from_tgmpa' );

					$hash = uniqid( 'activate-' );
					Flags::set( 'activation-hash', $hash );
					$url = add_query_arg(
						array(
							'page'                  => 'kubio-get-started',
							'kubio-activation-hash' => $hash,
						),
						admin_url( 'admin.php' )
					);

					wp_redirect( $url );
					exit();
				}
			},
			5
		);

		add_action(
			'init',
			function () use ( $self ) {
				$hash       = sanitize_text_field( Arr::get( $_REQUEST, 'kubio-activation-hash', null ) );
				$saved_hash = Flags::get( 'activation-hash', false );
				if ( $saved_hash === $hash ) {
					Flags::delete( 'activation-hash' );
					$self->activate();
				}
			},
			500
		);

		add_action( 'after_switch_theme', array( $this, 'afterSwitchTheme' ) );
	}

	public function isCLI() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	public function activeWithFrontpage() {
		 return Flags::get( 'import_design', false ) !== false;
	}

	public function importUnmodifiedTemplates() {
		return ! CustomizerImporter::themeHasModifiedOptions();
	}

	public function importCustomizedTemplates() {
		return CustomizerImporter::themeHasModifiedOptions();
	}


	private function shouldRestoreDeactivtionBackup( Backup $backup ) {
		$template   = get_stylesheet();
		$identifier = Flags::getSetting( "deactivation_backup_key.{$template}", null );
		return $identifier && $backup->hasBackup( $identifier );
	}

	private function restoreDeactivtionBackup( Backup $backup ) {
		$template   = get_stylesheet();
		$identifier = Flags::getSetting( "deactivation_backup_key.{$template}", null );
		$status     = $backup->restoreBackup( $identifier );

		if ( ! is_wp_error( $status ) ) {
			$backup->deleteBackup( $identifier );
		}
		Flags::delete( $identifier );
	}


	public function activate() {
		$backup = new Backup();

		if ( $this->shouldRestoreDeactivtionBackup( $backup ) ) {
			$this->restoreDeactivtionBackup( $backup );
			return;
		}

		// activate pro
		if ( kubio_is_pro() && ! Flags::get( 'kubio_pro_activation_time', false ) ) {
			Flags::set( 'kubio_pro_activation_time', time() );
		}

		// if free previously activated return
		if ( Flags::get( 'kubio_activation_time', false ) ) {
			$stylesheet = Flags::get( 'stylesheet', null );
			if ( $stylesheet === get_stylesheet() ) {
				return;
			}
		}

		Flags::set( 'kubio_activation_time', time() );
		Flags::set( 'stylesheet', get_stylesheet() );

		$this->addCommonFilters();
		$this->prepareRemoteData();

		add_filter( 'kubio/importer/page_path', array( $this, 'getDesignPagePath' ), 10, 2 );

		if ( $this->importCustomizedTemplates() ) {
			add_filter( 'kubio/importer/content', array( $this, 'importCustomizerOptions' ), 20, 3 );
		}

		wp_cache_flush();
		$this->importDesign();
		$this->importTemplates();
		$this->importTemplateParts();
		wp_cache_flush();

		do_action( 'kubio/after_activation' );
	}

	public function addCommonFilters() {
		add_filter( 'kubio/importer/skip-remote-file-import', '__return_true' );
		add_filter( 'kubio/importer/content', array( $this, 'getFileContent' ), 1, 3 );
		add_filter( 'kubio/importer/content', array( $this, 'templateMapPartsTheme' ), 10, 2 );
		add_filter( 'kubio/importer/content', array( $this, 'importAssets' ), 10, 1 );
		remove_filter( 'theme_mod_nav_menu_locations', 'kubio_nav_menu_locations_from_global_data' );
		remove_filter( 'wp_insert_post_data', 'kubio_on_post_update', 10, 3 );
		remove_action( 'wp_insert_post', 'kubio_update_meta', 10, 3 );

		add_filter( 'kubio/importer/available_templates', array( $this, 'getAvailableTemplates' ), 10 );
		add_filter( 'kubio/importer/available_template_parts', array( $this, 'getAvailableTemplateParts' ), 10 );
	}

	public function prepareRemoteData() {
		if ( ! \kubio_theme_has_kubio_block_support() ) {
			return;
		}

		$with_front_page = apply_filters( 'kubio/importer/with_front_page', $this->importUnmodifiedTemplates() );

		$base_url  = 'https://themes.kubiobuilder.com';
		$file_name = get_stylesheet() . '__' . get_template() . '__' . ( $with_front_page ? 'with-front' : 'default' ) . '.data';

		$url      = apply_filters( 'kubio/remote_data_url', "{$base_url}/{$file_name}" );
		$response = wp_safe_remote_get( $url );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 /* && Utils::isProduction()  */ ) {
			$content = wp_remote_retrieve_body( $response );
			$data    = unserialize( $content );

			if ( ! is_array( $data ) || Arr::get( $data, 'error' ) ) {
				return;
			}

			$this->remote_content = $data;
		} else {
			$content              = file_get_contents( KUBIO_ROOT_DIR . '/defaults/default-site.dat' );
			$this->remote_content = unserialize( $content );
		}

		if ( $global_data = Arr::get( $this->remote_content, 'global-data' ) ) {
			$global_data = json_decode( $global_data, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				Arr::forget( $global_data, 'menuLocations' );
				kubio_replace_global_data_content( $global_data );
			}
		}

		$theme = Arr::get( $this->remote_content, 'theme' );

		// if the child theme does not exists use the theme name for assets
		if ( $theme && $theme !== get_stylesheet() ) {
			add_filter(
				'kubio/importer/kubio-url-placeholder-replacement',
				function () use ( $theme ) {

					return "https://static-assets.kubiobuilder.com/themes/{$theme}/assets/";
				},
				10
			);
		}
	}

	public function importDesign() {
		if ( $this->isCLI() ) {
			return;
		}

		$result = $this->setPages();

		// try to set the blog page and menu
		if ( ! is_wp_error( $result ) ) {
			$this->preparePrimaryMenu();
		}
	}

	private function setPages( $data = array() ) {

		if ( ! kubio_theme_has_kubio_block_support() ) {
			return new \WP_Error( 'not_supporterd_themes' );
		}

		$data = array_merge(
			array(
				'front_content'  => null,
				'with_blog_page' => true,
			),
			$data
		);

		$front_page_id = $this->importFrontPage();

		if ( is_wp_error( $front_page_id ) ) {
			return $front_page_id;
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page_id );

		$posts_page_id = intval( get_option( 'page_for_posts' ) );

		if ( ! $posts_page_id && $data['with_blog_page'] ) {
			$posts_page_id = wp_insert_post(
				array(
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
					'post_name'      => 'blog',
					'post_title'     => __( 'Blog', 'kubio' ),
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'page_template'  => apply_filters(
						'kubio/front_page_template',
						'page-templates/homepage.php'
					),
					'post_content'   => '',
					'meta_input'     => array(
						'_kubio_created_at_activation' => 1,
					),
				)
			);

			if ( ! is_wp_error( $posts_page_id ) ) {
				update_option( 'page_for_posts', $posts_page_id );
			}
		}

		return $posts_page_id;
	}

	/**
	 *
	 * @return int|WP_Error
	 */
	private function importFrontPage() {
		$page_on_front = get_option( 'page_on_front' );
		$query         = new \WP_Query(
			array(
				'post__in'    => array( $page_on_front ),
				'post_status' => array( 'publish' ),
				'fields'      => 'ids',
				'post_type'   => 'page',
			)
		);

		if ( $query->have_posts() ) {
			return intval( $page_on_front );
		}

		$content = '';

		if ( $this->activeWithFrontpage() ) {
			$content = Importer::getTemplateContent( 'page', 'front-page' );
		}

		if ( ! is_string( $content ) ) {
			$content = '';
		}

		return wp_insert_post(
			array(
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_name'      => 'front_page',
				'post_title'     => __( 'Home', 'kubio' ),
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'page_template'  => apply_filters(
					'kubio/front_page_template',
					'kubio-full-width'
				),
				'post_content'   => wp_slash( kubio_serialize_blocks( parse_blocks( $content ) ) ),
				'meta_input'     => array(
					'_kubio_created_at_activation' => 1,
				),
			)
		);
	}

	private function preparePrimaryMenu() {
		 $theme_menu_locations   = array_keys( get_registered_nav_menus() );
		$common_header_locations = array(
			'header-menu',
			'header',
			'primary',
			'main',
			'menu-1',
		);

		$selected_location = null;
		/**
		 *  Try to make an educated guess and primary menu location
		 */
		foreach ( $theme_menu_locations as $location ) {
			foreach ( $common_header_locations as $common_header_location ) {
				if ( stripos( $location, $common_header_location ) !== false ) {
					$selected_location = $location;
					break;
				}
			}

			if ( $selected_location ) {
				break;
			}
		}

		$selected_location = apply_filters( 'kubio/primary_menu_location', $selected_location );

		if ( $selected_location ) {

			$current_set_locations = get_nav_menu_locations();

			$primary_menu_id = Arr::get( $current_set_locations, $selected_location, null );

			if ( ! $primary_menu_id ) {
				$primary_menu_id = wp_create_nav_menu( __( 'Primary menu', 'kubio' ) );
			}

			if ( is_wp_error( $primary_menu_id ) ) {
				return;
			}

			$menu_items     = wp_get_nav_menu_items( $primary_menu_id );
			$has_front_page = false;
			$has_blog_page  = false;

			foreach ( $menu_items as $menu_item ) {

				if ( ! $has_front_page ) {
					$menu_item_object_is_front_page = $menu_item->type === 'post_type' && $menu_item->object === 'page' && intval( $menu_item->object_id ) === intval( get_option( 'page_on_front' ) );
					$custom_url                     = $menu_item->type === 'custom' ? $menu_item->url : null;
					$menu_item_link_is_front_page   = false;
					$parsed_url                     = parse_url( $custom_url );

					if ( $parsed_url && $custom_url ) {
						$site_url = site_url();

						$parsed_url = array_merge(
							array(
								'scheme' => '',
								'host'   => '',
								'path'   => '',
							),
							$parsed_url
						);

						$menu_item_url                = "{$parsed_url['scheme']}://{$parsed_url['host']}{$parsed_url['path']}";
						$menu_item_link_is_front_page = untrailingslashit( $menu_item_url ) === untrailingslashit( $site_url );
					}

					if ( $menu_item_object_is_front_page || $menu_item_link_is_front_page ) {
						$has_front_page = true;
					}
				}

				if ( $menu_item->type === 'post_type' && $menu_item->object === 'page' && intval( $menu_item->object_id ) === intval( get_option( 'page_for_posts' ) ) ) {
					$has_blog_page = true;
				}
			}

			if ( ! $has_front_page ) {
				wp_update_nav_menu_item(
					$primary_menu_id,
					0,
					array(
						'menu-item-title'     => __( 'Home', 'kubio' ),
						'menu-item-object'    => 'page',
						'menu-item-object-id' => get_option( 'page_on_front' ),
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
					)
				);
			}

			if ( ! $has_blog_page && get_option( 'page_for_posts', 0 ) ) {
				wp_update_nav_menu_item(
					$primary_menu_id,
					0,
					array(
						'menu-item-title'     => __( 'Blog', 'kubio' ),
						'menu-item-object'    => 'page',
						'menu-item-object-id' => get_option( 'page_for_posts' ),
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
					)
				);
			}

			set_theme_mod(
				'nav_menu_locations',
				array_merge(
					$current_set_locations,
					array(
						$selected_location => $primary_menu_id,
					)
				)
			);
		}
	}

	private function importTemplates() {
		$entities = array_keys( Importer::getAvailableTemplates() );

		foreach ( $entities as $slug ) {
			$is_current_kubio_template = apply_filters( 'kubio/template/is_importing_kubio_template', kubio_theme_has_kubio_block_support(), $slug );
			Importer::createTemplate( $slug, Importer::getTemplateContent( 'wp_template', $slug ), false, $is_current_kubio_template ? 'kubio' : 'theme' );
		}

		Flags::set( 'kubio_templates_imported', time() );
	}

	private function importTemplateParts() {
		$entities = array_keys( Importer::getAvailableTemplateParts() );

		foreach ( $entities as $slug ) {
			$is_current_kubio_template = apply_filters( 'kubio/template/is_importing_kubio_template', kubio_theme_has_kubio_block_support(), $slug );
			Importer::createTemplatePart( $slug, Importer::getTemplateContent( 'wp_template_part', $slug ), false, $is_current_kubio_template ? 'kubio' : 'theme' );
		}

		Flags::set( 'kubio_template_parts_imported', time() );
	}

	public static function load() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function skipAfterSwitchTheme() {
		 set_transient( 'kubio_skip_after_theme_switch', true );
	}

	public function afterSwitchTheme() {
		$skip = get_transient( 'kubio_skip_after_theme_switch' );

		if ( $skip ) {
			delete_transient( 'kubio_skip_after_theme_switch' );
			return;
		}

		$this->addCommonFilters();

		add_filter( 'kubio/importer/page_path', array( $this, 'getDesignPagePath' ), 10, 2 );
		$this->prepareRemoteData( true );

		$this->importDesign();

		$this->importTemplates();
		$this->importTemplateParts();

		do_action( 'kubio/after_switch_theme' );
	}

	public function getAvailableTemplates( $current_templates = array() ) {

		$templates = $current_templates;

		if ( kubio_theme_has_kubio_block_support() ) {
			$templates = Arr::get( $this->remote_content, 'block-templates', array() );

			foreach ( array_keys( $templates ) as $template ) {
				$templates[ $template ] = null;
			}

			$templates = array_replace( $templates, $templates );
		}

		return $templates;
	}

	public function getAvailableTemplateParts( $current_parts = array() ) {

		$templates = $current_parts;

		if ( kubio_theme_has_kubio_block_support() ) {
			$templates = Arr::get( $this->remote_content, 'block-template-parts', array() );

			foreach ( array_keys( $templates ) as $template ) {
				$templates[ $template ] = null;
			}

			$templates = array_replace( $templates, $templates );
		}

		return $templates;
	}

	public function getDesignPagePath( $path, $slug ) {
		if ( $slug === 'front-page' ) {
			return null;
		}

		return $path;
	}

	public function importAssets( $content ) {
		$blocks = parse_blocks( $content );
		$blocks = Importer::maybeImportBlockAssets( $blocks );

		return kubio_serialize_blocks( $blocks );
	}

	public function getFileContent( $content, $type, $slug ) {

		if ( $content !== null ) {
			return $content;
		}

		$category = '';
		switch ( $type ) {
			case 'wp_template':
				$category = 'block-templates';
				break;
			case 'wp_template_part':
				$category = 'block-template-parts';
				break;
			case 'page':
				$category = 'pages';
				break;
		}

		return Arr::get( $this->remote_content, "{$category}.{$slug}", '' );
	}

	public function templateMapPartsTheme( $content, $type ) {

		if ( $type === 'wp_template' || $type === 'wp_template_part' ) {
			$blocks         = parse_blocks( $content );
			$updated_blocks = kubio_blocks_update_template_parts_theme( $blocks, get_stylesheet() );

			return kubio_serialize_blocks( $updated_blocks );
		}

		return $content;
	}

	public function importCustomizerOptions( $content, $type, $slug ) {

		if ( ! kubio_theme_has_kubio_block_support() ) {
			return $content;
		}

		$customizer_importer = new CustomizerImporter( $content, $type, $slug );

		return $customizer_importer->process();
	}
}
