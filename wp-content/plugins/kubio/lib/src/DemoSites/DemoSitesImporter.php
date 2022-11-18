<?php

namespace Kubio\DemoSites;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Importer;
use Kubio\Flags;

class DemoSitesImporter {
	private $logger = null;
	private $slug   = 0;
	private $log_file_path;

	private $importer;
	private $wxr_file;
	private $before_import_executed = false;
	private $options;
	private $config;

	public function __construct( $type = 'ajax', $config = array() ) {
		if ( $type === 'ajax' ) {
			// pick up only a few keys from the request.
			$this->config = wp_parse_args(
				$_REQUEST,
				array(
					'is_stepped'           => true,
					'first_call'           => false,
					'before_import_action' => '',
				)
			);
		} else {
			$this->config = wp_parse_args(
				$config,
				array(
					'is_stepped' => false,
				)
			);

			// on cli we only set these once.
			$this->setLogger();
			$this->setImporter( $this->config['is_stepped'] );
			$this->maybeCleanupData();

			DemoSitesHelpers::setImportStartTime();

			$this->log_file_path       = DemoSitesHelpers::getLogFile();
			$this->logger->logger_file = $this->log_file_path;
		}

		$this->slug = Arr::get( $this->config, 'slug', 0 );
		add_action( 'wp_ajax_kubio-demo-site-import-data', array( $this, 'ajaxImportDemoData' ) );
	}

	public static function load() {
		new static();
	}

	/**
	 * This is the ajax callback added to `wp_ajax_kubio-demo-site-import-data` and it will import a demo-data by a
	 * given slug, and it will kill the execution  with a json message on success ore failure.
	 *
	 */
	public function ajaxImportDemoData() {
		$this->setLogger();
		$this->setImporter();
		$this->maybeCleanupData();

		DemoSitesHelpers::verifyAjaxCall();

		// Is this a new AJAX call to continue the previous import?
		$use_existing_importer_data = $this->useExistingImporterData();

		if ( ! $use_existing_importer_data ) {
			DemoSitesHelpers::setImportStartTime();

			$this->log_file_path = DemoSitesHelpers::getLogFile();
		}

		$this->logger->logger_file = $this->log_file_path;

		$this->setIniMemoryLimit();

		if ( ! $use_existing_importer_data ) {
			$kds_file = Arr::get( $_FILES, 'kds_file', null );
			if ( ! $kds_file ) {
				$this->prepareRemoteImport();
			} else {
				$this->prepareManualImport( $kds_file );
			}
		}

		DemoSitesHelpers::setImportDataTransient( $this->getCurrentImportData() );

		if ( ! $this->before_import_executed ) {

			$before_import_action = Arr::get( $this->config, 'before_import_action', null );

			if ( ! $before_import_action ) {
				$response = array(
					'status'               => 'requires-new-ajax-call',
					'before_import_action' => 'init',
				);
				wp_send_json( $response );
			} else {
				$this->executeBeforeImportAction( $before_import_action );
			}

			$this->before_import_executed = true;
			DemoSitesHelpers::setImportDataTransient( $this->getCurrentImportData() );
			$response = array(
				'status' => 'requires-new-ajax-call',
				'log'    => 'Before import executed',
			);
			wp_send_json( $response );

		}

		if ( $this->wxr_file && file_exists( $this->wxr_file ) ) {
			add_action( 'kubio/demo-site-import/post-process', array( $this, 'postProcessContentImport' ) );
			$this->importer->import_content( $this->wxr_file );
		}

		$this->afterImport();
	}

	/**
	 * This method is used to import a demo-data design via WP CLI for a given slug.
	 * eg: `wp kubio:import-design accounting-demo-site`
	 *
	 * @return false|string
	 */
	public function cliImportDemoData( $args ) {
		$this->setIniMemoryLimit();

		if ( isset( $args['is_custom'] ) && $args['is_custom'] ) {
			$this->loadKDSFile( $args['kds_url'] );
		} else {
			$this->prepareRemoteImport();
		}

		DemoSitesHelpers::setImportDataTransient( $this->getCurrentImportData() );

		if ( $this->wxr_file && file_exists( $this->wxr_file ) ) {
			add_action( 'kubio/demo-site-import/post-process', array( $this, 'postProcessContentImport' ) );
			$this->importer->import_content( $this->wxr_file );
		}

		return $this->afterImport( false );
	}

