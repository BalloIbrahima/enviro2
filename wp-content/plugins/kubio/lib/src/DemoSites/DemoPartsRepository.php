<?php

namespace Kubio\DemoSites;

class DemoPartsRepository {

	private static $demoParts  = null;
	const DEMO_PARTS_TRANSIENT = 'kubio-demo-parts-repository';
	private static $instance   = null;

	public function __construct() {
		$this->addRestEndPoints();
		$this->loadData();
	}

	public function loadData() {
		$data = get_transient( self::DEMO_PARTS_TRANSIENT );
		if ( $data !== false ) {
			static::$demoParts = $data;
		}
	}

	public function getDemoParts() {
		return static::$demoParts;
	}

	public function saveDemoParts( \WP_REST_Request $request  ) {
		$demoParts = $request->get_param('demoParts');
		if(isset($demoParts) && !empty($demoParts)) {
			set_transient( static::DEMO_PARTS_TRANSIENT, $demoParts );
		}

	}
	public function addRestEndPoints() {
		add_action(
			'rest_api_init',
			function () {
				$namespace = 'kubio/v1';

				register_rest_route(
					$namespace,
					'/demo-sites/demo-parts',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'saveDemoParts' ),
						'permission_callback' => function () {
							return current_user_can( 'edit_theme_options' );
						},

					)
				);
			}
		);

	}

	public static function getInstance() {
		if ( ! self::$instance ) {

			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function load() {
		return self::getInstance();
	}

}
