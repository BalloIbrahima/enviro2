<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVActLogCallback')) :
	
require_once dirname( __FILE__ ) . '/../../wp_actlog.php';

class BVActLogCallback extends BVCallbackBase {
	public $db;
	public $settings;

	const ACTLOG_WING_VERSION = 1.0;

	public function __construct($callback_handler) {
		$this->db = $callback_handler->db;
		$this->settings = $callback_handler->settings;
	}

	public function dropActLogTable() {
		return $this->db->dropBVTable(BVWPActLog::$actlog_table);
	}

	public function createActLogTable($usedbdelta = false) {
		$db = $this->db;
		$charset_collate = $db->getCharsetCollate();
		$table = $this->db->getBVTable(BVWPActLog::$actlog_table);
		$query = "CREATE TABLE $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			site_id int NOT NULL,
			user_id int DEFAULT 0,
			username text DEFAULT '',
			request_id text DEFAULT '',
			ip varchar(20) DEFAULT '',
			event_type varchar(40) NOT NULL DEFAULT '',
			event_data mediumtext NOT NULL,
			time int,
			PRIMARY KEY (id)
		) $charset_collate;";
		return $db->createTable($query, BVWPActLog::$actlog_table, $usedbdelta);
	}

	public function process($request) {
		$settings = $this->settings;
		$params = $request->params;
		switch ($request->method) {
		case "truncactlogtable":
			$resp = array("status" => $this->db->truncateBVTable(BVWPActLog::$actlog_table));
			break;
		case "dropactlogtable":
			$resp = array("status" => $this->dropActLogTable());
			break;
		case "createactlogtable":
			$usedbdelta = array_key_exists('usedbdelta', $params);
			$resp = array("status" => $this->createActLogTable($usedbdelta));
			break;
		default:
			$resp = false;
		}
		return $resp;
	}
}
endif;