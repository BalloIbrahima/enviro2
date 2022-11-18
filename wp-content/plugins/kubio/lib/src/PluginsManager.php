<?php

namespace Kubio;

class PluginsManager {

	const ACTIVE        = 'ACTIVE';
	const INSTALLED     = 'INSTALLED';
	const NOT_INSTALLED = 'NOT_INSTALLED';

	private static $instance = null;

	public function __construct() {
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * @return PluginsManager
	 */
	public static function getInstance() {
		if ( ! static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * @param $slug
	 *
	 * @return bool|\WP_Error
	 */
	public function installPlugin( $slug ) {
		if ( $this->isPluginInstalled( $slug ) ) {
			return true;
		}

		$plugin_api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $plugin_api ) ) {
			return $plugin_api;
		}

		if ( ! class_exists( '\Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $plugin_api->download_link );

		if ( $result !== true ) {
			return is_wp_error( $result ) ? $result : new \WP_Error( 'failed_install', sprintf( __( 'Failed to install plugin: %s', 'kubio' ), $slug ) );
		}

		return true;
	}

	public function isPluginInstalled( $slug ) {
		return ! empty( $this->getPluginBaseName( $slug ) );
	}

	public function getPluginBaseName( $slug ) {
		$plugins = get_plugins();

		foreach ( array_keys( $plugins ) as $key ) {
			if ( preg_match( '/^' . $slug . '\//', $key ) ) {
				return $key;
			}
		}

		return false;
	}

	/**
	 * Attempts activation of a plugin
	 *
	 * @param string $slug    The plugin slug.
	 * @param boolean $silent  Optional. Whether to prevent calling activation hooks. Default false.
	 * @return void
	 */
	public function activatePlugin( $slug, $silent = false ) {
		$result = activate_plugin( $this->getPluginBaseName( $slug ), '', false, $silent );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	public function getPluginStatus( $slug ) {
		if ( $this->isPluginActive( $slug ) ) {
			return static::ACTIVE;
		}

		if ( $this->isPluginInstalled( $slug ) ) {
			return static::INSTALLED;
		}

		return static::NOT_INSTALLED;
	}

	public function isPluginActive( $slug ) {
		$plugin_path = $this->getPluginBaseName( $slug );

		if ( empty( $plugin_path ) ) {
			return false;
		}

		return is_plugin_active( $plugin_path );
	}
}
