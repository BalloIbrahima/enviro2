<?php
if (!defined('MCDATAPATH')) exit;

if (!class_exists('BVPrependIPStore')) :
	class BVPrependIPStore {
		public $whitelistedIPs;
		public $blacklistedIPs;

		#TYPE
		const BLACKLISTED = 1;
		const WHITELISTED = 2;

		#CATEGORY
		const FW = 3;

		function __construct($confHash) {
			$this->whitelistedIPs = array_key_exists('whitelisted', $confHash) ? $confHash['whitelisted'] : array();
			$this->blacklistedIPs = array_key_exists('blacklisted', $confHash) ? $confHash['blacklisted'] : array();
		}

		public function isFWIPBlacklisted($ip) {
			return $this->checkIPPresent($ip, BVPrependIPStore::BLACKLISTED);
		}

		public function isFWIPWhitelisted($ip) {
			return $this->checkIPPresent($ip, BVPrependIPStore::WHITELISTED);
		}

		public function checkIPPresent($ip, $type) {
			$flag = false;

			switch($type) {

			case BVPrependIPStore::BLACKLISTED:
				if (isset($this->blacklistedIPs[$ip]))
					$flag = true;
				break;

			case BVPrependIPStore::WHITELISTED:
				if (isset($this->whitelistedIPs[$ip]))
					$flag = true;
				break;
			}

			return $flag;
		}

	}
endif;