<?php

namespace Kubio\Blocks;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Registry;

class PaginationNavButtonBlock extends ButtonBlock {

	private $action_url_map = array(
		'prev'        => 'get_previous_posts_page_link',
		'next'        => 'get_next_posts_page_link',
		'single_prev' => array( PaginationNavButtonBlock::class, 'getPrevPostLink' ),
		'single_next' => array( PaginationNavButtonBlock::class, 'getPrevNextLink' ),
	);


	public static function getPrevPostLink() {
		if ( is_attachment() ) {
			$post = get_post( get_post()->post_parent );
		} else {
			$post = get_adjacent_post( false, '', true, 'category' );
		}

		return get_permalink( $post );
	}

	public function getPrevNextLink() {

		$post = get_adjacent_post( false, '', false, 'category' );

		return get_permalink( $post );
	}


	public function mapPropsToElements() {
		$current_map = parent::mapPropsToElements();

		$action_type = $this->getAttribute( 'action', 'prev' );

		global $wp_query;

		if ( $wp_query->is_single() ) {
			$action_type = "single_{$action_type}";
		}

		$current_map[ ButtonBlock::LINK ] = array(
			'href'          => call_user_func( $this->action_url_map[ $action_type ] ),
			'typeOpenLink'  => 'sameWindow',
			'noFollow'      => false,
			'lightboxMedia' => '',
		);

		return $current_map;
	}

	public function render( $wp_block ) {
		if ( ! $this->shouldRender() && ! $this->isSandboxRender() ) {
			return '';
		}

		return parent::render( $wp_block );
	}

	private function shouldRender() {

		if ( empty( $this->block_context ) || Arr::get( $this->block_context, 'useMainQuery', false ) ) {
			return $this->shouldRenderMainQueryButton();
		}

		return $this->shouldRenderCustomQueryButton();
	}

	private function shouldRenderMainQueryButton() {
		global $wp_query;

		if ( $wp_query->is_single() ) {
			if ( $this->getAttribute( 'action', 'prev' ) === 'prev' ) {
				$post = get_adjacent_post( false, '', true, 'category' );

			} else {
				$post = get_adjacent_post( false, '', false, 'category' );
			}

			return ( $post instanceof \WP_Post );
		}

		$total   = isset( $wp_query->max_num_pages ) ? $wp_query->max_num_pages : 1;
		$current = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;

		if ( $this->getAttribute( 'action' ) === 'prev' ) {
			return ( $current > 1 );
		} else {
			return ( intval( $current ) < intval( $total ) );
		}
	}

	private function shouldRenderCustomQueryButton() {
		$query_id = Arr::get( $this->block_context, 'queryId', false );
		$page_key = $query_id ? 'query-' . $query_id . '-page' : 'query-page';
		$page     = empty( $_GET[ $page_key ] ) ? 1 : filter_var( $_GET[ $page_key ], FILTER_VALIDATE_INT );
		$pages    = Arr::get( $this->block_context, 'query.pages', 1 );

		if ( $this->getAttribute( 'action' ) === 'prev' ) {
			return ( intval( $page ) !== 1 );
		} else {
			return ( intval( $page ) < intval( $pages ) );
		}
	}

}

Registry::registerBlock(
	__DIR__,
	PaginationNavButtonBlock::class,
	array(
		'metadata'             => '../button/block.json',
		'metadata_mixins'      => array( './block.json' ),
		'mixins_exact_replace' => array(
			'./block.json' => array(
				'supports.kubio.template',
			),
		),
	)
);
