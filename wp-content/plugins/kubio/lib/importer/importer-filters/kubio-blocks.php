<?php

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Importer;


/**
 * @param WP_Block_Parser_Block $block
 */
function kubio_maybe_import_background_assets( $block ) {
	$kubio_attr = Arr::get( $block->attrs, 'kubio', array() );

	if ( ! empty( $kubio_attr ) ) {
		array_walk_recursive(
			$kubio_attr,
			function ( &$value ) {

				if ( Importer::isValidURLORHasKubioPlaceholder( $value ) ) {

					$imported = Importer::importRemoteFile( $value );

					if ( $imported ) {
						$value = $imported['url'];
					}
				}
			}
		);
		// put back the kubio attr
		Arr::set( $block->attrs, 'kubio', $kubio_attr );
	}

	return $block;
}

add_filter( 'kubio/importer/maybe_import_block_assets', 'kubio_maybe_import_background_assets', 10 );
