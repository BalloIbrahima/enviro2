<?php

namespace Templately\Utils;

class Plan extends Base {
	const ALL = 1;
	const STARTER = 2;
	const PRO = 3;

	public static function get( $plan = 'all' ) : int {
		$plan = strtoupper( $plan );
		return $plan === 'STARTER' ? self::STARTER : ( $plan === 'PRO' ? self::PRO : self::ALL );
	}
}