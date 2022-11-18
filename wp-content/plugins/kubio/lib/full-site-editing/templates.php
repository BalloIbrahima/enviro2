<?php

/**
 * Registers block editor 'wp_theme' taxonomy.
 */
function kubio_register_wp_theme_taxonomy() {
	if ( taxonomy_exists( 'wp_theme' ) ) {
		return;
	}

	register_taxonomy(
		'wp_theme',
		array( 'wp_template', 'wp_template_part' ),
		array(
			'public'            => false,
			'hierarchical'      => false,
			'labels'            => array(
				'name'          => __( 'Themes', 'kubio' ),
				'singular_name' => __( 'Theme', 'kubio' ),
			),
			'query_var'         => false,
			'rewrite'           => false,
			'show_ui'           => false,
			'_builtin'          => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => false,
		)
	);
}

add_action( 'init', 'kubio_register_wp_theme_taxonomy', 100 );

/**
 * Registers block editor 'wp_template' post type.
 */
function kubio_register_template_post_type() {
	if ( post_type_exists( 'wp_template' ) ) {

		return;
	}

	$labels = array(
		'name'                  => __( 'Templates', 'kubio' ),
		'singular_name'         => __( 'Template', 'kubio' ),
		'menu_name'             => _x( 'Templates', 'Admin Menu text', 'kubio' ),
		'add_new'               => _x( 'Add New', 'Template', 'kubio' ),
		'add_new_item'          => __( 'Add New Template', 'kubio' ),
		'new_item'              => __( 'New Template', 'kubio' ),
		'edit_item'             => __( 'Edit Template', 'kubio' ),
		'view_item'             => __( 'View Template', 'kubio' ),
		'all_items'             => __( 'All Templates', 'kubio' ),
		'search_items'          => __( 'Search Templates', 'kubio' ),
		'parent_item_colon'     => __( 'Parent Template:', 'kubio' ),
		'not_found'             => __( 'No templates found.', 'kubio' ),
		'not_found_in_trash'    => __( 'No templates found in Trash.', 'kubio' ),
		'archives'              => __( 'Template archives', 'kubio' ),
		'insert_into_item'      => __( 'Insert into template', 'kubio' ),
		'uploaded_to_this_item' => __( 'Uploaded to this template', 'kubio' ),
		'filter_items_list'     => __( 'Filter templates list', 'kubio' ),
		'items_list_navigation' => __( 'Templates list navigation', 'kubio' ),
		'items_list'            => __( 'Templates list', 'kubio' ),
	);

	$args = array(
		'labels'                => $labels,
		'description'           => __( 'Templates to include in your theme.', 'kubio' ),
		'public'                => false,
		'has_archive'           => false,
		'show_ui'               => true,
		'show_in_menu'          => 'themes.php',
		'show_in_admin_bar'     => false,
		'show_in_rest'          => true,
		'rest_base'             => 'templates',
		'rest_controller_class' => KubioRestTemplateController::class,
		'capability_type'       => array( 'template', 'templates' ),
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
	register_post_type( 'wp_template', $args );
}

add_action( 'init', 'kubio_register_template_post_type', 100 );


/**
 * Fixes the label of the 'wp_template' admin menu entry.
 */
function kubio_fix_template_admin_menu_entry() {
	global $submenu;
	if ( ! isset( $submenu['themes.php'] ) ) {
		return;
	}
	$post_type = get_post_type_object( 'wp_template' );
	if ( ! $post_type ) {
		return;
	}
	foreach ( $submenu['themes.php'] as $key => $submenu_entry ) {
		if ( $post_type->labels->all_items === $submenu['themes.php'][ $key ][0] ) {
			$submenu['themes.php'][ $key ][0] = $post_type->labels->menu_name; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			break;
		}
	}
}

add_action( 'admin_menu', 'kubio_fix_template_admin_menu_entry', 100 );


/**
 * Sets a custom slug when creating auto-draft templates.
 * This is only needed for auto-drafts created by the regular WP editor.
 * If this page is to be removed, this won't be necessary.
 *
 * @param int $post_id Post ID.
 */
function kubio_set_unique_slug_on_create_template( $post_id ) {
	$post = get_post( $post_id );
	if ( 'auto-draft' !== $post->post_status ) {
		return;
	}

	if ( ! $post->post_name ) {
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => "template-{$post_id}",
			)
		);
	}

	$terms = get_the_terms( $post_id, 'wp_theme' );
	if ( ! $terms || ! count( $terms ) ) {
		wp_set_post_terms( $post_id, wp_get_theme()->get_stylesheet(), 'wp_theme' );
	}
}

