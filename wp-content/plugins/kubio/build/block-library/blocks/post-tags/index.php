<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;

class PostTagsBlock extends BlockBase {

	const CONTAINER   = 'container';
	const TAGS        = 'tags';
	const PLACEHOLDER = 'placeholder';

	public function mapPropsToElements() {
		$post_tags = get_the_tags( LodashBasic::get( $this->block_context, 'postId' ) );

		return array(
			self::TAGS        => array(
				'innerHTML' => $this->renderTagsContent( $post_tags ),
			),
			self::PLACEHOLDER => array(
				'innerHTML' => empty( $post_tags ) ? $this->getAttribute( 'placeholder' ) : '',
			),
		);

	}

	function renderTagsContent( $post_tags ) {
		if ( ! ( LodashBasic::get( $this->block_context, 'postId' ) ) ) {
			return '';
		}

		if ( ! empty( $post_tags ) ) {
			$output = '<div>';

			foreach ( $post_tags as $tag ) {
				$output .= '<a href="' . esc_url( get_tag_link( $tag->term_id ) ) . '">' . $tag->name . '</a>';
			}

			$output  = trim( $output );
			$output .= '</div>';

			return $output;
		}
	}
}

Registry::registerBlock( __DIR__, PostTagsBlock::class );
