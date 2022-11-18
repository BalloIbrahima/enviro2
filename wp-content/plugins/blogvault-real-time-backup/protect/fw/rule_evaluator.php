<?php

if (!(defined('ABSPATH') || defined('MCDATAPATH'))) exit;
if (!class_exists('BVFWRuleEvaluator')) :

class BVFWRuleEvaluator {
	private $request;

	const VERSION = 0.4;

	public function __construct($fw) {
		$this->fw = $fw;
		$this->request = $fw->request;
	}

	function getErrors() {
			return $this->errors;
	}

	function resetErrors() {
		$this->errors = array();
	}

	// ================================ Functions for type checking ========================================
	function isNumeric($value) {
		return (preg_match('/^\d+$/', $value));
	}

	function isRegularWord($value) {
		return (preg_match('/^\w+$/', $value));
	}

	function isSpecialWord($value) {
		return (preg_match('/^\S+$/', $value));
	}

	function isRegularSentence($value) {
		return (preg_match('/^[\w\s]+$/', $value));
	}

	function isSpecialCharsSentence($value) {
		return (preg_match('/^[\w\W]+$/', $value));
	}

	function isLink($value) {
		return (preg_match('/^(http|ftp)s?:\/\/\S+$/i', $value));
	}

	function isFileUpload($value) {
		$file = $this->getFiles($value);
		if (is_array($file) && in_array('tmp_name', $file)) {
			return is_uploaded_file($file['tmp_name']);
		}
		return false;
	}

	function isIpv4($value) {
		return (preg_match('/^\b((25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\.){3}(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\b$/x', $value));
	}

	function isEmbededIpv4($value) {
		return (preg_match('/\b((25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\.){3}(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\b/x', $value));
	}

	function isIpv6($value) {
		return (preg_match('/^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/x', $value));
	}

	function isEmbededIpv6($value) {
		return (preg_match('/(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/x', $value));
	}

