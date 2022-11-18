<?php

namespace Kubio\Blocks;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\StyleManager\DynamicStyles;

class PaginationNumbersBlock extends BlockBase {
	const OUTER              = 'outer';
	const TYPOGRAPHY_HOLDERS = 'typographyHolders';


	public function mapDynamicStyleToElements() {
		$dynamicStyles = array();

		$typographyHoldersByMedia = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => $this->getDefaultElement(),
			)
		);

		$dynamicStyles[ self::TYPOGRAPHY_HOLDERS ] = DynamicStyles::typographyHolders( $typographyHoldersByMedia );

		return $dynamicStyles;
	}

	public function mapPropsToElements() {
		return array(
			self::OUTER => array(
				'innerHTML' => $this->getPageNumbers(),
			),
		);
	}

	private function getPageNumbers() {

		$pages_data = $this->getPagesData();

		return paginate_links(
			array(
				'prev_next' => false,
				'total'     => $pages_data['total'],
				'current'   => $pages_data['current'],
				'show_all'  => $this->getAttribute( 'show_all', false ),
			)
		);
	}

	private function getPagesData() {
		global $wp_query;
		$current = 1;
		$total   = 1;

		if ( Arr::get( $this->block_context, 'useMainQuery', false ) ) {
			$current = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;
			$total   = isset( $wp_query->max_num_pages ) ? $wp_query->max_num_pages : 1;
		} else {

			$query_id = Arr::get( $this->block_context, 'queryId', false );
			$page_key = $query_id ? 'query-' . $query_id . '-page' : 'query-page';
			$current  = empty( $_GET[ $page_key ] ) ? 1 : filter_var( $_GET[ $page_key ], FILTER_VALIDATE_INT );
			$total    = Arr::get( $this->block_context, 'query.pages', 1 );
		}

		return array(
			'total'   => $total,
			'current' => $current,
		);
	}

	public function serverSideRender( $wp_block ) {
		return paginate_links(
			array(
				'prev_next' => false,
				'total'     => 12,
				'current'   => 2,
				'show_all'  => $this->getAttribute( 'show_all', false ),
			)
		);
	}

	public function render( $wp_block ) {

		if ( ! $this->shouldRender() && ! $this->isSandboxRender() ) {
			return '';
		}

		return parent::render( $wp_block );
	}

	private function shouldRender() {

		if ( Arr::get( $this->block_context, 'useMainQuery', false ) ) {
			return $this->shouldRenderMainQueryButton();
		}

		return $this->shouldRenderCustomQueryButton();
	}

	private function shouldRenderMainQueryButton() {
		global $wp_query;

		$total = isset( $wp_query->max_num_pages ) ? $wp_query->max_num_pages : 1;

		return $total > 1;
	}

	private function shouldRenderCustomQueryButton() {
		$pages = Arr::get( $this->block_context, 'query.pages', 1 );

		return ( $pages > 1 );
	}

}

Registry::registerBlock( __DIR__, PaginationNumbersBlock::class );
