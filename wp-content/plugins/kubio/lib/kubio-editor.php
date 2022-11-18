<?php

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Utils;
use Kubio\Flags;
use Kubio\Core\LodashBasic;

function kubio_is_kubio_editor_page() {
	 global $pagenow;

	if ( substr( $pagenow, 0, -4 ) === 'admin' ) {
		$page = Arr::get( $_REQUEST, 'page', false );

		if ( $page === 'kubio' ) {
			return true;
		}
	}
}

function kubio_edit_site_get_first_post_id( $loaded_id ) {
	if ( ! $loaded_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		if ( ! empty( $ids ) ) {
			$loaded_id = $ids[0];
		}
	}

	if ( ! $loaded_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $ids ) ) {
			$loaded_id = $ids[0];
		}
	}
	return $loaded_id;
}
//this should also treat the template parts case. But at the moment you can't preview template parts but if will in the future
//the logic should work the same
function kubio_edit_site_get_template_id( $loaded_id, $postType ) {

	$parts = explode( '//', $loaded_id );
	if ( count( $parts ) !== 2 ) {
		return null;
	}
	$templateName = $parts[1];
	$query        = array(
		'name'           => $templateName,
		'post_type'      => $postType,
		'fields'         => 'ids',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
	);
	$ids          = get_posts( $query );
	$resultPostId = null;
	if ( ! empty( $ids ) ) {
		$resultPostId = $ids[0];
	}
	return $resultPostId;
}
function kubio_edit_site_get_edited_entity() {
	$pag_on_front = intval( get_option( 'page_on_front' ) );
	$loaded_id    = isset( $_GET['postId'] ) ? intval( $_GET['postId'] ) : $pag_on_front;
	$post_type    = isset( $_GET['postType'] ) ? sanitize_text_field( $_GET['postType'] ) : null;

	if ( ! post_type_exists( $post_type ) || ! is_numeric( $loaded_id ) ) {
		return array();
	}

	//when you have latest post set as your home page and you enter the builder without the post id the loaded_id will be 0
	//in this case we'll load the index template
	if ( $loaded_id === 0 ) {
		$themeName       = get_stylesheet();
		$indexTemplateId = strtr( ':theme//index', array( ':theme' => $themeName ) );
		$loaded_id       = $indexTemplateId;
		$post_type       = 'wp_template';
	}

	$templatePostId = null;
	if ( ! ! $loaded_id && ! is_numeric( $loaded_id ) ) {
		$templatePostId = $loaded_id;
		$loaded_id      = kubio_edit_site_get_template_id( $loaded_id, $post_type );
	}
	if ( ! $loaded_id || ! is_numeric( $loaded_id ) ) {
		$loaded_id = kubio_edit_site_get_first_post_id( $loaded_id );
	}

	if ( $loaded_id ) {
		$entity = get_post( $loaded_id );

		return array(
			'path'    => get_permalink( $loaded_id ),
			'context' => array(
				'postType'  => $entity ? $entity->post_type : '',
				'postId'    => $templatePostId ? $templatePostId : $loaded_id,
				'query'     => '',
				'postTitle' => $entity ? $entity->post_title : '',
				'title'     => $entity ? $entity->post_title : '',
			),
			'slug'    => $entity ? $entity->post_name : '',
			'label'   => $entity ? $entity->post_title : '',
		);
	}

	return array();
}


function kubio_get_patterns_categories() {
	return array(
		array(
			'label' => __( 'Hero accent', 'kubio' ),
			'name'  => 'kubio-content/overlappable',
		),

		array(
			'label' => __( 'About', 'kubio' ),
			'name'  => 'kubio-content/about',
		),

		array(
			'label' => __( 'Features', 'kubio' ),
			'name'  => 'kubio-content/features',
		),

		array(
			'label' => __( 'Content', 'kubio' ),
			'name'  => 'kubio-content/content',
		),

		array(
			'label' => __( 'Call to action', 'kubio' ),
			'name'  => 'kubio-content/cta',
		),
		array(
			'label' => __( 'Blog', 'kubio' ),
			'name'  => 'kubio-content/blog',
		),

		array(
			'label' => __( 'Counters', 'kubio' ),
			'name'  => 'kubio-content/counters',
		),

		array(
			'label' => __( 'Portfolio', 'kubio' ),
			'name'  => 'kubio-content/portfolio',
		),

		array(
			'label' => __( 'Photo gallery', 'kubio' ),
			'name'  => 'kubio-content/photo gallery',
		),

		array(
			'label' => __( 'Testimonials', 'kubio' ),
			'name'  => 'kubio-content/testimonials',
		),

		array(
			'label' => __( 'Clients', 'kubio' ),
			'name'  => 'kubio-content/clients',
		),

		array(
			'label' => __( 'Team', 'kubio' ),
			'name'  => 'kubio-content/team',
		),

		array(
			'label' => __( 'Contact', 'kubio' ),
			'name'  => 'kubio-content/contact',
		),

		array(
			'label' => __( 'F.A.Q.', 'kubio' ),
			'name'  => 'kubio-content/f.a.q.',
		),

		array(
			'label' => __( 'Pricing', 'kubio' ),
			'name'  => 'kubio-content/pricing',
		),
		array(
			'label' => __( 'Inner Headers', 'kubio' ),
			'name'  => 'kubio-header/inner headers',
		),

		array(
			'label' => __( 'Headers', 'kubio' ),
			'name'  => 'kubio-header/headers',
		),

		array(
			'label' => __( 'Footers', 'kubio' ),
			'name'  => 'kubio-footer/footers',
		),
	);
}

