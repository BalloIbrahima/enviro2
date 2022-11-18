<?php

namespace Kubio\Core\License;

use Kubio\Core\License\ActivationForm;

class License {

	private static $instance = null;

	private $activate_endpoint;
	private $check_endpoint;
	private $dashboard_url;
	private $product_update_endpoint;


	private $activation_form;
	private $check_form;

	public function __construct( $base_dir ) {

		if ( isset( $_GET['page'] ) && 'kubio-get-started' === $_GET['page'] ) {
			add_filter( 'kubio/inside_kubio_platform', '__return_true' );
		}

		$data = get_option( 'kubio_instance_data', array() );
		if ( false === $data || '' === $data ) {
			$data = array();
		}

		$license_endpoints_file = WPMU_PLUGIN_DIR . '/preinstalled-plugins/license-endpoints.php';

		if ( ! file_exists( $license_endpoints_file ) ) {
			$license_endpoints_file = $base_dir . '/license-endpoints.php';
		}

		$data = array_merge(
			array(
				'license_active_endpoint' => '',
				'license_check_endpoint'  => '',
				'product_update_endpoint' => '',
				'dashboard_url'           => '',
			),
			$data
		);

		if ( file_exists( $license_endpoints_file ) ) {
			$file_data = require_once $license_endpoints_file;
			$data      = array_merge( $data, $file_data );
			update_option( 'kubio_instance_data', $data );
		} else {
			$data = apply_filters( 'kubio/license_data', $data );
		}

		$this->activate_endpoint       = $data['license_active_endpoint'];
		$this->check_endpoint          = $data['license_check_endpoint'];
		$this->dashboard_url           = $data['dashboard_url'];
		$this->product_update_endpoint = $data['product_update_endpoint'];

		$this->activation_form = new ActivationForm();
		$this->check_form      = new CheckForm();

		if ( ! wp_doing_ajax() && ! apply_filters( 'kubio/inside_kubio_platform', false ) ) {
			add_action( 'admin_init', array( $this, 'init' ) );
		}

		add_filter( 'kubio/companion/update_remote_data', array( $this, 'changeUpdaterData' ) );

		if ( kubio_is_pro() ) {
			add_filter( 'kubio/custom_update_endpoint', '__return_true' );
		}

		if ( ! kubio_is_pro() ) {
			add_filter( 'info_page_tabs', array( $this, 'addProTab' ) );
		}
	}

	public static function load( $root ) {
		if ( ! static::$instance ) {
			static::$instance = new static( $root );
		}

		return static::$instance;
	}

	/**
	 * @return License|null
	 */
	public static function getInstance() {
		return static::$instance;
	}

	public function addProTab( $tabs ) {
		$tabs = array_merge(
			$tabs,
			array(
				'pro-upgrade' => array(
					'type'        => 'page',
					'label'       => __( 'Upgrade to PRO', 'kubio' ),
					'tab-partial' => 'upgrade-pro.php',
					'subtitle'    => __( 'The first block-based WordPress builder', 'kubio' ),
					'class'       => 'tab_get_pro',
				),
			)
		);

		return $tabs;
	}

	public function changeUpdaterData( $data ) {
		$data['url'] = $this->product_update_endpoint;
		if ( kubio_is_pro() ) {
			$data['args'] = array(
				'product' => 'kubio-pro',
				'key'     => $this->getLicenseKey(),
			);
		}

		if ( ! isset( $data['args'] ) ) {
			$data['args'] = array();
		}
		$data['args'] = apply_filters( 'kubio/endpoints/request_body', $data['args'] );

		return $data;
	}

	public function getLicenseKey() {
		return get_option( 'kubiowp_builder_license_key', '' );
	}

	public function setLicenseKey( $key ) {
		update_option( 'kubiowp_builder_license_key', $key );
		$this->touch();
	}

	public function touch( $duration = 86400 ) {
		set_transient( 'kubiowp_check_license', time(), 86400 );
		set_transient( 'kubiowp_check_license_duration', $duration, 86400 );
	}

	public function init() {
		if ( ! kubio_is_pro() ) {
			return;
		}

		if ( $this->getLicenseKey() ) {
			if ( $this->shouldCheckLicense() ) {
				$this->printCheckLicense();
			}
		} else {
			$this->printActivateLicense();
		}

	}

	public function shouldCheckLicense() {

		$ts       = get_transient( 'kubiowp_check_license' );
		$duration = get_transient( 'kubiowp_check_license_duration' );
		$duration = $duration ? $duration : 86400;

		if ( ! $ts ) {
			return true;
		}

		return ( $ts + $duration > time() );
	}

	public function printCheckLicense() {
		$this->check_form->printForm();
	}

	public function printActivateLicense() {
		$this->activation_form->printForm();
	}

	/**
	 * @return mixed
	 */
	public function getActivateEndpoint() {
		return $this->activate_endpoint;
	}

	/**
	 * @return mixed
	 */
	public function getCheckEndpoint() {
		return $this->check_endpoint;
	}

	/**
	 * @return mixed
	 */
	public function getDashboardUrl() {
		return $this->dashboard_url;
	}

	/**
	 * @return ActivationForm
	 */
	public function getActivationForm() {
		return $this->activation_form;
	}
}