	/**
	 * A methods which sets `$this->logger` as an instance of `DemoSitesLogger`
	 */
	private function setLogger() {
		$this->logger              = new DemoSitesLogger();
		$this->logger->min_level   = 'info';
		$this->logger->logger_file = '';
	}

	/**
	 * A methods which sets `$this->importer` as an instance of `WXRImporter`
	 */
	private function setImporter( $is_stepped = true ) {
		if ( ! class_exists( '\WP_Importer' ) ) {
			require ABSPATH . '/wp-admin/includes/class-wp-importer.php';
		}

		$this->importer = new WXRImporter(
			array(
				'fetch_attachments' => true,
				'is_stepped'        => $is_stepped,
			),
			$this->logger
		);

	}

	/**
	 * In the first call try to delete old transients.
	 */
	private function maybeCleanupData() {
		if ( Arr::get( $this->config, 'first_call', null ) ) {
			delete_transient( DemoSitesHelpers::IMPORT_TRANSIENT );
			$this->importer->delete_import_data_transient();
		}
	}

	/**
	 * Try to set a ini value of 512M for `memory_limit`.
	 * If it is not allowed warn in the logs about it.
	 */
	private function setIniMemoryLimit() {
		if ( ! @ini_set( 'memory_limit', '512M' ) ) {
			DemoSitesHelpers::appendToFile(
				esc_html__( 'Warn: Unable set memory_limit', 'kubio' ),
				$this->log_file_path
			);
		}
	}

	private function useExistingImporterData() {
		if ( $data = get_transient( DemoSitesHelpers::IMPORT_TRANSIENT ) ) {
			$this->log_file_path          = empty( $data['log_file_path'] ) ? '' : $data['log_file_path'];
			$this->slug                   = empty( $data['slug'] ) ? null : $data['slug'];
			$this->wxr_file               = empty( $data['wxr_file'] ) ? array() : $data['wxr_file'];
			$this->options                = empty( $data['options'] ) ? array() : $data['options'];
			$this->before_import_executed = empty( $data['before_import_executed'] ) ? array() : $data['before_import_executed'];

			return true;
		}

		return false;
	}

	private function prepareRemoteImport() {
		$this->logger->info( 'Prepare remote import' );

		$demo = $this->getDemo();
		if ( $demo !== null ) {
			$kds = Arr::get( $demo, 'kds_url', null );
			$this->loadKDSFile( $kds );
		} else {
			// Send JSON Error response to the AJAX call.
			DemoSitesHelpers::sendAjaxError( esc_html__( 'No import files specified!', 'kubio' ) );
		}
	}


	private function loadKDSFile( $kds ) {
		if ( $kds ) {
			list( $wxr_file, $extra_options ) = DemoSitesHelpers::downloadImportFile( $kds );

			if ( is_wp_error( $wxr_file ) ) {
				DemoSitesHelpers::logErrorAndSendAjaxResponse(
					$wxr_file->get_error_message(),
					$this->log_file_path,
					esc_html__( 'Downloaded files', 'kubio' )
				);
			} else {
				$this->wxr_file = $wxr_file;
				$this->options  = $extra_options;
			}
		} else {
			DemoSitesHelpers::logErrorAndSendAjaxResponse(
				esc_html__( 'Import file undefined', 'kubio' ),
				$this->log_file_path,
				esc_html__( 'Downloaded files', 'kubio' )
			);
		}

		DemoSitesHelpers::appendToFile(
			sprintf( /* translators: %s - the name of the selected import. */
				__( 'The import files for: %s were successfully downloaded!', 'kubio' ),
				$kds
			),
			$this->log_file_path,
			esc_html__( 'Downloaded files', 'kubio' )
		);
	}

	private function getDemo() {
		$demos = apply_filters( 'kubio/demo-sites/list', array() );
		$slug  = $this->slug;

		return Arr::get( $demos, $slug, null );
	}

