<?php
if (! (defined('ABSPATH') || defined('MCDATAPATH')) ) exit;
if (!class_exists('BVProtectBase')) :

class BVProtectBase {
	public static function getIP($ipHeader) {
		$ip = '127.0.0.1';
		if ($ipHeader && is_array($ipHeader)) {
			if (array_key_exists($ipHeader['hdr'], $_SERVER)) {
				$_ips = preg_split("/(,| |\t)/", $_SERVER[$ipHeader['hdr']]);
				if (array_key_exists(intval($ipHeader['pos']), $_ips)) {
					$ip = $_ips[intval($ipHeader['pos'])];
				}
			}
		} else if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		$ip = trim($ip);
		if (preg_match('/^\[([0-9a-fA-F:]+)\](:[0-9]+)$/', $ip, $matches)) {
			$ip = $matches[1];
		} elseif (preg_match('/^([0-9.]+)(:[0-9]+)$/', $ip, $matches)) {
			$ip = $matches[1];
		}

		return $ip;
	}

	public static function hasIPv6Support() {
		return defined('AF_INET6');
	}

	public static function isValidIP($ip) {
		return filter_var($ip, FILTER_VALIDATE_IP) !== false;
	}

	public static function bvInetPton($ip) {
		$pton = self::isValidIP($ip) ? (self::hasIPv6Support() ? inet_pton($ip) : self::_bvInetPton($ip)) : false;
		return $pton;
	}

	public static function _bvInetPton($ip) {
		if (preg_match('/^(?:\d{1,3}(?:\.|$)){4}/', $ip)) {
			$octets = explode('.', $ip);
			$bin = chr($octets[0]) . chr($octets[1]) . chr($octets[2]) . chr($octets[3]);
			return $bin;
		}

		if (preg_match('/^((?:[\da-f]{1,4}(?::|)){0,8})(::)?((?:[\da-f]{1,4}(?::|)){0,8})$/i', $ip)) {
			if ($ip === '::') {
				return "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
			}
			$colon_count = substr_count($ip, ':');
			$dbl_colon_pos = strpos($ip, '::');
			if ($dbl_colon_pos !== false) {
				$ip = str_replace('::', str_repeat(':0000',
					(($dbl_colon_pos === 0 || $dbl_colon_pos === strlen($ip) - 2) ? 9 : 8) - $colon_count) . ':', $ip);
				$ip = trim($ip, ':');
			}

			$ip_groups = explode(':', $ip);
			$ipv6_bin = '';
			foreach ($ip_groups as $ip_group) {
				$ipv6_bin .= pack('H*', str_pad($ip_group, 4, '0', STR_PAD_LEFT));
			}

			return strlen($ipv6_bin) === 16 ? $ipv6_bin : false;
		}

		if (preg_match('/^(?:\:(?:\:0{1,4}){0,4}\:|(?:0{1,4}\:){5})ffff\:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $matches)) {
			$octets = explode('.', $matches[1]);
			return chr($octets[0]) . chr($octets[1]) . chr($octets[2]) . chr($octets[3]);
		}

		return false;
	}

	public static function isIPInRange($start_ip_range, $end_ip_range, $ip) {
		$bin_ip = null;
		if ($ip) {
			$bin_ip = self::bvInetPton($ip);
		}
		if ($bin_ip && $bin_ip >= self::bvInetPton($start_ip_range)
				&& $bin_ip <= self::bvInetPton($end_ip_range)) {
			return true;
		}
		return false;
	}

	public static function isPrivateIP($ip) {
		$private_ip_ranges = array(
			array("10.0.0.0", "10.255.255.255"),
			array("172.16.0.0", "172.31.255.255"),
			array("192.168.0.0", "192.168.255.255"),
			array("127.0.0.1", "127.255.255.255"),
			array("::1","::1"),
			array("fc00::","fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff")
		);

		$result = false;
		foreach ($private_ip_ranges as $ip_range) {
			$result = self::isIPInRange($ip_range[0], $ip_range[1], $ip);
			if($result) {
				return $result;
			}
		}
		return $result;
	}
}
endif;