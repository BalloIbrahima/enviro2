<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('BVIPStore')) :

	class BVIPStore {

		public $db;
		public static $name = 'ip_store';

		#TYPE
		const BLACKLISTED = 1;
		const WHITELISTED = 2;

		#CATEGORY
		const FW = 3;
		const LP = 4;

		function __construct($db) {
			$this->db = $db;
		} 

		function init() {
			add_action('clear_ip_store', array($this, 'clearConfig'));
		}

		public function clearConfig() {
			$this->db->dropBVTable(BVIPStore::$name);
		}

		public function isLPIPBlacklisted($ip) {
			return $this->checkIPPresent($ip, BVIPStore::BLACKLISTED, BVIPStore::LP);
		}

		public function isLPIPWhitelisted($ip) {
			return $this->checkIPPresent($ip, BVIPStore::WHITELISTED, BVIPStore::LP);
		}


		public function isFWIPBlacklisted($ip) {
			return $this->checkIPPresent($ip, BVIPStore::BLACKLISTED, BVIPStore::FW);
		}

		public function isFWIPWhitelisted($ip) {
			return $this->checkIPPresent($ip, BVIPStore::WHITELISTED, BVIPStore::FW);
		}

		public function checkIPPresent($ip, $type, $category) {
			$db = $this->db;
			$table = $db->getBVTable(BVIPStore::$name);
			if ($db->isTablePresent($table)) {
				$binIP = BVProtectBase::bvInetPton($ip);
				if ($binIP !== false) {
					$category_str = ($category == BVIPStore::FW) ? "`is_fw` = true" : "`is_lp` = true";
					$query_str = "SELECT * FROM $table WHERE %s >= `start_ip_range` && %s <= `end_ip_range` && " . $category_str . " && `type` = %d LIMIT 1;";
					$query = $db->prepare($query_str, array($binIP, $binIP, $type));
					if ($db->getVar($query) > 0)
						return true;
				}
				return false;
			}
			return false;
		}

	}
endif;