<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVProtectCallback')) :

require_once dirname( __FILE__ ) . '/../../protect/wp/protect.php';
require_once dirname( __FILE__ ) . '/../../protect/fw/config.php';
require_once dirname( __FILE__ ) . '/../../protect/wp/lp/config.php';

class BVProtectCallback extends BVCallbackBase {
	public $db;
	public $settings;

	const PROTECT_WING_VERSION = 1.0;

	public function __construct($callback_handler) {
		$this->db = $callback_handler->db;
		$this->settings = $callback_handler->settings;
	}

	public function serverConfig() {
		return array(
			'software' => $_SERVER['SERVER_SOFTWARE'],
			'sapi' => (function_exists('php_sapi_name')) ? php_sapi_name() : false,
			'has_apache_get_modules' => function_exists('apache_get_modules'),
			'posix_getuid' => (function_exists('posix_getuid')) ? posix_getuid() : null,
			'uid' => (function_exists('getmyuid')) ? getmyuid() : null,
			'user_ini' => ini_get('user_ini.filename'),
			'php_major_version' => PHP_MAJOR_VERSION
		);
	}

	public function unBlockLogins() {
		$this->settings->deleteTransient('bvlp_block_logins');
		$this->settings->setTransient('bvlp_allow_logins', 'true', 1800);
		return $this->settings->getTransient('bvlp_allow_logins');
	}

	public function blockLogins($time) {
		$this->settings->deleteTransient('bvlp_allow_logins');
		$this->settings->setTransient('bvlp_block_logins', 'true', $time);
		return $this->settings->getTransient('bvlp_block_logins');
	}

	public function unBlockIP($ip, $attempts, $time) {
		$transient_name = BVWPLP::$unblock_ip_transient.$ip;
		$this->settings->setTransient($transient_name, $attempts, $time);
		return $this->settings->getTransient($transient_name);
	}

	public function process($request) {
		$bvinfo = new BVInfo($this->settings);
		$params = $request->params;

		switch ($request->method) {
		case "gtipprobeinfo":
			$resp = array();
			$headers = $params['hdrs'];
			$hdrsinfo = array();
			if ($headers && is_array($headers)) {
				foreach($headers as $hdr) {
					if (array_key_exists($hdr, $_SERVER)) {
						$hdrsinfo[$hdr] = $_SERVER[$hdr];
					}
				}
			}
			$resp["hdrsinfo"] = $hdrsinfo;
			break;
		case "gtrulcnf":
			$resp = array('conf' => $this->settings->getOption('bvruleset'));
			break;
		case "clrrulcnf":
			$this->settings->deleteOption('bvruleset');
			$resp = array("clearconfig" => true);
			break;
		case "dorulcnf":
			$this->settings->updateOption('bvruleset', $params['conf']);
			$resp = array('conf' => $this->settings->getOption('bvruleset'));
			break;
		case "gtraddr":
			$raddr = array_key_exists('REMOTE_ADDR', $_SERVER) ? $_SERVER['REMOTE_ADDR'] : false;
			$resp = array("raddr" => $raddr);
			break;
		case "svrcnf":
			$resp = array("serverconfig" => $this->serverConfig());
			break;
		case "unblklogins":
			$resp = array("unblocklogins" => $this->unBlockLogins());
			break;
		case "blklogins":
			$time = array_key_exists('time', $params) ? $params['time'] : 1800;
			$resp = array("blocklogins" => $this->blockLogins($time));
			break;
		case "unblkip":
			$resp = array("unblockip" => $this->unBlockIP($params['ip'], $params['attempts'], $params['time']));
			break;
		case "rmwatchtime":
			$this->settings->deleteOption('bvwatchtime');
			$resp = array("rmwatchtime" => !$bvinfo->getWatchTime());
			break;
		default:
			$resp = false;
		}

		return $resp;
	}
}
endif;