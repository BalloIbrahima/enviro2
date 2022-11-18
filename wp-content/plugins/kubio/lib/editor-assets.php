<?php

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Utils;
use Kubio\DemoSites\DemoPartsRepository;
use Kubio\DemoSites\DemoSitesRepository;
use Kubio\Flags;

function kubio_override_script( $scripts, $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
	$script = $scripts->query( $handle, 'registered' );
	if ( $script ) {

		$script->src  = $src;
		$script->deps = $deps;
		$script->ver  = $ver;
		$script->args = $in_footer;

		unset( $script->extra['group'] );
		if ( $in_footer ) {
			$script->add_data( 'group', 1 );
		}
	} else {
		$scripts->add( $handle, $src, $deps, $ver, $in_footer );
	}

	if ( in_array( 'wp-i18n', $deps, true ) ) {
		$scripts->set_translations( $handle, 'kubio' );
	}
}

function kubio_override_style( $styles, $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
	$style = $styles->query( $handle, 'registered' );
	if ( $style ) {
		$styles->remove( $handle );
	}
	$styles->add( $handle, $src, $deps, $ver, $media );
}

function kubio_register_kubio_scripts_scripts_dependencies( $version ) {
	$scripts = array(
		array(
			'handle' => 'typed',
			'deps'   => array( 'jquery' ),
			'src'    => 'typed.js',
		),
		array(
			'handle' => 'fancybox',
			'deps'   => array( 'jquery' ),
			'src'    => 'fancybox/jquery.fancybox.min.js',
		),
		array(
			'handle' => 'swiper',
			'deps'   => array( 'jquery' ),
			'src'    => 'swiper/js/swiper.js',
		),
	);

	$script_handles = array();

	foreach ( $scripts as $script ) {
		$handle                    = "kubio-scripts-dep-{$script['handle']}";
		$script_handles[ $handle ] = array(
			$handle,
			kubio_url( "/static/{$script['src']}" ),
			$script['deps'],
			$version,
			true,
		);
	}

	return $script_handles;
}

function kubio_register_frontend_script( $handle ) {
	add_filter(
		'kubio/frontend/scripts',
		function( $scripts ) use ( $handle ) {

			if ( ! in_array( $handle, $scripts ) ) {
				$scripts[] = $handle;
			}

			return $scripts;
		}
	);
}

function kubio_get_frontend_scripts() {
	return apply_filters( 'kubio/frontend/scripts', array() );
}

function kubio_enqueue_frontend_scripts() {
	$scripts = apply_filters( 'kubio/frontend/scripts', array() );
	foreach ( $scripts as $handle ) {
		wp_enqueue_script( $handle );
	}
}

function kubio_register_packages_scripts() {

	$registered = array();

	$paths = glob( KUBIO_ROOT_DIR . 'build/*/index.js' );
	foreach ( $paths as $path ) {
		$handle       = 'kubio-' . basename( dirname( $path ) );
		$asset_file   = substr( $path, 0, - 3 ) . '.asset.php';
		$asset        = file_exists( $asset_file )
				? require( $asset_file )
				: null;
		$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : array();

		if ( Utils::isDebug() ) {
			$version = uniqid( time() . '-' );
		} else {
			$version = isset( $asset['version'] ) ? $asset['version'] : filemtime( $path );
		}

		switch ( $handle ) {
			case 'kubio-editor':
				array_push( $dependencies, 'wp-dom-ready', 'editor' );
				break;

			case 'kubio-format-library':
				array_push( $dependencies, 'wp-format-library' );
				break;

			case 'kubio-scripts':
				$extra_scripts = kubio_register_kubio_scripts_scripts_dependencies( $version );
				$registered    = array_merge( $registered, $extra_scripts );
				$extra_deps    = array_keys( $extra_scripts );
				$dependencies  = array_merge( $dependencies, $extra_deps, array( 'jquery', 'jquery-masonry' ) );
				$dependencies  = array_diff( $dependencies, array( 'wp-polyfill' ) );
				break;

			case 'kubio-frontend':
				$dependencies = array( 'kubio-scripts' );
				kubio_register_frontend_script( 'kubio-frontend' );
				break;

			case 'kubio-block-library':
				array_push( $dependencies, 'kubio-format-library' );
				break;

			case 'kubio-block-editor':
				array_push( $dependencies, 'wp-block-editor', 'wp-block-directory' );
				break;

		}

		$kubio_path = substr( $path, strlen( KUBIO_ROOT_DIR ) );

		$registered[] = array(
			$handle,
			kubio_url( $kubio_path ),
			$dependencies,
			$version,
			true,
		);
	}

	foreach ( $registered as $script ) {

		if ( is_array( $script ) && count( $script ) >= 2 ) {
			$handle = $script[0];
			$deps   = $script[2];
			if ( in_array( 'wp-i18n', $deps, true ) ) {
				wp_set_script_translations( $handle, 'kubio' );
			}

			call_user_func_array( 'wp_register_script', $script );
			do_action( 'kubio_registered_script', $script[0], $script[3] );
		}
	}

	do_action( 'kubio_scripts_registered', $registered );
}