	function isEmail($value) {
		return (preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/', $value));
	}

	function isEmbededEmail($value) {
		return (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}/', $value));
	}

	function isEmbededLink($value) {
		return (preg_match('/(http|ftp)s?:\/\/\S+$/i', $value));
	}

	function isEmbededHtml($value) {
		return (preg_match('/<(html|head|title|base|link|meta|style|picture|source|img|iframe|embed|object|param|video|audio|track|map|area|form|label|input|button|select|datalist|optgroup|option|textarea|output|progress|meter|fieldset|legend|script|noscript|template|slot|canvas)/ix', $value));
	}

	function isFile($value) {
		return (preg_match('/\.(jpg|jpeg|png|gif|ico|pdf|doc|docx|ppt|pptx|pps|ppsx|odt|xls|zip|gzip|xlsx|psd|mp3|m4a|ogg|wav|mp4|m4v|mov|wmv|avi|mpg|ogv|3gp|3g2|php|html|phtml|js|css)/ix', $value));
	}

	function isPathTraversal($value) {
		return (preg_match('/(?:\.{2}[\/]+)/', $value));
	}

	function isPhpEval($value) {
		return (preg_match('/\\b(?i:eval)\\s*\\(\\s*(?i:base64_decode|exec|file_get_contents|gzinflate|passthru|shell_exec|stripslashes|system)\\s*\\(/', $value));
	}

	// ================================ Functions to perform operations ========================================
	function inArray($element, $array) {
		if (is_array($array)) {
			return in_array($element, $array);
		}

		array_push($this->errors, array("inArray", "Expects an array"));
		return false;
	}

	function isSubstring($string, $substring) {
		return strpos((string) $string, (string) $substring) !== false;
	}

	function containsAnySubstring($string, $array_of_substrings) {
		if (is_array($array_of_substrings)) {
			foreach ($array_of_substrings as $i => $substring) {
				if ($this->isSubstring($string, $substring)) {
					return true;
				}
			}
		} else {
			array_push($this->errors, array("containsAnySubstring", "Expects an array of substrings."));
		}
		return false;
	}

	function match($pattern, $subject) {
		if (is_array($subject)) {
			foreach ($subject as $k => $v) {
				if ($this->match($pattern, $v)) {
					return true;
				}
			}
			return false;
		}
		$resp = preg_match((string) $pattern, (string) $subject);
		if ($resp === false) {
			array_push($this->errors, array("preg_match", $subject));
		} else if ($resp > 0) {
			return true;
		}
		return false;
	}

	function notMatch($pattern, $subject) {
		return !$this->match($pattern, $subject);
	}

	function matchCount($pattern, $subject) {
		$count = 0;
		if (is_array($subject)) {
			foreach ($subject as $val) {
				$count += $this->matchCount($pattern, $val);
			}
			return $count;
		}
		$count = preg_match_all((string) $pattern, (string) $subject, $matches);
		if ($count === false) {
			array_push($this->errors, array("preg_match_all", $subject));
		}
		return $count;
	}

	function maxMatchCount($pattern, $subject) {
		$count = 0;
		if (is_array($subject)) {
			foreach ($subject as $val) {
				$count = max($count, $this->matchCount($pattern, $val));
			}
			return $count;
		}
		$count = preg_match_all((string) $pattern, (string) $subject, $matches);
		if ($count === false) {
			array_push($this->errors, array("preg_match_all", $subject));
		}
		return $count;
	}

	function equals($val, $subject) {
		return ($val == $subject);
	}

	function notEquals($val, $subject) {
		return !$this->equals($val, $subject);
	}

	function isIdentical($val, $subject) {
		return ($val === $subject);
	}

	function notIdentical($val, $subject) {
		return !$this->isIdentical($val, $subject);
	}

	function greaterThan($val, $subject) {
		return ($subject > $val);
	}

	function greaterThanEqualTo($val, $subject) {
		return ($subject >= $val);
	}

	function lessThan($val, $subject) {
		return ($subject < $val);
	}

	function lessThanEqualTo($val, $subject) {
		return ($subject <= $val);
	}

	function lengthGreaterThan($val, $subject) {
		return (strlen((string) $subject) > $val);
	}

	function lengthLessThan($val, $subject) {
		return (strlen((string) $subject) < $val);
	}

	function md5Equals($val, $subject) {
		return (md5((string) $subject) === $val);
	}

	function matchActions($actions) {
		return $this->inArray($this->getAction(), $actions);
	}

	function compareMultipleSubjects($func, $args, $subjects) {
		// TODO
	}

	// ================================ Functions to get request data ========================================
	function getReqInfo($key) {
		return $this->request->getReqInfo($key);
	}

	function getAction() {
		return $this->request->getAction();
	}

	function getPath() {
		return $this->request->getPath();
	}

	function getServerValue($key) {
		return $this->request->getServerValue($key);
	}

	function getHeader($key) {
		return $this->request->getHeader($key);
	}

	function getHeaders() {
		return $this->request->getHeaders();
	}

	function getPostParams() {
		if (func_num_args() > 0) {
			$args = func_get_args();
			return $this->request->getPostParams($args);
		}
		return $this->request->getPostParams();
	}

	function getReqMethod() {
		return $this->request->getMethod();
	}

	function getGetParams() {
		if (func_num_args() > 0) {
			$args = func_get_args();
			return $this->request->getGetParams($args);
		}
		return $this->request->getGetParams();
	}

	function getCookies() {
		if (func_num_args() > 0) {
			$args = func_get_args();
			return $this->request->getCookies($args);
		}
		return $this->request->getCookies();
	}

	function getFiles() {
		if (func_num_args() > 0) {
			$args = func_get_args();
			return $this->request->getFiles($args);
		}
		return $this->request->getFiles();
	}

	function getFileNames() {
		if (func_num_args() > 0) {
			$args = func_get_args();
			return $this->request->getFileNames($args);
		}
		return $this->request->getFileNames();
	}

	function getHost() {
		return $this->host;
	}

	function getURI() {
		return $this->request->getURI();
	}

	function getIP() {
		return $this->request->getIP();
	}

	function getTimestamp() {
		return $this->request->getTimeStamp();
	}

	function getUserRoleLevel() {
		return $this->request->getUserRoleLevel();
	}

	function isUserRoleLevel($level) {
		return $this->request->isUserRoleLevel($level);
	}

	function getAllParams() {
		return $this->request->getAllParams();
	}

	// ================================ Functions to evaluate rule logic ========================================
	function evaluateRule($ruleLogic) {
		return $this->evaluateExpression($ruleLogic);
	}
	
	function evaluateExpression($expr) {
		switch ($expr["type"]) {
		case "AND" :
			return ($this->getValue($expr["left_operand"]) &&
					$this->getValue($expr["right_operand"]));
		case "OR" :
			return ($this->getValue($expr["left_operand"]) ||
					$this->getValue($expr["right_operand"]));
		case "NOT" :
			return !$this->getValue($expr["value"]);
		case "FUNCTION" :
			return $this->executeFunctionCall($expr);
		default :
			break;
		}
	}

	function fetchConstantValue($name) {
		$value = constant($name);
		if ($value) {
			return $value;
		}
		array_push($this->errors, array("fetch_constant_value", $name));
		return false;
	}

	function getArgs($args) {
		$_args = array();
		foreach ($args as $arg) {
			array_push($_args, $this->getValue($arg));
		}
		return $_args;
	}

	function loadPluggable() {
		if (!function_exists('wp_get_current_user')) {
			@include_once(ABSPATH . "wp-includes/pluggable.php");
		}
	}

	function addWPAction($hook_name, $func_name, $priority, $accepted_args, $config) {
		$this->loadPluggable();
		add_action($hook_name, array($this, $func_name), $priority, $accepted_args);
		$this->setVariable($hook_name, $config);
		return false;
	}

	function addWPFilter($hook_name, $func_name, $priority, $accepted_args, $config) {
		$this->loadPluggable();
		add_filter($hook_name, array($this, $func_name), $priority, $accepted_args);
		$this->setVariable($hook_name, $config);
		return false;
	}

	function setVariable($name, $value) {
		$this->{$name} = $value;
	}

	function getVariable($name) {
		return $this->{$name};
	}

	function preInsertUpdatePost($maybe_empty, $postarr) {
		$curr_hook = current_filter();
		$config = $this->getVariable($curr_hook);
		$posts_to_consider = $config["posts_to_consider"];
		$rule_id = $config["rule_id"];
		if (in_array($postarr['post_type'], $posts_to_consider)) {
			if ((!empty($postarr['ID']) && !current_user_can("edit_{$postarr['post_type']}", $postarr['ID']))
					|| !current_user_can("edit_posts")) {
				$log_data = array($postarr['post_type'], $postarr['ID']);
				$this->request->updateRulesInfo("wp_hook_info", $curr_hook, $log_data);
				$this->fw->handleMatchedRule($rule_id);
			}
		}
		return false;
	}

	function preDeletePost($delete, $post) {
		$curr_hook = current_filter();
		$config = $this->getVariable($curr_hook);
		$posts_to_consider = $config["posts_to_consider"];
		$rule_id = $config["rule_id"];
		if (isset($post->post_type) && in_array($post->post_type, $posts_to_consider) &&
				!current_user_can("delete_{$post->post_type}", $post->ID)) {
			$log_data = array($post->post_type, $post->ID);
			$this->request->updateRulesInfo("wp_hook_info", $curr_hook, $log_data);
			$this->fw->handleMatchedRule($rule_id);
		}
	}

	function preUserCreationV2($meta, $user, $update, $userdata) {
		$curr_hook = current_filter();
		$config = $this->getVariable($curr_hook);
		$rule_id = $config["rule_id"];
		$username = sanitize_user($userdata['user_login'], true);
		$roles_not_allowed = $config["roles_not_allowed"];

		if (!$update && !current_user_can('create_users') &&
				(isset($userdata['role']) && in_array($userdata['role'], $roles_not_allowed))) {
			$log_data = array($user->ID, $username, $userdata['role']);
			$this->request->updateRulesInfo("wp_hook_info", $curr_hook, $log_data);
			$this->fw->handleMatchedRule($rule_id);
		}
		return $meta;
	}

	function preDeletePostV2($delete, $post) {
		$curr_hook = current_filter();
		$config = $this->getVariable($curr_hook);
		$posts_to_consider = $config["posts_to_consider"];
		$rule_id = $config["rule_id"];

		if (isset($post->post_type) && isset($post->post_status) &&
				in_array(array($post->post_type, $post->post_status), $posts_to_consider) &&
				!current_user_can("delete_{$post->post_type}", $post->ID)) {
			$log_data = array($post->ID, $post->post_type, $post->status);
			$this->request->updateRulesInfo("wp_hook_info", $curr_hook, $log_data);
			$this->fw->handleMatchedRule($rule_id);
		}
	}

	function preUserCreation($user_login) {
		$curr_hook = current_filter();
		$config = $this->getVariable($curr_hook);
		$rule_id = $config["rule_id"];
		if (!username_exists($user_login) && !current_user_can('create_users')) {
			$this->request->updateRulesInfo("wp_hook_info", $curr_hook, $user_login);
			$this->fw->handleMatchedRule($rule_id);
		}
		return $user_login;
	}

	function preDeleteUser($id, $reassign, $user) {
		$curr_hook = current_filter();
		$config = $this->getVariable($curr_hook);
		$rule_id = $config["rule_id"];
		if (!current_user_can('delete_users')) {
			$log_data = array($id, $reassign, array("ID" => $user->ID,
					"username" => $user->user_login,
					"user_email" => $user->user_email,
					"caps" => $user->allcaps,
					"roles" => $user->roles));
			$this->request->updateRulesInfo("wp_hook_info", $curr_hook, $log_data);
			$this->fw->handleMatchedRule($rule_id);
		}
	}

	function handleOption($option, $log_data) {
		$curr_hook = current_filter();
		$config = $this->getVariable($curr_hook);
		$options_to_consider = $config["options_to_consider"];
		$rule_id = $config["rule_id"];
		if (in_array($option, $options_to_consider) && !current_user_can('manage_options')) {
			$this->request->updateRulesInfo("wp_hook_info", $curr_hook, $log_data);
			$this->fw->handleMatchedRule($rule_id);
		}
	}

	function preUpdateOption($value, $option, $old_value) {
		if ($value !== $old_value && maybe_serialize($value) !== maybe_serialize($old_value)) {
			$log_data = array($option, $value, $old_value);
			$this->handleOption($option, $log_data);
		}
		return $value;
	}

	function preDeleteOption($option) {
		$this->handleOption($option, $option);
		return $option;
	}

	function executeFunctionCall($func) {
		$name = $func["name"];
		$handler = array($this, $name);
		if (!is_callable($handler)) {
			array_push($this->errors, array("execute_function_call", "function_not_allowed", $name));
			return false;
		}
		return call_user_func_array($handler, $this->getArgs($func["args"]));
	}

	function getValue($expr) {
		switch ($expr["type"]) {
		case "NUMBER" :
			return $expr["value"];
		case "STRING" :
			return $expr["value"];
		case "STRING_WITH_QUOTES" :
			$expr["value"] = preg_replace("/^('|\")/", "", $expr["value"]);
			$expr["value"] = preg_replace("/('|\")$/", "", $expr["value"]);
			return $expr["value"];
		case "CONST" :
			return $this->fetchConstantValue($expr["value"]);
		case "FUNCTION" :
			return $this->executeFunctionCall($expr);
		case "ARRAY" :
			$arr = array();
			foreach ($expr["value"] as $element) {
				$arr[] = $this->getValue($element);
			}
			return $arr;
		case "HASH" :
			$hash = array();
			foreach($expr["value"] as $key => $value) {
				$hash[strval($key)] = $value;
			}
			return $hash;
		default :
			return $this->evaluateExpression($expr);
		}
	}
}
endif;