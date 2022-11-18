<?php

if (! (defined('ABSPATH') || defined('MCDATAPATH')) ) exit;
if (!class_exists('BVFW')) :

require_once dirname( __FILE__ ) . '/rule_evaluator.php';

class BVFW {
	public $bvinfo;
	public $request;
	public $config;
	public $ipstore;
	public $category;
	public $logger;
	public $generic_rule_set = array();
	public $wpf_rule_set = array();
	public $ruleEvaluator;
	public $break_rule_evaluation;
	public $ruleActions = array();
	private static $instance = null;

	#RuleLevels
	const GENERIC = 1;
	const WPF = 2;

	const SQLIREGEX = '/(?:[^\\w<]|\\/\\*\\![0-9]*|^)(?:
		@@HOSTNAME|
		ALTER|ANALYZE|ASENSITIVE|
		BEFORE|BENCHMARK|BETWEEN|BIGINT|BINARY|BLOB|
		CALL|CASE|CHANGE|CHAR|CHARACTER|CHAR_LENGTH|COLLATE|COLUMN|CONCAT|CONDITION|CONSTRAINT|CONTINUE|CONVERT|CREATE|CROSS|CURRENT_DATE|CURRENT_TIME|CURRENT_TIMESTAMP|CURRENT_USER|CURSOR|
		DATABASE|DATABASES|DAY_HOUR|DAY_MICROSECOND|DAY_MINUTE|DAY_SECOND|DECIMAL|DECLARE|DEFAULT|DELAYED|DELETE|DESCRIBE|DETERMINISTIC|DISTINCT|DISTINCTROW|DOUBLE|DROP|DUAL|DUMPFILE|
		EACH|ELSE|ELSEIF|ELT|ENCLOSED|ESCAPED|EXISTS|EXIT|EXPLAIN|EXTRACTVALUE|
		FETCH|FLOAT|FLOAT4|FLOAT8|FORCE|FOREIGN|FROM|FULLTEXT|
		GRANT|GROUP|HAVING|HEX|HIGH_PRIORITY|HOUR_MICROSECOND|HOUR_MINUTE|HOUR_SECOND|
		IFNULL|IGNORE|INDEX|INFILE|INNER|INOUT|INSENSITIVE|INSERT|INTERVAL|ISNULL|ITERATE|
		JOIN|KILL|LEADING|LEAVE|LIMIT|LINEAR|LINES|LOAD|LOAD_FILE|LOCALTIME|LOCALTIMESTAMP|LOCK|LONG|LONGBLOB|LONGTEXT|LOOP|LOW_PRIORITY|
		MASTER_SSL_VERIFY_SERVER_CERT|MATCH|MAXVALUE|MEDIUMBLOB|MEDIUMINT|MEDIUMTEXT|MID|MIDDLEINT|MINUTE_MICROSECOND|MINUTE_SECOND|MODIFIES|
		NATURAL|NO_WRITE_TO_BINLOG|NULL|NUMERIC|OPTION|ORD|ORDER|OUTER|OUTFILE|
		PRECISION|PRIMARY|PRIVILEGES|PROCEDURE|PROCESSLIST|PURGE|
		RANGE|READ_WRITE|REGEXP|RELEASE|REPEAT|REQUIRE|RESIGNAL|RESTRICT|RETURN|REVOKE|RLIKE|ROLLBACK|
		SCHEMA|SCHEMAS|SECOND_MICROSECOND|SELECT|SENSITIVE|SEPARATOR|SHOW|SIGNAL|SLEEP|SMALLINT|SPATIAL|SPECIFIC|SQLEXCEPTION|SQLSTATE|SQLWARNING|SQL_BIG_RESULT|SQL_CALC_FOUND_ROWS|SQL_SMALL_RESULT|STARTING|STRAIGHT_JOIN|SUBSTR|
		TABLE|TERMINATED|TINYBLOB|TINYINT|TINYTEXT|TRAILING|TRANSACTION|TRIGGER|
		UNDO|UNHEX|UNION|UNLOCK|UNSIGNED|UPDATE|UPDATEXML|USAGE|USING|UTC_DATE|UTC_TIME|UTC_TIMESTAMP|
		VALUES|VARBINARY|VARCHAR|VARCHARACTER|VARYING|WHEN|WHERE|WHILE|WRITE|YEAR_MONTH|ZEROFILL)(?=[^\\w]|$)/ix';

	const XSSREGEX = '/(?:
		#tags
		(?:\\<|\\+ADw\\-|\\xC2\\xBC)(script|iframe|svg|object|embed|applet|link|style|meta|\\/\\/|\\?xml\\-stylesheet)(?:[^\\w]|\\xC2\\xBE)|
		#protocols
		(?:^|[^\\w])(?:(?:\\s*(?:&\\#(?:x0*6a|0*106)|j)\\s*(?:&\\#(?:x0*61|0*97)|a)\\s*(?:&\\#(?:x0*76|0*118)|v)\\s*(?:&\\#(?:x0*61|0*97)|a)|\\s*(?:&\\#(?:x0*76|0*118)|v)\\s*(?:&\\#(?:x0*62|0*98)|b)|\\s*(?:&\\#(?:x0*65|0*101)|e)\\s*(?:&\\#(?:x0*63|0*99)|c)\\s*(?:&\\#(?:x0*6d|0*109)|m)\\s*(?:&\\#(?:x0*61|0*97)|a)|\\s*(?:&\\#(?:x0*6c|0*108)|l)\\s*(?:&\\#(?:x0*69|0*105)|i)\\s*(?:&\\#(?:x0*76|0*118)|v)\\s*(?:&\\#(?:x0*65|0*101)|e))\\s*(?:&\\#(?:x0*73|0*115)|s)\\s*(?:&\\#(?:x0*63|0*99)|c)\\s*(?:&\\#(?:x0*72|0*114)|r)\\s*(?:&\\#(?:x0*69|0*105)|i)\\s*(?:&\\#(?:x0*70|0*112)|p)\\s*(?:&\\#(?:x0*74|0*116)|t)|\\s*(?:&\\#(?:x0*6d|0*109)|m)\\s*(?:&\\#(?:x0*68|0*104)|h)\\s*(?:&\\#(?:x0*74|0*116)|t)\\s*(?:&\\#(?:x0*6d|0*109)|m)\\s*(?:&\\#(?:x0*6c|0*108)|l)|\\s*(?:&\\#(?:x0*6d|0*109)|m)\\s*(?:&\\#(?:x0*6f|0*111)|o)\\s*(?:&\\#(?:x0*63|0*99)|c)\\s*(?:&\\#(?:x0*68|0*104)|h)\\s*(?:&\\#(?:x0*61|0*97)|a)|\\s*(?:&\\#(?:x0*64|0*100)|d)\\s*(?:&\\#(?:x0*61|0*97)|a)\\s*(?:&\\#(?:x0*74|0*116)|t)\\s*(?:&\\#(?:x0*61|0*97)|a)(?!(?:&\\#(?:x0*3a|0*58)|\\:)(?:&\\#(?:x0*69|0*105)|i)(?:&\\#(?:x0*6d|0*109)|m)(?:&\\#(?:x0*61|0*97)|a)(?:&\\#(?:x0*67|0*103)|g)(?:&\\#(?:x0*65|0*101)|e)(?:&\\#(?:x0*2f|0*47)|\\/)(?:(?:&\\#(?:x0*70|0*112)|p)(?:&\\#(?:x0*6e|0*110)|n)(?:&\\#(?:x0*67|0*103)|g)|(?:&\\#(?:x0*62|0*98)|b)(?:&\\#(?:x0*6d|0*109)|m)(?:&\\#(?:x0*70|0*112)|p)|(?:&\\#(?:x0*67|0*103)|g)(?:&\\#(?:x0*69|0*105)|i)(?:&\\#(?:x0*66|0*102)|f)|(?:&\\#(?:x0*70|0*112)|p)?(?:&\\#(?:x0*6a|0*106)|j)(?:&\\#(?:x0*70|0*112)|p)(?:&\\#(?:x0*65|0*101)|e)(?:&\\#(?:x0*67|0*103)|g)|(?:&\\#(?:x0*74|0*116)|t)(?:&\\#(?:x0*69|0*105)|i)(?:&\\#(?:x0*66|0*102)|f)(?:&\\#(?:x0*66|0*102)|f)|(?:&\\#(?:x0*73|0*115)|s)(?:&\\#(?:x0*76|0*118)|v)(?:&\\#(?:x0*67|0*103)|g)(?:&\\#(?:x0*2b|0*43)|\\+)(?:&\\#(?:x0*78|0*120)|x)(?:&\\#(?:x0*6d|0*109)|m)(?:&\\#(?:x0*6c|0*108)|l))(?:(?:&\\#(?:x0*3b|0*59)|;)(?:&\\#(?:x0*63|0*99)|c)(?:&\\#(?:x0*68|0*104)|h)(?:&\\#(?:x0*61|0*97)|a)(?:&\\#(?:x0*72|0*114)|r)(?:&\\#(?:x0*73|0*115)|s)(?:&\\#(?:x0*65|0*101)|e)(?:&\\#(?:x0*74|0*116)|t)(?:&\\#(?:x0*3d|0*61)|=)[\\-a-z0-9]+)?(?:(?:&\\#(?:x0*3b|0*59)|;)(?:&\\#(?:x0*62|0*98)|b)(?:&\\#(?:x0*61|0*97)|a)(?:&\\#(?:x0*73|0*115)|s)(?:&\\#(?:x0*65|0*101)|e)(?:&\\#(?:x0*36|0*54)|6)(?:&\\#(?:x0*34|0*52)|4))?(?:&\\#(?:x0*2c|0*44)|,)))\\s*(?:&\\#(?:x0*3a|0*58)|&colon|\\:)|
		#css expression
		(?:^|[^\\w])(?:(?:\\\\0*65|\\\\0*45|e)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*78|\\\\0*58|x)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*70|\\\\0*50|p)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*72|\\\\0*52|r)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*65|\\\\0*45|e)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*73|\\\\0*53|s)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*73|\\\\0*53|s)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*69|\\\\0*49|i)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*6f|\\\\0*4f|o)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*6e|\\\\0*4e|n))[^\\w]*?(?:\\\\0*28|\\()|
		#css properties
		(?:^|[^\\w])(?:(?:(?:\\\\0*62|\\\\0*42|b)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*65|\\\\0*45|e)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*68|\\\\0*48|h)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*61|\\\\0*41|a)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*76|\\\\0*56|v)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*69|\\\\0*49|i)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*6f|\\\\0*4f|o)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*72|\\\\0*52|r)(?:\\/\\*.*?\\*\\/)*)|(?:(?:\\\\0*2d|\\\\0*2d|-)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*6d|\\\\0*4d|m)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*6f|\\\\0*4f|o)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*7a|\\\\0*5a|z)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*2d|\\\\0*2d|-)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*62|\\\\0*42|b)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*69|\\\\0*49|i)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*6e|\\\\0*4e|n)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*64|\\\\0*44|d)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*69|\\\\0*49|i)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*6e|\\\\0*4e|n)(?:\\/\\*.*?\\*\\/)*(?:\\\\0*67|\\\\0*47|g)(?:\\/\\*.*?\\*\\/)*))[^\\w]*(?:\\\\0*3a|\\\\0*3a|:)[^\\w]*(?:\\\\0*75|\\\\0*55|u)(?:\\\\0*72|\\\\0*52|r)(?:\\\\0*6c|\\\\0*4c|l)|
		#properties
		(?:^|[^\\w])(?:on(?:abort|activate|afterprint|afterupdate|autocomplete|autocompleteerror|beforeactivate|beforecopy|beforecut|beforedeactivate|beforeeditfocus|beforepaste|beforeprint|beforeunload|beforeupdate|blur|bounce|cancel|canplay|canplaythrough|cellchange|change|click|close|contextmenu|controlselect|copy|cuechange|cut|dataavailable|datasetchanged|datasetcomplete|dblclick|deactivate|drag|dragend|dragenter|dragleave|dragover|dragstart|drop|durationchange|emptied|encrypted|ended|error|errorupdate|filterchange|finish|focus|focusin|focusout|formchange|forminput|hashchange|help|input|invalid|keydown|keypress|keyup|languagechange|layoutcomplete|load|loadeddata|loadedmetadata|loadstart|losecapture|message|mousedown|mouseenter|mouseleave|mousemove|mouseout|mouseover|mouseup|mousewheel|move|moveend|movestart|mozfullscreenchange|mozfullscreenerror|mozpointerlockchange|mozpointerlockerror|offline|online|page|pagehide|pageshow|paste|pause|play|playing|popstate|progress|propertychange|ratechange|readystatechange|reset|resize|resizeend|resizestart|rowenter|rowexit|rowsdelete|rowsinserted|scroll|search|seeked|seeking|select|selectstart|show|stalled|start|storage|submit|suspend|timer|timeupdate|toggle|unload|volumechange|waiting|webkitfullscreenchange|webkitfullscreenerror|wheel)|formaction|data\\-bind|ev:event)[^\\w]
		)/ix';

	const BYPASS_COOKIE = "bvfw-bypass-cookie";
	const IP_COOKIE = "bvfw-ip-cookie";
	const PREVENT_CACHE_COOKIE = "wp-bvfw-prevent-cache-cookie";

	#singleton design
	private function __construct($logger, $confHash, $ip, $bvinfo, $ipstore, $ruleSet) {
		$this->config = new BVFWConfig($confHash);
		$this->request = new BVWPRequest($ip);
		$this->bvinfo = $bvinfo;
		$this->ipstore = $ipstore;
		$this->logger = $logger;
		$this->initializeLevelWiseRuleSets($ruleSet);
		$this->ruleEvaluator = new BVFWRuleEvaluator($this);
		$this->break_rule_evaluation = false;
	}

	public static function getInstance($logger, $confHash, $ip, $bvinfo, $ipstore, $ruleSet) {
		if (!isset(self::$instance)) {
			self::$instance = new BVFW($logger, $confHash, $ip, $bvinfo, $ipstore, $ruleSet);
		}

		return self::$instance;
	}

	public function setcookie($name, $value, $expire) {
		$path = $this->config->cookiePath;
		$cookie_domain = $this->config->cookieDomain;

		if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
			$secure = function_exists('is_ssl') ? is_ssl() : false;
			@setcookie($name, $value, $expire, $path, $cookie_domain, $secure, true);
		} else {
			@setcookie($name, $value, $expire, $path);
		}
	}

	public function setBypassCookie() {
		if (function_exists('is_user_logged_in') && is_user_logged_in() && !$this->hasValidBypassCookie()) {
			$roleLevel = $this->getCurrentRoleLevel();
			$bypassLevel = $this->config->bypassLevel;
			if ($roleLevel >= $bypassLevel) {
				$cookie = $this->generateBypassCookie();
				$this->setcookie(BVFW::BYPASS_COOKIE, $cookie, time() + 43200);
			}
		}
	}

	public function generateBypassCookie() {
		$time = floor(time() / 43200);
		$bypassLevel = $this->config->bypassLevel;
		$cookiekey = $this->config->cookieKey;
		return sha1($bypassLevel.$time.$cookiekey);
	}

	public function hasValidBypassCookie() {
		$cookie = (string) $this->request->getCookies(BVFW::BYPASS_COOKIE);
		return ($this->canSetAdminCookie() && ($cookie === $this->generateBypassCookie()));
	}

	public function setIPCookie() {
		if (!$this->request->getCookies(BVFW::IP_COOKIE)) {
			$ip = $this->request->getIP();
			$cookiekey = $this->config->cookieKey;
			$time = floor(time() / 86400);
			$cookie = sha1($ip.$time.$cookiekey);
			$this->setcookie(BVFW::IP_COOKIE, $cookie, time() + 86400);
		}
	}

	public function getBVCookies() {
		$cookies = array();
		if ($this->request->getCookies(BVFW::IP_COOKIE) !== NULL) {
			$cookies[BVFW::IP_COOKIE] = (string) $this->request->getCookies(BVFW::IP_COOKIE);
		}
		return $cookies;
	}

	public function getCurrentRoleLevel() {
		if (function_exists('current_user_can')) {
			if (function_exists('is_super_admin') &&  is_super_admin()) {
				return BVFWConfig::ROLE_LEVEL_ADMIN;
			}
			foreach ($this->config->customRoles as $role) {
				if (current_user_can($role)) {
					return BVFWConfig::ROLE_LEVEL_CUSTOM;
				}
			}
			foreach (BVFWConfig::$roleLevels as $role => $level) {
				if (current_user_can($role)) {
					return $level;
				}
			}
		}
		return 0;
	}

	public function isActive() {
		return $this->config->isActive();
	}
	public function canSetAdminCookie() {
		return ($this->config->adminCookieMode === BVFWConfig::ADMIN_COOKIE_MODE_ENABLED);
	}

	public function canSetIPCookie() {
		return ($this->config->ipCookieMode === BVFWConfig::IP_COOKIE_MODE_ENABLED);
	}

	public function setResponseCode() {
		if (!function_exists('http_response_code')) {
			return false;
		}

		$this->request->setRespCode(http_response_code());
		return true;
	}

	public function canLog() {
		$canlog = false;

		if ($this->config->isCompleteLoggingEnabled()) {
			$canlog = true;
		} else if ($this->config->isVisitorLoggingEnabled()) {
			$canlog = ($this->request->hasMatchedRules()) || (!$this->hasValidBypassCookie() &&
					(!function_exists('is_user_logged_in') || !is_user_logged_in()));
		}
		return $canlog;
	}

	public function log() {
		if ($this->canLog()) {
			$this->setResponseCode();
			$this->logger->log($this->request->getDataToLog());
		}
	}

	public function terminateRequest($category) {
		$this->request->setCategory($category);
		$this->request->setStatus(BVWPRequest::BLOCKED);
		$this->request->setRespCode(403);

		if ($this->config->canSetCachePreventionCookie &&
				!$this->request->getCookies(BVFW::PREVENT_CACHE_COOKIE)) {
			$value = "Prevent Caching Response.";
			$this->setcookie(BVFW::PREVENT_CACHE_COOKIE, $value, time() + 43200);
		}

		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Pragma: no-cache");
		header("Expires: 0");
		header('HTTP/1.0 403 Forbidden');
		$brandname = $this->bvinfo->getBrandName().' Firewall';
		die("
				<div style='height: 98vh;'>
					<div style='text-align: center; padding: 10% 0; font-family: Arial, Helvetica, sans-serif;'>
						<div><p>$brandname</p></div>
						<p>Blocked because of Malicious Activities</p>
						<p>Reference ID: " . $this->request->getRequestID() . "</p>
					</div>
				</div>
			");
	}

	public function isBlacklistedIP() {
		return $this->ipstore->isFWIPBlacklisted($this->request->getIP());
	}

	public function isWhitelistedIP() {
		return $this->ipstore->isFWIPWhitelisted($this->request->getIP());
	}

	public function canBypassFirewall() {
		if ($this->isWhitelistedIP() || $this->hasValidBypassCookie()) {
			$this->request->setCategory(BVWPRequest::WHITELISTED);
			$this->request->setStatus(BVWPRequest::BYPASSED);
			return true;
		} else if(BVProtectBase::isPrivateIP($this->request->getIP())) {
			$this->request->setCategory(BVWPRequest::PRIVATEIP);
			$this->request->setStatus(BVWPRequest::BYPASSED);
			return true;
		}
		return false;
	}
	
	public function canLogValue($key) {
		$skip_keys = array('password' => true, 'passwd' => true, 'pwd' => true);
		if (isset($skip_keys[$key])) {
			return false;
		}
		return true;
	}

	public function execute() {
		if ($this->config->canProfileReqInfo()) {
			$result = array();
			$has_debug_mode = $this->config->isReqProfilingModeDebug();
			$action = $this->request->getAction();
			if (isset($action)) {
				$result += $this->profileRequestInfo(array("action" => $action),
						true, 'ACTION[');
			}
			$result += $this->profileRequestInfo($this->request->getPostParams(),
					$has_debug_mode, 'BODY[');
			$result += $this->profileRequestInfo($this->request->getGetParams(),
					true, 'GET[');
			$result += $this->profileRequestInfo($this->request->getFiles(),
					true, 'FILES[');
			$cookies = $has_debug_mode ? $this->request->getCookies() : $this->getBVCookies();
			$result += $this->profileRequestInfo($cookies, true, 'COOKIES[');
			$this->request->updateReqInfo($result);
		}

		if (!$this->canBypassFirewall() && $this->config->isProtecting()) {
			if ($this->isBlacklistedIP()) {
				$this->terminateRequest(BVWPRequest::BLACKLISTED);
			}
		}
	}

	public function canExecuteRules() {
		if (!$this->isWhitelistedIP() && $this->config->isRulesModeEnabled()) {
			return true;
		}
		return false;
	}

	public function initializeLevelWiseRuleSets($rule_set) {
		if (!is_array($rule_set)) {
			$this->request->updateRulesInfo('errors', 'ruleset', 'Invalid RuleSet');
			return;
		}

		foreach ($rule_set as $rule) {
			if (BVFWRuleEvaluator::VERSION >= $rule["min_rule_engine_ver"]) {
				if (array_key_exists("level", $rule) && $rule["level"] == BVFW::WPF) {
					array_push($this->wpf_rule_set, $rule);
				} else {
					array_push($this->generic_rule_set, $rule);
				}
			}
		}
	}

	public function ruleSetToExecute() {
		$rule_set = array();
		if ($this->isWpLoaded()) {
			$rule_set = $this->wpf_rule_set;
		}
		if (!defined('MCWAFLOADED') && !$this->hasValidBypassCookie()) {
			$rule_set = array_merge($rule_set, $this->generic_rule_set);
		}
		return $rule_set;
	}

	public function executeRules() {
		if (!$this->canExecuteRules()) {
			return;
		}

		$rule_set = $this->ruleSetToExecute();
		$this->evaluateRules($rule_set);
	}

	public function matchCount($pattern, $subject) {
		$count = 0;
		if (is_array($subject)) {
			foreach ($subject as $val) {
				$count += $this->matchCount($pattern, $val);
			}
			return $count;
		} else {
			$count = preg_match_all((string) $pattern, (string) $subject, $matches);
			return ($count === false ? 0 : $count);
		}
	}

	public function getLength($val) {
		$length = 0;
		if (is_array($val)) {
			foreach ($val as $v) {
				$length += $this->getLength($v);
			}
			return $length;
		} else {
			return strlen((string) $val);
		}
	}

	public function profileRequestInfo($params, $debug = false, $prefix = '', $obraces = 1) {
		$result = array();
		if (is_array($params)) {
			foreach ($params as $key => $value) {
				$original_key = $key;
				$key = $prefix . $key;
				if (is_array($value)) {
					$result = $result + $this->profileRequestInfo($value, $debug, $key . '[', $obraces + 1);
				} else {
					$key = $key . str_repeat(']', $obraces);
					$result[$key] = array();
					$valsize = $this->getLength($value);
					$result[$key]["size"] = $valsize;
					if ($debug === true && $valsize < 256 && $this->canLogValue($original_key)) {
						$result[$key]["value"] = $value;
						continue;
					}

					if (preg_match('/^\d+$/', $value)) {
						$result[$key]["numeric"] = true;
					} else if (preg_match('/^\w+$/', $value)) {
						$result[$key]["regular_word"] = true;
					} else if (preg_match('/^\S+$/', $value)) {
						$result[$key]["special_word"] = true;
					} else if (preg_match('/^[\w\s]+$/', $value)) {
						$result[$key]["regular_sentence"] = true;
					} else if (preg_match('/^[\w\W]+$/', $value)) {
						$result[$key]["special_chars_sentence"] = true;
					}

					if (preg_match('/^\b((25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\.){3}
						(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\b$/x', $value)) {
						$result[$key]["ipv4"] = true;
					} else if (preg_match('/\b((25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\.){3}
						(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\b/x', $value)) {
						$result[$key]["embeded_ipv4"] = true;
					} else if (preg_match('/^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|
						([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|
						([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}
						(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|
						([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|
						:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|
						::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3}
						(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|
						(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/x', $value)) {
						$result[$key]["ipv6"] = true;
					} else if (preg_match('/(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|
						([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|
						([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}
						(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|
						([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|
						:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|
						::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3}
						(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|
						(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/x', $value)) {
						$result[$key]["embeded_ipv6"] = true;
					}

					if (preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/', $value)) {
						$result[$key]["email"] = true;
					} else if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}/', $value)) {
						$result[$key]["embeded_email"] = true;
					}

					if (preg_match('/^(http|ftp)s?:\/\/\S+$/i', $value)) {
						$result[$key]["link"] = true;
					} else if (preg_match('/(http|ftp)s?:\/\/\S+$/i', $value)) {
						$result[$key]["embeded_link"] = true;
					}

					if (preg_match('/<(html|head|title|base|link|meta|style|picture|source|img|
						iframe|embed|object|param|video|audio|track|map|area|form|label|input|button|
						select|datalist|optgroup|option|textarea|output|progress|meter|fieldset|legend|
						script|noscript|template|slot|canvas)/ix', $value)) {
						$result[$key]["embeded_html"] = true;
					}

					if (preg_match('/\.(jpg|jpeg|png|gif|ico|pdf|doc|docx|ppt|pptx|pps|ppsx|odt|xls|zip|gzip|
						xlsx|psd|mp3|m4a|ogg|wav|mp4|m4v|mov|wmv|avi|mpg|ogv|3gp|3g2|php|html|phtml|js|css)/ix', $value)) {
						$result[$key]["file"] = true;
					}

					if ($this->matchCount(BVFW::SQLIREGEX, $value) > 2) {
						$result[$key]["sql"] = true;
					}

					if (preg_match('/(?:\.{2}[\/]+)/', $value)) {
						$result[$key]["path_traversal"] = true;
					}

					if (preg_match('/\\b(?i:eval)\\s*\\(\\s*(?i:base64_decode|exec|file_get_contents|gzinflate|passthru|shell_exec|stripslashes|system)\\s*\\(/', $value)) {
						$result[$key]["php_eval"] = true;
					}
				}
			}
		}
		return $result;
	}

	public function evaluateRules($ruleSet) {
		foreach ($ruleSet as $rule) {
			$id = $rule["id"];
			$ruleLogic = $rule["rule_logic"];
			$this->ruleActions[$id] = $rule["actions"];
			$this->ruleEvaluator->resetErrors();

			if ($this->ruleEvaluator->evaluateRule($ruleLogic) && empty($this->ruleEvaluator->getErrors())) {
				$this->handleMatchedRule($id);
			} elseif (!empty($this->ruleEvaluator->getErrors())) {
				$this->request->updateRulesInfo("errors", (string) $id, $this->ruleEvaluator->getErrors());
			}

			if ($this->break_rule_evaluation) {
				return;
			}
		}
	}

	function handleMatchedRule($id) {
		$this->request->updateMatchedRules($id);
		$this->executeActions($id);
	}

	function executeActions($id){
		foreach($this->ruleActions[$id] as $action) {
			switch ($action["type"]) {
			case "ALLOW":
				$this->break_rule_evaluation = true;
				$this->request->setCategory(BVWPRequest::RULE_ALLOWED);
				return;
			case "BLOCK":
				if ($this->config->isProtecting()) {
					$this->terminateRequest(BVWPRequest::RULE_BLOCKED);
				}
				return;
			case "INSPECT":
				$this->inspectRequest();
				break;
			}
		}
	}

	function isWPLoaded() {
		return defined('BVWPLOADED');
	}

	function getCurrentWPUser() {
		if (!$this->isWPLoaded()) {
			return;
		}
		if (!function_exists('wp_get_current_user')) {
			@include_once(ABSPATH . "wp-includes/pluggable.php");
		}
		return wp_get_current_user();
	}

	public function inspectRequest() {
		$this->request->updateRulesInfo('inspect', "headers", $this->request->getHeaders());

		$wp_user = $this->getCurrentWPUser();
		if ($wp_user && isset($wp_user->ID)) {
			$this->request->updateRulesInfo('inspect', "userID", $wp_user->ID);
		}

		$this->request->updateRulesInfo('inspect', "getParams", $this->request->getGetParams());
		$this->request->updateRulesInfo('inspect', "postParams", $this->getPostParamsToLog($this->request->getPostParams()));
		$this->request->updateRulesInfo('inspect', "cookies", $this->request->getCookies());
	}

	function getPostParamsToLog($params) {
		$result = array();
		if (is_array($params)) {
			foreach ($params as $key => $value) {
				if (is_array($value)) {
					$result[$key] = $this->getPostParamsToLog($value);
				} else {
					$valsize = $this->getLength($value);
					if ($valsize > 1024) {
						$result[$key] = "Data too long: {$valsize}";
					} elseif (!$this->canLogValue($key)) {
						$result[$key] = "Sensitive Data";
					} else {
						$result[$key] = $value;
					}
				}
			}
		}
		return $result;
	}
}
endif;