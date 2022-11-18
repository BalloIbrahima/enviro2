<?php

namespace Kubio\DemoSites;


use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Importer;
use ProteusThemes\WPContentImporter2\Importer as ProteusImporter;

class WXRImporter extends ProteusImporter {

	const WXR_IMPORTER_TRANSIENT = 'kubio-wxr-importer-transient';
	const EMAIL_REGEXP           = "/(?:[a-z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+=?^_`{|}~-]+)*|\"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*\")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/";
	/**
	 * Time in milliseconds, marking the beginning of the import.
	 *
	 * @var float
	 */
	private $start_time;

	public function __construct( $options = array( 'is_stepped' => true ), $logger = null ) {

		if ( ! defined( 'KUBIO_IS_STARTER_SITES_IMPORT' ) ) {
			define( 'KUBIO_IS_STARTER_SITES_IMPORT', true );
		}

		add_filter( 'wxr_importer.pre_process.post', array( $this, 'preProcessPost' ), 10, 4 );
		add_filter( 'wp_import_post_data_processed', array( $this, 'beforePostImport' ), 10, 1 );
		add_filter( 'wp_import_post_terms', array( $this, 'importPostTerms' ), 10, 3 );
		add_action( 'wp_import_insert_post', array( $this, 'afterPostImport' ), 10, 4 );

		add_action( 'wxr_importer.pre_process.term', array( $this, 'beforeTermImport' ), 10, 2 );
		add_action( 'wxr_importer.processed.term', array( $this, 'afterTermImport' ), 10, 2 );

		DemoSitesImportBlockMap::init();

		parent::__construct( $options, $logger );
	}


	public function updatePermalink() {
		global $wp_rewrite;

		$new_permalink_structure     = '/%postname%/';
		$current_permalink_structure = $wp_rewrite->permalink_structure;
		if ( $new_permalink_structure === $current_permalink_structure ) {
			return;
		}
		//Write the rule
		$wp_rewrite->set_permalink_structure( $new_permalink_structure );

		//Set the option
		update_option( 'rewrite_rules', false );

		//Flush the rules and tell it to write htaccess
		$wp_rewrite->flush_rules( true );
	}

	public function preProcessPost( $data, $meta, $comments, $terms ) {
		if ( $data['post_type'] === kubio_global_data_post_type() ) {

			$current_global_data = kubio_global_data_post_id( true, true );

			$ids = get_posts(
				array(
					'post_type'      => kubio_global_data_post_type(),
					'post_status'    => array( 'publish' ),
					'posts_per_page' => - 1,
					'fields'         => 'ids',
					'exclude'        => kubio_global_data_post_id(),
				)
			);

			foreach ( $ids as $id ) {
				if ( intval( $id ) !== intval( $current_global_data ) ) {
					wp_delete_post( $id, true );
				}
			}

			kubio_replace_global_data_content( $data['post_content'] );
			return false;
		}

		if ( $data['post_type'] === 'wp_template' || $data['post_type'] === 'wp_template_part' ) {
			// Skip imported template from twenty* themes
			foreach ( $terms as $term ) {
				$taxonomy = Arr::get( $term, 'taxonomy' );
				$slug     = Arr::get( $term, 'slug' );
				if ( $taxonomy === 'wp_theme' && is_string( $slug ) && strpos( $slug, 'twenty' ) === 0 ) {
					return false;
				}
			}
		}

		return $data;
	}

	public function beforePostImport( $postdata ) {

		remove_filter( 'wp_insert_post_data', 'kubio_on_post_update', 10, 3 );
		remove_action( 'wp_insert_post', 'kubio_update_meta', 10, 3 );
		remove_filter( 'theme_mod_nav_menu_locations', 'kubio_nav_menu_locations_from_global_data' );

		$content = $postdata['post_content'];
		$content = str_replace( 'var(\\u002d\\u002d', 'var(--', $content );

		$blocks = parse_blocks( $content );

		if ( empty( $blocks ) ) {
			return $postdata;
		}

		$blocks = kubio_blocks_update_template_parts_theme( $blocks, get_stylesheet() );
		$blocks = kubio_blocks_update_block_links( $blocks, $this->base_url );

		$has_blocks = false;

		foreach ( $blocks as $block ) {
			$has_blocks = $has_blocks || Arr::get( $block, 'blockName', null );
			if ( $has_blocks ) {
				break;
			}
		}

		if ( ! $has_blocks ) {
			return $postdata;
		}

		$this->blocksMapping( $postdata['import_id'], $blocks );

		$blocks = Importer::maybeImportBlockAssets( $blocks, array( $this, 'requestNewAjaxCall' ) );

		// WP_REST_Posts_Controller is adding slashes when saving content
		$postdata['post_content'] = wp_slash( kubio_serialize_blocks( $blocks ) );

		return $postdata;
	}