function kubio_get_editor_style( $get_css = false, $skiped_handlers = array() ) {
	$style_handles = array(
		'wp-edit-blocks',
		'wp-block-editor',
		'wp-block-library',
		'wp-block-library-theme',
		'kubio-editor',
		'kubio-controls',
		'kubio-format-library',
		'kubio-pro',
		'kubio-block-library-editor',
		'kubio-third-party-blocks',
	);

	foreach ( $skiped_handlers as $index => $handler ) {
		$handler                   = preg_replace( '#(.*?)-inline-css$#', '$1', $handler );
		$handler                   = preg_replace( '#(.*?)-css$#', '$1', $handler );
		$skiped_handlers[ $index ] = $handler;
	}

	$skiped_handlers = array_unique( $skiped_handlers );
	$style_handles   = array_diff( $style_handles, $skiped_handlers );

	$block_registry = WP_Block_Type_Registry::get_instance();

	foreach ( $block_registry->get_all_registered() as $block_type ) {
		if ( ! empty( $block_type->style ) ) {
			$style_handles[] = $block_type->style;
		}

		if ( ! empty( $block_type->editor_style ) ) {
			$style_handles[] = $block_type->editor_style;
		}
	}

	$style_handles = Arr::flatten( $style_handles );
	$style_handles = array_values( array_unique( $style_handles ) );
	$done          = wp_styles()->done;

	ob_start();
	wp_styles()->done = $skiped_handlers;
	wp_styles()->do_items( $style_handles );
	wp_styles()->done = $done;

	$style = ob_get_clean();

	if ( $get_css ) {
		$style = preg_replace( "#<link(.*)href='(.*?)'(.*)/>#", '@import url($2);', $style );
		$style = preg_replace( '#<style(.*)>#', '', $style );
		$style = preg_replace( '#</style>#', '', $style );
	}

	return $style;
}

function kubio_get_editor_scripts( $skiped_handlers = array() ) {
	$script_handles = kubio_get_frontend_scripts();

	foreach ( $skiped_handlers as $index => $handler ) {
		$handler                   = preg_replace( '#(.*?)-js-after#', '$1', $handler );
		$handler                   = preg_replace( '#(.*?)-js-before#', '$1', $handler );
		$handler                   = preg_replace( '#(.*?)-js-translations#', '$1', $handler );
		$handler                   = preg_replace( '#(.*?)-js-extra#', '$1', $handler );
		$skiped_handlers[ $index ] = $handler;
	}

	$script = '';
	$done   = wp_scripts()->done;
	ob_start();
	wp_scripts()->done = $skiped_handlers;
	wp_scripts()->do_items( $script_handles );
	wp_scripts()->done = $done;
	$script            = ob_get_clean();

	return $script;
}

function kubio_extend_block_editor_styles_html() {
	$script = kubio_get_editor_scripts();
	$style  = kubio_get_editor_style();
	$script = "<template id='kubio-scripts-template'>{$script}</template>";

	printf(
		'<script>window.__kubioEditorStyles = %s</script>',
		wp_json_encode( array( 'html' => $style . $script ) )
	);

	if ( function_exists( 'wp_add_iframed_editor_assets_html' ) ) {
		wp_add_iframed_editor_assets_html();
	}
}

