<?php

namespace Kubio;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Importer;
use Kubio\Core\Utils;

class WPImportFilters {

	private $supported_theme       = array( 'elevate-wp', 'pathway', 'kubio', 'pixy' );
	private $global_data_processed = false;
	private $fresh_site            = false;

	private $handled_by_kubio = array();

	private function __construct() {
		$this->fresh_site = kubio_is_fresh_site();

		add_action( 'import_end', array( $this, 'handleKubioImports' ) );
		add_action( 'wp_get_object_terms', array( $this, 'addExtraTermsToExport' ) );
		add_filter( 'wp_import_posts', array( $this, 'filterImportedPosts' ), 5 );

	}

	public function addExtraTermsToExport( $terms ) {
		if ( defined( 'WXR_VERSION' ) ) {
			// exclude if active theme is not kubio compatible
			if ( ! kubio_theme_has_kubio_block_support() ) {
				return $terms;
			}

			$stylesheet = \get_stylesheet();
			foreach ( $terms as $term ) {
				if ( $term->taxonomy === 'wp_theme' && $term->name === $stylesheet ) {
					$terms[] = (object) array(
						'taxonomy' => '_kubio_suports_rename',
						'slug'     => $stylesheet,
						'name'     => $stylesheet,
					);
					break;
				}
			}
		}

		return $terms;
	}

	public function filterImportedPosts( $posts ) {

		if ( defined( 'KUBIO_IS_STARTER_SITES_IMPORT' ) ) {
			return $posts;
		}

		$template           = get_stylesheet();
		$is_supported_theme = kubio_theme_has_kubio_block_support();

		kubio_register_wp_theme_taxonomy( true );
		kubio_register_wp_template_part_area_taxonomy( true );

		wp_insert_term( $template, 'wp_theme', array( 'slug' => $template ) );

		$this->log( "Theme: {$template}" );
		$this->log( $this->fresh_site ? 'Fresh site' : 'Not a fresh site' );

		$contains_pages = false;
		foreach ( $posts as $index => $post ) {
			$post_type = Arr::get( $post, 'post_type', null );

			switch ( $post_type ) {
				case 'page':
					$contains_pages = true;
					break;
				case 'wp_template':
				case 'wp_template_part':
					$posts[ $index ] = $this->processFSETemplate( $post, $template, $is_supported_theme );
					break;

				case kubio_global_data_post_type():
					$posts[ $index ] = $this->processGlobalData( $posts );
					break;
			}
		}

		// remove precreated front and blog page if the site is fresh & the xml file contains pages to reduce pages duplication
		if ( count( $this->handled_by_kubio ) > 0 && $contains_pages ) {
			$this->log( 'Cleanup Kubio intial pages' );
			wp_cache_flush();
			$page_on_front  = intval( get_option( 'page_on_front', 0 ) );
			$page_for_posts = intval( get_option( 'page_for_posts', 0 ) );

			if ( $page_on_front && intval( get_post_meta( $page_on_front, '_kubio_created_at_activation', true ) ) ) {
				update_option( 'show_on_front', 'posts' );
				wp_delete_post( $page_on_front, true );
			}

			if ( $page_for_posts && intval( get_post_meta( $page_for_posts, '_kubio_created_at_activation', true ) ) ) {
				wp_delete_post( $page_for_posts, true );
			}
			wp_cache_flush();
		}

		$posts = array_filter( $posts );

		return $posts;

	}

	private function processFSETemplate( $post, $template, $is_supported_theme ) {
		$terms           = Arr::get( $post, 'terms', array() );
		$supports_rename = false;

		// remove _kubio_suports_rename temporrary term if exists
		foreach ( $terms as $term_index => $term ) {
			$domain = Arr::get( $term, 'domain', null );
			if ( $domain === '_kubio_suports_rename' ) {
				$supports_rename = true;
				unset( $terms[ $term_index ] );
				break;
			}
		}

		if ( ! $is_supported_theme || ! $this->fresh_site ) {
			$post['terms'] = $terms;
			return $post;
		}

		$use_kubio_importer = $supports_rename;

		foreach ( $terms as $term ) {
			$domain = Arr::get( $term, 'domain', null );
			if ( $domain === 'wp_theme' ) {
				$use_kubio_importer = $use_kubio_importer || in_array( $term['slug'], $this->supported_theme, true );
				break;
			}
		}

		if ( $use_kubio_importer ) {
				$blocks  = parse_blocks( $post['post_content'] );
				$blocks  = kubio_blocks_update_template_parts_theme( $blocks, $template );
				$content = kubio_serialize_blocks( $blocks );

				$this->handled_by_kubio[] = array(
					'slug'    => $post['post_name'],
					'type'    => $post['post_type'],
					'content' => $content,
				);

				return false;
		}

		return $post;
	}

	private function processGlobalData( $posts ) {
		if ( $this->global_data_processed || ! $this->fresh_site ) {
			return false;
		}

		$global_data_post = null;

		foreach ( $posts as $post ) {
			$post_type = Arr::get( $post, 'post_type', null );
			if ( $post_type !== kubio_global_data_post_type() ) {
				continue;
			}

			if ( ! $global_data_post ) {
				$global_data_post = $post;
			}

			$terms = Arr::get( $post, 'terms', array() );

			$is_theme_global_data = false;

			foreach ( $terms as  $term ) {
				$domain = Arr::get( $term, 'domain', null );
				if ( $domain === '_kubio_suports_rename' ) {
					$is_theme_global_data = true;
					break;
				}
			}

			if ( $is_theme_global_data ) {
				$global_data_post = $post;
				break;
			}
		}

		$this->handled_by_kubio[] = array(
			'slug'    => kubio_global_data_post_type(),
			'type'    => kubio_global_data_post_type(),
			'content' => $global_data_post['post_content'],
		);

		$this->global_data_processed = true;
		return false;
	}

	public function handleKubioImports() {

		$stylesheet = \get_stylesheet();

		if ( ! count( $this->handled_by_kubio ) ) {
			_kubio_remove_fresh_install_flag();
		}

		if ( Utils::isCLI() ) {
			add_filter( 'user_has_cap', array( Importer::class, 'allowImportCaps' ), 10, 2 );
		}

		$this->log( "\n\nKubio import handler \n\n" );

		foreach ( $this->handled_by_kubio as $import ) {
			$type = $import['type'];

			$this->log( "Import entity: {$type} - {$import['slug']}" );

			switch ( $type ) {
				case 'wp_template':
					Importer::createTemplate( $import['slug'], $import['content'], true, 'kubio' );
					break;
				case 'wp_template_part':
					Importer::createTemplatePart( $import['slug'], $import['content'], true, 'kubio' );
					break;
				case kubio_global_data_post_type():
					kubio_replace_global_data_content( $import['content'], $stylesheet );
					break;
			}
		}

		$this->handled_by_kubio = array();
	}

	public static function load() {
		new static();
	}

	private function log( $message ) {
		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::log( "Kubio Imported -- $message" );
		} else {
			if ( defined( 'WP_DEBUG' ) ) {
				printf( '<script>console.log(%s)</script>', wp_json_encode( "Kubio Log: {$message}" ) );
			}
		}
	}
}



WPImportFilters::load();
