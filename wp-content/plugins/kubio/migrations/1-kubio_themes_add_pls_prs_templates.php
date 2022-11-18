<?php

use Kubio\Core\Importer;

/**
 *
 *  Kubio migration.
 *  check if kubio supported themes needs have page-with-left-sidebar and page-with-right-sidebar templates to be installed.
 *
 */
function kubio_themes_add_pls_prs_templates() {
	if ( ! kubio_theme_has_kubio_block_support() ) {
		return;
	}

	$root                    = KUBIO_ROOT_DIR . '/defaults/supported-themes-templates';
	$page_with_left_sidebar  = file_get_contents( "{$root}/templates/page-with-left-sidebar.html" );
	$page_with_right_sidebar = file_get_contents( "{$root}/templates/page-with-right-sidebar.html" );
	$page_sidebar            = file_get_contents( "{$root}/parts/page-sidebar.html" );

	Importer::createTemplate( 'page-with-left-sidebar', $page_with_left_sidebar, false, 'kubio' );
	Importer::createTemplate( 'page-with-right-sidebar', $page_with_right_sidebar, false, 'kubio' );
	Importer::createTemplatePart( 'page-sidebar', $page_sidebar, false, 'kubio' );

}
