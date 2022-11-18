<?php

namespace Kubio\DemoSites;

use IlluminateAgnostic\Arr\Support\Arr;

class DemoSitesImportBlockMap {

	private static $mapped_data = array();

	public static function init() {
		add_filter( 'kubio/demo-import/should-map', array( static::class, 'isBlockMappedFilter' ), 10, 2 );
		add_filter( 'kubio/demo-import/apply-block-mapping', array( static::class, 'applyBlockMapping' ), 10, 2 );
		static::loadDefaultImports();
	}

	private static function loadDefaultImports() {
		static::addMap(
			'kubio/contact',
			array(
				static::class,
				'updateContactFormData',
			)
		);

		static::addMap(
			'kubio/subscribe-form',
			array(
				static::class,
				'updateContactFormData',
			)
		);

		static::addMap(
			'kubio/image-gallery',
			array(
				static::class,
				'updateImageGalleryData',
			)
		);

		static::addMap(
			'kubio/button',
			array(
				static::class,
				'updateBaseUrl',
			)
		);
		static::addMap(
			'kubio/link',
			array(
				static::class,
				'updateBaseUrl',
			)
		);
	}

	public static function addMap( $block_name, $resolver_callback ) {
		static::$mapped_data[ $block_name ] = $resolver_callback;

	}

	public static function isBlockMappedFilter( $should_map, $block_name ) {

		if ( Arr::get( static::$mapped_data, $block_name ) ) {
			return true;
		}

		return $should_map;
	}

	public static function applyBlockMapping( $block, $wxr_importer ) {
		$block_name = Arr::get( $block, 'blockName', null );
		if ( $block_name && $map_fn = Arr::get( static::$mapped_data, $block_name ) ) {
			return call_user_func( $map_fn, $block, $wxr_importer );
		}

		return $block;
	}

	/**
	 * @param array $block
	 * @param WXRImporter $wxr_importer
	 *
	 * @return mixed
	 */
	public static function updateImageGalleryData( $block, $wxr_importer ) {
		$mappings       = $wxr_importer->get_mapping();
		$post_mapping   = Arr::get( $mappings, 'post', array() );
		$upload_dir     = wp_upload_dir();
		$upload_dir_url = $upload_dir['url'];

		foreach ( Arr::get( $block, 'attrs.imagesData', array() ) as $index => $initial_image_data ) {
			$initial_image_id = Arr::get( $initial_image_data, 'id', 0 );
			$image_id         = Arr::get( $post_mapping, $initial_image_id, 0 );

			if ( $image_id ) {
				$image_data = wp_get_attachment_metadata( $image_id );

				$sizes = array();

				foreach ( $image_data['sizes'] as $size => $size_data ) {
					$sizes[ $size ] = array(
						'url' => "$upload_dir_url/{$size_data['file']}",
					);
				}

				Arr::set(
					$block,
					"attrs.imagesData.{$index}",
					array_replace_recursive(
						$initial_image_data,
						array(
							'id'    => $image_id,
							'url'   => "$upload_dir_url/{$image_data['file']}",
							'link'  => "$upload_dir_url/{$image_data['file']}",
							'sizes' => $sizes,
						)
					)
				);

			}
		}

		return $block;
	}


	/**
	 * @param array $block
	 * @param WXRImporter $wxr_importer
	 *
	 * @return mixed
	 */
	public static function updateContactFormData( $block, $wxr_importer ) {

		$mappings     = $wxr_importer->get_mapping();
		$post_mapping = Arr::get( $mappings, 'post', array() );

		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		if ( $id = Arr::get( $block, 'attrs.formType' ) ) {
			Arr::set(
				$block,
				'attrs.formType',
				Arr::get( $post_mapping, $id, $id )
			);
		}

		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		if ( $id = Arr::get( $block, 'attrs.formId' ) ) {
			Arr::set(
				$block,
				'attrs.formId',
				Arr::get( $post_mapping, $id, $id )
			);
		}

		$shortcode = Arr::get( $block, 'attrs.shortcode', '' );
		$shortcode = preg_replace_callback(
			'#id="(\d+)"#',
			function ( $matches ) use ( $post_mapping ) {
				$id = array_pop( $matches );
				$id = Arr::get( $post_mapping, $id, $id );

				return 'id="' . $id . '"';
			},
			$shortcode
		);

		Arr::set( $block, 'attrs.shortcode', $shortcode );

		return $block;
	}

	public static function updateBaseUrl( $block, $wxr_importer ) {
		if ( ! ! ( $linkValue = Arr::get( $block, 'attrs.link.value' ) ) ) {

			$link          = &$block['attrs']['link'];
			$baseUrl       = $wxr_importer->getBaseUrl();
			$link['value'] = str_replace( $baseUrl, site_url(), $linkValue );
		}
		return $block;
	}
}