function kubio_edit_site_get_settings() {
	$max_upload_size = wp_max_upload_size();
	if ( ! $max_upload_size ) {
		$max_upload_size = 0;
	}

	// This filter is documented in wp-admin/includes/media.php.
	// This filter is documented in wp-admin/includes/media.php.
	$image_size_names      = apply_filters(
		'image_size_names_choose',
		array(
			'thumbnail' => __( 'Thumbnail', 'kubio' ),
			'medium'    => __( 'Medium', 'kubio' ),
			'large'     => __( 'Large', 'kubio' ),
			'full'      => __( 'Full Size', 'kubio' ),
		)
	);
	$available_image_sizes = array();
	foreach ( $image_size_names as $image_size_slug => $image_size_name ) {
		$available_image_sizes[] = array(
			'slug' => $image_size_slug,
			'name' => $image_size_name,
		);
	}

	global $current_screen;
	if ( function_exists( '_register_core_block_patterns_and_categories' ) ) {
		_register_core_block_patterns_and_categories();
	}

	if ( function_exists( '_load_remote_block_patterns' ) ) {
		_load_remote_block_patterns();
	}

	if ( function_exists( '_load_remote_featured_patterns' ) ) {
		_load_remote_featured_patterns( $current_screen );
	}

	global $wp_version;
	$block_editor_context = new WP_Block_Editor_Context( array( 'name' => 'core/edit-site' ) );
	$settings             = array_merge(
		get_default_block_editor_settings(),
		array(
			'alignWide'                            => true, //get_theme_support( 'align-wide' ),
			'siteUrl'                              => site_url( '/' ),
			'postsPerPage'                         => get_option( 'posts_per_page' ),
			'defaultTemplateTypes'                 => kubio_get_indexed_default_template_types(),
			'__experimentalBlockPatterns'          => WP_Block_Patterns_Registry::get_instance()->get_all_registered(),
			'__experimentalBlockPatternCategories' => array_merge(
				WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered(),
				kubio_get_patterns_categories()
			),
			'imageSizes'                           => $available_image_sizes,
			'isRTL'                                => is_rtl(),
			'maxUploadFileSize'                    => $max_upload_size,

			'wpVersion'                            => preg_replace( '/([0-9]+).([0-9]+).*/', '$1.$2', $wp_version ),
		)
	);

	if ( function_exists( 'get_block_editor_settings' ) && class_exists( '\WP_Block_Editor_Context' ) ) {
		$settings = get_block_editor_settings( $settings, $block_editor_context );
	}

	if ( function_exists( 'gutenberg_experimental_global_styles_settings' ) ) {
		$settings = gutenberg_experimental_global_styles_settings( $settings );
	}

	$settings['state'] = array(
		'entity' => kubio_edit_site_get_edited_entity(),
	);

	$settings['kubioPrimaryMenuLocation'] = apply_filters(
		'kubio/primary_menu_location',
		'header-menu'
	);

	$settings['isKubioTheme'] = kubio_theme_has_kubio_block_support();

	$settings['supportsTemplateMode'] = kubio_theme_has_block_templates_support();

	global $post;
	$settings                  = apply_filters( 'block_editor_settings_all', $settings, $post );
	$settings                  = apply_filters( 'kubio/block_editor_settings', $settings, $post );
	$settings['isWooCommerce'] = function_exists( 'WC' );

	$settings['defaultTemplatePartAreas'] = kubio_get_allowed_template_part_areas();
	$settings['classicThemeTemplates']    = kubio_get_classic_theme_templates();
	$settings['kubioThemeAssetsUrlBase']  = untrailingslashit( apply_filters( 'kubio/importer/kubio-url-placeholder-replacement', '' ) );

	// adding it here since `select('core').getCurrentTheme().theme_uri` returns an empty string.
	$settings['themeUri']                     = get_template_directory_uri();
	$settings['classicHasFrontPageTemplate']  = kubio_third_party_theme_has_front_page_template();
	$settings['classicThemePrimaryTemplates'] = array_keys( kubio_get_classic_theme_primary_templates() );
	$scrollableImagePath                      = wp_get_theme()->get_file_path( 'resources/images/front-page-preview.jpg' );
	// Handle the Front page suggestion preview image
	if ( file_exists( $scrollableImagePath ) ) {
		$settings['kubioHasFpsScrollPreview'] = true;  // 'resources/images/front-page-preview.jpg';
	} else {
		$settings['kubioHasFpsScrollPreview'] = false; //'screenshot.jpg';
	}
	$settings['supportsLayout'] = true;
	$layout                     = LodashBasic::get( $settings, '__experimentalFeatures.layout', array() );
	$mergedLayout               = array_merge( array( 'contentSize' => '1172px' ), $layout );
	if ( ! isset( $settings['__experimentalFeatures'] ) ) {
		$settings['__experimentalFeatures'] = array();
	}
	$settings['__experimentalFeatures']['layout'] = $mergedLayout;
	LodashBasic::set( $settings, '__experimentalFeatures.blocks.core/post-content.layout', $mergedLayout );

	// use array_values to ensure that json_encode sees it as array not object
	$styles = array_values(
		array_merge(
			function_exists( 'get_block_editor_theme_styles' ) ? get_block_editor_theme_styles() : array(),
			array(
				array(
					'css'            => kubio_get_editor_style( true ),
					'__unstableType' => 'theme',
				),
			)
		)
	);

	if ( function_exists( 'wp_get_global_stylesheet' ) ) {
		$block_classes = array(
			'css'            => 'base-layout-styles',
			'__unstableType' => 'base-layout',
			'isGlobalStyles' => true,
		);
		$actual_css    = wp_get_global_stylesheet( array( $block_classes['css'] ) );

		if ( $actual_css ) {
			$block_classes['css'] = $actual_css;
			$styles[]             = $block_classes;
		}
	}

	$settings['styles'] = $styles;

	return $settings;
}

