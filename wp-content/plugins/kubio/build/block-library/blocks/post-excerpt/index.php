<?php

namespace Kubio\Blocks;
use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Utils;

class PostExcerptBlock extends BlockBase {
	const TEXT = 'text';

	/**
	 * List of Kubio blocks which should be allowed in excerpts.
	 */
	const ALLOWED_EXCERPT_BLOCKS = array( 'kubio/text', 'kubio/heading' );

	/**
	 * Retrieves the maximum number of words declared by the user.
	 *
	 * @return numeric
	 */
	public function getExcerptWordCount() {
		return $this->getAttribute( 'wordCount', 16 );
	}

	/**
	 * This method will be passed to the `excerpt_allowed_blocks` filter and will add our ALLOWED_EXCERPT_BLOCKS
	 *
	 * @param $allowed_blocks array
	 *
	 * @return array
	 */
	public function excerpt_allowed_blocks( $allowed_blocks ) {
		array_push( $allowed_blocks, ...self::ALLOWED_EXCERPT_BLOCKS );
		return $allowed_blocks;
	}

	public function mapPropsToElements() {
		add_filter( 'excerpt_allowed_blocks', array( $this, 'excerpt_allowed_blocks' ) );

		$post_id   = Arr::get( $this->block_context, 'postId', 0 );
		$post_type = Arr::get( $this->block_context, 'postType', 0 );

		//workaround for http://mantis.extendstudio.net/view.php?id=39609
		if ( $post_type === 'page' && is_single( $post_id ) ) {
			$content = Utils::getFrontendPlaceHolder(
				sprintf(
					'%s<br/><div class="kubio-frontent-placeholder--small">%s</div>',
					__( 'Pages do not support excerpt by default.', 'kubio' ),
					__( 'Edit this page and remove the current block or use it in a posts list.', 'kubio' )
				)
			);
		} else {
			$content = ( get_the_excerpt( $post_id ) );

			$moreWords  = false;
			$word_count = $this->getAttribute( 'wordCount', 16 );
			$content    = explode( ' ', $content );
			if ( count( $content ) > $word_count ) {
				$moreWords = true;
			}
			$content = array_slice( $content, 0, $word_count - 1 );
			$content = implode( ' ', $content );
			if ( $moreWords ) {
				$content .= '[&hellip;]';
			}
		}

		$tag_name = 'p';
		return array(
			self::TEXT => array(
				'innerHTML' => $content,
				'tag'       => $tag_name,
			),
		);
	}
}


Registry::registerBlock(
	__DIR__,
	PostExcerptBlock::class,
	array(
		'metadata'        => '../text/block.json',
		'metadata_mixins' => array( './block.json' ),
	)
);

