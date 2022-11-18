<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVManageCallback')) :
class BVManageCallback extends BVCallbackBase {
	public $settings;
	public $skin;

	const MANAGE_WING_VERSION = 1.0;

	public function __construct($callback_handler) {
		$this->settings = $callback_handler->settings;
	}

	function getError($err) {
		return $this->objectToArray($err);
	}	

	function is_pantheon() {
		return (!empty($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] !== 'dev');
	}

	function isServerWritable() {
		if ($this->is_pantheon()) {
			return false;
		}

		if ((!defined('FTP_HOST') || !defined('FTP_USER')) && (get_filesystem_method(array(), false) != 'direct')) {
			return false;
		} else {
			return true;
		}
	}

	function include_files() {
		@include_once ABSPATH.'wp-admin/includes/file.php';
		@include_once ABSPATH.'wp-admin/includes/plugin.php';
		@include_once ABSPATH.'wp-admin/includes/theme.php';
		@include_once ABSPATH.'wp-admin/includes/misc.php';
		@include_once ABSPATH.'wp-admin/includes/template.php';
		@include_once ABSPATH.'wp-includes/pluggable.php';
		@include_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
		@include_once ABSPATH.'wp-admin/includes/user.php';
		@include_once ABSPATH.'wp-includes/registration.php';
		@include_once ABSPATH.'wp-admin/includes/upgrade.php';
		@include_once ABSPATH.'wp-admin/includes/update.php';
		@require_once ABSPATH.'wp-admin/includes/update-core.php';
	}

	function edit($args) {
		$result = array();
		if ($args['type'] == 'plugins') {
			$result['plugins'] = $this->editPlugins($args);
		} elseif ($args['type'] == 'themes') {
			$result['themes'] = $this->editThemes($args);
		} elseif ($args['type'] == 'users') {
			$result['users'] = $this->editWpusers($args);
		}
		return $result;
	}

	function editPlugins($args) {
		$result = array();
		$plugins = $args['items'];
		foreach ($plugins as $plugin) {
			if (array_key_exists('network', $plugin)) {
				$networkwide = $plugin['network'];
			} else {
				$networkwide = false;
			}
			switch ($args['action']) {
			case 'activate':
				$res = activate_plugin($plugin['file'], '', $networkwide);
				break;
			case 'deactivate':
				$res = deactivate_plugins(array($plugin['file']), false, $networkwide);
				break;
			case 'delete':
				$res = delete_plugins(array($plugin['file']));
				break;
			case 'deactivate_delete':
				$res = deactivate_plugins(array($plugin['file']), false, $networkwide);
				if ($res || is_wp_error($res))
					break;
				$res = delete_plugins(array($plugin['file']));
			default:
				break;
			}
			if (is_wp_error($res)) {
				$res = array('status' => "Error", 'message' => $res->get_error_message());
			} elseif ($res === false) {
				$res = array('status' => "Error", 'message' => "Failed to perform action.");
			} else {
				$res = array('status' => "Done");
			}
			$result[$plugin['file']] = $res;
		}
		return $result;
	}

	function editThemes($args) {
		$result = array();
		$themes = $args['items'];
		foreach ($themes as $theme) {
			switch ($args['action']) {
			case 'activate':
				$res = switch_theme($theme['template'], $theme['stylesheet']);
				break;
			case 'delete':
				$res = delete_theme($theme['stylesheet']);
				break;
			default:
				break;
			}

			if (is_wp_error($res)) {
				$res = array('status' => "Error", 'message' => $res->get_error_message());
			} elseif ($res === false) {
				$res = array('status' => "Error", 'message' => "Failed to perform action.");
			} else {
				$res = array( 'status' => "Done");
			}
			$result[$theme['template']] = $res;
		}
		return $result;
	}

	function editWpusers($args) {
		$result = array();
		$items = $args['items'];
		foreach ($items as $item) {
			$res = array();
			$user = get_user_by('id', $item['id']);
			if ($user) {
				switch ($args['action']) {
				case 'changerole':
					$data = array();
					$data['role'] = $item['newrole'];
					$data['ID'] = $user->ID;
					$res = wp_update_user($data);
					break;
				case 'changepass':
						$data	= array();
						$data['user_pass'] = $item['newpass'];
						$data['ID']	= $user->ID;
						$res	= wp_update_user($data);
					break;
				case 'delete':
					if (array_key_exists('reassign', $args)) {
						$user_to = get_user_by('id', $args['reassign']);
						if ($user_to != false) {
							$res = wp_delete_user($user->ID, $user_to->ID);
						} else {
							$res = array('status' => "Error", 'message' => 'Reassigned user doesnot exists');
						}
					} else {
						$res = wp_delete_user($user->ID);
					}
					break;
				}
				if (is_wp_error($res)) {
					$res = array('status' => "Error", 'message' => $res->get_error_message());
				} else {
					$res = array( 'status' => "Done");
				}
			} else {
				$res = array('status' => "Error", 'message' => "Unable to find user");
			}
			$result[$item['id']] = $res;
		}
		return $result;
	}