function kubio_block_editor_general_settings( $settings ) {
	$settings['__unstableEnableFullSiteEditingBlocks'] = true;
	$settings['enableFSEBlocks']                       = true;
	$settings['kubioGlobalSettings']                   = (object) Flags::getSettings();

	// settings for outside Kubio editor
	// __unstableResolvedAssets was added in WP 6.0
	if ( isset( $settings['__unstableResolvedAssets'] ) && ! kubio_is_kubio_editor_page() ) {
		$current_style   = Arr::get( $settings, '__unstableResolvedAssets.styles', '' );
		$current_scripts = Arr::get( $settings, '__unstableResolvedAssets.scripts', '' );

		preg_match_all( "#id='(.*?)'#m", $current_style, $matches );
		$style_ids = Arr::get( $matches, 1, array() );

		preg_match_all( "#id='(.*?)'#m", $current_scripts, $matches );
		$script_ids = Arr::get( $matches, 1, array() );

		$style = array(
			sprintf( '<style>%s</style>', kubio_render_global_colors() ),
			kubio_get_editor_style( false, $style_ids ),
		);

		$script = kubio_get_editor_scripts( $script_ids );

		Arr::set(
			$settings,
			'__unstableResolvedAssets.styles',
			$current_style . "\n\n" . implode( "\n", $style )
		);

		Arr::set(
			$settings,
			'__unstableResolvedAssets.scripts',
			$current_scripts . "\n\n$script"
		);

		// kubio_enqueue_frontend_scripts();

	}

	$settings['kubioLoaded']        = true;
	$settings['kubioCloudUrl']      = Utils::getCloudUrl();
	$settings['kubioCloudSnippets'] = KUBIO_CLOUD_SNIPPETS;
	return $settings;
}

add_filter( 'block_editor_settings_all', 'kubio_block_editor_general_settings', 50 );

function kubio_load_gutenberg_assets() {
	wp_enqueue_style( 'kubio-pro' );

	wp_enqueue_script( 'kubio-block-patterns' );
	wp_enqueue_script( 'kubio-third-party-blocks' );
	wp_localize_script(
		'kubio-block-patterns',
		'kubioBlockPatterns',
		array(
			'inGutenbergEditor' => ! kubio_is_kubio_editor_page(),
		)
	);

	wp_add_inline_style( 'kubio-block-library', kubio_get_shapes_css() );
}

function kubio_get_post_types() {
	$extra_types = get_post_types(
		array(
			'_builtin' => false,
			'public'   => true,
		)
	);

	$types = array();
	global $wp_post_types;

	foreach ( $extra_types as $type ) {
		$types[] = array(
			'entity' => $type,
			'kind'   => 'postType',
			'title'  => $wp_post_types[ $type ]->label,
		);
	}

	return $types;
}

add_action( 'enqueue_block_editor_assets', 'kubio_load_gutenberg_assets' );

function kubio_navigation_get_menu_items_endpoint(
	$menu_id,
	$results_per_page = 100
) {
	return '/__experimental/menu-items?' .
		build_query(
			array(
				'context'  => 'edit',
				'menus'    => $menu_id,
				'per_page' => $results_per_page,
				'_locale'  => 'user',
			)
		);
}

function kubio_add_endpoint_details(
	$url,
	$results_per_page = null,
	$locale = null
) {
	$vars = array( 'context' => 'edit' );
	if ( $results_per_page !== null ) {
		$vars['per_page'] = $results_per_page;
	}
	if ( $locale !== null ) {
		$vars['_locale'] = 'user';
	}
	return $url . '?' . build_query( $vars );
}