function kubio_replace_default_scripts( $scripts ) {

	if ( ! kubio_is_kubio_editor_page() ) {
		return;
	}

	$to_replace = array(
		'wp-block-editor' => 'block-editor',
	);

	foreach ( $to_replace as $old => $new ) {
		$script_path = KUBIO_ROOT_DIR . "/build/{$new}/index.js";
		$asset_file  = KUBIO_ROOT_DIR . "/build/{$new}/index.asset.php";

		$asset        = file_exists( $asset_file )
				? require( $asset_file )
				: null;
		$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : array();
		$version      = isset( $asset['version'] ) ? $asset['version'] : filemtime( $script_path );

		kubio_override_script(
			$scripts,
			$old,
			kubio_url( "/build/{$new}/index.js" ),
			$dependencies,
			$version,
			true
		);
	}

}


function kubio_register_kubio_block_library__style_dependencies( $version ) {
	$styles = array(
		array(
			'handle' => 'fancybox',
			'src'    => 'fancybox/jquery.fancybox.min.css',
		),
		array(
			'handle' => 'swiper',
			'src'    => 'swiper/css/swiper.min.css',
		),
	);

	$handles = array();

	foreach ( $styles as $style ) {
		$handle             = "kubio-block-library-dep-{$style['handle']}";
		$handles[ $handle ] = array(
			$handle,
			kubio_url( "/static/{$style['src']}" ),
			isset( $style['deps'] ) ? $style['deps'] : array(),
			$version,
		);
	}

	return $handles;
}


function kubio_register_packages_styles() {

	$registered = array();

	foreach ( glob( KUBIO_ROOT_DIR . 'build/*/style.css' ) as $path ) {
		$handle       = 'kubio-' . basename( dirname( $path ) );
		$kubio_path   = substr( $path, strlen( KUBIO_ROOT_DIR ) );
		$version      = filemtime( $path );
		$dependencies = array();

		switch ( $handle ) {
			case 'kubio-editor':
				$dependencies = array( 'wp-edit-blocks' );
				break;

			case 'kubio-format-library':
				array_push( $dependencies, 'wp-format-library' );
				break;

			case 'kubio-admin-panel':
				array_push( $dependencies, 'kubio-utils' );
				break;

			case 'kubio-block-library':
				$extra_styles = kubio_register_kubio_block_library__style_dependencies( $version );
				$registered   = array_merge( $registered, $extra_styles );
				$extra_deps   = array_keys( $extra_styles );
				$dependencies = array_merge( $dependencies, $extra_deps, array( 'wp-block-library' ) );
				break;
		}

		$registered[] = array(
			$handle,
			kubio_url( $kubio_path ),
			$dependencies,
			$version,
		);
	}

	foreach ( glob( KUBIO_ROOT_DIR . 'build/*/editor.css' ) as $path ) {
		$handle       = 'kubio-' . basename( dirname( $path ) );
		$kubio_path   = substr( $path, strlen( KUBIO_ROOT_DIR ) );
		$version      = filemtime( $path );
		$dependencies = array();

		switch ( $handle ) {
			case 'kubio-editor':
				$dependencies = array( 'wp-edit-blocks' );
				break;

			case 'kubio-block-library':
				$dependencies = array( /* 'wp-block-library' */ );
				break;
		}

		$registered[] = array(
			"{$handle}-editor",
			kubio_url( $kubio_path ),
			$dependencies,
			$version,
		);
	}

	foreach ( $registered as $style ) {

		if ( is_array( $style ) && count( $style ) >= 2 ) {

			call_user_func_array( 'wp_register_style', $style );

		}
	}
}


function kubio_replace_default_styles( $styles ) {

	if ( ! kubio_is_kubio_editor_page() ) {
		return;
	}

	// Editor Styles .
	kubio_override_style(
		$styles,
		'wp-block-editor',
		kubio_url( 'build/block-editor/style.css' ),
		array( 'wp-components', 'wp-editor-font' ),
		filemtime( KUBIO_ROOT_DIR . 'build/editor/style.css' )
	);
	$styles->add_data( 'wp-block-editor', 'rtl', 'replace' );

}

add_action( 'init', 'kubio_register_packages_scripts' );
add_action( 'init', 'kubio_register_packages_styles' );

add_action( 'wp_default_styles', 'kubio_replace_default_styles' );
add_action( 'wp_default_scripts', 'kubio_replace_default_scripts' );


