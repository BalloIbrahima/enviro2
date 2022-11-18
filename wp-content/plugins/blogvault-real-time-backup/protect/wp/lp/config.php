<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVWPLPConfig')) :
class BVWPLPConfig {
	public $mode;
	public $captchaLimit;
	public $tempBlockLimit;
	public $blockAllLimit;
	public $failedLoginGap;
	public $successLoginGap;
	public $allBlockedGap;
	
	public static $requests_table = 'lp_requests';
	
	#mode
	const DISABLED = 1;
	const AUDIT    = 2;
	const PROTECT  = 3;

	public function __construct($confHash) {
		$this->mode = array_key_exists('mode', $confHash) ? intval($confHash['mode']) : BVWPLPConfig::DISABLED;
		$this->captchaLimit = array_key_exists('captchalimit', $confHash) ? intval($confHash['captchalimit']) : 3;
		$this->tempBlockLimit = array_key_exists('tempblocklimit', $confHash) ? intval($confHash['tempblocklimit']) : 10;
		$this->blockAllLimit = array_key_exists('blockalllimit', $confHash) ? intval($confHash['blockalllimit']) : 100;
		$this->failedLoginGap = array_key_exists('failedlogingap', $confHash) ? intval($confHash['failedlogingap']) : 1800;
		$this->successLoginGap = array_key_exists('successlogingap', $confHash) ? intval($confHash['successlogingap']) : 1800;
		$this->allBlockedGap = array_key_exists('allblockedgap', $confHash) ? intval($confHash['allblockedgap']) : 1800;
	}
}
endif;