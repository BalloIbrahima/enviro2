<?php

namespace Kubio;

use Kubio\Core\Utils as CoreUtils;

/**
 *
 *  Kubio migration purpose is to apply certain changes to existing sites besides new ones.
 *  The migrations that are available inside the /migrations folder in kubio plugin have the following nameing scheme: {index}-{callback}.php
 *  The {index} is to ensure the migration execution order.
 *  The {callback} is the function that will be called to execute the migration.
 *
 */
class Migrations {


	private static function getMigrations() {
		$files = glob( KUBIO_ROOT_DIR . '/migrations/*.php' );

		$migrations = array();
		foreach ( $files as $file ) {
			$migration = preg_replace( '#(.*)/migrations/(.*).php#', '$2', wp_normalize_path( $file ) );
			$migration = Migrations::parseMigration( $migration );

			if ( $migration ) {
				$migrations[] = $migration;
			}
		}

		return apply_filters( 'kubio/available_migrations', $migrations );

	}

	private static function parseMigration( $migration ) {
		preg_match( '#(\d+?)-(.*$)#', $migration, $matches );

		if ( count( $matches ) === 3 ) {
			return array(
				'slug'     => $migration,
				'callback' => $matches[2],
			);
		}

		return null;

	}
	public static function loadMissingMigrations() {

		$is_actived = Flags::get( 'kubio_activation_time' ) || Flags::get( 'kubio_pro_activation_time' );
		if ( ! $is_actived ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'REST_REQUEST ' ) && REST_REQUEST ) {
			return;
		}

		Migrations::executeMigrations();
	}

	private static function executeMigrations() {
		$available_migrations = Migrations::getMigrations();
		$executed_migrations  = kubio_get_global_data( 'migrations', array() );

		$callbacks = array();

		foreach ( $available_migrations as $migration ) {
			$slug     = $migration['slug'];
			$callback = $migration['callback'];

			if ( ! isset( $executed_migrations[ $slug ] ) ) {
				require_once KUBIO_ROOT_DIR . "/migrations/{$slug}.php";
				if ( ! function_exists( $callback ) ) {
					if ( CoreUtils::isDebug() ) {
						wp_die( "Migrations functon kubio_{$callback} does not exists" );
					}
					return; // leave migration process
				}
				$callbacks[ $slug ] = $callback;
			}
		}

		foreach ( $callbacks as $slug => $callback ) {
			try {
				call_user_func( $callback );
			} catch ( \Exception $e ) {
				if ( CoreUtils::isDebug() ) {
					wp_die( "Migrations {$callback} error" );
				}
			}
			$executed_migrations [ $slug ] = true;
		}

		kubio_set_global_data( 'migrations', $executed_migrations );

	}


	public static function load() {
		add_action( 'admin_init', array( Migrations::class, 'loadMissingMigrations' ) );
	}
}
