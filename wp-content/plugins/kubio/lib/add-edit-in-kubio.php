<?php

function kubio_edit_post_row_style() {
	?>
	<style>

		span.kubio_edit_post * {
			fill: currentColor;
		}
		span.kubio-edit svg {
			width: 12px;
			height: 12px;
			margin-right: 2px;
			fill: currentColor;
		}

		span.kubio-edit {
			display: inline !important;
			vertical-align: middle;
		}
	</style>
	<?php
}
function kubio_add_edit_post_row_actions( $actions, $post ) {
	$supported_post_type = array( 'page', 'wp_template', 'wp_template_part' );
	if ( ! in_array( $post->post_type, $supported_post_type ) ) {
		return $actions;
	}

	$post_id = $post->ID;

	if ( in_array( $post->post_type, array( 'wp_template', 'wp_template_part' ) ) ) {
		$template = _kubio_build_template_result_from_post( $post );

		if ( is_wp_error( $template ) ) {
			return $actions;
		}

		$post_id = $template->id;
	}

	$edit_url = add_query_arg(
		array(
			'page'     => 'kubio',
			'postId'   => $post_id,
			'postType' => $post->post_type,
		),
		admin_url( 'admin.php' )
	);

	$status    = get_post_status( $post_id );
	$isTrashed = strpos( $post_id, '__trashed' );

	if ( $status === 'draft' || $status === 'auto-draft' ) {
		$edit_url = add_query_arg(
			array(
				'action'              => 'edit',
				'post'                => $post_id,
				'kubio-publish-draft' => 1,
			),
			admin_url( 'post.php' )
		);
	}

	if ( $status !== 'trash' && $isTrashed === false ) {
		$link = sprintf(
			'<a href="%s"><span style="display:none" class="kubio-edit">%s</span>%s</a>',
			esc_url( $edit_url ),
			wp_kses_post( KUBIO_LOGO_SVG ),
			esc_html__( 'Edit with Kubio', 'kubio' )
		);

		$actions = array_merge(
			array(
				'kubio_edit_post' => $link,
			),
			$actions
		);

		if ( ! has_action( 'admin_footer', 'kubio_edit_post_row_style' ) ) {
			add_action( 'admin_footer', 'kubio_edit_post_row_style' );
		}
	}

	return $actions;
}

function kubio_post_edit_add_button() {
	global $post;

	if ( ! $post ) {
		return;
	}

	if ( kubio_is_kubio_editor_page() ) {
		return;
	}

	$post_id = $post->ID;

	if ( in_array( $post->post_type, array( 'wp_template', 'wp_template_part' ) ) ) {
		$template = _kubio_build_template_result_from_post( $post );

		if ( is_wp_error( $template ) ) {
			esc_html_e( 'Unknown', 'kubio' );
		}

		$post_id = $template->id;
	}

	$edit_url = add_query_arg(
		array(
			'page'     => 'kubio',
			'postId'   => $post_id,
			'postType' => $post->post_type,
		),
		admin_url( 'admin.php' )
	);

	add_action(
		'admin_head',
		function() {
			?>
			<style>
				a.components-button.edit-in-kubio.is-primary svg {
					width: 1em;
					height: 1em;
					margin-right: 0.5em;
					fill: currentColor;
				}
			</style>
			<?php
		}
	);

	$label = base64_encode( wp_kses_post( KUBIO_LOGO_SVG ) . '<span>' . esc_html__( 'Edit with Kubio', 'kubio' ) . '</span>' );
	ob_start();
	?>

	<script>
		(function (_,data) {

			var url = data.url
			var label =data.label

			var createButton = _.throttle(() => {
				var toolbar = document.querySelector('.edit-post-header-toolbar');
				if (toolbar instanceof HTMLElement) {
					if (!toolbar.querySelector('.components-button.edit-in-kubio')) {
						var link = document.createElement('a');
						link.href = url;
						link.innerHTML = atob(label);
						link.setAttribute('class', 'components-button edit-in-kubio is-primary');
						toolbar.appendChild(link);
						link.addEventListener('click',function(event){
							var editorSelect = wp.data.select('core/editor');
							if(editorSelect){
								if(
								   'draft' === editorSelect.getEditedPostAttribute('status') ||
								   'auto-draft' === editorSelect.getEditedPostAttribute('status')
								){
									event.preventDefault();
									event.stopPropagation();
									wp.hooks.doAction('kubio.post-edit.open-draft-page',{target:event.currentTarget,url});
								}
							}
						});
						wp.hooks.doAction('kubio.post-edit.button-created',{target:link,url});
					}
				} else {
					createButton();
				}
			}, 100);

			wp.data.subscribe(() => createButton());
		})(lodash ,
		<?php
			echo wp_json_encode(
				array(
					'url'   => $edit_url,
					'label' => $label,
				)
			);
		?>
			);
	</script>
	<?php

	$script = str_replace( "\n\t\t", "\n", ob_get_clean() );

	wp_add_inline_script( 'wp-block-editor', strip_tags( $script ), 'after' );
}


