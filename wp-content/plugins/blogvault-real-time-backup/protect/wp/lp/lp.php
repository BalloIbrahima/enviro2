<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVWPLP')) :
	

class BVWPLP {
	public $db;
	public $settings;
	private $ip;
	private $time;
	private $category;
	private $username;
	private $message;
	public $config;
	public $logger;
	public $ipstore;
	public static $requests_table = 'lp_requests';
	public static $unblock_ip_transient = 'bvlp_unblock_ip';

	#status
	const LOGINFAILURE = 1;
	const LOGINSUCCESS = 2;
	const LOGINBLOCKED = 3;

	#categories
	const CAPTCHABLOCK = 1;
	const TEMPBLOCK    = 2;
	const ALLBLOCKED   = 3;
	const UNBLOCKED    = 4;
	const BLACKLISTED  = 5;
	const BYPASSED     = 6;
	const ALLOWED      = 7;
	const PRIVATEIP    = 8;
	
	public function __construct($db, $settings, $ip, $ipstore, $confHash) {
		$this->db = $db;
		$this->settings = $settings;
		$this->ip = $ip;
		$this->config = new BVWPLPConfig($confHash);
		$this->ipstore = $ipstore;
		$this->logger = new BVLogger($db, BVWPLPConfig::$requests_table);
		$this->time = strtotime(date("Y-m-d H:i:s"));
	}

	public function init() {
		add_filter('authenticate', array($this, 'loginInit'), 30, 3);
		add_action('wp_login', array($this, 'loginSuccess'));
		add_action('wp_login_failed', array($this, 'loginFailed'));
	}

	public function setMessage($message) {
		$this->message = $message;
	}

	public function setUserName($username) {
		$this->username = $username;
	}

	public function setCategory($category) {
		$this->category = $category;
	}

	public function getCaptchaLink() {
		$account = BVAccount::apiPublicAccount($this->settings);
		$url = $account->authenticatedUrl('/captcha/solve');
		$url .= "&adminurl=".base64_encode(get_admin_url());
		return $url;
	}

	public function getUserName() {
		return $this->username ? $this->username : '';
	}

	public function getMessage() {
		return $this->message ? $this->message : '';
	}

	public function getCategory() {
		return $this->category ? $this->category : BVWPLP::ALLOWED;
	}

	public function getCaptchaLimit() {
		return $this->config->captchaLimit;
	}

	public function getFailedLoginGap() {
		return $this->config->failedLoginGap;
	}

	public function getSuccessLoginGap() {
		return $this->config->successLoginGap;
	}

	public function getAllBlockedGap() {
		return $this->config->allBlockedGap;
	}

	public function getTempBlockLimit() {
		return $this->config->tempBlockLimit;
	}

	public function getBlockAllLimit() {
		return $this->config->blockAllLimit;
	}

	public function getAllowLoginsTransient() {
		return $this->settings->getTransient('bvlp_allow_logins');
	}

	public function getBlockLoginsTransient() {
		return $this->settings->getTransient('bvlp_block_logins');
	}

	public function terminateTemplate() {
		$info = new BVInfo($this->settings);
		$brandname = $info->getBrandName().' Firewall';
		$templates = array (
			1 => "<p>Too many failed attempts, You are barred from logging into this site.</p><a href=".$this->getCaptchaLink()." 
					class='btn btn-default'>Click here</a> to unblock yourself.",
			2 => "You cannot login to this site for 30 minutes because of too many failed login attempts.",
			3 => "<p>Logins to this site are currently blocked.</p><a href=".$this->getCaptchaLink()." 
					class='btn btn-default'>Click here</a> to unblock yourself.",
			5 => "Your IP is blacklisted."
		);
			return "
			<div style='height: 98vh;'>
				<div style='text-align: center; padding: 10% 0; font-family: Arial, Helvetica, sans-serif;'>
					<div><p><img src=".plugins_url('/../../../img/icon.png', __FILE__)."><h2>Login Protection</h2><h3>powered by</h3><h2>"
							.$brandname."</h2></p><div>
					<p>" . $templates[$this->getCategory()]. "</p>
					<p>Reference ID: " . BVInfo::getRequestID() . "</p>
				</div>
			</div>";
	}

	public function isProtecting() {
		return ($this->config->mode === BVWPLPConfig::PROTECT);
	}

