<?php

namespace Templately\Utils;

use function get_transient;
use function set_transient;

class Database extends Base {

	public static function set_transient( $key, $value, $expiration = DAY_IN_SECONDS ) : bool {
		$key = '_templately_' . trim( $key );
		return set_transient( $key, $value, $expiration );
	}

	public static function get_transient( $key ){
		$key = '_templately_' . trim( $key );
		return get_transient( $key );
	}

}