	function addUser($args) {
		if (username_exists($args['user_login'])) {
			return array('status' => "Error", 'message' => "Username already exists");
		}
		if (email_exists($args['user_email'])) {
			return array('status' => "Error", 'message' => "Email already exists");
		}
		$result = wp_insert_user($args);
		if ( !is_wp_error( $result ) ) {
			return array('status' => "Done", 'user_id' => $result);
		} else {
			return array('status' => "Error", 'message' => $this->getError($result));
		}
	}

	function upgrade($params = null, $has_bv_skin = false) {
		$result = array();
		$premium_upgrades = array();
		if (array_key_exists('core', $params) && !empty($params['core'])) {
			$result['core'] = $this->upgradeCore($params['core']);
		}
		if (array_key_exists('translations', $params) && !empty($params['translations'])) {
			$result['translations'] = $this->upgradeTranslations($params['translations'], $has_bv_skin);
		}
		if (array_key_exists('plugins', $params) && !empty($params['plugins'])) {
			$files = array();
			foreach ($params['plugins'] as $plugin) {
				$files[] = $plugin['file'];
			}
			if (!empty($files)) {
				$result['plugins'] = $this->upgradePlugins($files, $has_bv_skin);
			}
		}
		if (array_key_exists('themes', $params) && !empty($params['themes'])) {
			$stylesheets = array();
			foreach ($params['themes'] as $theme) {
				$stylesheets[] = $theme['stylesheet'];
			}
			if (!empty($stylesheets)) {
				$result['themes'] = $this->upgradeThemes($stylesheets, $has_bv_skin);
			}
		}
		return $result;
	}

	function get_translation_updates() {
		$updates = array();
		$transients = array( 'update_core' => 'core', 'update_plugins' => 'plugin', 'update_themes' => 'theme' );
		foreach ( $transients as $transient => $type ) {
			$transient = $this->settings->getTransient( $transient );
			if ( empty( $transient->translations ) )
				continue;

			foreach ( $transient->translations as $translation ) {
				$updates[] = (object) $translation;
			}
		}
		return $updates;
	}

	function upgradeTranslations($translations, $has_bv_skin = false) {
		$language_updates = $this->get_translation_updates();
		$valid_updates = array();
		$result = array();
		if (!empty($language_updates)) {
			foreach($language_updates as $update) {
				if ($update && in_array($update->package, $translations)) {
					$valid_updates[] = $update;
				}
			}
		}
		if (!empty($valid_updates)) {
			if (class_exists('Language_Pack_Upgrader')) {
				if ($has_bv_skin) {
					require_once( "bv_upgrader_skin.php" );
					$skin = new BVUpgraderSkin("upgrade_translations");
					$this->skin = $skin;
				} else {
					$skin = new Language_Pack_Upgrader_Skin(array());
				}
				$upgrader = new Language_Pack_Upgrader($skin);
				$result = $upgrader->bulk_upgrade($valid_updates);
				if (is_array($result) && !empty($result)) {
					foreach ($result as $translate_tmp => $translate_info) {
						if (is_wp_error($translate_info) || empty($translate_info)) {
							$error = (!empty($translate_info)) ? is_wp_error($translate_info) : "Upgrade failed";
							return array('status' => "Error", 'message' => $error);
						}
					}
				}
				return array('status' => "Done");
			}
		}
		return array('status' => "Error", 'message' => "Upgrade failed");
	}

