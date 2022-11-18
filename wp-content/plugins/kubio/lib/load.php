<?php

/** base */

use Kubio\Core\Activation;
use Kubio\Core\Deactivation;
use Kubio\Core\License\License;
use Kubio\Core\License\Updater;
use Kubio\DemoSites\DemoSites;
use Kubio\GoogleFontsLocalLoader;
use Kubio\Migrations;
use Kubio\NotificationsManager;

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/filters.php';
require_once __DIR__ . '/preview/index.php';
require_once __DIR__ . '/shortcodes/index.php';
require_once __DIR__ . '/global-data.php';
require_once __DIR__ . '/shapes/index.php';
require_once __DIR__ . '/api/index.php';
require_once __DIR__ . '/editor-assets.php';
require_once __DIR__ . '/frontend.php';
require_once __DIR__ . '/add-edit-in-kubio.php';
require_once __DIR__ . '/kubio-block-library.php';
require_once __DIR__ . '/kubio-editor.php';
require_once __DIR__ . '/admin-pages/pages.php';
require_once __DIR__ . '/menu/index.php';
require_once __DIR__ . '/customizer/index.php';
require_once KUBIO_BUILD_DIR . '/third-party-blocks/index.php';

require_once __DIR__ . '/src/DemoSites/WpCliCommand.php';

/** full site editing */
require_once __DIR__ . '/full-site-editing/default-template-types.php';
require_once __DIR__ . '/full-site-editing/block-templates.php';
require_once __DIR__ . '/full-site-editing/templates.php';
require_once __DIR__ . '/full-site-editing/template-parts-area.php';
require_once __DIR__ . '/full-site-editing/class-kubio-rest-template-part-controller.php';
require_once __DIR__ . '/full-site-editing/class-kubio-rest-template-controller.php';
require_once __DIR__ . '/full-site-editing/template-parts.php';
require_once __DIR__ . '/full-site-editing/template-loader.php';
require_once __DIR__ . '/full-site-editing/full-site-editing.php';
require_once __DIR__ . '/full-site-editing/get-block-templates.php';


function kubio_load_integrations() {
	$integrations_dir = __DIR__ . '/integrations';
	$integrations     = array_diff( scandir( $integrations_dir ), array( '.', '..' ) );

	foreach ( $integrations as $integration ) {
		$integration_entry = "{$integrations_dir}/{$integration}/{$integration}.php";
		if ( file_exists( $integration_entry ) ) {
			require_once $integration_entry;
		}
	}

}

function kubio_get_iframe_loader( $props = array() ) {
	$params = array_merge(
		array(
			'color'    => '',
			'size'     => '40px',
			'bg-color' => 'transparent',
			'message'  => '',
		),
		$props
	);

	foreach ( $params as $key => $value ) {
		$params[ $key ] = urlencode( $value );
	}

	$url = add_query_arg( $params, kubio_url( '/static/kubio-iframe-loader.html' ) );

	return sprintf( '<iframe style="border:none;pointer-events:none;user-select:none;display:block" allowtransparency="true" width="%2$s" height="%2$s" src="%1$s"></iframe>', $url, $params['size'] );
}

Updater::load( KUBIO_ENTRY_FILE );
License::load( KUBIO_ROOT_DIR );

kubio_load_integrations();

Activation::load();
Deactivation::load();
DemoSites::load();
NotificationsManager::load();
GoogleFontsLocalLoader::registerFontResolver();
Migrations::load();
