<?php
if (!defined('MCDATAPATH')) exit;

if (!class_exists('BVPrependLogger')) :
	class BVPrependLogger {
		public $logFile;

		function __construct() {
			$this->logFile = MCDATAPATH . MCCONFKEY . '-mc.log';
		}

		public function log($data) {
			$_data = serialize($data);
			$str = "bvlogbvlogbvlog" . ":";
			$str .= strlen($_data) . ":" . $_data;
			error_log($str, 3, $this->logFile);
		}

	}
endif;
