<?php

use IlluminateAgnostic\Arr\Support\Arr;

/**
 * @param string $block_type
 * @param callable $callback
 */
function kubio_add_block_importer_asset_filter( $block_type, $callback ) {
	add_filter(
		'kubio/importer/maybe_import_block_assets',
		function ( $block, $current_block_type ) use ( $block_type, $callback ) {
			if ( $block_type === $current_block_type ) {
				return call_user_func( $callback, $block );
			}

			return $block;
		},
		10,
		2
	);
}

require_once __DIR__ . '/importer-filters/kubio-blocks.php';
require_once __DIR__ . '/importer-filters/kubio-block-support-import-mapper.php';