	private function prepareManualImport( $kds_file ) {

		DemoSitesHelpers::appendToFile(
			__( 'Manual kds import!', 'kubio' ),
			$this->log_file_path,
			esc_html__( 'Manual import', 'kubio' )
		);

		list( $wxr_file, $extra_options ) = DemoSitesHelpers::useUploadedKDSFile( Arr::get( $kds_file, 'tmp_name', null ) );
		$this->wxr_file                   = $wxr_file;
		$this->options                    = $extra_options;
	}

	/**
	 * Get the current state of selected data.
	 *
	 * @return array
	 */
	public function getCurrentImportData() {
		return array(
			'log_file_path'          => $this->log_file_path,
			'slug'                   => $this->slug,
			'wxr_file'               => $this->wxr_file,
			'options'                => $this->options,
			'before_import_executed' => $this->before_import_executed,
		);
	}

	public function getBeforeImportActions() {
		return array( 'init', 'prepare_templates', 'prepare_template_parts', 'prepare_menus', 'prepare_pages' );
	}

	/**
	 * This method executes the valid given $action, logs in case of error, and returns the status of ajax/cli queue.
	 * Based on the $ajax parameter it will kill the execution with an `wp_send_json` response or it will print the json.
	 *
	 * @param $action
	 * @param bool $ajax
	 * @return false|string|void
	 */
	public function executeBeforeImportAction( $action, $ajax = true ) {
		$actions      = $this->getBeforeImportActions();
		$action_index = array_search( $action, $actions );

		if ( $action_index === false ) {
			DemoSitesHelpers::sendAjaxError( __( 'Importer action not found', 'kubio' ) );
		}

		$this->logger->info( "Executing before import step: {$action}" );

		switch ( $action ) {

			case 'init':
				$site_url = Arr::get( $this->options, 'site_url' );
				if ( $site_url ) {
					wp_remote_get( $site_url );
				}
				break;

			case 'prepare_templates':
				$this->prepareTemplates();
				break;

			case 'prepare_template_parts':
				$this->prepareTemplateParts();
				break;

			case 'prepare_pages':
				$this->preparePages();
				break;

			case 'prepare_menus':
				$this->prepareMenus();
				break;
		}

		if ( $action_index + 1 < count( $actions ) ) {
			$response = array(
				'status'               => 'requires-new-ajax-call',
				'before_import_action' => $actions[ $action_index + 1 ],
			);

			if ( $ajax ) {
				wp_send_json( $response );
			}

			return wp_json_encode( $response );
		}
	}