	function upgradeCore($args) {
		global $wp_filesystem, $wp_version;
		$core = $this->settings->getTransient('update_core');
		$core_update_index = intval($args['coreupdateindex']);
		if (isset($core->updates) && !empty($core->updates)) {
			$to_update = $core->updates[$core_update_index];
		} else {
			return array('status' => "Error", "message" => "Updates not available");
		}
		$resp = array("Core_Upgrader", class_exists('Core_Upgrader'));
		if (version_compare($wp_version, '3.1.9', '>')) {
			$core   = new Core_Upgrader();
			$result = $core->upgrade($to_update);
			if (is_wp_error($result)) {
				return array('status' => "Error", "message" => $this->getError($result));
			} else {
				return array('status' => 'Done');
			}
		} else {
			$resp = array("wp_update_core", function_exists('wp_update_core'));
			if (function_exists('wp_update_core')) {
				$result = wp_update_core($to_update);
				if (is_wp_error($result)) {
					return array('status' => "Error", "message" => $this->getError($result));
				} else {
					return array('status' => 'Done');
				}
			}

			$resp = array("WP_Upgrader", class_exists('WP_Upgrader'));
			if (class_exists('WP_Upgrader')) {
				$upgrader = new WP_Upgrader();

				$res = $upgrader->fs_connect(
					array(
						ABSPATH,
						WP_CONTENT_DIR,
					)
				);
				if (is_wp_error($res)) {
					return array('status' => "Error", "message" => $this->getError($res));
				}

				$wp_dir = trailingslashit($wp_filesystem->abspath());

				$core_package = false;
				if (isset($to_update->package) && !empty($to_update->package)) {
					$core_package = $to_update->package;
				} elseif (isset($to_update->packages->full) && !empty($to_update->packages->full)) {
					$core_package = $to_update->packages->full;
				}

				$download = $upgrader->download_package($core_package);
				if (is_wp_error($download)) {
					return array('status' => "Error", "message" => $this->getError($download));
				}
				$working_dir = $upgrader->unpack_package($download);
				if (is_wp_error($working_dir)) {
					return array('status' => "Error", "message" => $this->getError($working_dir));
				}

				if (!$wp_filesystem->copy($working_dir.'/wordpress/wp-admin/includes/update-core.php', $wp_dir.'wp-admin/includes/update-core.php', true)) {
					$wp_filesystem->delete($working_dir, true);
					return array('status' => "Error", "message" => "Unable to move files.");
				}

				$wp_filesystem->chmod($wp_dir.'wp-admin/includes/update-core.php', FS_CHMOD_FILE);

				$result = update_core($working_dir, $wp_dir);

				if (is_wp_error($result)) {
					return array('status' => "Error", "message" => $this->getError($result));
				}
				return array('status' => 'Done');
			}
		}
	}

	function upgradePlugins($plugins, $has_bv_skin = false) {
		$result = array();
		if (class_exists('Plugin_Upgrader')) {
			if ($has_bv_skin) {
				require_once( "bv_upgrader_skin.php" );
				$skin = new BVUpgraderSkin("plugin_upgrade");
				$this->skin = $skin;
			} else {
				$skin = new Bulk_Plugin_Upgrader_Skin();
			}
			$upgrader = new Plugin_Upgrader($skin);
			$result   = $upgrader->bulk_upgrade($plugins);
		}
		foreach($plugins as $file) {
			$res = $result[$file];
				if (!$res || is_wp_error($res)) {
					$result[$file] = array('status' => "Error");
				} else {
					$result[$file] = array('status' => "Done");
				}
		}
		return $result;
	}

	function upgradeThemes($themes, $has_bv_skin = false) {
		$result  = array();
		if (class_exists('Theme_Upgrader')) {
			if ($has_bv_skin) {
				require_once( "bv_upgrader_skin.php" );
				$skin = new BVUpgraderSkin("theme_upgrade");
				$this->skin = $skin;
			} else {
				$skin = new Bulk_Theme_Upgrader_Skin();
			}
			$upgrader = new Theme_Upgrader($skin);
			$result   = $upgrader->bulk_upgrade($themes);
		}
		foreach($themes as $stylesheet) {
			$res = $result[$stylesheet];
			if (!$res || is_wp_error($res)) {
				$result[$stylesheet] = array('status' => "Error");
			} else {
				$result[$stylesheet] = array('status' => "Done");
			}
		}
		return $result;
	}
	
	function install($params, $has_bv_skin = false) {
		$result = array();
		if (isset($params['plugins'])) {
			foreach ($params['plugins'] as $plugin) {
				if (!array_key_exists('plugins', $result))
					$result["plugins"] = array();
				$plugin['dest'] = WP_PLUGIN_DIR;
				$res = $this->installPackage($plugin, $has_bv_skin);
				$pluginName = $plugin['package'];
				$result["plugins"][$pluginName] = $res;
			}
		}
		if (isset($params['themes'])) {
			foreach ($params['themes'] as $theme) {
				if (!array_key_exists('themes', $result))
					$result["themes"] = array();
				$theme['dest'] = WP_CONTENT_DIR.'/themes';
				$res = $this->installPackage($theme, $has_bv_skin);
				$themeName = $theme['package'];
				$result["themes"][$themeName] = $res;
			}
		}
		return $result;
	}

