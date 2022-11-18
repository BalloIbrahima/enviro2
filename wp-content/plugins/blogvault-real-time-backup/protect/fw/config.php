<?php

if (! (defined('ABSPATH') || defined('MCDATAPATH')) ) exit;
if (!class_exists('BVFWConfig')) :

class BVFWConfig {
	public $mode;
	public $requestProfilingMode;
	public $roleLevel;
	public $ipCookieMode;
	public $adminCookieMode;
	public $bypassLevel;
	public $customRoles;
	public $cookieKey;
	public $cookiePath;
	public $cookieDomain;
	public $loggingMode;
	public $rulesMode;

	public static $requests_table = 'fw_requests';
	public static $roleLevels = array(
		'administrator' => BVFWConfig::ROLE_LEVEL_ADMIN,
		'editor' => BVFWConfig::ROLE_LEVEL_EDITOR,
		'author' => BVFWConfig::ROLE_LEVEL_AUTHOR,
		'contributor' => BVFWConfig::ROLE_LEVEL_CONTRIBUTOR,
		'subscriber' => BVFWConfig::ROLE_LEVEL_SUBSCRIBER
	);

	function __construct($confHash) {
		$this->mode = array_key_exists('mode', $confHash) ? intval($confHash['mode']) : BVFWConfig::DISABLED;
		$this->requestProfilingMode = array_key_exists('reqprofilingmode', $confHash) ? intval($confHash['reqprofilingmode']) : BVFWConfig::REQ_PROFILING_MODE_DISABLED;
		$this->ipCookieMode = array_key_exists('ipcookiemode', $confHash) ? intval($confHash['ipcookiemode']) : BVFWConfig::IP_COOKIE_MODE_DISABLED;
		$this->adminCookieMode = array_key_exists('admincookiemode', $confHash) ? intval($confHash['admincookiemode']) : BVFWConfig::ADMIN_COOKIE_MODE_DISABLED;
		$this->loggingMode = array_key_exists('loggingmode', $confHash) ? intval($confHash['loggingmode']) : BVFWConfig::LOGGING_MODE_VISITOR;
		$this->bypassLevel = array_key_exists('bypasslevel', $confHash) ? intval($confHash['bypasslevel']) : BVFWConfig::ROLE_LEVEL_CONTRIBUTOR;
		$this->customRoles = array_key_exists('customroles', $confHash) ? $confHash['customroles'] : array();
		$this->cookieKey = array_key_exists('cookiekey', $confHash) ? $confHash['cookiekey'] : "";
		$this->cookiePath = array_key_exists('cookiepath', $confHash) ? $confHash['cookiepath'] : "";
		$this->cookieDomain = array_key_exists('cookiedomain', $confHash) ? $confHash['cookiedomain'] : "";
		$this->canSetCachePreventionCookie = array_key_exists('cansetcachepreventioncookie', $confHash) ?
				$confHash['cansetcachepreventioncookie'] : false;
		$this->rulesMode = array_key_exists('rulesmode', $confHash) ? intval($confHash['rulesmode']) : BVFWConfig::DISABLED;
	}
	
	#mode
	const DISABLED = 1;
	const AUDIT    = 2;
	const PROTECT  = 3;

	#Request Profiling Mode
	const REQ_PROFILING_MODE_DISABLED = 1;
	const REQ_PROFILING_MODE_NORMAL = 2;
	const REQ_PROFILING_MODE_DEBUG = 3;

	#IP Cookie Mode
	const IP_COOKIE_MODE_ENABLED = 1;
	const IP_COOKIE_MODE_DISABLED = 2;

	#Admin Cookie Mode
	const ADMIN_COOKIE_MODE_ENABLED = 1;
	const ADMIN_COOKIE_MODE_DISABLED = 2;

	#Role Level
	const ROLE_LEVEL_SUBSCRIBER = 1;
	const ROLE_LEVEL_CONTRIBUTOR = 2;
	const ROLE_LEVEL_AUTHOR = 3;
	const ROLE_LEVEL_EDITOR = 4;
	const ROLE_LEVEL_ADMIN = 5;
	const ROLE_LEVEL_CUSTOM = 6;

	#WebServer Conf Mode
	const MODE_APACHEMODPHP = 1;
	const MODE_APACHESUPHP = 2;
	const MODE_CGI_FASTCGI = 3;
	const MODE_NGINX = 4;
	const MODE_LITESPEED = 5;
	const MODE_IIS = 6;

	#Logging Mode
	const LOGGING_MODE_VISITOR = 1;
	const LOGGING_MODE_COMPLETE = 2;
	const LOGGING_MODE_DISABLED = 3;

	#Valid mc_data filenames (not used anywhere) 
	public static $validMcDataFilenames = array('mc.conf', 'mc_ips.conf', 'mc_rules.json');
	public static $validDeletableFiles = array('mc.conf', 'mc_ips.conf', 'malcare-waf.php', 'mc.log', 'mc_data', 'mc_rules.json');

	public function isActive() {
		return ($this->mode !== BVFWConfig::DISABLED);
	}

	public function isProtecting() {
		return ($this->mode === BVFWConfig::PROTECT);
	}

	public function isAuditing() {
		return ($this->mode === BVFWConfig::AUDIT);
	}

	public function isReqProfilingModeDebug() {
		return ($this->requestProfilingMode === BVFWConfig::REQ_PROFILING_MODE_DEBUG);
	}

	public function canProfileReqInfo() {
		return ($this->requestProfilingMode !== BVFWConfig::REQ_PROFILING_MODE_DISABLED);
	}

	public function isCompleteLoggingEnabled() {
		return ($this->loggingMode === BVFWConfig::LOGGING_MODE_COMPLETE);
	}

	public function isVisitorLoggingEnabled() {
		return ($this->loggingMode === BVFWConfig::LOGGING_MODE_VISITOR);
	}

	public function isLoggingDisabled() {
		return ($this->loggingMode === BVFWConfig::LOGGING_MODE_DISABLED);
	}

	public function isRulesModeEnabled() {
		return ($this->rulesMode === BVFWConfig::PROTECT);
	}
}
endif;