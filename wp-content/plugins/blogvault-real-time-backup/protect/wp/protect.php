<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('BVProtect')) :

require_once dirname( __FILE__ ) . '/../base.php';
require_once dirname( __FILE__ ) . '/logger.php';
require_once dirname( __FILE__ ) . '/ipstore.php';
require_once dirname( __FILE__ ) . '/../fw/fw.php';
require_once dirname( __FILE__ ) . '/../fw/config.php';
require_once dirname( __FILE__ ) . '/../fw/request.php';
require_once dirname( __FILE__ ) . '/lp/lp.php';
require_once dirname( __FILE__ ) . '/lp/config.php';

class BVProtect {
	public $db;
	public $settings;
	
	function __construct($db, $settings) {
		$this->settings = $settings;
		$this->db = $db;
	}

	public function init() {
		add_action('clear_pt_config', array($this, 'uninstall'));
	}

	public function run() {
		$bvipstore = new BVIPStore($this->db);
		$bvipstore->init();
		$bvinfo = new BVInfo($this->settings);

		$config = $this->settings->getOption($bvinfo->services_option_name);
		if (array_key_exists('protect', $config)) {
			$config = $config['protect'];
		} else {
			$config = array();
		}

		$ipHeader = array_key_exists('ipheader', $config) ? $config['ipheader'] : false;
		$ip = BVProtectBase::getIP($ipHeader);

		$fwLogger = new BVLogger($this->db, BVFWConfig::$requests_table);

		$fwConfHash = array_key_exists('fw', $config) ? $config['fw'] : array();
		$ruleSet = $this->getRuleSet();
		$fw = BVFW::getInstance($fwLogger, $fwConfHash, $ip, $bvinfo, $bvipstore, $ruleSet);

		if ($fw->isActive()) {

			if ($fw->canSetAdminCookie()) {
				add_action('init', array($fw, 'setBypassCookie'));
			}

			if (!defined('MCWAFLOADED') && $fw->canSetIPCookie()) {
				$fw->setIPCookie();
			}

			define('BVWPLOADED', true);

			if (!defined('MCWAFLOADED')) {
				register_shutdown_function(array($fw, 'log'));

				$fw->execute();
			}
			$fw->executeRules();
		}

		$lpConfHash = array_key_exists('lp', $config) ? $config['lp'] : array();
		$lp = new BVWPLP($this->db, $this->settings, $ip, $bvipstore, $lpConfHash);
		if ($lp->isActive()) {
			$lp->init();
		}
	}

	public function uninstall() {
		$this->settings->deleteOption('bvptconf');
		$this->db->dropBVTable(BVFWConfig::$requests_table);
		$this->db->dropBVTable(BVWPLPConfig::$requests_table);
		$this->settings->deleteOption('bvptplug');
		$this->remove_wp_prepend();
		$this->remove_php_prepend();
		$this->remove_mcdata();
		return true;
	}

	private function remove_wp_prepend() {
		$wp_conf_paths = array(ABSPATH . "wp-config.php", ABSPATH . "../wp-config.php");
		if (file_exists($wp_conf_paths[0])) {
			$fname = $wp_conf_paths[0];
		} elseif (file_exists($wp_conf_paths[1])) {
			$fname = $wp_conf_paths[1];
		} else {
			return;
		}

		$content = file_get_contents($fname);
		if ($content) {
			$pattern = "@include '" . ABSPATH . "malcare-waf.php" . "';";
			$modified_content = str_replace($pattern, "", $content);
			if ($content !== $modified_content) {
				file_put_contents($fname, $modified_content);
			}
		}
	}

	private function remove_php_prepend() {
		$this->remove_htaccess_prepend();
		$this->remove_userini_prepend();
	}

	private function remove_prepend($fname, $pattern) {
		if (!file_exists($fname)) return;

		$content = file_get_contents($fname);
		if ($content) {
			$modified_content = preg_replace($pattern, "", $content);
			if ($content !== $modified_content) {
				file_put_contents($fname, $modified_content);
			}
		}
	}

	private function remove_htaccess_prepend() {
		$pattern = "/# MalCare WAF(.|\n)*# END MalCare WAF/i";
		$this->remove_prepend(ABSPATH . ".htaccess", $pattern);
	}

	private function remove_userini_prepend() {
		$pattern = "/; MalCare WAF(.|\n)*; END MalCare WAF/i";
		$this->remove_prepend(ABSPATH . ".user.ini", $pattern);
	}

	private function remove_mcdata() {
		$this->rrmdir($this->get_contdir() . "mc_data");
	}

	private function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object)) {
						rrmdir($dir . "/" . $object);
					} else {
						unlink($dir . "/" . $object);
					}
				}
			}
			rmdir($dir);
		}
	}

	public function get_contdir() {
		return defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . "/" : ABSPATH . "wp-content/";
	}

	public function getRuleSet() {
		$ruleSet = $this->settings->getOption('bvruleset');
		if ($ruleSet) {
			return $ruleSet;
		}
		return array();
	}
}
endif;