<?php

namespace Kubio\DemoSites;

use ProteusThemes\WPContentImporter2\WPImporterLoggerCLI;

class DemoSitesLogger extends WPImporterLoggerCLI {
	/**
	 * Variable for front-end error display.
	 *
	 * @var string
	 */
	public $error_output = '';

	public $logger_file = '';

	/**
	 * Overwritten log function from WP_Importer_Logger_CLI.
	 *
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level level of reporting.
	 * @param string $message log message.
	 * @param array $context context to the log message.
	 */
	public function log( $level, $message, array $context = array() ) {
		// Save error messages for front-end display.
		$this->error_output( $level, $message, $context = array() );

		if ( $this->level_to_numeric( $level ) < $this->level_to_numeric( $this->min_level ) ) {
			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::log(
				sprintf(
					'[%s] %s',
					strtoupper( $level ),
					$message
				)
			);

			return;
		}

		$content = sprintf(
			'[%s] %s' . PHP_EOL,
			strtoupper( $level ),
			$message
		);

		DemoSitesHelpers::appendToFile( $content, $this->logger_file );

		if ( $level === 'critical' ) {
			DemoSitesHelpers::sendAjaxError( $content );
		}
	}


	/**
	 * Save messages for error output.
	 * Only the messages greater then Error.
	 *
	 * @param mixed $level level of reporting.
	 * @param string $message log message.
	 * @param array $context context to the log message.
	 */
	public function error_output( $level, $message, array $context = array() ) {
		if ( $this->level_to_numeric( $level ) < $this->level_to_numeric( 'error' ) ) {
			return;
		}

		$this->error_output .= sprintf(
			'[%s] %s<br>',
			strtoupper( $level ),
			$message
		);
	}
}
