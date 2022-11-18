<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVWPSettings')) :
	class BVWPSettings {
		public function getOption($key) {
			$res = false;
			if (function_exists('get_site_option')) {
				$res = get_site_option($key, false);
			}
			if ($res === false) {
				$res = get_option($key, false);
			}
			return $res;
		}

		public function deleteOption($key) {
			if (function_exists('delete_site_option')) {
				return delete_site_option($key);
			} else {
				return delete_option($key);
			}
		}

		public function updateOption($key, $value) {
			if (function_exists('update_site_option')) {
				return update_site_option($key, $value);
			} else {
				return update_option($key, $value);
			}
		}

		public function getOptions($options = array()) {
			$result = array();

			foreach ($options as $option)
				$result[$option] = $this->getOption($option);

			return $result;
		}

		public function updateOptions($args) {
			$result = array();

			foreach ($args as $option => $value) {
				$this->updateOption($option, $value);
				$result[$option] = $this->getOption($option);
			}

			return $result;
		}

		public function deleteOptions($options) {
			$result = array();

			foreach ($options as $option) {
				$this->deleteOption($option);
				$result[$option] = !$this->getOption($option);
			}

			return $result;
		}

		public function setTransient($name, $value, $time) {
			if (function_exists('set_site_transient')) {
				return set_site_transient($name, $value, $time);
			}
			return false;
		}

		public function deleteTransient($name) {
			if (function_exists('delete_site_transient')) {
				return delete_site_transient($name);
			}
			return false;
		}

		public function getTransient($name) {
			if (function_exists('get_site_transient')) {
				return get_site_transient($name);
			}
			return false;
		}
	}
endif;