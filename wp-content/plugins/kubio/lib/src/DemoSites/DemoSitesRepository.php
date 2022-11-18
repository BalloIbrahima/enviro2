<?php

namespace Kubio\DemoSites;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\LodashBasic;
use Kubio\Core\Utils;
use Kubio\PluginsManager;

class DemoSitesRepository {

	const DEMO_SITES_TRANSIENT = 'kubio-demo-sites-repository';
	private static $instance   = null;
	public $demos              = array();
	public $demoParts 		   =    array();
	public $pluginsStates      = null;
	public function __construct() {

		$data = get_transient( self::DEMO_SITES_TRANSIENT );
		if ( $data !== false ) {
			$version = Arr::get( $data, 'version' );
			if ( $version !== KUBIO_VERSION ) {
				$this->prepareRetrieveDemoSites();
			}
			$this->demos = $data;
		} else {
			$this->prepareRetrieveDemoSites();
		}

		add_action( 'upgrader_process_complete', array( $this, 'onPluginUpgrade' ), 10, 2 );
		add_filter( 'kubio/demo-sites/list', array( $this, 'getDemoSitesList' ) );
		add_action( 'wp_ajax_kubio-demo-sites-retrieve', array( $this, 'actionRetrieveDemoSites' ) );
	}

	public function onPluginUpgrade( $upgrader, $hook_extra ) {
		if ( in_array( KUBIO_ENTRY_FILE, Arr::get( $hook_extra, 'plugins', array() ) ) ) {
			delete_transient( self::DEMO_SITES_TRANSIENT );
		}
	}

	public function actionRetrieveDemoSites() {
		return $this->retrieveDemoSites();
	}

	public function prepareRetrieveDemoSites() {
		$empty  = empty( $this->getDemoSitesList() );
		$reload = $empty && Arr::get( $_REQUEST, 'tab' ) === 'demo-sites' && Arr::get( $_REQUEST, 'page' ) === 'kubio-get-started';

		add_action(
			'admin_footer',
			function () use ( $reload ) {
				$fetch_url = add_query_arg(
					array( 'action' => 'kubio-demo-sites-retrieve' ),
					admin_url( 'admin-ajax.php' )
				);
				?>
			<script>
				window.fetch("<?php echo esc_js( $fetch_url ); ?>").then(()=>{
					if(<?php echo  $reload ? 'true' : 'false'; ?>){
						window.location.reload();
					}
				})
			</script>
				<?php
			}
		);
	}

	public static function getDemos( $with_internals = false ) {

		$demos = static::getInstance()->getDemoSitesList();

		if ( ! $with_internals ) {
			foreach ( $demos as $slug => $demo ) {
				if ( Arr::get( $demo, 'internal', false ) ) {
					unset( $demos[ $slug ] );
				}
			}
		}

		return  $demos;
	}

	public static function getPluginsStates() {
		$demoRepository = static::getInstance();

		if($demoRepository->pluginsStates) {
			return $demoRepository->pluginsStates;
		}
		$demos = $demoRepository::getDemos();

		$pluginsStates = array();

		foreach ( $demos as $demo ) {
			$plugins = Arr::get( $demo, 'plugins', array() );
			foreach ( $plugins as $plugin ) {
				$slug = Arr::get( $plugin, 'slug', null );
				if ( $slug && ! isset( $pluginsStates[ $slug ] ) ) {
					$pluginsStates[ $slug ] = PluginsManager::getInstance()->getPluginStatus( $slug );
				}
			}
		}

		$demoRepository->pluginsStates = $pluginsStates;

		return $pluginsStates;
	}

	/**
	 * @return DemoSitesRepository
	 */
	public static function getInstance() {
		static::load();

		return static::$instance;
	}

	public static function load() {
		if ( ! static::$instance ) {
			static::$instance = new DemoSitesRepository();
		}
	}

	public function getDemoSitesList() {
		return Arr::get( (array) $this->demos, 'demos', array() );
	}

	public function retrieveDemoSites( $print = true, $verifySSL = true, $scope = null ) {
		$demos = array();

		foreach ( $this->getLocalDemoSites() as $site ) {
			$slug = Arr::get( $site, 'slug', null );

			if ( $slug ) {
				$demos[ $slug ] = $site;
			}
		}

		foreach ( $this->getRemoteDemoSites( $verifySSL, $scope ) as $site ) {
			$slug = Arr::get( $site, 'slug', null );

			if ( $slug ) {
				$demos[ $slug ] = $site;
			}
		}

		// remove keys from assoc array
		$this->demos = array(
			'version' => KUBIO_VERSION,
			'demos'   => $demos,
		);

		set_transient( self::DEMO_SITES_TRANSIENT, $this->demos, DAY_IN_SECONDS );

		if ( $print ) {
			wp_send_json_success( $this->demos );
		}
	}

	private function getLocalDemoSites() {
		return apply_filters( 'kudio/demo-sites/local-demos', array() );
	}

	public function getRemoteDemoSites( $verifySSL = true, $scope = null ) {
		$url = Utils::getCloudUrl( 'api/project/demo-sites' );

		if ( $scope ) {
			$url = add_query_arg( 'scope', $scope, $url );
		}

		if ( defined( 'KUBIO_INCLUDE_TEST_TEMPLATES' ) && KUBIO_INCLUDE_TEST_TEMPLATES === true ) {
			$url = add_query_arg( 'tests', true, $url );
		}

		if ( ! $url ) {
			return array();
		}

		$response = wp_remote_get(
			$url,
			array(
				'sslverify' => $verifySSL,
			)
		);

		if ( $response instanceof \WP_Error ) {
			return array();
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );

		$response = json_decode( $body, true );

		$demos = $response['body'];

		//quick fix convert from cloud format to kubio format to be easier to implement.
		foreach ( $demos as &$demo ) {
			$plugins    = LodashBasic::get( $demo, 'plugins', array() );
			$newPlugins = array();
			foreach ( $plugins as $pluginName => $pluginLabel ) {
				$pluginNameParts = explode( '/', $pluginName );
				if ( count( $pluginNameParts ) > 0 ) {
					$newPlugins[] = array(
						'slug'  => $pluginNameParts[0],
						'label' => $pluginLabel,
					);
				}
			}
			$demo['plugins'] = $newPlugins;
		}
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array();
		}

		return $demos;
	}

}
