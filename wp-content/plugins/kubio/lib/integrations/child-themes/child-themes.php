<?php

use Kubio\Core\Activation;
use Kubio\Core\Importer;

function kubio_sync_child_theme_templates_and_parts() {
	$stylesheet = get_stylesheet();
	$template   = get_template();

	if ( $stylesheet !== $template ) {

		$parent_templates = kubio_get_block_templates( array( 'theme' => $template ), 'wp_template' );
		foreach ( $parent_templates as $parent_template ) {
			$source = kubio_retrieve_template_source( $parent_template );
			$blocks = parse_blocks( $parent_template->content );
			$blocks = kubio_blocks_update_template_parts_theme( $blocks, $stylesheet );
			Importer::createTemplate( $parent_template->slug, kubio_serialize_blocks( $blocks ), false, $source );
		}

		$parent_templates = kubio_get_block_templates( array( 'theme' => $template ), 'wp_template_part' );
		foreach ( $parent_templates as $parent_template ) {
			$source = kubio_retrieve_template_source( $parent_template );
			Importer::createTemplatePart( $parent_template->slug, $parent_template->content, false, $source );
		}
	}

}


function kubio_sync_child_theme_global_data() {
	$stylesheet = get_stylesheet();
	$template   = get_template();

	if ( $stylesheet !== $template ) {
		$has_global_data = kubio_has_global_data( $stylesheet );

		if ( ! $has_global_data ) {
			$global_data = kubio_get_theme_global_data_content( $template );
			if ( $global_data ) {
				Activation::skipAfterSwitchTheme();
				kubio_replace_global_data_content( $global_data );
			}
		}
	}

}

add_action( 'admin_init', 'kubio_sync_child_theme_templates_and_parts', 10 );

add_action( 'switch_theme', 'kubio_sync_child_theme_global_data', 5 ); // priority should be less than 10 to execute earlyear
add_action( 'switch_theme', 'kubio_sync_child_theme_templates_and_parts', 5 ); // priority should be less than 10 to execute earlyear
