<?php
namespace Kubio\Core\License;

class RequestResponse {

	private $response;
	private $response_body;
	private $response_code;

	public function __construct( $response ) {
		$this->response      = $response;
		$this->response_body = json_decode( wp_remote_retrieve_body( $this->response ) );

		$this->response_code = wp_remote_retrieve_response_code( $this->response );

		if ( ! $this->response_body ) {
			$this->response_body         = new \stdClass();
			$this->response_body->errors = array(
				'body' => wp_remote_retrieve_body( $this->response ),
			);
			$this->response_body->status = 'error';
		}
	}

	public function getMessage( $implode = false ) {
		$message = array();
		if ( $this->isWPError() ) {
			$message = $this->getWPError();
		} else {
			if ( $this->isSuccess() ) {
				$message = $this->getResponseBody()->body;

			} else {
				$message = $this->getResponseBody()->errors;
			}
		}

		if ( $implode ) {
			$message = $this->flattenResponse( $message );

			return implode( ',', (array) $message );
		}

		return $message;
	}

	private function flattenResponse( $data = array() ) {
		$result = array();

		if ( ! is_array( $data ) ) {
			$data = array( $data );
		}

		foreach ( $data as $values ) {
			if ( is_object( $values ) ) {
				$values = (array) $values;
			}

			if ( is_array( $values ) ) {
				$result = array_merge( $result, $this->flattenResponse( $values ) );
			} else {
				$result[] = $values;
			}
		}

		return $result;
	}

	public function isSuccess() {
		return ( ! $this->isWPError() && $this->getResponseBody() && $this->getResponseBody()->status !== 'error' );
	}

	/**
	 * @return array|mixed|object
	 */
	public function getResponseBody() {
		return $this->response_body;
	}

	/**
	 * @return int|string
	 */
	public function getResponseCode() {

		if ( $this->isWPError() ) {
			return 403;
		}

		return $this->response_code;
	}

	public function isWPError() {
		return ( $this->response instanceof \WP_Error );
	}

	public function getWPError() {
		return $this->response->get_error_message();
	}

	public function isError() {
		return ( $this->isWPError() || ! $this->isSuccess() );
	}

}