add_action(
	'kubio_registered_script',
	function ( $handle, $version ) {
		if ( $handle === 'kubio-utils' || $handle === 'kubio-admin-panel' ) {
			$include_test_templates = defined( 'KUBIO_INCLUDE_TEST_TEMPLATES' ) && KUBIO_INCLUDE_TEST_TEMPLATES === true;
			$data                   = 'window.kubioUtilsData=' . wp_json_encode(
				array_merge(
					kubio_get_site_urls(),
					array(

						'defaultAssetsUrl'       => kubio_url( 'static/default-assets' ),
						'patternsAssetsUrl'      => kubio_url( 'static/patterns' ),
						'kubioRemoteContentFile' => 'https://static-assets.kubiobuilder.com/content-2022-05-17.json',
						'kubioRemoteContent'     => Utils::getCloudUrl( '/api/snippets/globals' ),
						'kubioLocalContentFile'  => kubio_url( 'static/patterns/content-converted.json' ),
						'kubioEditorURL'         => add_query_arg( 'page', 'kubio', admin_url( 'admin.php' ) ),
						'patternsOnTheFly'       =>
						( defined( 'KUBIO_PATTERNS_ON_THE_FLY' ) && KUBIO_PATTERNS_ON_THE_FLY )
						? KUBIO_PATTERNS_ON_THE_FLY
						: '',
						'base_url'               => site_url(),
						'admin_url'              => admin_url(),
						'admin_plugins_url'      => admin_url( 'plugins.php' ),
						'demo_sites'             => DemoSitesRepository::getInstance()->getDemoSitesList(),
						'demo_parts_by_slug'     => DemoPartsRepository::getInstance()->getDemoParts(),
						'demo_sites_url'         => Utils::getCloudUrl( 'api/project/demo-sites' ),
						'demo_parts_url'         => Utils::getCloudUrl( 'api/demo-sites/get-demo-content' ),
						'plugins_states'         => DemoSitesRepository::getInstance()->getPluginsStates(),
						'include_test_templates' => $include_test_templates,
						'last_imported_starter'  => Flags::get( 'last_imported_starter' ),

					)
				)
			);

			wp_add_inline_script( $handle, $data, 'before' );
		}

		if ( $handle === 'kubio-style-manager' ) {

			$url = add_query_arg(
				array(
					'action' => 'kubio_style_manager_web_worker',
					'v'      => Utils::isDebug() ? time() : KUBIO_VERSION,
				),
				admin_url( 'admin-ajax.php' )
			);

			wp_add_inline_script(
				$handle,
				'var _kubioStyleManagerWorkerURL=' . wp_json_encode( $url ),
				'before'
			);
		}
	},
	10,
	2
);

function kubio_print_style_manager_web_worker() {
	header( 'content-type: application/javascript' );

	$script = '';
	$done   = wp_scripts()->done;
	ob_start();
	wp_scripts()->done = array();
	wp_scripts()->do_items( 'kubio-style-manager' );
	wp_scripts()->done = $done;
	$script            = ob_get_clean();

	$script = preg_replace_callback(
		'#<script(.*?)>(.*?)</script>#s',
		function( $matches ) {
			$script_attrs = Arr::get( $matches, 1, '' );
			preg_match( "#src='(.*?)'#", $script_attrs, $attrs_match );
			$url     = Arr::get( $attrs_match, 1, '' );
			$content = trim( Arr::get( $matches, 2, '' ) );

			$result = array();

			if ( ! empty( $url ) ) {
				$result[] = sprintf( "importScripts('%s');", $url );
			}

			if ( ! empty( $content ) ) {
				$result[] = $content;
			}

			return trim( implode( "\n", $result ) ) . "\n\n";
		},
		$script
	);

	$content = file_get_contents( KUBIO_ROOT_DIR . '/defaults/style-manager-web-worker-template.js' );
	$content = str_replace( '// {{{importScriptsPlaceholder}}}', $script, $content );

	if ( ! Utils::isDebug() ) {
		header( 'Cache-control: public' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', time() ) . ' GMT' );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + YEAR_IN_SECONDS ) . ' GMT' );
		header( 'Etag: ' . md5( $content ) );
	}

	die( $content );
}

add_action( 'wp_ajax_kubio_style_manager_web_worker', 'kubio_print_style_manager_web_worker' );

// quick test for safari
add_action(
	'admin_init',
	function () {
		ob_start();
		?>
	<script>
		window.requestIdleCallback =
			window.requestIdleCallback ||
			function (cb) {
				var start = Date.now();
				return setTimeout(function () {
					cb({
						didTimeout: false,
						timeRemaining: function () {
							return Math.max(0, 50 - (Date.now() - start));
						},
					});
				}, 1);
			};

		window.cancelIdleCallback =
			window.cancelIdleCallback ||
			function (id) {
				clearTimeout(id);
			};
	</script>
		<?php

		$content = strip_tags( ob_get_clean() );

		wp_add_inline_script( 'wp-polyfill', $content, 'after' );
	}
);
