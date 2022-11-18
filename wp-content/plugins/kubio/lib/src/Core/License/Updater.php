<?php
namespace Kubio\Core\License;

use Kubio\Core\LodashBasic;

class Updater {


	private static $instance = null;

	private $product_data = null;
	private $path         = null;

	public function __construct( $path ) {
		if ( is_admin() ) {
			$this->path = $path;
			if ( $this->canCheckForUpdates() ) {
				add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'checkForUpdate' ) );
			}
			add_filter( 'plugins_api', array( $this, 'pluginsApiHandler' ), 10, 3 );
		}
	}

	public function canCheckForUpdates() {
		if ( kubio_is_pro() ) {
			return true;
		}

		$data = apply_filters(
			'kubio/companion/update_remote_data',
			array(
				'url'  => '',
				'args' => array(
					'product' => 'kubio-pro',
				),
			),
			$this
		);

		$plugin = LodashBasic::array_get_value( $data, 'args.product', false );
		if ( $plugin ) {
			if ( $plugin === 'kubio-pro' ) {
				return true;
			}
		}
	}

	public static function load( $path ) {

		if ( ! self::$instance ) {
			self::$instance = new static( $path );
		}

		return static::getInstance();
	}

	/**
	 * @return Updater|null
	 */
	public static function getInstance() {
		return self::$instance;
	}

	public function checkForUpdate( $transient ) {
		$has_custom_update_endpoint = apply_filters( 'kubio/custom_update_endpoint', false );

		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		if ( ! $has_custom_update_endpoint ) {
			return $transient;
		}

		$info = $this->isUpdateAvailable();
		if ( $info !== false ) {
			$transient = $this->addTransientData( $transient, $info );
		}

		return $transient;
	}

	public function isUpdateAvailable() {
		$status = $this->getRemoteInfo();
		if ( $status ) {
			if ( version_compare( $status->version, $this->getLocalVersion(), '>' ) ) {
				return $status;
			}
		}

		return false;
	}

	public function getRemoteInfo() {
		$data = apply_filters(
			'kubio/companion/update_remote_data',
			array(
				'url'  => '',
				'args' => array(
					'product' => 'kubio-pro',
				),
			),
			$this
		);

		$data = array_merge(
			array(
				'url'  => '',
				'args' => array(),
			),
			(array) $data
		);

		$url  = $data['url'];
		$args = apply_filters( 'kubio/endpoints/request_body', $data['args'] );

		if ( ! $url ) {
			return false;
		}

		$url = add_query_arg( $args, $url );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				'sslverify'  => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$result        = json_decode( $response_body );

		if ( ! $result || ! $result->status || $result->status !== 'success' ) {
			return false;
		}

		$result = array_merge(
			array(
				'name'            => '',
				'description'     => '',
				'version'         => '',
				'tested'          => '',
				'author'          => '',
				'last_updated'    => '',
				'banner_low'      => '',
				'banner_high'     => '',
				'package_url'     => '',
				'description_url' => '',
				'icons'           => array(),
				'requires'        => '',
			),
			(array) $result->body
		);

		$product_data = $this->getProductData();

		if ( ! $result['description'] ) {
			$result['description'] = LodashBasic::array_get_value( $product_data, 'Description', '' );
		}

		if ( ! $result['author'] ) {
			$result['author'] = LodashBasic::array_get_value( $product_data, 'Author', '' );
		}

		return (object) $result;
	}

	public function getProductData() {
		if ( ! $this->product_data ) {
			$path = $this->path;

			$data = apply_filters(
				'kubio/companion/update_remote_data',
				array(
					'url'  => '',
					'args' => array(
						'product' => 'kubio-pro',
					),
				),
				$this
			);

			$path               = LodashBasic::array_get_value( $data, 'plugin_path', $path );
			$this->product_data = get_plugin_data( $path, false );
		}

		return $this->product_data;
	}

	public function getLocalVersion() {
		$data = $this->getProductData();

		return $data['Version'];
	}

	public function addTransientData( $transient, $info ) {
		$plugin_slug = plugin_basename( $this->path );

		$transient->response[ $plugin_slug ] = (object) array(
			'new_version' => $info->version,
			'package'     => $info->package_url,
			'icons'       => (array) $info->icons,
			'slug'        => $plugin_slug,
			'tested'      => $info->tested,
		);

		return $transient;
	}

	/**
	 * A function for the WordPress "plugins_api" filter. Checks if
	 * the user is requesting information about the current plugin and returns
	 * its details if needed.
	 *
	 * This function is called before the Plugins API checks
	 * for plugin information on WordPress.org.
	 *
	 * @param $res    bool|object The result object, or false (= default value).
	 * @param $action string      The Plugins API action. We're interested in 'plugin_information'.
	 * @param $args   array       The Plugins API parameters.
	 *
	 * @return bool|object
	 */
	public function pluginsApiHandler( $res, $action, $args ) {
		if ( $action == 'plugin_information' ) {
			if ( isset( $args->slug ) && $args->slug == plugin_basename( $this->path ) ) {
				$info = $this->getRemoteInfo();

				if ( ! $info ) {
					return $res;
				}

				$res = (object) array(
					'name'          => $info->name,
					'version'       => $info->version,
					'slug'          => $args->slug,
					'download_link' => $info->package_url,

					'tested'        => $info->tested,
					'requires'      => $info->requires,
					'last_updated'  => $info->last_updated,
					'homepage'      => $info->description_url,

					'sections'      => array(
						'description' => $info->description,
					),

					'banners'       => array(
						'low'  => $info->banner_low,
						'high' => $info->banner_high,
					),

					'external'      => true,
				);

				// Add change log tab if the server sent it
				if ( isset( $info->changelog ) ) {
					$res['sections']['changelog'] = $info->changelog;
				}

				return $res;
			}
		}

		return false;
	}


}
