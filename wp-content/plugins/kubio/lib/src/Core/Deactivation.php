<?php

namespace Kubio\Core;

use Kubio\Flags;

class Deactivation {

	private static $instance = null;

	public static function load() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'kubio/plugin_deactivated', array( $this, 'deactivate' ) );
	}

	public function deactivate() {

		$active_plugins = get_option( 'active_plugins', array() );

		$current_plugin_file = trim( str_replace( wp_normalize_path( WP_PLUGIN_DIR ), '', wp_normalize_path( KUBIO_ENTRY_FILE ) ), '/' );
		$active_plugins      = array_diff( $active_plugins, array( $current_plugin_file ) );

		// if another kubio plugin is still active ( pro or free version ) skip backup
		foreach ( $active_plugins as $active_plugin ) {
			if ( strpos( $active_plugin, 'kubio/' ) === 0 || strpos( $active_plugin, 'kubio-pro/' ) === 0 ) {
				return;
			}
		}

		$identifier = uniqid( 'bkp-deactivation-' );
		$backup     = new Backup();
		$result     = $backup->backupSiteStructureAndStyle( $identifier );

		// stop deactivating block templates is the backup fails
		if ( is_wp_error( $result ) ) {
			return;
		}

		$template = get_stylesheet();
		Flags::setSetting( "deactivation_backup_key.{$template}", $identifier );
		$this->deleteBlockTemplates( 'wp_template' );
		$this->deleteBlockTemplates( 'wp_template_part' );

	}

	private function deleteBlockTemplates( $post_type = 'wp_template' ) {
		$entities = get_block_templates( array(), $post_type );

		foreach ( $entities as $entity ) {
			wp_delete_post( $entity->wp_id, true );
		}
	}

}