add_action( 'save_post_wp_template', 'kubio_set_unique_slug_on_create_template' );


function kubio_get_template_types() {
	$template_types = kubio_get_template_type_slugs();
	$template_types = array_merge( $template_types, array( 'archive-product', 'single-product' ) );

	return $template_types;
}

function kubio_make_template_post_type_editable( $type ) {

	if ( $type === 'wp_template' ) {
		global $wp_post_types;
		$wp_post_types['wp_template']->show_ui               = true;
		$wp_post_types['wp_template']->show_in_menu          = 'themes.php';
		$wp_post_types['wp_template']->_edit_link            = 'post.php?post=%d';
		$wp_post_types['wp_template']->rest_controller_class = KubioRestTemplateController::class;
	}
}

add_action( 'registered_post_type', 'kubio_make_template_post_type_editable', 10, 1 );

function kubio_is_block_template() {
	global $_wp_current_template_content;

	return apply_filters( 'kubio_is_block_template', ! ! $_wp_current_template_content );
}

function kubio_theme_has_kubio_block_support() {
	return apply_filters( 'kubio/has_block_templates_support', false );
}

function kubio_theme_has_block_templates_support() {
	$folders_to_check = array( 'full-site-editing/block-templates/index.html', 'block-templates/index.html', 'templates/index.html' );

	$stylesheet_dir = get_stylesheet_directory();
	$parent_dir     = get_template_directory();

	foreach ( $folders_to_check as $folder ) {
		$candidate        = $stylesheet_dir . '/' . $folder;
		$candidate_parent = $parent_dir . '/' . $folder;
		if ( file_exists( $candidate ) ) {
			return true;
		}

		if ( $candidate_parent !== $candidate && file_exists( $candidate_parent ) ) {
			return true;
		}
	}

	return kubio_theme_has_kubio_block_support();

}

// skip unrelated templates to display
function kubio_skip_unrelated_templates( $post_templates, $theme, $post, $post_type ) {

	$exclude = array();

	switch ( $post_type ) {
		case 'page':
			$exclude[] = 'page';
			break;
	}

	$default_template_types = array_diff( array_keys( kubio_get_default_template_types() ), $exclude );

	foreach ( $post_templates as $slug => $name ) {
		if ( in_array( $slug, $default_template_types ) ) {
			unset( $post_templates[ $slug ] );
		}
	}

	return $post_templates;
}

add_filter(
	'theme_templates',
	'kubio_skip_unrelated_templates',
	100,
	4
);

// view and edit the slug in quick settings

function kubio_template_slug_editor( $column_name, $post_type ) {
	if ( $column_name === 'slug' && $post_type === 'wp_template' ) {
		?>
		<fieldset class="inline-edit-col-left">
			<div class="inline-edit-col">
				<div class="inline-edit-group wp-clearfix">
					<label class="inline-edit-status alignleft">
						<span class="title"><?php esc_html_e( 'Slug', 'kubio' ); ?></span>
						<span class="input-text-wrap"><input type="text" name="post_name" value="" /></span>
					</label>
				</div>
			</div>
		</fieldset>
		<?php
	}
}


function kubio_template_view_columns( $columns ) {
	if ( ! isset( $columns['slug'] ) ) {
		$columns['slug'] = __( 'Template slug', 'kubio' );
	}

	if ( ! isset( $columns['theme'] ) ) {
		$columns['theme'] = __( 'Theme', 'kubio' );
	}

	return $columns;
}

function kubio_template_view_columns_data( $column, $post_id ) {
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



add_action( 'manage_edit-wp_template_columns', 'kubio_template_view_columns', 10, 3 );
add_action( 'manage_wp_template_posts_custom_column', 'kubio_template_view_columns_data', 10, 2 );

add_action( 'quick_edit_custom_box', 'kubio_template_slug_editor', 10, 2 );


function kubio_edit_wp_template_filter_current_theme_templates_only( $query ) {

	if ( ! is_admin() ) {
		return;
	}

	$screen = get_current_screen();

	$stylesheet = get_stylesheet();

	if ( $screen && $screen->id === 'edit-wp_template' ) {
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
		add_action( 'pre_get_posts', 'kubio_edit_wp_template_filter_current_theme_templates_only' );

	}
);
