<?php

/**
 * Plugin Name: Kubio
 * Plugin URI: https://kubiobuilder.com
 * Description: Kubio is an innovative block-based WordPress website builder that enriches the block editor with new blocks and gives its users endless styling options.
 * Author: ExtendThemes
 * Author URI: https://extendthemes.com
 * Version: 1.6.1
 * License: GPL3+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: kubio
 * Domain Path: /languages
 * Requires PHP: 7.1.3
 * Requires at least: 5.8
 *
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// skip loading free version if the Kubio Page Builder PRO is active
if ( ! function_exists( 'kubio_is_free_and_pro_already_active' ) ) {

	function kubio_is_free_and_pro_already_active() {
		$plugin_name = plugin_basename( __FILE__ );
		$is_free     = strpos( $plugin_name, 'pro' ) === false;

		$flags_option = get_option( '__kubio_instance_flags' );

		update_option( '__kubio_instance_flags', $flags_option );

		$pro_builder_is_active = false;
		if ( $is_free ) {
			$active_plugins        = get_option( 'active_plugins' );
			$pro_builder_is_active = in_array( 'kubio-pro/plugin.php', $active_plugins );
		}

		return $is_free && $pro_builder_is_active;
	}

	if ( kubio_is_free_and_pro_already_active() ) {
		return;
	}
}


if ( defined( 'KUBIO_VERSION' ) ) {
	return;
}

define( 'KUBIO_VERSION', '1.6.1' );
define( 'KUBIO_BUILD_NUMBER', '145' );

define( 'KUBIO_ENTRY_FILE', __FILE__ );
define( 'KUBIO_ROOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'KUBIO_BUILD_DIR', plugin_dir_path( __FILE__ ) . '/build' );
define( 'KUBIO_LOGO_URL', plugins_url( '/static/kubio-logo.svg', __FILE__ ) );
define( 'KUBIO_LOGO_PATH', plugin_dir_path( __FILE__ ) . '/static/kubio-logo.svg' );
define( 'KUBIO_LOGO_SVG', file_get_contents( KUBIO_LOGO_PATH ) );

if ( ! defined( 'KUBIO_CLOUD_URL' ) ) {
	define( 'KUBIO_CLOUD_URL', 'https://cloud.kubiobuilder.com' );
}

if ( ! defined( 'KUBIO_CLOUD_SNIPPETS' ) ) {
	define( 'KUBIO_CLOUD_SNIPPETS', false );
}
if ( ! defined( 'KUBIO_MINIMUM_WP_VERSION' ) ) {
	define( 'KUBIO_MINIMUM_WP_VERSION', '6.0' );
}


define( 'KUBIO_SLUG', str_replace( wp_normalize_path( WP_PLUGIN_DIR ) . '/', '', wp_normalize_path( dirname( __FILE__ ) ) ) );

if ( ! function_exists( 'kubio_url' ) ) {
	function kubio_url( $path = '' ) {
		return plugins_url( $path, __FILE__ );
	}
}

/**
 * @var \Composer\Autoload\ClassLoader $kubio_autoloader ;
 */
global $kubio_autoloader;
$kubio_autoloader = require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

require_once 'lib/init.php';
