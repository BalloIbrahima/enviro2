<?php
if (!defined('MCDATAPATH')) exit;

if (!class_exists('BVPrependProtect')) :

require_once dirname( __FILE__ ) . '/../base.php';
require_once dirname( __FILE__ ) . '/../fw/fw.php';
require_once dirname( __FILE__ ) . '/../fw/request.php';
require_once dirname( __FILE__ ) . '/../fw/config.php';
require_once dirname( __FILE__ ) . '/info.php';
require_once dirname( __FILE__ ) . '/ipstore.php';
require_once dirname( __FILE__ ) . '/logger.php';

	class BVPrependProtect {
		public $mcConfFile;
		public $mcIPsFile;
		public $mcRulesFile;

		function __construct() {
			$this->mcConfFile = MCDATAPATH .  MCCONFKEY .	'-' . 'mc.conf';
			$this->mcIPsFile = MCDATAPATH . MCCONFKEY . '-' . 'mc_ips.conf';
			$this->mcRulesFile = MCDATAPATH . MCCONFKEY . '-' . 'mc_rules.json';
		}

		public function parseFile($fname) {
			$result = array();

			if (file_exists($fname)) {
				$content = file_get_contents($fname);
				if (($content !== false) && is_string($content)) {
					$result = json_decode($content, true);

					if (!is_array($result)) {
						$result = array();
					}
				}
			}

			return $result;
		}

		public function run() {
			$mcConf = $this->parseFile($this->mcConfFile);
			$mcIPsConf = $this->parseFile($this->mcIPsFile);
			$mcRuleSet = $this->parseFile($this->mcRulesFile);

			if (!array_key_exists('time', $mcConf) || !isset($mcConf['time']) || !($mcConf['time'] > time() - (48*3600))) {
				return false;
			}

			$brand = array_key_exists('brandname', $mcConf) ? $mcConf['brandname'] : "Protect";
			$bvinfo = new BVPrependInfo($brand);
			$bvipstore = new BVPrependIPStore($mcIPsConf);

			$ipHeader = array_key_exists('ipheader', $mcConf) ? $mcConf['ipheader'] : false;
			$ip = BVProtectBase::getIP($ipHeader);

			$fwlogger = new BVPrependLogger();

			$fwConfHash = array_key_exists('fw', $mcConf) ? $mcConf['fw'] : array();
			$fw = BVFW::getInstance($fwlogger, $fwConfHash, $ip, $bvinfo, $bvipstore, $mcRuleSet);

			if ($fw->isActive()) {

				if ($fw->canSetIPCookie()) {
					$fw->setIPCookie();
				}

				register_shutdown_function(array($fw, 'log'));

				$fw->execute();
				$fw->executeRules();
				define('MCWAFLOADED', true);
			}

			return true;
		}

	}
endif;