	private function blocksMapping( $import_id, $blocks ) {
		foreach ( $blocks as $block ) {

			if ( ! Arr::get( $block, 'blockName' ) ) {
				continue;
			}

			$should_map = apply_filters( 'kubio/demo-import/should-map', false, $block['blockName'], $block );

			if ( $should_map ) {
				$this->setHasBlockMapping( $import_id, $block['blockName'] );
			}

			$this->blocksMapping( $import_id, $block['innerBlocks'] );
		}

	}

	private function setHasBlockMapping( $import_id, $block_name ) {
		Arr::set( $this->mapping, "blocks.{$import_id}.{$block_name}", true );
	}

	public function afterPostImport( $post_id, $original_id, $postdata, $data ) {

		if ( Arr::get( $postdata, 'post_type', null ) === kubio_global_data_post_type() ) {
			$global_data = json_decode( $postdata['post_content'], true );
			Arr::forget( $global_data, 'menuLocations' );
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => json_encode( $global_data ),
				)
			);
		}

		$this->updatePermalink();
	}

	public function beforeTermImport( $data ) {

		if ( $data['taxonomy'] === 'nav_menu' ) {
			if ( strpos( strtolower( $data['name'] ), '[old]' ) !== false ) {
				return false;

			}
		}

		if ( $data['taxonomy'] === 'wp_theme' ) {
			$theme = get_stylesheet();
			return array(
				'taxonomy' => 'wp_theme',
				'slug'     => $theme,
				'name'     => $theme,
			);
		}

		return $data;

	}

	public function afterTermImport( $term_id, $data ) {
		$original_id = isset( $data['id'] ) ? (int) $data['id'] : 0;

		if ( $original_id && $data['taxonomy'] === 'nav_menu' ) {
			Arr::set( $this->mapping, "menus_map.{$original_id}", (int) $term_id );
		}
	}

	public function importPostTerms( $terms, $post_id, $data ) {

		$post_type = Arr::get( $data, 'post_type', null );
		$theme     = get_stylesheet();

		if ( $post_type && in_array( $post_type, array( 'wp_template', 'wp_template_part' ), true ) ) {

			Arr::set( $this->requires_remapping, "post.{$post_id}", true );

			foreach ( $terms as $index => $term ) {
				if ( $term['taxonomy'] === 'wp_theme' ) {
					array_splice( $terms, $index, 1 );
				}
			}

			$terms[] = array(
				'taxonomy' => 'wp_theme',
				'slug'     => $theme,
				'name'     => $theme,
			);

			$key = sha1( $term['taxonomy'] . ':' . $term['taxonomy'] );
			Arr::forget( $this->mapping, "term.{$key}" );
		}

		return $terms;
	}


	/**
	 * Restore the importer data from the transient.
	 *
	 * @return boolean
	 */
	public function restore_import_data_transient() {
		if ( $data = get_transient( static::WXR_IMPORTER_TRANSIENT ) ) {
			$this->options            = empty( $data['options'] ) ? array() : $data['options'];
			$this->mapping            = empty( $data['mapping'] ) ? array() : $data['mapping'];
			$this->requires_remapping = empty( $data['requires_remapping'] ) ? array() : $data['requires_remapping'];
			$this->exists             = empty( $data['exists'] ) ? array() : $data['exists'];
			$this->user_slug_override = empty( $data['user_slug_override'] ) ? array() : $data['user_slug_override'];
			$this->url_remap          = empty( $data['url_remap'] ) ? array() : $data['url_remap'];
			$this->featured_images    = empty( $data['featured_images'] ) ? array() : $data['featured_images'];

			return true;
		}

		return false;
	}

	public function delete_import_data_transient() {
		delete_transient( static::WXR_IMPORTER_TRANSIENT );
	}

	public function import_content( $import_file ) {

		if ( strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) === false ) {
			set_time_limit( 300 );
		}

		// Disable import of authors.
		add_filter( 'wxr_importer.pre_process.user', '__return_false' );

		// Check, if we need to send another AJAX request and set the importing author to the current user.
		add_filter( 'wxr_importer.pre_process.post', array( $this, 'new_ajax_request_maybe' ) );

		// Import content.
		if ( ! empty( $import_file ) ) {
			ob_start();
			$this->import( $import_file );
			$output = ob_get_clean();
		}

		return $this->logger->error_output;
	}

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the WXR file for importing.
	 * @param array $options Import options (which parts to import).
	 *
	 * @return boolean
	 */
	public function import( $file, $options = array() ) {
		// Start the import timer.
		$this->start_time = microtime( true );

		return parent::import( $file, $options );
	}

	/**
	 * Does the post exist?
	 *
	 * @param array $data Post data to check against.
	 *
	 * @return int|bool Existing post ID if it exists, false otherwise.
	 */
	protected function post_exists( $data ) {
		// Constant-time lookup if we prefilled
		$exists_key = $data['guid'];

		if ( empty( $exists_key ) ) {
			$exists_key = $data['post_title'] . '___' . $data['post_type'];
		}

		if ( $this->options['prefill_existing_posts'] ) {
			// OCDI: fix for custom post types. The guids in the prefilled section are escaped, so these ones should be as well.
			$exists_key = htmlentities( $exists_key );

			return isset( $this->exists['post'][ $exists_key ] ) ? $this->exists['post'][ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it
		if ( isset( $this->exists['post'][ $exists_key ] ) ) {
			return $this->exists['post'][ $exists_key ];
		}

		if ( $data['post_type'] === 'attachment' && $data['guid'] ) {
			if ( $imported_asset = Importer::getImportByGuid( $data['guid'] ) ) {
				$this->exists['post'][ $exists_key ] = $imported_asset['id'];
			}
		}

		// Still nothing, try post_exists, and cache it
		$exists                              = post_exists( $data['post_title'], $data['post_content'], $data['post_date'] );
		$this->exists['post'][ $exists_key ] = $exists;

		return $exists;
	}

	protected function process_attachment( $post, $meta, $remote_url ) {

		$imported = Importer::importRemoteFile( $remote_url );
		$post_id  = $imported['id'];

		if ( $post_id === 0 ) {
			$error_message = sprintf( __( 'Unable to import remote file: %s', 'kubio' ), $remote_url );
			$this->logger->warning( $error_message );

			return new \WP_Error( 'attachment_processing_error', $error_message );
		}

		return $post_id;
	}

	/**
	 * Process and import post meta items.
	 *
	 * @param array $meta List of meta data arrays
	 * @param int $post_id Post to associate with
	 * @param array $post Post data
	 *
	 * @return int|WP_Error Number of meta items imported on success, error otherwise.
	 */
	protected function process_post_meta( $meta, $post_id, $post ) {

		// replace anchors urls
		if ( Arr::get( $post, 'post_type' ) === 'nav_menu_item' ) {
			foreach ( $meta as $index => $meta_item ) {
				if ( $meta_item['key'] === '_menu_item_url' ) {
					$value = $meta_item['value'];

					$meta[ $index ] = array_merge(
						$meta_item,
						array(
							'value' => str_replace( $this->base_url, site_url(), $value ),
						)
					);
					break;
				}
			}
		} else {
			foreach ( $meta as $index => $meta_item ) {
				$meta_item['value'] = maybe_serialize( $this->cleanupEmails( maybe_unserialize( $meta_item['value'] ) ) );
				$meta[ $index ]     = $meta_item;
			}
		}

		if ( Arr::get( $post, 'post_type' ) === 'wp_template' || Arr::get( $post, 'post_type' ) === 'wp_template_part' ) {
			$meta[] = array(
				'key'   => '_kubio_template_source',
				'value' => 'kubio',
			);
		}

		return parent::process_post_meta( $meta, $post_id, $post );
	}


	private function cleanupEmails( $content ) {
		if ( is_array( $content ) ) {
			$copy = $content;

			array_walk_recursive(
				$copy,
				function ( &$value ) {
					if ( is_string( $value ) ) {
						$value = preg_replace( static::EMAIL_REGEXP, 'mail@example.com', $value );
					}
				}
			);

			return $copy;
		}

		return preg_replace( static::EMAIL_REGEXP, 'mail@example.com', $content );

	}

	protected function post_process() {
		wp_cache_flush();
		do_action( 'kubio/demo-site-import/post-process', $this );

		$this->postProcessMenuItems();

		parent::post_process();

		// execute post process blocks after the template parts were set
		$this->postProccessBlocks();

	}

	// update nav menu items parent meta value
	protected function postProcessMenuItems() {
		global $wpdb;
		$post_mapping = $this->mapping['post'];

		foreach ( $post_mapping as $original => $next_value ) {

			$query = $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %d WHERE meta_key='_menu_item_menu_item_parent' AND meta_value= %d",
				intval( $next_value ),
				intval( $original )
			);

			$wpdb->query( $query );
		}
	}

	protected function postProccessBlocks() {

		$blocks_mapping = Arr::get( $this->mapping, 'blocks', array() );

		foreach ( $blocks_mapping as $import_id => $mapped_blocks ) {
			if ( $mapped_blocks !== null ) {

				$post_id = Arr::get( $this->mapping, "post.{$import_id}", $import_id );

				$post = get_post( $post_id );
				if ( is_wp_error( $post ) ) {
					DemoSitesHelpers::sendAjaxError( $post );
				}

				$blocks = parse_blocks( $post->post_content );

				$blocks  = $this->applyBlocksMapping( $blocks, $import_id );
				$content = kubio_serialize_blocks( $blocks );

				$result = wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $content,
						)
					),
					true,
					false
				);

				if ( is_wp_error( $result ) ) {
					DemoSitesHelpers::sendAjaxError( $result );
				}

				Arr::set( $this->mapping, "blocks.{$import_id}", null );
				$this->new_ajax_request_maybe();
			}
		}

	}

	private function applyBlocksMapping( $blocks, $import_id ) {

		$mapped_blocks = Arr::get( $this->mapping, "blocks.{$import_id}", array() );

		foreach ( $blocks as $index => $block ) {
			$block_name = Arr::get( $block, 'blockName' );

			if ( ! $block_name ) {
				continue;
			}

			$block_has_mappings = Arr::get( $mapped_blocks, $block_name, false );

			if ( $block_has_mappings ) {
				$block = apply_filters( 'kubio/demo-import/apply-block-mapping', $block, $this );
			}

			$block['innerBlocks'] = $this->applyBlocksMapping( $block['innerBlocks'], $import_id );
			$blocks[ $index ]     = $block;
		}

		return $blocks;
	}

	/**
	 * Check if we need to create a new AJAX request, so that server does not timeout.
	 * And fix the import warning for missing post author.
	 *
	 * @param array $data current post data.
	 *
	 * @return array
	 */
	public function new_ajax_request_maybe( $data = null ) {
		$time = microtime( true ) - $this->start_time;

		// We should make a new ajax call, if the time is right.
		// On CLI execute this in one step.
		if ( $this->options['is_stepped'] && $time > 20 ) {
			$this->requestNewAjaxCall();
		}

		// Set importing author to the current user.
		// Fixes the [WARNING] Could not find the author for ... log warning messages.
		$current_user_obj    = wp_get_current_user();
		$data['post_author'] = $current_user_obj->user_login;

		return $data;
	}

	public function requestNewAjaxCall() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		$time     = microtime( true ) - $this->start_time;
		$response = array(
			'status'                => 'requires-new-ajax-call',
			'log'                   => 'Request time almost expired. Start new request!: ' . $time,
			'num_of_imported_posts' => count( $this->mapping['post'] ),
		);

		// Add message to log file.
		$this->logger->info( __( 'New AJAX call!', 'wordpress-importer' ) );

		// Set the current importer state, so it can be continued on the next AJAX call.
		$this->set_current_importer_data();

		// Send the request for a new AJAX call.
		wp_send_json( $response );
	}

	/**
	 * Save current importer data to the DB, for later use.
	 */
	public function set_current_importer_data() {
		$data = array(
			'options'            => $this->options,
			'mapping'            => $this->mapping,
			'requires_remapping' => $this->requires_remapping,
			'exists'             => $this->exists,
			'user_slug_override' => $this->user_slug_override,
			'url_remap'          => $this->url_remap,
			'featured_images'    => $this->featured_images,
		);

		$this->save_current_import_data_transient( $data );
	}

	/**
	 * Set the importer data to the transient.
	 *
	 * @param array $data Data to be saved to the transient.
	 */
	public function save_current_import_data_transient( $data ) {
		set_transient( static::WXR_IMPORTER_TRANSIENT, $data, MINUTE_IN_SECONDS );
	}

	protected function post_process_posts( $todo ) {
		$this->logger->info( __( 'Posts post processing', 'kubio' ) );
		foreach ( $todo as $post_id => $_ ) {
			$terms = get_post_meta( $post_id, '_wxr_import_term', false );
			foreach ( $terms as $term ) {

				$found_term = term_exists( $term['slug'], $term['taxonomy'] );

				if ( ! $found_term ) {
					$this->logger->info( sprintf( __( 'Create term `%1$s` in `%2$s`', 'kubio' ), $term['slug'], $term['taxonomy'] ) );
					$found_term = wp_insert_term(
						$term['name'],
						$term['taxonomy'],
						array(
							'slug' => $term['slug'],
						)
					);
				}

				if ( is_wp_error( $found_term ) ) {
					continue;
				}

				$term_id = (int) $found_term['term_id'];
				wp_set_post_terms( $post_id, array( $term_id ), $term['taxonomy'], false );
			}

			delete_post_meta( $post_id, '_wxr_import_term' );
		}

		parent::post_process_posts( $todo );
	}

	public function getBaseUrl() {
		return $this->base_url;
	}
}