add_filter( 'page_row_actions', 'kubio_add_edit_post_row_actions', 0, 2 );
add_filter( 'post_row_actions', 'kubio_add_edit_post_row_actions', 0, 2 );


add_action( 'enqueue_block_editor_assets', 'kubio_post_edit_add_button', 0, 2 );

function kubio_fronend_get_editor_url() {
	global $post;
	// Add site-editor link.
	$url = null;
	if ( ! is_admin() && current_user_can( 'edit_theme_options' ) ) {
		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$args = array();
		if ( is_singular() || is_single() ) {
			$args = array(
				'postId'   => $post->ID,
				'postType' => $post->post_type,
			);
		} else {

			$block_template = null;

			if ( is_front_page() && is_home() ) {
				$stylesheet = get_stylesheet();
				$query      = new WP_Query(
					array(
						'post_type'      => 'wp_template',
						'post_status'    => array( 'publish' ),
						'post_name__in'  => array( 'index', 'home' ),
						'posts_per_page' => 1,
						'no_found_rows'  => true,
						'tax_query'      => array(
							array(
								'taxonomy' => 'wp_theme',
								'field'    => 'name',
								'terms'    => array( $stylesheet ),
							),
						),
					)
				);

				$block_template = $query->have_posts() ? _kubio_build_template_result_from_post( $query->next_post() ) : null;
			}

			if ( $block_template ) {
				$args = array(
					'postId'   => urlencode( $block_template->id ),
					'postType' => 'wp_template',
				);
			} else {
				$args['pageURL'] = $current_url;

			}
		}

		$args = apply_filters( 'kubio/frontend/edit-in-kubio-args', $args );

		$url = add_query_arg(
			array_merge( array( 'page' => 'kubio' ), $args ),
			admin_url( 'admin.php' )
		);
	}

	return $url;
}

function kubio_frontend_adminbar_items( $wp_admin_bar ) {

	$url = kubio_fronend_get_editor_url();

	if ( $url ) {
		$wp_admin_bar->add_menu(
			array(
				'id'    => 'kubio-site-editor',
				'title' => sprintf( '<span class="kubio-admin-bar-menu-item">%s<span>%s</span></span>', wp_kses_post( KUBIO_LOGO_SVG ), __( 'Edit with Kubio', 'kubio' ) ),
				'href'  => $url,
			)
		);
	}

}

add_action( 'admin_bar_menu', 'kubio_frontend_adminbar_items', 80 );


function kubio_frontend_adminbar_items_style() {
	?>
		<style>
		.kubio-admin-bar-menu-item {
			display: flex;
			align-items:center;
		}

		.kubio-admin-bar-menu-item span {
			display: block;
			white-space: nowrap;
		}
		.kubio-admin-bar-menu-item svg {
			max-height: 14px;
			fill: #09f;
			flex-grow: 0;
			min-width: 20px;
			margin-right: 0.4em !important;
		}

		a:focus .kubio-admin-tbar-menu-item,
		a:hover .kubio-admin-tbar-menu-item {
			background: rgba(0, 153 ,255 , 0.6);
			color: #fff;

		}

		</style>
	<?php
}

add_action( 'wp_after_admin_bar_render', 'kubio_frontend_adminbar_items_style' );

