<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;

class PostCategoriesBlock extends BlockBase {

	const CONTAINER   = 'container';
	const TAGS        = 'tags';
	const PLACEHOLDER = 'placeholder';
	const SEPARATOR   = 'separator';

	public function mapPropsToElements() {
		$post_tags = get_the_category( LodashBasic::get( $this->block_context, 'postId' ) );

		return array(
			self::TAGS        => array(
				'innerHTML' => $this->renderCategoriesContent( $post_tags ),
			),
			self::PLACEHOLDER => array(
				'shouldRender' => empty( $post_tags ) ? true : false,
				'innerHTML'    => $this->getAttribute( 'placeholder' ),
			),
		);

	}

	function renderCategoriesContent( $post_tags ) {
		if ( ! ( LodashBasic::get( $this->block_context, 'postId' ) ) ) {
			return '';
		}

		if ( ! empty( $post_tags ) ) {
			$output = '';

			$i     = 0;
			$count = count( $post_tags );
			foreach ( $post_tags as $tag ) {
				$output .= '<a href="' . esc_url( get_category_link( $tag->term_id ) ) . '">' . $tag->name . '</a>';
				if ( $i < $count - 1 ) {
					$output .= '<span class="separator">' . $this->getAttribute( 'separator' ) . '</span>';
				}
				$i++;
			}

			$output = trim( $output );

			return $output;
		}
	}
}

Registry::registerBlock( __DIR__, PostCategoriesBlock::class );
