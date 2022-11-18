<?php
namespace Kubio\Core\License;

class CheckForm {

	public function __construct() {
		add_action( 'wp_ajax_kubiowp-page-builder-check-license', array( $this, 'callCheckLicenseEndpoint' ) );
	}

	public function printForm() {
		wp_enqueue_script( 'wp-util' );
		add_action( 'admin_notices', array( $this, 'makeNotice' ) );
	}

	public function makeNotice() {
		License::getInstance()->getActivationForm()->makeActivateNotice( 'kubio-page-builder-check-license', array( 'hidden' ) );
	}

	public function callCheckLicenseEndpoint() {
		$response         = Endpoint::check();
		$response_message = $response->getMessage( true );

		if ( $response->isWPError() ) {
			$url              = esc_attr( License::getInstance()->getDashboardUrl() );
			$response_message = sprintf( __( 'There was an error calling Kubio License Server: <code>%1$s</code>. Please contact us for support on <a href="%2$s" target="_blank">Kubio</a> website', 'kubio' ), $response_message, $url );
		}

		if ( $response->isSuccess() ) {
			License::getInstance()->touch();
		} else {
			if ( ! $response->isWPError() && $response->getResponseCode() === 403 ) {
				delete_option( 'kubiowp_builder_license_key' );
				$response_message = sprintf( __( 'Current license key was removed! Reason: %s', 'kubio' ), $response_message );
			}
		}

		wp_send_json(
			array(
				'data'    => $response_message,
				'success' => $response->isSuccess(),
			),
			$response->getResponseCode()
		);
	}
}
