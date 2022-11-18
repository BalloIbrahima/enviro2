<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVWPActLog')) :

	class BVWPActLog {

		public static $actlog_table = 'activities_store';
		public $db;
		public $settings;
		public $bvinfo;

		public function __construct($db, $settings, $info, $config) {
			$this->db = $db;
			$this->settings = $settings;
			$this->bvinfo = $info;
			$this->request_id = BVInfo::getRequestID();
			$this->ip_header = array_key_exists('ip_header', $config) ? $config['ip_header'] : false;
		}

		function init() {
			$this->add_actions_and_listeners();
		}

		function get_post($post_id) {
			$post = get_post($post_id);
			$data = array('id' => $post_id);
			if (!empty($post)) {
				$data['title'] = $post->post_title;
				$data['status'] = $post->post_status;
				$data['type'] = $post->post_type;
				$data['url'] = get_permalink($post_id);
				$data['date'] = $post->post_date;
			}
			return $data;
		}

		function get_comment($comment_id) {
			$comment = get_comment($comment_id);
			$data = array('id' => $comment_id);
			if (!empty($comment)) {
				$data['author'] = $comment->comment_author;
				$data['post_id'] = $comment->comment_post_ID;
			}
			return $data;
		}

		function get_term($term_id) {
			$term = get_term($term_id);
			$data = array('id' => $term_id);
			if (!empty($term)) {
				$data['name'] = $term->name;
				$data['slug'] = $term->slug;
				$data['taxonomy'] = $term->taxonomy;
			}
			return $data;
		}

		function get_user($user_id) {
			$user = get_userdata($user_id);
			$data = array('id' => $user_id);
			if (!empty($user)) {
				$data['username'] = $user->user_login;
				$data['email'] = $user->user_email;
			}
			return $data;
		}

		function get_blog($blog_id) {
			$blog = get_blog_details($blog_id);
			$data = array('id' => $blog_id);
			if (!empty($blog)) {
				$data['name'] = $blog->blogname;
				$data['url'] = $blog->path;
			}
			return $data;
		}

		function wc_get_attribute($attribute_id, $attribute_data = null) {
			$data = array('id' => $attribute_id);
			if (!is_null($attribute_data) && is_array($attribute_data)) {
				$data['name'] = $attribute_data['attribute_label'];
				$data['slug'] = $attribute_data['attribute_name'];
			} else {
				$attribute = wc_get_attribute($attribute_id);
				if (!empty($attribute)) {
					$data['name'] = $attribute->name;
					$data['slug'] = substr($attribute->slug, 3);
				}
			}
			return $data;
		}

		function wc_get_tax_rate($tax_rate_id, $tax_rate) {
			$data = array('id' => $tax_rate_id);
			if (!empty($tax_rate)) {
				$data['name'] = array_key_exists('tax_rate_name', $tax_rate) ? $tax_rate['tax_rate_name'] : '';
				$data['country'] = array_key_exists('tax_rate_country', $tax_rate) ? $tax_rate['tax_rate_country'] : '';
				$data['rate'] = array_key_exists('tax_rate', $tax_rate) ? $tax_rate['tax_rate'] : '';
			}
			return $data;
		}

		function get_ip($ipHeader) {
			$ip = '127.0.0.1';
			if ($ipHeader && is_array($ipHeader)) {
				if (array_key_exists($ipHeader['hdr'], $_SERVER)) {
					$_ips = preg_split("/(,| |\t)/", $_SERVER[$ipHeader['hdr']]);
					if (array_key_exists(intval($ipHeader['pos']), $_ips)) {
						$ip = $_ips[intval($ipHeader['pos'])];
					}
				}
			} else if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
				$ip = $_SERVER['REMOTE_ADDR'];
			}

			$ip = trim($ip);
			if (preg_match('/^\[([0-9a-fA-F:]+)\](:[0-9]+)$/', $ip, $matches)) {
				$ip = $matches[1];
			} elseif (preg_match('/^([0-9.]+)(:[0-9]+)$/', $ip, $matches)) {
				$ip = $matches[1];
			}

			return $ip;
		}

		function add_activity($event_data) {
			if (!function_exists('wp_get_current_user')) {
				@include_once(ABSPATH . "wp-includes/pluggable.php");
			}

			$user = wp_get_current_user();
			$values = array();
			if (!empty($user)) {
				$values["user_id"] = $user->ID;
				$values["username"] = $user->user_login;
			}
			$values["request_id"] = $this->request_id;
			$values["site_id"] = get_current_blog_id();
			$values["ip"] = $this->get_ip($this->ip_header);
			$values["event_type"] = current_filter();
			$values["event_data"] = maybe_serialize($event_data);
			$values["time"] = time();
			$this->db->replaceIntoBVTable(BVWPActLog::$actlog_table, $values);
		}

		function user_login_handler($user_login, $user) {
			$event_data = array("user" => $this->get_user($user->ID));
			$this->add_activity($event_data);
		}

		function user_logout_handler($user_id) {
			$user = $this->get_user($user_id);
			$event_data = array("user" => $user);
			$this->add_activity($event_data);
		}

		function password_reset_handler($user, $new_pass) {
			if (!empty($user)) {
				$event_data = array("user" => $this->get_user($user->ID));
				$this->add_activity($event_data);
			}
		}

		function comment_handler($comment_id) {
			$comment = $this->get_comment($comment_id);
			$post = $this->get_post($comment['post_id']);
			$event_data = array(
				"comment" => $comment,
				"post" => $post
			);
			$this->add_activity($event_data);
		}

		function comment_status_changed_handler($new_status, $old_status, $comment) {
			$post = $this->get_post($comment->comment_post_ID);
			$event_data = array(
				"comment" => $this->get_comment($comment->comment_ID),
				"post" => $post,
				"old_status" => $old_status,
				"new_status" => $new_status
			);
			$this->add_activity($event_data);
		}

		function post_handler($post_id) {
			$post = $this->get_post($post_id);
			$event_data = array();
			if ($post["type"] === "product") {
				$event_data["product"] = $post;
			} elseif ($post["type"] === "shop_order") {
				$event_data["order"] = $post;
			} else {
				$event_data["post"] = $post;
			}
			$this->add_activity($event_data);
		}

		function post_saved_handler($post_id, $post, $update) {
			$post = $this->get_post($post_id);
			$event_data = array();
			if ($post["type"] === "product") {
				$event_data["product"] = $post;
			} elseif ($post["type"] === "shop_order") {
				$event_data["order"] = $post;
			} else {
				$event_data["post"] = $post;
			}
			$event_data["updated"] = $update;
			$this->add_activity($event_data);
		}

		function term_handler($term_id) {
			$term = $this->get_term($term_id);
			$event_data = array(
				"term" => $term,
			);
			$this->add_activity($event_data);
		}

		function term_updation_handler($data, $term_id) {
			$term = $this->get_term($term_id);
			$event_data = array(
				"term" => $term,
				"new_term" => $data
			);
			$this->add_activity($event_data);
			return $data;
		}

		function term_deletion_handler($term_id) {
			$event_data = array(
				"term" => array("id" => $term_id)
			);
			$this->add_activity($event_data);
		}

		function user_handler($user_id) {
			$user = $this->get_user($user_id);
			$event_data = array(
				"user" => $user,
			);
			$this->add_activity($event_data);
		}

		function user_update_handler($user_id, $old_userdata) {
			$new_userdata = $this->get_user($user_id);
			$event_data = array(
				"old_user" => $this->get_user($old_userdata->ID),
				"user" => $new_userdata,
			);
			$this->add_activity($event_data);
		}

		function plugin_action_handler($plugin) {
			$event_data = array("plugin" => $plugin);
			$this->add_activity($event_data);
		}

		function theme_action_handler($theme_name) {
			$event_data = array("theme" => $theme_name);
			$this->add_activity($event_data);
		}

		function mu_handler($blog_id) {
			$blog = $this->get_blog($blog_id);
			$event_data = array(
				"blog" => $blog
			);
			$this->add_activity($event_data);
		}

		function mu_site_handler($blog) {
			$event_data = array(
				"blog" => $this->get_blog($blog->blog_id)
			);
			$this->add_activity($event_data);
		}

		function woocommerce_attribute_created_handler($attribute_id, $attribute_data) {
			$event_data = array(
				"attribute" => $this->wc_get_attribute($attribute_id, $attribute_data)
			);
			$this->add_activity($event_data);
		}

		function woocommerce_attribute_handler($attribute_id) {
			$event_data = array(
				"attribute" => $this->wc_get_attribute($attribute_id)
			);
			$this->add_activity($event_data);
		}

		function woocommerce_tax_rate_handler($tax_rate_id, $tax_rate) {
			$event_data = array(
				"tax_rate" => $this->wc_get_tax_rate($tax_rate_id, $tax_rate)
			);
			$this->add_activity($event_data);
		}

		function woocommerce_tax_rate_deleted_handler($tax_rate_id) {
			$event_data = array(
				"tax_rate" => array("id" => $tax_rate_id)
			);
			$this->add_activity($event_data);
		}

		function woocommerce_grant_product_download_access_handler($data) {
			$event_data = array(
				"download_id" => $data['download_id'],
				"user_id" => $data['user_id'],
				"order_id" => $data['order_id'],
				"product_id" => $data['product_id']
			);
			$this->add_activity($event_data);
		}

		function woocommerce_revoke_access_to_product_download_handler($download_id, $product_id, $order_id) {
			$event_data = array(
				"download_id" => $download_id,
				"product_id" => $product_id,
				"order_id" => $order_id
			);
			$this->add_activity($event_data);
		}

		function woocommerce_shipping_zone_method_handler($instance_id, $method_id, $zone_id) {
			$event_data = array(
				"instance_id" => absint ($instance_id),
				"method_id" => $method_id,
				"zone_id" => $zone_id
			);
			$this->add_activity($event_data);
		}

		function get_plugin_update_data($plugins) {
			$data = array();
			if (!empty($plugins) && defined('WP_PLUGIN_DIR')) {
				foreach ($plugins as $plugin) {
					$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
					$install_data = array('title' => $plugin_data['Name'], 'version' => $plugin_data['Version']);
					array_push($data, $install_data);
				}
			}
			return $data;
		}

		function get_theme_update_data($themes) {
			$data = array();
			if (!empty($themes)) {
				foreach ($themes as $theme) {
					$theme_data = wp_get_theme($theme);
					$install_data = array('title' => $theme_data['Name'], 'version' => $theme_data['Version']);
					array_push($data, $install_data);
				}
			}
			return $data;
		}

		function get_plugin_install_data($upgrader) {
			$data = array();
			if ($upgrader->bulk != "1") {
				$plugin_data = $upgrader->new_plugin_data;
				$install_data = array('title' => $plugin_data['Name'], 'version' => $plugin_data['Version']);
				array_push($data, $install_data);
			}
			return $data;
		}

		function get_theme_install_data($upgrader) {
			$data = array();
			$theme_data = $upgrader->new_theme_data;
			$install_data = array('title' => $theme_data['Name'], 'version' => $theme_data['Version']);
			array_push($data, $install_data);
			return $data;
		}

		function get_update_data($options) {
			global $wp_version;
			$event_data = array('action' => 'update');
			if ($options['type'] === 'plugin') {
				$event_data['type'] = 'plugin';
				$event_data['plugins'] = $this->get_plugin_update_data($options['plugins']);
			}
			else if ($options['type'] === 'theme') {
				$event_data['type'] = 'theme';
				$event_data['themes'] = $this->get_theme_update_data($options['themes']);
			}
			else if ($options['type'] === 'core') {
				$event_data['type'] = 'core';
				$event_data['wp_core'] = array('prev_version' => $wp_version);
			}
			return $event_data;
		}

		function get_install_data($upgrader, $options) {
			$event_data = array('action' => 'install');
			if ($options['type'] === 'plugin') {
				$event_data['type'] = 'plugin';
				$event_data['plugins'] = $this->get_plugin_install_data($upgrader);
			}
			else if ($options['type'] === 'theme') {
				$event_data['type'] = 'theme';
				$event_data['themes'] = $this->get_theme_install_data($upgrader);
			}
			return $event_data;
		}

		function upgrade_handler($upgrader, $data) {
			$event_data = array();
			if ($data['action'] === 'update') {
				$event_data = $this->get_update_data($data);
			} else if ($data['action'] === 'install') {
				$event_data = $this->get_install_data($upgrader, $data);
			}
			$this->add_activity($event_data);
		}

		/* ADDING ACTION AND LISTENERS FOR SENSING EVENTS. */
		public function add_actions_and_listeners() {
			/* SENSORS FOR POST AND PAGE CHANGES */
			add_action('pre_post_update', array($this, 'post_handler'));
			add_action('save_post', array($this, 'post_saved_handler'), 10, 3);
			add_action('post_stuck', array($this, 'post_handler'));
			add_action('post_unstuck', array($this, 'post_handler'));
			add_action('delete_post', array($this, 'post_handler'));

			/* SENSORS FOR COMMENTS */
			add_action('comment_post', array($this, 'comment_handler'));
			add_action('edit_comment', array($this, 'comment_handler'));
			add_action('transition_comment_status', array($this, 'comment_status_changed_handler'), 10, 3);

			/* SENSORS FOR TAG AND CATEGORY CHANGES */
			add_action('create_term', array($this, 'term_handler'));
			add_action('pre_delete_term', array($this, 'term_handler'));
			add_action('delete_term', array($this, 'term_deletion_handler'));
			add_filter('wp_update_term_data', array($this, 'term_updation_handler'), 10, 2);

			/* SENSORS FOR USER CHANGES*/
			add_action('user_register', array($this, 'user_handler'));
			add_action('wpmu_new_user', array($this, 'user_handler'));
			add_action('profile_update', array($this, 'user_update_handler'), 10, 2);
			add_action('delete_user', array($this, 'user_handler'));
			add_action('wpmu_delete_user', array($this, 'user_handler'));

			/* SENSORS FOR PLUGIN AND THEME*/
			add_action('activate_plugin', array($this, 'plugin_action_handler'));
			add_action('deactivate_plugin', array($this, 'plugin_action_handler'));
			add_action('switch_theme', array($this, 'theme_action_handler'));

			/* SENSORS FOR MULTISITE CHANGES */
			add_action('wp_insert_site', array($this, 'mu_site_handler'));
			add_action('archive_blog', array($this, 'mu_handler'));
			add_action('unarchive_blog', array( $this, 'mu_handler'));
			add_action('activate_blog', array($this, 'mu_handler'));
			add_action('deactivate_blog', array($this, 'mu_handler'));
			add_action('wp_delete_site', array($this, 'mu_site_handler'));

			/* SENSORS USER ACTIONS AT FRONTEND */
			add_action('wp_login', array($this, 'user_login_handler'), 10, 2);
			add_action('wp_logout', array( $this, 'user_logout_handler'), 5, 1);
			add_action('password_reset', array( $this, 'password_reset_handler'), 10, 2);

			/* SENSOR FOR PLUGIN, THEME, WPCORE UPGRADES */
			add_action('upgrader_process_complete', array($this, 'upgrade_handler'), 10, 2);

			/* SENSORS FOR WOOCOMMERCE EVENTS */
			add_action('woocommerce_attribute_added', array($this, 'woocommerce_attribute_created_handler'), 10, 2);
			add_action('woocommerce_attribute_updated', array($this, 'woocommerce_attribute_handler'), 10, 1);
			add_action('woocommerce_before_attribute_delete', array($this, 'woocommerce_attribute_handler'), 10, 1);
			add_action('woocommerce_attribute_deleted', array($this, 'woocommerce_attribute_handler'), 10, 1);

			add_action('woocommerce_tax_rate_added', array($this, 'woocommerce_tax_rate_handler'), 10, 2);
			add_action('woocommerce_tax_rate_deleted', array($this, 'woocommerce_tax_rate_deleted_handler'), 10, 1);
			add_action('woocommerce_tax_rate_updated', array($this, 'woocommerce_tax_rate_handler'), 10, 2);

			add_action('woocommerce_grant_product_download_access', array($this, 'woocommerce_grant_product_download_access_handler'), 10, 1);
			add_action('woocommerce_ajax_revoke_access_to_product_download', array($this, 'woocommerce_revoke_access_to_product_download_handler'), 10, 3);

			add_action('woocommerce_shipping_zone_method_added', array($this, 'woocommerce_shipping_zone_method_handler'), 10, 3);
			add_action('woocommerce_shipping_zone_method_status_toggled', array($this, 'woocommerce_shipping_zone_method_handler'), 10, 3);
			add_action('woocommerce_shipping_zone_method_deleted', array($this, 'woocommerce_shipping_zone_method_handler'), 10, 3);
		}
	}
endif;