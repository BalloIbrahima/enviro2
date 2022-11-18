<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVAccount')) :
	class BVAccount {
		public $settings;
		public $public;
		public $secret;
		public $sig_match;
		public static $api_public_key = 'bvApiPublic';
		public static $accounts_list = 'bvAccountsList';
	
		public function __construct($settings, $public, $secret) {
			$this->settings = $settings;
			$this->public = $public;
			$this->secret = $secret;
		}

		public static function find($settings, $public) {
			$accounts = self::allAccounts($settings);
			if (array_key_exists($public, $accounts) && isset($accounts[$public]['secret'])) {
				$secret = $accounts[$public]['secret'];
			}
			if (empty($secret) || (strlen($secret) < 32)) {
				return null;
			}
			return new self($settings, $public, $secret);
		}

		public static function update($settings, $allAccounts) {
			$settings->updateOption(self::$accounts_list, $allAccounts);
		}

		public static function randString($length) {
			$chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

			$str = "";
			$size = strlen($chars);
			for( $i = 0; $i < $length; $i++ ) {
				$str .= $chars[rand(0, $size - 1)];
			}
			return $str;
		}

		public static function sanitizeKey($key) {
			return preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
		}

		public static function apiPublicAccount($settings) {
			$pubkey = $settings->getOption(self::$api_public_key);
			return self::find($settings, $pubkey);
		}

		public static function updateApiPublicKey($settings, $pubkey) {
			$settings->updateOption(self::$api_public_key, $pubkey);
		}

		public static function getApiPublicKey($settings) {
			return $settings->getOption(self::$api_public_key);
		}

		public static function getPlugName($settings) {
			$bvinfo = new BVInfo($settings);
			return $bvinfo->plugname;
		}

		public static function allAccounts($settings) {
			$accounts = $settings->getOption(self::$accounts_list);
			if (!is_array($accounts)) {
				$accounts = array();
			}
			return $accounts;
		}

		public static function accountsByPlugname($settings) {
			$accounts = self::allAccounts($settings);
			$accountsByPlugname = array();
			$plugname = self::getPlugName($settings);
			foreach ($accounts as $pubkey => $value) {
				if (array_key_exists($plugname, $value) && $value[$plugname] == 1) {
					$accountsByPlugname[$pubkey] = $value;
				}
			}
			return $accountsByPlugname;
		}

		public static function accountsByType($settings, $account_type) {
			$accounts = self::allAccounts($settings);
			$accounts_by_type = array();
			foreach ($accounts as $pubkey => $value) {
				if (array_key_exists('account_type', $value) && $value['account_type'] === $account_type) {
					$accounts_by_type[$pubkey] = $value;
				}
			}
			return $accounts_by_type;
		}

		public static function accountsByGid($settings, $account_gid) {
			$accounts = self::allAccounts($settings);
			$accounts_by_gid = array();
			foreach ($accounts as $pubkey => $value) {
				if (array_key_exists('account_gid', $value) && $value['account_gid'] === $account_gid) {
					$accounts_by_gid[$pubkey] = $value;
				}
			}
			return $accounts_by_gid;
		}

		public static function accountsByPattern($settings, $search_key, $search_pattern) {
			$accounts = self::allAccounts($settings);
			$accounts_by_pattern = array();
			foreach ($accounts as $pubkey => $value) {
				if (array_key_exists($search_key, $value) && preg_match($search_pattern, $value[$search_key]) == 1) {
					$accounts_by_pattern[$pubkey] = $value;
				}
			}
			return $accounts_by_pattern;
		}

		public static function isConfigured($settings) {
			$accounts = self::accountsByPlugname($settings);
			return (sizeof($accounts) >= 1);
		}

		public static function setup($settings) {
			$bvinfo = new BVInfo($settings);
			$settings->updateOption($bvinfo->plug_redirect, 'yes');
			$settings->updateOption('bvActivateTime', time());
		}

		public function authenticatedUrl($method) {
			$bvinfo = new BVInfo($this->settings);
			$qstr = http_build_query($this->newAuthParams($bvinfo->version));
			return $bvinfo->appUrl().$method."?".$qstr;
		}

		public function newAuthParams($version) {
			$bvinfo = new BVInfo($this->settings);
			$args = array();
			$time = time();
			$sig = sha1($this->public.$this->secret.$time.$version);
			$args['sig'] = $sig;
			$args['bvTime'] = $time;
			$args['bvPublic'] = $this->public;
			$args['bvVersion'] = $version;
			$args['sha1'] = '1';
			$args['plugname'] = $bvinfo->plugname;
			return $args;
		}

		public static function addAccount($settings, $public, $secret) {
			$accounts = self::allAccounts($settings);
			if (!isset($public, $accounts)) {
				$accounts[$public] = array();
			}
			$accounts[$public]['secret'] = $secret;
			self::update($settings, $accounts);
		}

		public function info() {
			return array(
				"public" => substr($this->public, 0, 6),
				"sigmatch" => substr($this->sig_match, 0, 6)
			);
		}

		public static function getSigMatch($request, $secret) {
			$method = $request->method;
			$time = $request->time;
			$version = $request->version;
			if ($request->is_sha1) {
				$sig_match = sha1($method.$secret.$time.$version);
			} else {
				$sig_match = md5($method.$secret.$time.$version);
			}
			return $sig_match;
		}

		public function authenticate($request) {
			$time = $request->time;
			if ($time < intval($this->settings->getOption('bvLastRecvTime')) - 300) {
				return false;
			}
			$this->sig_match = self::getSigMatch($request, $this->secret);
			if ($this->sig_match !== $request->sig) {
				return false;
			}
			$this->settings->updateOption('bvLastRecvTime', $time);
			return 1;
		}

		public function updateInfo($info) {
			$accounts = self::allAccounts($this->settings);
			$account_type = $info["account_type"];
			$pubkey = $info['pubkey'];
			if (!array_key_exists($pubkey, $accounts)) {
				$accounts[$pubkey] = array();
			}
			if (array_key_exists('secret', $info)) {
				$accounts[$pubkey]['secret'] = $info['secret'];
			}
			$accounts[$pubkey]['account_gid'] = $info['account_gid'];
			$accounts[$pubkey]['lastbackuptime'] = time();
			if (isset($info["speed_plugname"])) {
				$speed_plugname = $info["speed_plugname"];
				$accounts[$pubkey][$speed_plugname] = true;
			}
			if (isset($info["plugname"])) {
				$plugname = $info["plugname"];
				$accounts[$pubkey][$plugname] = true;
			}
			$accounts[$pubkey]['account_type'] = $account_type;
			$accounts[$pubkey]['url'] = $info['url'];
			$accounts[$pubkey]['email'] = $info['email'];
			self::update($this->settings, $accounts);
		}

		public static function remove($settings, $pubkey) {
			$accounts = self::allAccounts($settings);
			if (array_key_exists($pubkey, $accounts)) {
				unset($accounts[$pubkey]);
				self::update($settings, $accounts);
				return true;
			}
			return false;
		}

		public static function removeByAccountType($settings, $account_type) {
			$accounts = BVAccount::accountsByType($settings, $account_type);
			if (sizeof($accounts) >= 1) {
				foreach ($accounts as $pubkey => $value) {
					BVAccount::remove($settings, $pubkey);
				}
				return true;
			}
			return false;
		}

		public static function removeByAccountGid($settings, $account_gid) {
			$accounts = BVAccount::accountsByGid($settings, $account_gid);
			if (sizeof($accounts) >= 1) {
				foreach ($accounts as $pubkey => $value) {
					BVAccount::remove($settings, $pubkey);
				}
				return true;
			}
			return false;
		}

		public static function exists($settings, $pubkey) {
			$accounts = self::allAccounts($settings);
			return array_key_exists($pubkey, $accounts);
		}
	}
endif;