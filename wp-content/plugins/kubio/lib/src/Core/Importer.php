<?php

namespace Kubio\Core;

use IlluminateAgnostic\Arr\Support\Arr;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use WP_Query;

class Importer {

	/**
	 * Undocumented function
	 *
	 * @param [type] $slug
	 * @param [type] $content
	 * @param boolean $override
	 * @param string $source
	 * @return int|WP_Error|bool The post ID on success. The value 0 or WP_Error on failure.
	 */
	public static function createTemplate( $slug, $content, $override = false, $source = 'theme' ) {
		$template_types = kubio_get_default_template_types();
		$theme          = get_stylesheet();

		kubio_register_wp_theme_taxonomy();

		if ( isset( $template_types[ $slug ] ) ) {
			$title = $template_types[ $slug ]['title'];
		} else {
			$title = static::maybeTransformSlugToTitle( $slug );
		}

		if ( ! static::entityExists( $slug ) ) {
			return  wp_insert_post(
			// post content should be slashed - same thing happens on rest api call
				wp_slash(
					array(
						'post_content' => $content,
						'post_title'   => $title,
						'post_status'  => 'publish',
						'post_type'    => 'wp_template',
						'post_name'    => $slug,
						'tax_input'    => array(
							'wp_theme' => array( $theme ),
						),
						'meta_input'   => array(
							'_kubio_template_source' => $source,
						),

					)
				),
				true
			);
		} else {
			if ( $override ) {
				static::updateEntityContent( 'wp_template', $slug, $content, $source );
			}
		}

		return true;

	}

	public static function allowImportCaps( $all_cap, $caps ) {

		foreach ( $caps as $cap ) {
			$all_cap[ $cap ] = true;
		}

		return $all_cap;
	}

	public static function maybeTransformSlugToTitle( $slug ) {
		$slug_parts = explode(
			' ',
			trim(
				preg_replace(
					'/\s\s+/',
					' ',
					str_replace( array( '-', ' ' ), ' ', $slug )
				)
			)
		);

		$title = implode(
			' ',
			array_map(
				function ( $item ) {
					return ucfirst( $item );
				},
				$slug_parts
			)
		);

		if ( empty( trim( $title ) ) ) {
			$title = $slug;
		}

		return $title;
	}