function kubio_edit_site_init( $hook ) {
	if ( ! kubio_is_kubio_editor_page() ) {
		return;
	}
	add_filter( 'use_block_editor_for_post_type', '__return_true' );

	global $current_screen, $post;
	$current_screen->is_block_editor( true );

	// Inline the Editor Settings
	$settings = kubio_edit_site_get_settings();

	$menus         = wp_get_nav_menus();
	$first_menu_id = ! empty( $menus ) ? $menus[0]->term_id : null;

	$stylesheet = get_stylesheet();

	// Preload block editor paths.
	// most of these are copied from edit-forms-blocks.php.
	$preload_paths = array(
		array( '/wp/v2/media', 'OPTIONS' ),
		'/',
		'/?context=edit',
		'/wp/v2/types?context=edit',
		'/wp/v2/taxonomies?context=edit',
		'/wp/v2/categories?context=edit',
		'/wp/v2/categories/1?context=edit',
		'/wp/v2/tags?context=edit',

		'/wp/v2/settings',

		array( '/wp/v2/pages', 'OPTIONS' ),
		array( '/wp/v2/posts', 'OPTIONS' ),

		kubio_add_endpoint_details( '/wp/v2/pages', 100 ),
		kubio_add_endpoint_details( '/wp/v2/posts', 100 ),
		kubio_add_endpoint_details( '/wp/v2/templates', 100 ),
		kubio_add_endpoint_details( '/wp/v2/template-parts', 100 ),

		'/kubio/v1/contact-form/forms_by_type',
		'/kubio/v1/subscribe-form/forms_by_type',
		'/kubio/v1/page-templates/get',

		'/wp/v2/kubio_favorites?context=edit',
		//'/wp/v2/kubio_section',

		'/__experimental/menus?_locale=user&context=edit&per_page=100',
		'/__experimental/menus?context=edit&per_page=-1',
		'/__experimental/menu-locations?context=edit',

		kubio_add_endpoint_details( "/wp/v2/template-parts/{$stylesheet}//footer" ),
		kubio_add_endpoint_details( "/wp/v2/template-parts/{$stylesheet}//front-header" ),
		kubio_add_endpoint_details( "/wp/v2/templates/kub{$stylesheet}io//front-page" ),

		'/wp/v2/types/wp_template?context=edit',
		'/wp/v2/types/wp_template-part?context=edit',
		'/wp/v2/pages?context=edit',
		'/wp/v2/posts?context=edit',
		'/wp/v2/templates?context=edit&per_page=-1',
		'/wp/v2/template-parts?context=edit&per_page=-1',
		'/wp/v2/themes?context=edit&status=active',
	);

	$active_global_styles_id = 0;
	$active_theme            = wp_get_theme()->get_stylesheet();

	if ( class_exists( '\WP_Theme_JSON_Resolver' ) && method_exists( WP_Theme_JSON_Resolver::class, 'get_user_global_styles_post_id' ) ) {
		$active_global_styles_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
	}

	if ( $active_global_styles_id ) {
		$preload_paths = array_merge(
			$preload_paths,
			array(
				'/wp/v2/global-styles/' . $active_global_styles_id . '?context=edit',
				'/wp/v2/global-styles/' . $active_global_styles_id,
				'/wp/v2/global-styles/themes/' . $active_theme,
			)
		);
	}

	wp_enqueue_media();

	$preload_paths[] =
		'/wp/v2/kubio/global-data/' .
		kubio_global_data_post_id() .
		'?context=edit';

	$preload_paths[] = '/kubio/v1/menu/';
	$preload_paths[] =
		'/wp/v2/pages/' . get_option( 'page_on_front' ) . '?context=edit';

	$preload_paths[] = '/wp/v2/users/1?context=edit';
	$preload_paths[] = '/wp/v2/comments?context=edit&post=1';

	if ( $first_menu_id ) {
		$preload_paths[] = "/kubio/v1/menu/${first_menu_id}";
		$preload_paths[] = kubio_navigation_get_menu_items_endpoint(
			$first_menu_id
		);
	}

	$preload_data = array_reduce(
		$preload_paths,
		'rest_preload_api_request',
		array()
	);

	$block_editor_context = new WP_Block_Editor_Context( array( 'name' => 'core/edit-site' ) );

	if ( function_exists( 'block_editor_rest_api_preload' ) ) {
		block_editor_rest_api_preload( $preload_paths, $block_editor_context );
	} else {
		wp_add_inline_script(
			'wp-api-fetch',
			sprintf(
				'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );',
				wp_json_encode( $preload_data )
			),
			'after'
		);
	}

	wp_add_inline_script(
		'wp-blocks',
		sprintf(
			'wp.blocks.unstable__bootstrapServerSideBlockDefinitions( %s );',
			wp_json_encode( get_block_editor_server_block_settings() )
		),
		'after'
	);

	wp_add_inline_script(
		'wp-blocks',
		sprintf(
			'wp.blocks.setCategories( %s );',
			wp_json_encode( get_block_categories( $post ) )
		),
		'after'
	);

	// Editor default styles
	wp_enqueue_style( 'wp-format-library' );
	wp_enqueue_style( 'kubio-format-library' );
	wp_enqueue_script( 'kubio-editor' );
	wp_enqueue_script( 'kubio-block-library' );
	wp_enqueue_style( 'kubio-admin-panel' );

	wp_enqueue_style( 'kubio-editor' );
	wp_enqueue_style( 'kubio-advanced-panel' );
	wp_enqueue_style( 'kubio-block-library-editor' );
	wp_enqueue_style( 'kubio-controls' );
	wp_enqueue_style( 'kubio-scripts' );
	wp_enqueue_style( 'kubio-wp-global-styles' );

	$settings['postTypes'] = kubio_get_post_types();

	wp_add_inline_script(
		'kubio-editor',
		sprintf(
			'window.kubioEditSiteSettings = %s;' .
				"\n" .
				'wp.domReady( function() { kubio.editor.initialize( "kubio-editor", window.kubioEditSiteSettings );	} );',
			wp_json_encode( $settings )
		),
		'after'
	);

	wp_add_inline_style( 'kubio-editor', kubio_get_shapes_css() );

	add_action( 'admin_footer', 'kubio_extend_block_editor_styles_html' );
	do_action( 'enqueue_block_editor_assets' );
	do_action( 'kubio/editor/enqueue_assets' );

	$editor_styles = function_exists( 'get_editor_stylesheets' ) ? get_editor_stylesheets() : null;
	if (
		current_theme_supports( 'wp-block-styles' ) ||
		( ! is_array( $editor_styles ) || count( $editor_styles ) === 0 )
	) {
		wp_enqueue_style( 'wp-block-library-theme' );
	}
}

