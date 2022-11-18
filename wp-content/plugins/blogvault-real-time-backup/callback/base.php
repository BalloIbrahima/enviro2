<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVCallbackBase')) :

class BVCallbackBase {

	public static $wing_infos = array("MANAGE_WING_VERSION" => '1.0',
		"ACTLOG_WING_VERSION" => '1.0',
		"DYNSYNC_WING_VERSION" => '1.0',
		"UPGRADER_WING_VERSION" => '1.0',
		"BRAND_WING_VERSION" => '1.0',
		"DB_WING_VERSION" => '1.1',
		"ACCOUNT_WING_VERSION" => '1.1',
		"MISC_WING_VERSION" => '1.2',
		"FS_WING_VERSION" => '1.2',
		"INFO_WING_VERSION" => '1.5',
		"WATCH_WING_VERSION" => '1.0',
		"FS_WRITE_WING_VERSION" => '1.0',
		"IPSTORE_WING_VERSION" => '1.0',
		"PROTECT_WING_VERSION" => '1.0',
		);

	public function objectToArray($obj) {
		return json_decode(json_encode($obj), true);
	}

	public function base64Encode($data, $chunk_size) {
		if ($chunk_size) {
			$out = "";
			$len = strlen($data);
			for ($i = 0; $i < $len; $i += $chunk_size) {
				$out .= base64_encode(substr($data, $i, $chunk_size));
			}
		} else {
			$out = base64_encode($data);
		}
		return $out;
	}
}
endif;