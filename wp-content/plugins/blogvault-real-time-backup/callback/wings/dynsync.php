<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVDynSyncCallback')) :
	
require_once dirname( __FILE__ ) . '/../../wp_dynsync.php';

class BVDynSyncCallback extends BVCallbackBase {
	public $db;
	public $settings;

	const DYNSYNC_WING_VERSION = 1.0;

	public function __construct($callback_handler) {
		$this->db = $callback_handler->db;
		$this->settings = $callback_handler->settings;
	}

	public function dropDynSyncTable() {
		return $this->db->dropBVTable(BVWPDynSync::$dynsync_table);
	}

	public function createDynSyncTable($usedbdelta = false) {
		$db = $this->db;
		$charset_collate = $db->getCharsetCollate();
		$table = $this->db->getBVTable(BVWPDynSync::$dynsync_table);
		$query = "CREATE TABLE $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			site_id int NOT NULL,
			event_type varchar(40) NOT NULL DEFAULT '',
			event_tag varchar(40) NOT NULL DEFAULT '',
			event_data text NOT NULL DEFAULT '',
			PRIMARY KEY (id)
		) $charset_collate;";
		return $db->createTable($query, BVWPDynSync::$dynsync_table, $usedbdelta);
	}

	public function process($request) {
		$settings = $this->settings;
		$params = $request->params;
		switch ($request->method) {
		case "truncdynsynctable":
			$resp = array("status" => $this->db->truncateBVTable(BVWPDynSync::$dynsync_table));
			break;
		case "dropdynsynctable":
			$resp = array("status" => $this->dropDynSyncTable());
			break;
		case "createdynsynctable":
			$usedbdelta = array_key_exists('usedbdelta', $params);
			$resp = array("status" => $this->createDynSyncTable($usedbdelta));
			break;
		case "setdynsync":
			if (array_key_exists('dynplug', $params)) {
				$settings->updateOption('bvdynplug', $params['dynplug']);
			} else {
				$settings->deleteOption('bvdynplug');
			}
			$settings->updateOption('bvDynSyncActive', $params['dynsync']);
			$resp = array("status" => "done");
			break;
		case "setwoodyn":
			$resp = array("status" => $settings->updateOption('bvWooDynSync', $params['woodyn']));
			break;
		case "setignorednames":
			switch ($params['table']) {
			case "options":
				$settings->updateOption('bvIgnoredOptions', $params['names']);
				break;
			case "postmeta":
				$settings->updateOption('bvIgnoredPostmeta', $params['names']);
				break;
			}
			$resp = array("status" => "done");
			break;
		case "getignorednames":
			switch ($params['table']) {
			case "options":
				$names = $settings->getOption('bvIgnoredOptions');
				break;
			case "postmeta":
				$names = $settings->getOption('bvIgnoredPostmeta');
				break;
			}
			$resp = array("names", $names);
			break;
		default:
			$resp = false;
		}
		return $resp;
	}
}
endif;