	function installPackage($params, $has_bv_skin = false) {
		global $wp_filesystem;

		if (!isset($params['package']) || empty($params['package'])) {
			return array('status' => "Error", 'message' => "No package is sent");
		}
		$valid_domain_regex = "/^(http|https):\/\/[\-\w]*\.(blogvault\.net|w\.org|wp\.org|wordpress\.org)\//";
		if (preg_match($valid_domain_regex, $params['package']) !== 1) {
			return array('status' => "Error", 'message' => "Invalid package domain");
		}
		if ($has_bv_skin) {
			require_once( "bv_upgrader_skin.php" );
			$skin = new BVUpgraderSkin("installer", $params['package']);
			$this->skin = $skin;
		} else {
			$skin = new WP_Upgrader_Skin();
		}	
		$upgrader = new WP_Upgrader($skin);
		$upgrader->init();
		$destination = $params['dest'];
		$clear_destination = isset($params['cleardest']) ? $params['cleardest'] : false;
		$package_url = $params['package'];
		$key = basename($package_url);
		$res = $upgrader->run(
			array(
				'package' => $package_url,
				'destination' => $destination,
				'clear_destination' => $clear_destination,
				'clear_working' => true,
				'hook_extra' => array(),
			)
		);
		if (is_wp_error($res)) {
			$res = array('status' => "Error", 'message' => $this->getError($res));
		} else {
			$res = array( 'status' => "Done");
		}
		return $res;
	}

	function getPremiumUpdates() {
		return apply_filters( 'mwp_premium_update_notification', array() );
	}

	function getPremiumUpgradesInfo() {
		return apply_filters( 'mwp_premium_perform_update', array() );
	}

	function autoLogin($username, $isHttps) {
		$user = get_user_by('login', $username);
		if ($user != FALSE) {
			wp_set_current_user( $user->ID );
			if ($isHttps) {
				wp_set_auth_cookie( $user->ID, false, true );
			} else {
				# As we are not sure about wp-cofig.php settings for sure login
				wp_set_auth_cookie( $user->ID, false, true );
				wp_set_auth_cookie( $user->ID, false, false );
			}
			$redirect_to = get_admin_url();
			wp_safe_redirect( $redirect_to );
			exit;
		}
	}

	function upgrade_db(){
		if (function_exists('wp_upgrade')) {
			wp_upgrade();
			return "DONE";
		} else {
			return "NOUPGRADERFUNCTION";
		}
	}

	function process($request) {
		global $wp_filesystem;
		$this->include_files();

		if (!$this->is_pantheon() && !$wp_filesystem) {
			WP_Filesystem();
		}

		$params = $request->params;
		$resp = array();
		switch ($request->method) {
		case "adusr":
			$resp = array("adduser" => $this->addUser($params['args']));
			break;
		case "upgrde":
			$has_bv_skin = array_key_exists('bvskin', $params);
			$resp = array("upgrades" => $this->upgrade($params['args'], $has_bv_skin));
			break;
		case "edt":
			$resp = array("edit" => $this->edit($params['args']));
			break;
		case "instl":
			$has_bv_skin = array_key_exists('bvskin', $params); 
			$resp = array("install" => $this->install($params['args'], $has_bv_skin));
			break;
		case "getpremiumupdates":
			$resp = array("premiumupdates" => $this->getPremiumUpdates());
			break;
		case "getpremiumupgradesinfo":
			$resp = array("premiumupgradesinfo" => $this->getPremiumUpgradesInfo());
			break;
		case "wrteble":
			$resp = array("writeable" => $this->isServerWritable());
			break;
		case "atolgn":
			$isHttps = false;
			if (array_key_exists('https', $params))
				$isHttps = true;
			$resp = array("autologin" => $this->autoLogin($params['username'], $isHttps));
			break;
		case "updatedb":
			$resp = array("status" => $this->upgrade_db());
			break;
		default:
			$resp = false;
		}
		if ($this->skin && is_array($resp)) {
			$resp = array_merge($resp, $this->skin->status);
		}
		return $resp;
	}
}
endif;