	private static function entityExists( $slug, $type = 'wp_template' ) {
		$stylesheet = get_stylesheet();
		$query      = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => array( 'publish' ),
				'post_name__in'  => array( $slug ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => array( $stylesheet ),
					),
				),
			)
		);

		return $query->have_posts();
	}

	private static function updateEntityContent( $type, $slug, $content, $source = 'theme' ) {
		$posts = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => array( 'publish' ),
				'name'           => $slug,
				'no_found_rows'  => true,
				'posts_per_page' => 1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'slug',
						'terms'    => array( get_stylesheet() ),
					),
				),
			)
		);

		$post = Arr::get( $posts, '0' );

		if ( $post ) {
			$args = array(
				'ID'           => $post->ID,
				'post_content' => $content,
			);

			$result = wp_update_post( wp_slash( $args ) );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			delete_post_meta( $post->ID, '_kubio_template_source' );
			update_post_meta( $post->ID, '_kubio_template_source', $source );

			return true;
		}

		return  new \WP_Error( 'post_not_found' );
	}

	public static function createTemplatePart( $slug, $content, $override = false, $source = 'theme' ) {

		kubio_register_wp_theme_taxonomy();
		kubio_register_wp_template_part_area_taxonomy();

		if ( ! static::entityExists( $slug, 'wp_template_part' ) ) {
			$area = WP_TEMPLATE_PART_AREA_UNCATEGORIZED;

			if ( strpos( $slug, 'header' ) !== false ) {
				$area = WP_TEMPLATE_PART_AREA_HEADER;
			} else {
				if ( strpos( $slug, 'footer' ) !== false ) {
					$area = WP_TEMPLATE_PART_AREA_FOOTER;
				} else {
					if ( strpos( $slug, 'sidebar' ) !== false ) {
						$area = WP_TEMPLATE_PART_AREA_SIDEBAR;
					}
				}
			}

			$title = static::maybeTransformSlugToTitle( $slug );
			$theme = get_stylesheet();

			return  wp_insert_post(
			// post content should be slashed - same thing happens on rest api call
				wp_slash(
					array(
						'post_content' => $content,
						'post_title'   => $title,
						'post_status'  => 'publish',
						'post_type'    => 'wp_template_part',
						'post_name'    => $slug,
						'tax_input'    => array(
							'wp_theme'              => array( $theme ),
							'wp_template_part_area' => array(
								$area,
							),
						),
						'meta_input'   => array(
							'_kubio_template_source' => $source,
						),
					)
				),
				true
			);

		} else {
			if ( $override ) {
				return  static::updateEntityContent( 'wp_template_part', $slug, $content, $source );
			}
		}

		return true;
	}

	public static function getTemplateContent( $type, $slug ) {
		$path = null;
		switch ( $type ) {
			case 'page':
				$front_page_locations = array(
					get_stylesheet_directory() . "/full-site-editing/pages/${slug}.html",
					get_template_directory() . "/full-site-editing/pages/${slug}.html",
					KUBIO_ROOT_DIR . "/defaults/${slug}.html",
				);

				foreach ( $front_page_locations as $front_page_location ) {
					if ( file_exists( $front_page_location ) ) {
						$path = $front_page_location;
						break;
					}
				}

				$path = apply_filters( 'kubio/importer/page_path', $path, $slug );
				break;
			case 'wp_template':
				$templates = Importer::getAvailableTemplates();
				$path      = Arr::get( $templates, $slug, null );
				$path      = apply_filters( 'kubio/importer/wp_template_path', $path, $slug );
				break;

			case 'wp_template_part':
				$templates = Importer::getAvailableTemplateParts();
				$path      = Arr::get( $templates, $slug, null );
				$path      = apply_filters( 'kubio/importer/wp_template_part_path', $path, $slug );
				break;
		}

		$content = null;

		if ( $path ) {
			if ( file_exists( $path ) ) {
				$content = file_get_contents( $path );
			} else {
				if ( filter_var( $path, FILTER_VALIDATE_URL ) ) {
					$res = wp_remote_get( $path );

					if ( ! is_wp_error( $res ) ) {
						$content = wp_remote_retrieve_body( $res );
					}
				}
			}
		}

		return apply_filters( 'kubio/importer/content', $content, $type, $slug );
	}

	public static function getAvailableTemplates( $extra_paths = array() ) {
		$template_parts_rels = array(
			'/full-site-editing/block-templates/*.html',
			'/block-templates/*.html',
			'/templates/*.html',
		);
		$files               = array();

		foreach ( $template_parts_rels as $template_parts_rel ) {
			$parent_block_template_files = glob( get_stylesheet_directory() . $template_parts_rel );
			$files                       = is_array( $files ) ? array_merge( $files, $parent_block_template_files ) : array();

		}

		if ( is_child_theme() ) {
			foreach ( $template_parts_rels as $template_parts_rel ) {
				$child_block_template_files = glob( get_template_directory() . $template_parts_rel );
				$child_block_template_files = is_array( $child_block_template_files ) ? $child_block_template_files : array();
				$files                      = array_merge( $files, $child_block_template_files );
			}
		}

		foreach ( $extra_paths as $extra_path ) {
			foreach ( $template_parts_rels as $template_parts_rel ) {
				$child_block_template_files = glob( $extra_path . '/' . $template_parts_rel );
				$child_block_template_files = is_array( $child_block_template_files ) ? $child_block_template_files : array();
				$files                      = array_merge( $files, $child_block_template_files );
			}
		}

		$result = array();

		foreach ( $files as $file ) {
			$slug            = preg_replace( '#(.*)/block-templates/(.*).html#', '$2', wp_normalize_path( $file ) );
			$slug            = preg_replace( '#(.*)/templates/(.*).html#', '$2', wp_normalize_path( $slug ) );
			$result[ $slug ] = $file;
		}

		return apply_filters( 'kubio/importer/available_templates', $result );
	}



	public static function getAvailableTemplateParts( $extra_paths = array() ) {
		$result = array_merge(
			static::getTemplatePartsInDirectory( get_stylesheet_directory() ),
			static::getTemplatePartsInDirectory( get_template_directory() )
		);

		foreach ( $extra_paths as $extra_path ) {
			$result = array_merge( $result, static::getTemplatePartsInDirectory( $extra_path ) );
		}

		return apply_filters( 'kubio/importer/available_template_parts', $result );
	}

	public static function getTemplatesInDirectory( $base_directory = array() ) {
		$template_parts_rels = array(
			'/full-site-editing/block-templates/*.html',
			'/block-templates/*.html',
			'/templates/*.html',
		);

		$result = array();

		foreach ( $template_parts_rels as $template_parts_rel ) {
			$base_path      = wp_normalize_path( "{$base_directory}/{$template_parts_rel}" );
			$template_files = glob( $base_path );

			foreach ( $template_files as $file ) {
				$slug            = preg_replace( '#(.*)/block-templates/(.*).html#', '$2', wp_normalize_path( $file ) );
				$slug            = preg_replace( '#(.*)/templates/(.*).html#', '$2', wp_normalize_path( $slug ) );
				$result[ $slug ] = $file;
			}
		}

		return $result;
	}

	public static function getTemplatePartsInDirectory( $base_directory ) {
		$template_parts_rels = array(
			'/full-site-editing/block-template-parts',
			'/block-template-parts',
			'/parts',
		);

		$files = array();

		foreach ( $template_parts_rels as $template_parts_rel ) {
			$base_path = wp_normalize_path( "{$base_directory}/{$template_parts_rel}" );
			if ( file_exists( $base_path ) ) {
				$nested_files      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base_path ) );
				$nested_html_files = new RegexIterator( $nested_files, '/^.+\.html$/i', RegexIterator::GET_MATCH );
				foreach ( $nested_html_files as $path => $file ) {
					$slug = preg_replace(
						'#(.*)/block-template-parts/(.*).html#',
						'$2',
						wp_normalize_path( $path )
					);

					$slug = preg_replace(
						'#(.*)/parts/(.*).html#',
						'$2',
						wp_normalize_path( $slug )
					);

					$files[ $slug ] = $path;
				}
			}
		}

		return $files;
	}

	public static function importRemoteFile( $source_url ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		static $imported_files;
		$imported_files = is_array( $imported_files ) ? $imported_files : array();

		$replacement = untrailingslashit( apply_filters( 'kubio/importer/kubio-url-placeholder-replacement', '' ) );

		// the next replacement ensure we don't have double slashes inside our url
		$source_url = str_replace( '{{{kubio_asset_base_url}}}/', '{{{kubio_asset_base_url}}}', $source_url );
		$source_url = str_replace( '{{{kubio_asset_base_url}}}', "{$replacement}/", $source_url );

		$source_url = apply_filters( 'kubio/importer/kubio-source-url', $source_url );

		if ( apply_filters( 'kubio/importer/skip-remote-file-import', false ) ) {
			return array(
				'url' => $source_url,
				'id'  => 0,
			);
		}

		// continue import only if we have more than 10 seconds remaining for the execution time
		if ( ! static::timePermitsImport() ) {
			return array(
				'url' => $source_url,
				'id'  => 0,
			);
		}

		// file is already in this server - e.g. this appears on customizer import
		if ( strpos( $source_url, site_url() ) === 0 ) {
			$result = array(
				'url' => $source_url,
				'id'  => attachment_url_to_postid( $source_url ),
			);

			$imported_files[ $source_url ] = $result;

			return $result;
		}

		$parsed_url = parse_url( $source_url );
		$file_path  = $parsed_url ? $parsed_url['path'] : $source_url;
		$file_name  = urldecode( basename( $file_path ) );
		$path_info  = pathinfo( $file_name );

		// if you can not get an extension file from url skip importing
		if ( ! isset( $path_info['extension'] ) ) {
			$result                        = array(
				'url' => $source_url,
				'id'  => 0,
			);
			$imported_files[ $source_url ] = $result;

			return $result;
		};

		if ( isset( $imported_files[ $source_url ] ) ) {
			return $imported_files[ $source_url ];
		}

		if ( $result = static::getImportByGuid( $source_url ) ) {
			$imported_files[ $source_url ] = $result;

			return $result;
		}

		$response = wp_safe_remote_get( $source_url );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {

			$file_content = wp_remote_retrieve_body( $response );
			if ( empty( $file_content ) ) {
				return array(
					'url' => $source_url,
					'id'  => 0,
				);
			}

			$file_name = str_replace( 'colibri', 'kubio', $file_name );

			$upload = wp_upload_bits( $file_name, null, $file_content );

			if ( $upload['error'] !== false ) {
				return array(
					'url' => $source_url,
					'id'  => 0,
				);
			}

			$post = array(
				'post_title' => $file_name,
				// set the source url as guid to easily track reimport
				'guid'       => $source_url,
			);

			$info = wp_check_filetype( $upload['file'] );
			if ( $info ) {
				$post['post_mime_type'] = $info['type'];
			}

			$post_id = wp_insert_attachment( $post, $upload['file'] );

			wp_update_attachment_metadata(
				$post_id,
				wp_generate_attachment_metadata( $post_id, $upload['file'] )
			);

			$result = array(
				'id'  => intval( $post_id ),
				'url' => $upload['url'],
			);

			$imported_files[ $source_url ] = $result;

			return $result;

		}

		return array(
			'url' => $source_url,
			'id'  => 0,
		);
	}

	private static function timePermitsImport() {

		static $start_time;

		if ( ! $start_time ) {
			$start_time = intval( Arr::get( $_SERVER, 'REQUEST_TIME_FLOAT', time() ) );
		}

		$diff = time() - $start_time;

		$max_exec_time = @ini_get( 'max_execution_time' );

		// assume 30 seconds if not available
		if ( ! $max_exec_time ) {
			$max_exec_time = 30;
		}

		// allow 10sec for other tasks
		return ( intval( $max_exec_time ) - $diff > 10 );

	}

	public static function getImportByGuid( $guid ) {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid=%s", $guid ) );

		if ( $id ) {
			$id = intval( $id );

			return array(
				'id'  => $id,
				'url' => wp_get_attachment_url( $id ),
			);
		}

		return null;
	}

	public static function isValidURLORHasKubioPlaceholder( $value ) {
		return ( filter_var( $value, FILTER_VALIDATE_URL ) || strpos( $value, '{{{kubio_asset_base_url}}}' ) === 0 );
	}

	public static function maybeImportBlockAssets( $blocks, $on_time_expired = null ) {

		require_once KUBIO_ROOT_DIR . '/lib/importer/assets-importer-filters.php';

		foreach ( $blocks as $index => $block ) {
			if ( ! $block instanceof \WP_Block_Parser_Block ) {
				$block = new \WP_Block_Parser_Block( $block['blockName'], $block['attrs'], $block['innerBlocks'], $block['innerHTML'], $block['innerContent'] );
			}
			/** @var \WP_Block_Parser_Block $block */
			$block = apply_filters( 'kubio/importer/maybe_import_block_assets', $block, $block->blockName );

			$block->innerBlocks = static::maybeImportBlockAssets( $block->innerBlocks, $on_time_expired );
			$blocks[ $index ]   = (array) $block;
		}

		return $blocks;

	}
}
