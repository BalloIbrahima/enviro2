<?php

namespace Kubio\DemoSites;

use IlluminateAgnostic\Arr\Support\Arr;

class DemoSitesHelpers {

	const IMPORT_TRANSIENT            = 'kubio_importer_data';
	private static $import_start_time = 0;

	/**
	 * Check if the AJAX call is valid.
	 */
	public static function verifyAjaxCall() {
		check_ajax_referer( 'kubio-ajax-demo-site-verification', 'nonce' );

		// Check if user has the WP capability to import data.
		if ( ! current_user_can( 'import' ) ) {
			wp_die(
				wp_kses_post(
					sprintf(
					/* translators: %1$s - opening div and paragraph HTML tags, %2$s - closing div and paragraph HTML tags. */
						__( '%1$sYour user role isn\'t high enough. You don\'t have permission to import demo data.%2$s', 'kubio' ),
						'<div class="notice  notice-error"><p>',
						'</p></div>'
					)
				)
			);
		}
	}

	/**
	 * Set the transient with the current importer data.
	 *
	 * @param array $data Data to be saved to the transient.
	 */
	public static function setImportDataTransient( $data ) {
		set_transient( DemoSitesHelpers::IMPORT_TRANSIENT, $data, 0.1 * HOUR_IN_SECONDS );
	}

	public static function setImportStartTime() {
		self::$import_start_time = time();
	}

	/**
	 * Get log file path
	 *
	 * @return string, path to the log file
	 */
	public static function getLogFile() {
		$upload_dir  = wp_upload_dir();
		$upload_path = trailingslashit( $upload_dir['path'] );

		$time     = static::$import_start_time;
		$log_path = "{$upload_path}kubio-demo-import/log_file_{$time}.txt";

		self::registerFileAsMediaAttachment( $log_path );

		return $log_path;
	}