add_action( 'admin_enqueue_scripts', 'kubio_edit_site_init' );

// Add tinymce scripts within the Kubio Editor and WordPress Editor since we need them in blocks like Subscribe form.
// we use the `enqueue_block_editor_assets` action since it is called in both editors.
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_script( 'wp-tinymce' );
		wp_enqueue_editor();
		wp_enqueue_code_editor( array() );
		wp_tinymce_inline_scripts();
	}
);

/**
 * Renders the Menu Page
 *
 * @return void
 */
function kubio_edit_site_render_block_editor() {    ?>
	<style>
		div#wpcontent,
		#wpbody-content {
			position: fixed;
			z-index: 1000000;
			width: 100vw;
			height: 100vh;
			background: #fff;
			top: 0%;
			left: 0%;
			right: 0;
			border: 0;
			display: block;
			box-sizing: border-box;
		}

		#wp-link-backdrop {
			z-index: 1000050;
		}

		#wp-link-wrap {
			z-index: 1000100;
		}

		#kubio-editor {
			position: absolute;
			top: 0;
			bottom: 0;
			left: 0;
			right: 0;
			z-index: 1000;
			width: 100%;
			height: 100%;
		}

		.kubio-loading-editor {
			position: absolute;
			top: 50%;
			left: 50%;
			display: block;
			font-size: 1.5em;
			transform: translate(-50%, -50%);
		}

		.kubio-loading-editor iframe {
			margin: 0px auto 0px auto;
		}
	</style>
	<div id="kubio-editor" class="kubio">
		<div class="kubio-loading-editor">
			<?php echo kubio_get_iframe_loader( array( 'size' => '120px' ) ); ?>
		</div>
	</div>
	<?php
}

/**
 * Registers the new WP Admin Menu
 *
 * @return void
 */
