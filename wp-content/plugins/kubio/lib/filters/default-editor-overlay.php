<?php

use IlluminateAgnostic\Arr\Support\Arr;

add_filter( 'the_editor', 'kubio_maintainable_page_editor' );
add_filter( 'use_block_editor_for_post', 'kubio_choose_editor', 100, 2 );
add_action( 'edit_form_after_title', 'kubio_maintainable_page_editor_blog', 100 );

function kubio_choose_editor( $use_block_editor, $post ) {
	if ( $use_block_editor && $post && $post->post_type === 'page' && ! in_array( $post->post_status, array( 'draft', 'auto-draft' ) ) ) {
		if ( Arr::get( $_REQUEST, 'kubio-edit' ) !== 'default-editor' ) {
			return false;
		}
	}

	return $use_block_editor;
}

function kubio_maintainable_page_editor_blog() {
	global $post;
	if ( ! $post ) {
		return;
	}
	if ( (int) get_option( 'page_for_posts' ) === $post->ID && empty( $post->post_content ) ) {
		kubio_maintainable_page_editor( '', true );
	}
}

function kubio_maintainable_page_editor( $editor = '', $skip_default_editor = false ) {
	global $post;

	$is_blog_page = (int) get_option( 'page_for_posts' ) === $post->ID && empty( $post->post_content );

	$edit_with_default_url = add_query_arg(
		array(
			'post'       => $post->ID,
			'action'     => 'edit',
			'kubio-edit' => 'default-editor',
		),
		admin_url( 'post.php' )
	);

	if ( empty( $post ) ) {
		return $editor;
	}

	if ( ! property_exists( $post, 'post_type' ) || ( property_exists( $post, 'post_type' ) && 'page' !== $post->post_type ) ) {
		return $editor;
	}
	?>
	<style>
		div#wp-content-editor-container {
			position: relative;
		}

		div#wp-content-editor-container textarea {
			max-height: 500px;
			height: 500px;
		}

		div#wp-content-editor-tools {
			display: none;
		}

		table#post-status-info {
			display: none;
		}

		.kubio-classic-editor-overlay {
			position: absolute;
			top: 0;
			right: 0;
			bottom: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: #ececec;
			border: 1px solid #cacaca;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.kubio-classic-editor-overlay.kubio-blog-page {
			position: relative;
			min-height: 300px;
		}

		div#wp-content-wrap {
			margin-top: 1rem;
			margin-bottom: 1rem;
			min-height: 300px;
		}

		.kubio-classic-editor-overlay .middle-align {
			text-align: center;
		}


		.kubio-classic-editor-overlay i.dashicons.dashicons-edit {
			font-size: 1.8em;
			width: auto;
			margin-right: 2px;
			vertical-align: middle;
			height: 30px;
		}

		.kubio-classic-editor-overlay .button.button-link,
		.kubio-classic-editor-overlay .button.button-link:hover,
		.kubio-classic-editor-overlay .button.button-link:focus {
			background: transparent;
		}

		.kubio-classic-editor-overlay svg {
			width: 16px;
			height: 16px;
			display: inline-block;
			fill: #ffffff;
		}

		.kubio-classic-editor-overlay .button-primary {
			display: inline-flex;
			align-items: center;
			gap: 6px;
		}

		.kubio-edit-with-default-wrapper {
			margin-top: 10px;
		}
	</style>

	<script>
		window.cp_open_page_in_kubio = function(postId, postType) {
			var link = '<?php echo admin_url( 'admin.php?page=kubio' ); ?>&postId=' + postId + '&postType=' + postType;
			return link;
		}
	</script>

	<div class="kubio-classic-editor-overlay <?php echo ( $is_blog_page ? 'kubio-blog-page' : '' ); ?>">
		<div class="middle-align">
			<div>
				<button onclick="window.location.replace(cp_open_page_in_kubio('<?php echo $post->ID; ?>', '<?php echo get_post_type( $post->ID ); ?>')); return false;" class="button button-hero button-primary kubio-overlay-edit-with-kubio">
					<?php echo KUBIO_LOGO_SVG; ?>
					<?php _e( 'Edit with Kubio', 'kubio' ); ?>
				</button>
			</div>
			<?php if ( ! $skip_default_editor ) : ?>
				<div class="kubio-edit-with-default-wrapper">
					<a class="button button-link" href="<?php echo esc_url( $edit_with_default_url ); ?>"><?php _e( 'Edit with default editor', 'kubio' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
