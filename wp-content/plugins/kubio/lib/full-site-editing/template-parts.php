<?php


/**
 * Registers block editor 'wp_template_part' post type.
 */
function kubio_register_template_part_post_type() {
	if ( post_type_exists( 'wp_template_part' ) ) {
		return;
	}

	$labels = array(
		'name'                  => __( 'Template Parts', 'kubio' ),
		'singular_name'         => __( 'Template Part', 'kubio' ),
		'menu_name'             => _x( 'Template Parts', 'Admin Menu text', 'kubio' ),
		'add_new'               => _x( 'Add New', 'Template Part', 'kubio' ),
		'add_new_item'          => __( 'Add New Template Part', 'kubio' ),
		'new_item'              => __( 'New Template Part', 'kubio' ),
		'edit_item'             => __( 'Edit Template Part', 'kubio' ),
		'view_item'             => __( 'View Template Part', 'kubio' ),
		'all_items'             => __( 'All Template Parts', 'kubio' ),
		'search_items'          => __( 'Search Template Parts', 'kubio' ),
		'parent_item_colon'     => __( 'Parent Template Part:', 'kubio' ),
		'not_found'             => __( 'No template parts found.', 'kubio' ),
		'not_found_in_trash'    => __( 'No template parts found in Trash.', 'kubio' ),
		'archives'              => __( 'Template part archives', 'kubio' ),
		'insert_into_item'      => __( 'Insert into template part', 'kubio' ),
		'uploaded_to_this_item' => __( 'Uploaded to this template part', 'kubio' ),
		'filter_items_list'     => __( 'Filter template parts list', 'kubio' ),
		'items_list_navigation' => __( 'Template parts list navigation', 'kubio' ),
		'items_list'            => __( 'Template parts list', 'kubio' ),
	);

	$args = array(
		'labels'                => $labels,
		'description'           => __( 'Template parts to include in your templates.', 'kubio' ),
		'public'                => false,
		'has_archive'           => false,
		'show_ui'               => true,
		'show_in_menu'          => 'themes.php',
		'show_in_admin_bar'     => false,
		'show_in_rest'          => true,
		'rest_base'             => 'template-parts',
		'rest_controller_class' => KubioRestTemplatePartController::class,
		'map_meta_cap'          => true,
		'supports'              => array(
			'title',
			'slug',
			'excerpt',
			'editor',
			'revisions',
		),
		'_edit_link'            => 'post.php?post=%d',
	);

	// the posts registration is check at the beggining of the function
	// phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug
	register_post_type( 'wp_template_part', $args );
}

add_action( 'init', 'kubio_register_template_part_post_type', 100 );


function kubio_set_unique_slug_on_create_template_part( $post_id ) {
	$post = get_post( $post_id );
	if ( 'auto-draft' !== $post->post_status ) {
		return;
	}

	if ( ! $post->post_name ) {
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => "template-part-{$post_id}",
			)
		);
	}

	$terms = get_the_terms( $post_id, 'wp_theme' );
	if ( ! $terms || ! count( $terms ) ) {
		wp_set_post_terms( $post_id, wp_get_theme()->get_stylesheet(), 'wp_theme' );
	}
}


add_action( 'save_post_wp_template_part', 'kubio_set_unique_slug_on_create_template_part' );


/**
 * Fixes the label of the 'wp_template_part' admin menu entry.
 */
function kubio_fix_template_part_admin_menu_entry() {

	global $submenu;
	if ( ! isset( $submenu['themes.php'] ) ) {
		return;
	}
	$post_type = get_post_type_object( 'wp_template_part' );
	if ( ! $post_type ) {
		return;
	}
	foreach ( $submenu['themes.php'] as $key => $submenu_entry ) {
		if ( $post_type->labels->all_items === $submenu['themes.php'][ $key ][0] ) {
			$submenu['themes.php'][ $key ][0] = $post_type->labels->menu_name; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}
}

add_action( 'admin_menu', 'kubio_fix_template_part_admin_menu_entry' );


// view and edit the slug in quick settings


function kubio_template_part_slug_editor( $column_name, $post_type ) {
	if ( $column_name === 'slug' && $post_type === 'wp_template_part' ) {
		?>
		<fieldset class="inline-edit-col-left">
			<div class="inline-edit-col">
				<div class="inline-edit-group wp-clearfix">
					<label class="inline-edit-status alignleft">
						<span class="title"><?php esc_html_e( 'Slug', 'kubio' ); ?></span>
						<span class="input-text-wrap"><input type="text" name="post_name" value=""/></span>
					</label>
				</div>
			</div>
		</fieldset>
		<?php
	}
}


function kubio_template_part_view_columns( $columns ) {

	if ( ! isset( $columns['slug'] ) ) {
		$columns['slug'] = __( 'Template part slug', 'kubio' );
	}

	if ( ! isset( $columns['theme'] ) ) {
		$columns['theme'] = __( 'Theme', 'kubio' );
	}

	return $columns;
}

function kubio_template_part_view_columns_data( $column, $post_id ) {
	$content = '';
	if ( $column === 'slug' ) {
		$content = get_post( $post_id )->post_name;
	}

	if ( $column === 'theme' ) {
		$template = _kubio_build_template_result_from_post( get_post( $post_id ) );

		if ( is_wp_error( $template ) ) {
			esc_html_e( 'Unknown', 'kubio' );
			return;
		} else {
			$theme_slug = $template->theme;

		}

		$theme = wp_get_theme( $theme_slug );

		if ( is_wp_error( $theme->errors ) ) {
			$content = $theme_slug;
		} else {
			$content = $theme->get( 'Name' );
		}
	}

	echo esc_html( $content );
}

add_action( 'manage_edit-wp_template_part_columns', 'kubio_template_part_view_columns', 10, 3 );
add_action( 'manage_wp_template_part_posts_custom_column', 'kubio_template_part_view_columns_data', 10, 2 );

add_action( 'quick_edit_custom_box', 'kubio_template_part_slug_editor', 10, 2 );


function kubio_render_block_template_part( $block, $slug, $style_ref = null ) {
	return render_block(
		array(
			'blockName'    => $block,
			'attrs'        =>
					array(
						'slug'  => $slug,
						'theme' => get_stylesheet(),
						'kubio' => array( 'styleRef' => $style_ref ? $style_ref : "kubio-{$slug}" ),

					),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
}



function kubio_make_template_part_post_type_editable( $type ) {

	if ( $type === 'wp_template_part' ) {
		global $wp_post_types;
		$wp_post_types[ $type ]->show_ui               = true;
		$wp_post_types[ $type ]->show_in_menu          = 'themes.php';
		$wp_post_types[ $type ]->_edit_link            = 'post.php?post=%d';
		$wp_post_types[ $type ]->rest_controller_class = KubioRestTemplatePartController::class;
	}
}

add_action( 'registered_post_type', 'kubio_make_template_part_post_type_editable', 10, 1 );


function kubio_edit_wp_template_part_filter_current_theme_templates_only( $query ) {

	if ( ! is_admin() ) {
		return;
	}

	$screen = get_current_screen();

	$stylesheet = get_stylesheet();

	if ( $screen && $screen->id === 'edit-wp_template_part' ) {
		$query->query_vars['tax_query'] = array(
			array(
				'taxonomy' => 'wp_theme',
				'field'    => 'name',
				'terms'    => array( $stylesheet ),
			),
		);
	}
}

add_action(
	'current_screen',
	function() {
		add_action( 'pre_get_posts', 'kubio_edit_wp_template_part_filter_current_theme_templates_only' );

	}
);