function kubio_edit_site_add_menu_page() {
	add_menu_page(
		__( 'Kubio', 'kubio' ),
		sprintf(
			'<span class="kubio-menu-item"><span class="kubio-menu-item--icon">%s</span><span>%s</span></span>',
			wp_kses_post( KUBIO_LOGO_SVG ),
			__( 'Kubio', 'kubio' )
		),
		'edit_posts',
		'kubio',
		'kubio_edit_site_render_block_editor',
		'data:image/svg+xml;base64,' . base64_encode( str_replace( '<svg', '<svg fill="#5246F1" ', KUBIO_LOGO_SVG ) ),
		20
	);

	add_submenu_page(
		'themes.php',
		__( 'Kubio', 'kubio' ),
		__( 'Edit with Kubio', 'kubio' ),
		'edit_posts',
		'kubio_appearance_edit',
		'kubio_edit_site_render_block_editor',
		1
	);

	global $submenu;

	foreach ( $submenu['themes.php'] as $index => $submenu_item ) {
		if ( $submenu_item[2] === 'kubio_appearance_edit' ) {
			$submenu['themes.php'][ $index ][2] = add_query_arg(
				array(
					'page' => 'kubio',
				),
				'admin.php'
			);
		}
	}
}

function kubio_admin_page_submenu_hook() {
	global $submenu;

	$kubio_submenu = Arr::get( $submenu, 'kubio', null );

	if ( is_array( $kubio_submenu ) ) {
		foreach ( $kubio_submenu as $index => $submenu_data ) {
			$menu_slug = Arr::get( $submenu_data, '2', null );
			if ( $menu_slug === 'kubio' ) {
				$submenu['kubio'][ $index ][0] = __( 'Open Kubio Editor', 'kubio' );
			}
		}
	}
}

add_action( 'admin_menu', 'kubio_admin_page_submenu_hook', 200 );

add_action( 'admin_menu', 'kubio_edit_site_add_menu_page' );

function kubio_admin_menu_style() {
	?>
	<style>
		#adminmenu .toplevel_page_kubio .wp-menu-image {
			display: none;
		}

		.wp-admin.folded #adminmenu .toplevel_page_kubio .wp-menu-image {
			display: block;
			background-size: 16px auto;
		}

		#adminmenu .kubio-menu-item {
			display: flex;
			align-items: center;
		}

		#adminmenu .kubio-menu-item>span {
			display: block;
			line-height: 1;
		}

		#adminmenu .kubio-menu-item--icon {
			color: rgba(240, 246, 252, .6);
		}

		#adminmenu .wp-menu-name:hover .kubio-menu-item--icon {
			color: inherit;
		}

		#adminmenu .kubio-menu-item--icon svg {
			fill: currentColor;
			height: 15px;
			margin-right: 11px;
		}

		#adminmenu .wp-menu-open .kubio-menu-item--icon svg {
			fill: #fff;
		}

		#adminmenu .toplevel_page_kubio div.wp-menu-name {
			padding: 8px 8px 8px 10px;
		}

		#adminmenu .wp-submenu .kubio-menu-item--icon {
			display: none;
		}
	</style>
	<?php
}

add_action( 'admin_head', 'kubio_admin_menu_style' );

/**
 * Register a core site setting for front page information.
 */
function kubio_register_site_editor_homepage_settings() {
	global $wp_registered_settings;

	if ( ! isset( $wp_registered_settings['show_on_front'] ) ) {
		register_setting(
			'reading',
			'show_on_front',
			array(
				'show_in_rest' => true,
				'type'         => 'string',
				'description'  => __( 'What to show on the front page', 'kubio' ),
			)
		);
	}

	if ( ! isset( $wp_registered_settings['page_on_front'] ) ) {
		register_setting(
			'reading',
			'page_on_front',
			array(
				'show_in_rest' => true,
				'type'         => 'number',
				'description'  => __(
					'The ID of the page that should be displayed on the front page',
					'kubio'
				),
			)
		);
	}
	if ( ! isset( $wp_registered_settings['page_for_posts'] ) ) {
		register_setting(
			'reading',
			'page_for_posts',
			array(
				'show_in_rest' => true,
				'type'         => 'number',
				'description'  => __(
					'The ID of the page that displays the posts',
					'kubio'
				),
			)
		);
	}

	if ( ! isset( $wp_registered_settings['site_icon'] ) ) {
		register_setting(
			'general',
			'site_icon',
			array(
				'show_in_rest' => array(
					'name' => 'site_icon',
				),
				'type'         => 'integer',
				'description'  => __( 'Site icon.', 'kubio' ),
			)
		);
	}

	if ( class_exists( 'Jetpack_Notifications' ) ) {
		remove_action( 'wp_head', array( Jetpack_Notifications::init(), 'styles_and_scripts' ), 120 );
		remove_action( 'admin_head', array( Jetpack_Notifications::init(), 'styles_and_scripts' ) );
	}
}

add_action( 'init', 'kubio_register_site_editor_homepage_settings', 100 );

