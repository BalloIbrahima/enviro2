<?php

class Kubio_Walker_Comment extends Walker_Comment {

	private function prinAvatar( $comment, $depth, $args ) {
		if ( 0 != $args['avatar_size'] ) {
			echo get_avatar( $comment, $args['avatar_size'] );
		}
	}

	private function printAuthor( $comment, $depth, $args ) {
		$comment_author = get_comment_author_link( $comment );

		if ( '0' == $comment->comment_approved ) {
			$comment_author = get_comment_author( $comment );
		}

		printf( '<b class="fn">%s</b>', $comment_author );

	}

	private function printMetadata( $comment, $depth, $args ) {
		printf(
			'<a href="%s"><time datetime="%s">%s</time></a>',
			esc_url( get_comment_link( $comment, $args ) ),
			get_comment_time( 'c' ),
			sprintf(
				/* translators: 1: Comment date, 2: Comment time. */
				__( '%1$s at %2$s' ),
				get_comment_date( '', $comment ),
				get_comment_time()
			)
		);

		edit_comment_link( __( 'Edit' ), ' <span class="edit-link">', '</span>' );

		$commenter = wp_get_current_commenter();
		if ( $commenter['comment_author_email'] ) {
			$moderation_note = __( 'Your comment is awaiting moderation.' );
		} else {
			$moderation_note = __(
				'Your comment is awaiting moderation. This is a preview; your comment will be visible after it has been approved.'
			);
		}

		if ( '0' == $comment->comment_approved ) : ?>
			<em class="comment-awaiting-moderation"><?php echo $moderation_note; ?></em>
			<?php
			endif;
	}

	/**
	 * Outputs a comment in the HTML5 format.
	 *
	 * @since 3.6.0
	 *
	 * @see wp_list_comments()
	 *
	 * @param WP_Comment $comment Comment to display.
	 * @param int        $depth   Depth of the current comment.
	 * @param array      $args    An array of arguments.
	 */
	protected function html5_comment( $comment, $depth, $args ) {
		$tag = 'div' === $args['style'] ? 'div' : 'li';

		$commenter          = wp_get_current_commenter();
		$show_pending_links = ! empty( $commenter['comment_author'] );

		?>
		<<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( $this->has_children ? 'parent' : '', $comment ); ?>>
			<article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
				<footer class="comment-meta">
					<div class="comment-author vcard">
						<?php $this->prinAvatar( $comment, $depth, $args ); ?>
						<div>
						<div>
							<?php $this->printAuthor( $comment, $depth, $args ); ?>
						</div>
						<div class="comment-metadata ">
							<?php $this->printMetadata( $comment, $depth, $args ); ?>
						</div>
						</div>
					</div>
				</footer><!-- .comment-meta -->

				<div class="comment-content">
					<?php comment_text(); ?>
				</div><!-- .comment-content -->

				<?php
				if ( '1' == $comment->comment_approved || $show_pending_links ) {
					comment_reply_link(
						array_merge(
							$args,
							array(
								'add_below' => 'div-comment',
								'depth'     => $depth,
								'max_depth' => $args['max_depth'],
								'before'    => '<div class="reply">',
								'after'     => '</div>',
							)
						)
					);
				}
				?>
			</article><!-- .comment-body -->
		<?php
	}
}
