<?php

use IlluminateAgnostic\Str\Support\Arr;
use Kubio\DemoSites\DemoSitesImporter;
use Kubio\DemoSites\DemoSitesRepository;

/**
 * Implements example command.
 */
class DemoSites_Import_Command {

	/**
	 * Import a Kubio design.
	 *
	 * ## OPTIONS
	 *
	 * <design>
	 * : The slug or the url of the design to import.
	 *
	 * [--verify-ssl=<bool>]
	 * : Whatever or not to enforce SSL on requests.
	 * Default: true
	 *
	 * [--scope=<string>]
	 * : Whatever or not to enforce SSL on requests.
	 * Default: null
	 *
	 * ## EXAMPLES
	 *
	 *     wp kubio:import-design accounting-free
	 *     wp kubio:import-design https://static-assets.kubiobuilder.com/demo-sites/production/accounting-free/content.kds
	 *
	 * @when after_wp_load
	 * @param $args
	 * @param $assoc_args
	 * @throws \WP_CLI\ExitException
	 */
	public function __invoke( $args, $assoc_args ) {

		if ( ! defined( 'KUBIO_IS_STARTER_SITES_IMPORT' ) ) {
			define( 'KUBIO_IS_STARTER_SITES_IMPORT', true );
		}

		ini_set( 'max_execution_time', 0 );
		set_time_limit( 0 );

		if ( empty( $args[0] ) || ! is_string( $args[0] ) ) {
			\WP_CLI::error( 'This command expects the first argument to be an importable design slug.' );
			return;
		}

		// Interpret some associative arguments as booleans.
		foreach ( $assoc_args as $assoc_arg_key => $assoc_arg_value ) {

			switch ( $assoc_arg_key ) {
				case 'verify-ssl':
					$assoc_args[ $assoc_arg_key ] =
						boolval( json_decode( $assoc_arg_value ) );
					break;
			}
		}

		$defaults = array(
			'verify-ssl' => true,
		);

		$assoc_args = wp_parse_args( $assoc_args, $defaults );
		$design     = $args[0];

		$importer_args = array();

		$is_kds_url = filter_var( $design, FILTER_VALIDATE_URL )
		 && ( substr_compare( $design, '.kds', -4 ) === 0 || substr_compare( $design, '.kds.wxr', -8 ) === 0 );

		if ( $is_kds_url ) {
			$importer_args = array(
				'is_custom' => true,
				'kds_url'   => $design,
			);

			WP_CLI::line( 'Let\'s import "' . $design . '"' );
		} else {
			$repo = new DemoSitesRepository();

			$repo->retrieveDemoSites( false, $assoc_args['verify-ssl'], Arr::get( $assoc_args, 'scope', null ) );
			$data = get_transient( 'kubio-demo-sites-repository' );

			if ( ! Arr::get( (array) $data, "demos.{$design}" ) ) {
				\WP_CLI::error( '"' . $design . '" is not a valid design slug.' );
				return;
			}

			WP_CLI::line( 'Let\'s import "' . $design . '" ...' );

			$importer_args = array(
				'is_custom'            => false,
				'slug'                 => $design,
				'before_import_action' => 'init',
			);

			if ( ! empty( $assoc_args['non-ssl'] ) ) {
				$importer_args['allow--non-ssl'] = $assoc_args['non-ssl'];
			}
		}

		$importer = new DemoSitesImporter( 'cli', $importer_args );

		foreach ( $importer->getBeforeImportActions() as $key => $step ) {
			$importer_args['before_import_action'] = $step;
			$importer_args['first_call']           = $key === 0;
			$importer->executeBeforeImportAction( $step, false );
		}

		\WP_CLI::line( 'Start importing content' );

		$importer->cliImportDemoData( $importer_args );

		\WP_CLI::line( 'Finishing import' );

		WP_CLI::success( '"' . $design . '" was successfully imported!' );

		// remove scoped transient
		delete_transient( 'kubio-demo-sites-repository' );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'kubio:import-design', 'DemoSites_Import_Command' );
}