function kubio_editor_add_default_template_types( $template_types ) {
	if ( kubio_is_kubio_editor_page() && is_user_logged_in() ) {
		$kubio_templates = array(
			'full-width'       => array(
				'title'       => _x( 'Full Width', 'Template name', 'kubio' ),
				'description' => __(
					'Recommended Kubio template to display individual pages. This template works best with the Kubio provided blocks and predesigned sections.',
					'kubio'
				),
			),
			'kubio-full-width' => array(
				'title'       => _x( 'Kubio Full Width', 'Template name', 'kubio' ),
				'description' => __(
					'Recommended Kubio template to display individual pages. This template works best with the Kubio provided blocks and predesigned sections.',
					'kubio'
				),
			),
		);

		foreach ( $kubio_templates as $slug => $settings ) {
			$template_types[ $slug ] = $settings;
		}
	}

	return $template_types;
}

add_filter( 'default_template_types', 'kubio_editor_add_default_template_types' );

add_action(
	'init',
	function () {
		if ( kubio_is_kubio_editor_page() && is_user_logged_in() ) {
			$has_valid_req = Utils::validateRequirements();

			if ( is_wp_error( $has_valid_req ) ) {
				ob_start();
				?>
			<style>
				body#error-page {
					margin: 0;
					position: absolute;
					top: 50%;
					left: 50%;
					transform: translate(-50%, -50%);
					border-left: 4px solid #fb3e41;
					padding-top: 0;
					padding-bottom: 0;
				}

				body#error-page .kubio-mrn {
					flex-direction: column;
					align-items: flex-start;
				}

				body#error-page .kubio-mrn-icon-holder {
					padding-bottom: 12px;
				}

				body#error-page br {
					display: none;
				}

				p {
					margin: 12px 0 0 0;
				}

				body#error-page a {
					margin-top: 16px;
					display: block;
				}

				#error-page p.kubio-mrn-rollback-message>a {
					display: inline;
				}
			</style>
				<?php
				_kubio_requirements_not_met_notice();
				$content = ob_get_clean();
				wp_die( $content );
			}

			if ( ! current_user_can( 'edit_theme_options' ) ) {
				wp_die(
					'<h1>' . __( 'You need a higher level of permission.', 'kubio' ) . '</h1>' .
						'<p>' . __( 'Sorry, you are not allowed to customize this site.', 'kubio' ) . '</p>',
					403
				);
			}
		}
	},
	5
);

/**
 * Registers block editor 'kubio_section' post type.
 */
function kubio_register_kubio_section_post_type() {
	if ( post_type_exists( 'kubio_section' ) ) {
		return;
	}

	$args = array(
		'label'               => __( 'Kubio Section', 'kubio' ),
		'public'              => false,
		'show_ui'             => false,
		'show_in_rest'        => true,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => false,
		'rewrite'             => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'rest_base'           => 'kubio_section',
		'capabilities'        => array(
			'read'                   => 'edit_theme_options',
			'create_posts'           => 'edit_theme_options',
			'edit_posts'             => 'edit_theme_options',
			'edit_published_posts'   => 'edit_theme_options',
			'delete_published_posts' => 'edit_theme_options',
			'edit_others_posts'      => 'edit_theme_options',
			'delete_others_posts'    => 'edit_theme_options',
		),
		'map_meta_cap'        => true,
		'supports'            => array(
			'title',
			'editor',
			'revisions',
		),
	);

	register_post_type( 'kubio_section', $args );
}

add_action( 'init', 'kubio_register_kubio_section_post_type', 9 );


/**
 * Registers block editor 'kubio_section' post type.
 */
function kubio_register_kubio_favorites_post_type() {
	if ( post_type_exists( 'kubio_favorites' ) ) {
		return;
	}

	$args = array(
		'label'               => __( 'Kubio Favorites', 'kubio' ),
		'public'              => false,
		'show_ui'             => false,
		'show_in_rest'        => true,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => false,
		'rewrite'             => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'rest_base'           => 'kubio_favorites',
		'capabilities'        => array(
			'read'                   => 'edit_theme_options',
			'create_posts'           => 'edit_theme_options',
			'edit_posts'             => 'edit_theme_options',
			'edit_published_posts'   => 'edit_theme_options',
			'delete_published_posts' => 'edit_theme_options',
			'edit_others_posts'      => 'edit_theme_options',
			'delete_others_posts'    => 'edit_theme_options',
		),
		'map_meta_cap'        => true,
		'supports'            => array(
			'title',
			'editor',
			'revisions',
		),
	);

	register_post_type( 'kubio_favorites', $args );
}

add_action( 'init', 'kubio_register_kubio_favorites_post_type', 9 );