	/**
	 * Register file as attachment to the Media page.
	 *
	 * @param string $file_path log file path.
	 *
	 * @return void
	 */
	public static function registerFileAsMediaAttachment( $file_path ) {
		// Check the type of file.
		$log_mimes = array( 'txt' => 'text/plain' );
		$filetype  = wp_check_filetype( basename( $file_path ), $log_mimes );

		$upload_dir = wp_upload_dir();
		$upload_url = trailingslashit( $upload_dir['url'] );

		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => $upload_url . basename( $file_path ),
			'post_mime_type' => $filetype['type'],
			'post_title'     => esc_html__( 'Kubio Demo Import - ', 'kubio' ) . preg_replace( '/\.[^.]+$/', '', basename( $file_path ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the file as attachment in Media page.
		$attach_id = wp_insert_attachment( $attachment, $file_path );
	}

	public static function processUploadedFiles( $file, $path ) {

	}

	public static function downloadImportFile( $url ) {
		if ( empty( $url ) ) {
			return new \WP_Error(
				'missing_url',
				__( 'Missing URL for downloading a file!', 'kubio' )
			);
		}

		// Get file content from the server.
		$response = wp_remote_get( $url );

		// Test if the get request was not successful.
		if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
			// Collect the right format of error data (array or WP_Error).
			$response_error = static::getErrorMessageFromResponse( $response );

			return new \WP_Error(
				'download_error',
				sprintf( /* translators: %1$s and %3$s - strong HTML tags, %2$s - file URL, %4$s - br HTML tag, %5$s - error code, %6$s - error message. */
					__( 'An error occurred while fetching file from: %1$s%2$s%3$s!%4$sReason: %5$s - %6$s.', 'kubio' ),
					'<strong>',
					$url,
					'</strong>',
					'<br>',
					$response_error['error_code'],
					$response_error['error_message']
				) . '<br>'
			);
		}

		$content = wp_remote_retrieve_body( $response );

		$upload_dir  = wp_upload_dir();
		$upload_path = trailingslashit( $upload_dir['path'] );

		//basename($url);
		$time = static::$import_start_time;
		$path = "{$upload_path}/kubio-demo-import/demo-site-{$time}.wxr";

		$file_data   = unserialize( $content );
		$wxr_content = Arr::get( $file_data, 'content', '' );

		unset( $file_data['content'] );
		$import_options = $file_data;

		$wxr_path = static::writeFile( $wxr_content, $path );

		return array( $wxr_path, $import_options );

	}

	public static function useUploadedKDSFile( $kds_file ) {

		$upload_dir  = wp_upload_dir();
		$upload_path = trailingslashit( $upload_dir['path'] );

		//basename($url);
		$time = static::$import_start_time;
		$path = "{$upload_path}/kubio-demo-import/demo-site-{$time}.wxr";

		$content     = file_get_contents( $kds_file );
		$file_data   = unserialize( $content );
		$wxr_content = Arr::get( $file_data, 'content', '' );

		unset( $file_data['content'] );
		$import_options = $file_data;

		$wxr_path = static::writeFile( $wxr_content, $path );

		return array( $wxr_path, $import_options );

	}

	/**
	 * Helper function: get the right format of response errors.
	 *
	 * @param array|WP_Error $response Array or WP_Error or the response.
	 *
	 * @return array Error code and error message.
	 */
	private static function getErrorMessageFromResponse( $response ) {
		$response_error = array();

		if ( is_array( $response ) ) {
			$response_error['error_code']    = $response['response']['code'];
			$response_error['error_message'] = $response['response']['message'];
		} else {
			$response_error['error_code']    = $response->get_error_code();
			$response_error['error_message'] = $response->get_error_message();
		}

		return $response_error;
	}

	/**
	 * Write content to a file.
	 *
	 * @param string $content content to be saved to the file.
	 * @param string $file_path file path where the content should be saved.
	 *
	 * @return string|\WP_Error path to the saved file or WP_Error object with error message.
	 */
	public static function writeFile( $content, $file_path ) {
		// Verify WP file-system credentials.
		$verified_credentials = self::checkWpFileSystemCredentials();

		if ( is_wp_error( $verified_credentials ) ) {
			return $verified_credentials;
		}

		if ( ! file_exists( dirname( $file_path ) ) ) {
			mkdir( dirname( $file_path ), 0777, true );
		}

		if ( false === file_put_contents( $file_path, $content ) ) {
			return new \WP_Error(
				'failed_writing_file_to_server',
				sprintf( /* translators: %1$s - br HTML tag, %2$s - file path */
					__( 'An error occurred while writing file to your server! Tried to write a file to: %1$s%2$s.', 'kubio' ),
					'<br>',
					$file_path
				)
			);
		}

		// Return the file path on successful file write.
		return $file_path;
	}

	/**
	 * Helper function: check for WP file-system credentials needed for reading and writing to a file.
	 *
	 * @return boolean|\WP_Error
	 */
	private static function checkWpFileSystemCredentials() {
		// Check if the file-system method is 'direct', if not display an error.
		if ( ! ( 'direct' === get_filesystem_method() ) ) {
			return new \WP_Error(
				'no_direct_file_access',
				sprintf( /* translators: %1$s and %2$s - strong HTML tags, %3$s - HTML link to a doc page. */
					__( 'This WordPress site does not have %1$sdirect%2$s write file access. This plugin needs it in order to save the demo import files to the upload directory of your site. You can change this setting with these instructions: %3$s.', 'kubio' ),
					'<strong>',
					'</strong>',
					'<a href="https://wordpress.org/support/article/editing-wp-config-php/#override-of-default-file-permissions" target="_blank">How to set <strong>direct</strong> filesystem method</a>'
				)
			);
		}

		return true;
	}

	/**
	 * Write the error to the log file and send the AJAX response.
	 *
	 * @param string $error_text text to display in the log file and in the AJAX response.
	 * @param string $log_file_path path to the log file.
	 * @param string $separator title separating the old and new content.
	 */
	public static function logErrorAndSendAjaxResponse( $error_text, $log_file_path, $separator = '' ) {
		// Add this error to log file.
		$log_added = self::appendToFile(
			$error_text,
			$log_file_path,
			$separator
		);

		// Send JSON Error response to the AJAX call.
		static::sendAjaxError( $error_text );
	}

	/**
	 * Append content to the file.
	 *
	 * @param string $content content to be saved to the file.
	 * @param string $file_path file path where the content should be saved.
	 * @param string $separator_text separates the existing content of the file with the new content.
	 *
	 * @return boolean|\WP_Error, path to the saved file or WP_Error object with error message.
	 */
	public static function appendToFile( $content, $file_path, $separator_text = '' ) {
		// Verify WP file-system credentials.
		$verified_credentials = self::checkWpFileSystemCredentials();

		if ( is_wp_error( $verified_credentials ) ) {
			return $verified_credentials;
		}

		$existing_data = '';
		if ( file_exists( $file_path ) ) {
			$existing_data = file_get_contents( $file_path );
		}

		// Style separator.
		$separator = PHP_EOL . '---' . $separator_text . '---' . PHP_EOL;

		if ( ! file_exists( dirname( $file_path ) ) ) {
			mkdir( dirname( $file_path ), 0777, true );
		}

		if ( ! file_put_contents( $file_path, $existing_data . $separator . $content . PHP_EOL ) ) {
			return new \WP_Error(
				'failed_writing_file_to_server',
				sprintf( /* translators: %1$s - br HTML tag, %2$s - file path */
					__( 'An error occurred while writing file to your server! Tried to write a file to: %1$s%2$s.', 'one-click-demo-import' ),
					'<br>',
					$file_path
				)
			);
		}

		return true;
	}

	public static function sendAjaxError( $message ) {

		if ( is_wp_error( $message ) ) {
			/** @var  \WP_Error $message */

			$data = array();

			foreach ( $message->get_error_codes() as $code ) {
				$data[] = "<strong>$code</strong>: " . $message->get_error_message( $code );
			}

			wp_send_json_error( array( 'error' => $data ) );
		}

		wp_send_json_error( array( 'error' => $message ) );
	}
}