	public function isActive() {
		return ($this->config->mode !== BVWPLPConfig::DISABLED);
	}

	public function isBlacklistedIP() {
		return $this->ipstore->isLPIPBlacklisted($this->ip);
	}

	public function isWhitelistedIP() {
		return $this->ipstore->isLPIPWhitelisted($this->ip);
	}

	public function isUnBlockedIP() {
		$transient_name = BVWPLP::$unblock_ip_transient.$this->ip;
		$attempts = $this->settings->getTransient($transient_name);
		if ($attempts && $attempts > 0) {
			$this->settings->setTransient($transient_name, $attempts - 1, 600 * $attempts);
			return true;
		}
		return false;
	}

	public function isLoginBlocked() {
		if ($this->getAllowLoginsTransient() ||
				($this->getLoginCount(BVWPLP::LOGINFAILURE, null, $this->getAllBlockedGap()) < $this->getBlockAllLimit())) {
			return false;
		}
		return true;
	}

	public function log($status) {
		$data = array (
			"ip" => $this->ip,
			"status" => $status,
			"time" => $this->time,
			"category" => $this->getCategory(),
			"username" => $this->getUserName(),
			"request_id" => BVInfo::getRequestID(),
			"message" => $this->getMessage());
		$this->logger->log($data);
	}

	public function terminateLogin() {
		$this->setMessage('Login Blocked');
		$this->log(BVWPLP::LOGINBLOCKED);
		if ($this->isProtecting()) {
			header("Cache-Control: no-cache, no-store, must-revalidate");
			header("Pragma: no-cache");
			header("Expires: 0");
			header('HTTP/1.0 403 Forbidden');
			die($this->terminateTemplate());
			exit;
		}
	}

	public function loginInit($user, $username = '', $password = '') {
		if ($this->isUnBlockedIP()) {
			$this->setCategory(BVWPLP::UNBLOCKED);
		} else {
			$failed_attempts = $this->getLoginCount(BVWPLP::LOGINFAILURE, $this->ip, $this->getFailedLoginGap());
			if ($this->isWhitelistedIP()) {
				$this->setCategory(BVWPLP::BYPASSED);
			} else if (BVProtectBase::isPrivateIP($this->ip)) {
				$this->setCategory(BVWPLP::PRIVATEIP);
			} else if ($this->isBlacklistedIP()) {
				$this->setCategory(BVWPLP::BLACKLISTED);
				$this->terminateLogin();
			} else if ($this->isKnownLogin()) {
				$this->setCategory(BVWPLP::BYPASSED);
			} else if ($this->isLoginBlocked()) {
				$this->setCategory(BVWPLP::ALLBLOCKED);
				$this->terminateLogin();
			} else if ($failed_attempts >= $this->getTempBlockLimit()) {
				$this->setCategory(BVWPLP::TEMPBLOCK);
				$this->terminateLogin();
			} else if ($failed_attempts >= $this->getCaptchaLimit()) {
				$this->setCategory(BVWPLP::CAPTCHABLOCK);
				$this->terminateLogin();
			}
		}
		if (!empty($user) && !empty($password) && is_wp_error($user)) {
			$this->setMessage($user->get_error_code());
		}
		return $user;
	}

	public function loginFailed($username) {
		$this->setUserName($username);
		$this->log(BVWPLP::LOGINFAILURE);
	}

	public function loginSuccess($username) {
		$this->setUserName($username);
		$this->setMessage('Login Success');
		$this->log(BVWPLP::LOGINSUCCESS);
	}

	public function isKnownLogin() {
		return $this->getLoginCount(BVWPLP::LOGINSUCCESS, $this->ip, $this->getSuccessLoginGap()) > 0;
	}

	public function getLoginCount($status, $ip = null, $gap = 1800) {
		$db = $this->db;
		$table = $db->getBVTable(BVWPLP::$requests_table);
		$query = $db->prepare("SELECT COUNT(*) as count from `$table` WHERE status=%d && time > %d", array($status, ($this->time - $gap)));
		if ($ip) {
			$query .= $db->prepare(" && ip=%s", $ip);
		}
		$rows = $db->getResult($query);
		if (!$rows)
			return 0;
		return intval($rows[0]['count']);
	}
}
endif;