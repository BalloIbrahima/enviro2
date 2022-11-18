<?php

namespace Kubio\Blocks;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Registry;

class QueryPaginationBlock extends RowBlock {


	/**
	 * Render block only if the query has more pages
	 *
	 * @param mixed $wp_block
	 *
	 * @return string
	 */
	public function render( $wp_block ) {

		if ( $this->queryHasPages() || $this->isSandboxRender() ) {
			return parent::render( $wp_block );
		}

		return '';
	}

	/**
	 * Check if query has more than one page
	 * @return bool
	 */
	private function queryHasPages() {

		global $wp_query;
		$use_main_query = Arr::get( $this->block_context, 'useMainQuery', false );

		if ( empty( $this->block_context ) || $use_main_query ) {
			/**
			 * Global WordPress query
			 * @var \WP_Query $wp_query
			 */

			if ( $wp_query->is_singular() ) {
				$prev_post = get_adjacent_post();
				$next_post = get_adjacent_post( false, '', false );

				return ( $prev_post instanceof \WP_Post || $next_post instanceof \WP_Post );
			}

			global $wp_query;
			$total = isset( $wp_query->max_num_pages ) ? $wp_query->max_num_pages : 1;

			return ( $total > 1 );

		}

		return Arr::get( $this->block_context, 'query.pages', 1 ) > 1;
	}
}


Registry::registerBlock(
	__DIR__,
	QueryPaginationBlock::class,
	array(
		'metadata'        => '../row/block.json',
		'metadata_mixins' => array( './block.json' ),
	)
);
