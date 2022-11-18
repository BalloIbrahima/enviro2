<?php


use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Importer;

function kubio_apply_block_support_import_mapper_import_asset( $block, $url_attr, $id_attr = null ) {
	if ( $url_attr ) {
		$url_value = Arr::get( $block->attrs, $url_attr );

		if ( Importer::isValidURLORHasKubioPlaceholder( $url_value ) ) {
			$imported = Importer::importRemoteFile( $url_value );

			if ( $imported ) {
				Arr::set( $block->attrs, $url_attr, $imported['url'] );

				if ( $id_attr && $imported['id'] ) {
					Arr::set( $block->attrs, $id_attr, $imported['id'] );
				}
			}
		}
	}

	return $block;
}

/**
 * @param WP_Block_Parser_Block $block
 */
function kubio_apply_block_support_import_mapper( $block, $block_name ) {

	$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

	if ( isset( $block_type->supports['kubio'] ) ) {
		$assets_importer_map = Arr::get( $block_type->supports['kubio'], 'assetsURLImporterMap', array() );

		foreach ( $assets_importer_map as $url_attr => $import_map ) {

			$subpath  = Arr::get( $import_map, 'subpath', false );
			$is_array = ! ! $subpath;

			if ( ! $is_array ) {
				$block = kubio_apply_block_support_import_mapper_import_asset( $block, $url_attr, Arr::get( $import_map, 'assetIdToAttr' ) );
			} else {
				$url_attr_keys = array_keys( Arr::get( $assets_importer_map, $url_attr, array() ) );

				foreach ( $url_attr_keys as $attr_key ) {
					$item_attr = str_replace( '{index}', $attr_key, $subpath );

					$asset_id_map = Arr::get( $import_map, 'assetIdToAttr' );

					$block = kubio_apply_block_support_import_mapper_import_asset(
						$block,
						$item_attr,
						$asset_id_map ? "{$item_attr}.{$asset_id_map}" : $asset_id_map
					);
				}
			}
		}
	}

	return $block;
}

// automatic import by support
add_filter( 'kubio/importer/maybe_import_block_assets', 'kubio_apply_block_support_import_mapper', 10, 2 );