	private function prepareTemplates() {
		$stylesheet = get_stylesheet();
		$ids        = get_posts(
			array(
				'post_type'      => 'wp_template',
				'post_status'    => array( 'publish' ),
				'posts_per_page' => - 1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => array( $stylesheet ),
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	private function prepareTemplateParts() {
		$stylesheet = get_stylesheet();
		$ids        = get_posts(
			array(
				'post_type'      => 'wp_template_part',
				'post_status'    => array( 'publish' ),
				'posts_per_page' => - 1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => array( $stylesheet ),
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	private function preparePages() {
		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => - 1,
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( $id, false );
		}
	}

	private function prepareMenus() {
		$menus = wp_get_nav_menus();

		/** @var \WP_Term $menu */
		foreach ( $menus as $menu ) {
			$index = - 1;
			do {
				$index ++;
				list($base_name) = sscanf( $menu->name, '%s - %s' );

				$new_name = sprintf(
					'%s - %s',
					$base_name,
					$index ? sprintf( __( 'Old %s', 'kubio' ), $index ) : __( 'Old', 'kubio' )
				);

			} while ( ! ! get_term_by( 'name', $new_name, 'nav_menu' ) );

			wp_update_nav_menu_object(
				$menu->term_id,
				array(
					'menu-name' => $new_name,
				)
			);
		}

	}

	/**
	 * Send a JSON response with final report or simply returns the json if $ajax is false.
	 *
	 * @param bool $ajax
	 * @return false|string
	 */
	private function afterImport( $ajax = true ) {

		$this->useExistingImporterData();

		if ( $this->slug ) {
			Flags::set( 'last_imported_starter', $this->slug );
		}

		// Delete importer data transient for current import.
		delete_transient( DemoSitesHelpers::IMPORT_TRANSIENT );

		$response = array(
			'status' => 'finished',
		);

		if ( $ajax ) {
			wp_send_json( $response );
		}

		return wp_json_encode( $response );
	}

	//this updates the count column in the wp_term_taxonomy table because in some cases it was still at 0 showing empty menus
	public function updateMenuItemsCount( $wxr_importer ) {
		$update_taxonomy = 'nav_menu';
		$menus_id_map    = Arr::get( $wxr_importer->get_mapping(), 'menus_map', array() );
		wp_update_term_count_now( $menus_id_map, $update_taxonomy );
	}
	/**
	 * @param WXRImporter $wxr_importer
	 */
	public function postProcessContentImport( $wxr_importer ) {
		$this->updateThemeMods( $wxr_importer );
		$this->updateLogos( $wxr_importer );
		$this->updateFrontPages( $wxr_importer );
		$this->updateGlobalNonKubioTemplates();
		$this->updateMenuItemsCount( $wxr_importer );
	}


	private function updateGlobalNonKubioTemplates() {
		wp_cache_flush();
		$blog_templates = array(
			'singular' => 'single',
			'home'     => 'index',
		);

		foreach ( $blog_templates as $template => $replacement ) {
			if ( kubio_has_block_template( $template ) ) {
				$replacement_template = kubio_get_block_template( $replacement );
				if ( $replacement_template ) {
					Importer::createTemplate( $template, $replacement_template->content, true, 'kubio' );
				}
			}
		}
	}

	/**
	 * @param WXRImporter $wxr_importer
	 */
	private function updateThemeMods( $wxr_importer ) {

		$theme_mods = Arr::get( $this->options, 'customizer', array() );

		unset( $theme_mods[0] );

		// update nav menu locations
		$menus_id_map       = Arr::get( $wxr_importer->get_mapping(), 'menus_map', array() );
		$nav_menu_locations = Arr::get( $theme_mods, 'nav_menu_locations', array() );
		foreach ( $nav_menu_locations as $location => $term_id ) {
			$next_term_id = Arr::get( $menus_id_map, $term_id, null );

			if ( $next_term_id ) {
				$nav_menu_locations[ $location ] = (int) $next_term_id;
			} else {
				unset( $nav_menu_locations[ $location ] );
			}
		}
		$theme_mods['nav_menu_locations'] = $nav_menu_locations;

		$theme = get_option( 'stylesheet' );

		update_option( "theme_mods_$theme", $theme_mods );
	}

	/**
	 * @param WXRImporter $wxr_importer
	 */
	private function updateLogos( $wxr_importer ) {
		$alternate_logo_image = kubio_get_global_data( 'alternateLogo' );
		$post_mapping         = Arr::get( $wxr_importer->get_mapping(), 'post', array() );
		if ( $alternate_logo_image ) {
			kubio_set_global_data( 'alternateLogo', Arr::get( $post_mapping, $alternate_logo_image, $alternate_logo_image ) );
		}

		$logos_options = array( 'site_logo', 'site_icon' );

		foreach ( $logos_options as $option ) {
			$value = Arr::get( $this->options, "options.{$option}", 0 );
			$value = Arr::get( $post_mapping, $value, $value );
			update_option( $option, is_numeric( $value ) ? intval( $value ) : $value );
		}
	}

	/**
	 * @param WXRImporter $wxr_importer
	 */
	private function updateFrontPages( $wxr_importer ) {
		$page_options = array( 'page_on_front', 'page_for_posts', 'show_on_front' );

		$post_mapping = Arr::get( $wxr_importer->get_mapping(), 'post', array() );

		foreach ( $page_options as $option ) {
			$value = Arr::get( $this->options, "options.{$option}", 0 );
			$value = Arr::get( $post_mapping, $value, $value );
			update_option( $option, is_numeric( $value ) ? intval( $value ) : $value );
		}
	}
}
