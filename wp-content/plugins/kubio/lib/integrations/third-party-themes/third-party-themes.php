<?php

use IlluminateAgnostic\Arr\Support\Arr;

if ( ! defined( 'KUBIO_3RD_PARTY_THEME_EDITOR_QUERY_PARAM' ) ) {
	define( 'KUBIO_3RD_PARTY_THEME_EDITOR_QUERY_PARAM', '__kubio-site-edit-iframe-preview' );
}

if ( ! defined( 'KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH' ) ) {
	define( 'KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH', KUBIO_ROOT_DIR . '/lib/integrations/third-party-themes/block-based-templates' );
}

function kubio_is_hybdrid_theme_iframe_preview() {
	return Arr::has( $_REQUEST, '__kubio-site-edit-iframe-preview' );
}


require_once __DIR__ . '/fallback-compatibility.php';
require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/customizer-options.php';
require_once __DIR__ . '/editor-hooks.php';
require_once __DIR__ . '/frontend-hooks.php';
require_once __DIR__ . '/templates-importer.php';
require_once __DIR__ . '/preview.php';

