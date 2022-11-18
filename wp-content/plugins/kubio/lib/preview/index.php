<?php


use IlluminateAgnostic\Arr\Support\Arr;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param $uuid
 *
 * @return array|null
 */
function kubio_get_changeset_by_uuid( $uuid ) {
	if ( ! empty( $uuid ) ) {

		if ( $cached = wp_cache_get( "{$uuid}-data", 'kubio/preview' ) ) {
			return $cached;
		}

		$args = array(
			'name'        => $uuid,
			'post_type'   => 'kubio_changeset',
			'post_status' => array( 'publish', 'draft' ),
		);

		$posts = get_posts( $args );

		if ( ! empty( $posts ) ) {
			/** @var WP_Post $my_posts */
			$changeset = $posts[0];

			$content = json_decode( $changeset->post_content, true );
			wp_cache_set( "{$uuid}-data", $content, 'kubio/preview' );

			return $content;
		}
	}

	return null;
}


function kubio_get_current_changeset_data( $path = '', $fallback = null ) {
	static $chanset_data;

	if ( ! $chanset_data ) {
		$uuid         = sanitize_text_field( Arr::get( $_REQUEST, 'kubio-preview', false ) );
		$chanset_data = kubio_get_changeset_by_uuid( $uuid );
	}

	if ( empty( $path ) ) {
		return $chanset_data;
	}

	return Arr::get( $chanset_data, $path, $fallback );

}

function kubio_prepare_changest_post() {

	if ( $cached = wp_cache_get( 'uuid', 'kubio/preview' ) ) {
		return $cached;
	}

	$post_type = 'kubio_changeset';
	$uuid      = wp_generate_uuid4();

	// make a bit of a cleanup first
	$allposts = get_posts(
		array(
			'post_type'   => $post_type,
			'numberposts' => - 1,
			'date_query'  => array(
				'column' => 'post_date',
				'before' => '- 1 day',
			),
		)
	);

	foreach ( $allposts as $eachpost ) {
		wp_delete_post( $eachpost->ID, true );
	}

	wp_insert_post(
		array(
			'post_content' => '', // remove the preery prints
			'post_status'  => 'publish',
			'post_type'    => $post_type,
			'post_name'    => $uuid,
			'post_title'   => $uuid,
			'guid'         => uniqid( "$post_type-" . time() . '-' ),
		),
		false
	);

	wp_cache_set( 'uuid', $uuid, 'kubio/preview' );
	wp_cache_set( "{$uuid}-data", array(), 'kubio/preview' );

	return $uuid;
}

function kubio_register_preivew_data_post() {

	$args = array(
		'label'            => __( 'Kubio Preview', 'kubio' ),
		'public'           => false,
		'show_ui'          => false,
		'show_in_rest'     => true,
		'rest_base'        => 'kubio/preview-changeset',
		'hierarchical'     => false,
		'rewrite'          => false,
		'query_var'        => false,
		'can_export'       => false,
		'delete_with_user' => false,
		'capabilities'     => array(
			'read'                   => 'edit_theme_options',
			'create_posts'           => 'edit_theme_options',
			'edit_posts'             => 'edit_theme_options',
			'edit_published_posts'   => 'edit_theme_options',
			'delete_published_posts' => 'edit_theme_options',
			'edit_others_posts'      => 'edit_theme_options',
			'delete_others_posts'    => 'edit_theme_options',
		),
		'map_meta_cap'     => true,
		'supports'         => array(
			'title',
			'author',
			'editor',
		),
	);
	register_post_type( 'kubio_changeset', $args );
}


add_action( 'init', 'kubio_register_preivew_data_post' );

add_filter(
	'kubio/block_editor_settings',
	function ( $settings ) {
		$settings['changeset_uuid'] = kubio_prepare_changest_post();

		return $settings;
	}
);


// delete changeset when browser refreshes
add_action(
	'wp_ajax_kubio-delete-changeset',
	function () {
		$uuid = sanitize_text_field( Arr::get( $_REQUEST, 'uuid', false ) );

		$changeset = kubio_get_changeset_by_uuid( $uuid );

		if ( $changeset && intval( $changeset->post_author ) === get_current_user_id() ) {
			wp_delete_post( $changeset->ID, true );
		}

	}
);

function kubio_get_template_part_id_by_slug( $slug, $theme ) {
	$stylesheet          = get_stylesheet();
	$template_part_query = new WP_Query(
		array(
			'post_type'      => 'wp_template_part',
			'post_status'    => array( 'publish' ),
			'post_name__in'  => array( $slug ),
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

	return $template_part_query->have_posts() ? $template_part_query->next_post()->ID : null;
}

function kubio_get_template_part_block_id( $block ) {
	$id    = Arr::get( $block, 'attrs.postId', 0 );
	$slug  = Arr::get( $block, 'attrs.slug', '' );
	$theme = Arr::get( $block, 'attrs.theme', '' );

	if ( $id ) {
		return $id;
	}

	return kubio_get_template_part_id_by_slug( $slug, $theme );
}


require_once __DIR__ . '/autosaves-preview.php';
require_once __DIR__ . '/menu-preview.php';
require_once __DIR__ . '/site-data-preview.php';


function kubio_is_page_preview() {
	return Arr::has( $_REQUEST, 'kubio-preview', false );
}

add_action(
	'init',
	function () {
		$uuid = sanitize_text_field( Arr::get( $_REQUEST, 'kubio-preview', false ) );

		if ( ! empty( $uuid ) ) {

			$changeset_data = kubio_get_current_changeset_data();

			if ( empty( $changeset_data ) ) {
				wp_die( esc_html__( 'Current preview state is unavailable', 'kubio' ) );
			}

			show_admin_bar( false );
			kubio_handle_autosaved_posts_and_templates();

			$custom_entities = kubio_get_current_changeset_data( 'customEntities', array() );
			$custom_data     = (array) kubio_get_current_changeset_data( 'customData', array() );

			foreach ( $custom_entities as $item ) {
				do_action( 'kubio/preview/handle_custom_entities', $item );
			}

			do_action( 'kubio/preview/handle_custom_data', $custom_data );

			add_action( 'wp_enqueue_scripts', 'kubio_enqueue_preview_url_maintainer' );
		} else {
			if ( isset( $_REQUEST['kubio-random'] ) && ! ( is_admin() || is_customize_preview() ) ) {
				add_action( 'wp_enqueue_scripts', 'kubio_enqueue_preview_url_maintainer' );
			}
		}

	}
);


function kubio_enqueue_preview_url_maintainer() {
	if ( is_user_logged_in() ) {
		wp_enqueue_script( 'kubio-maintain-preview-url', kubio_url( '/static/maintain-preview-url.js' ), array( 'wp-url' ), KUBIO_VERSION, true );
		wp_add_inline_script( 'kubio-maintain-preview-url', 'kubioMaintainPreviewURLBase="' . site_url() . '"', 'before' );
	}
}


