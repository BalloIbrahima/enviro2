<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVWPAdmin')) :

class BVWPAdmin {
	public $settings;
	public $siteinfo;
	public $bvinfo;
	public $bvapi;

	function __construct($settings, $siteinfo, $bvapi = null) {
		$this->settings = $settings;
		$this->siteinfo = $siteinfo;
		$this->bvapi = new BVWPAPI($settings);
		$this->bvinfo = new BVInfo($this->settings);
	}

	public function mainUrl($_params = '') {
		if (function_exists('network_admin_url')) {
			return network_admin_url('admin.php?page='.$this->bvinfo->plugname.$_params);
		} else {
			return admin_url('admin.php?page='.$this->bvinfo->plugname.$_params);
		}
	}

	function removeAdminNotices() {
		if (array_key_exists('page', $_REQUEST) && $_REQUEST['page'] == $this->bvinfo->plugname) {
			remove_all_actions('admin_notices');
			remove_all_actions('all_admin_notices');
		}
	}

	public function initHandler() {
		if (!current_user_can('activate_plugins'))
			return;

		if (array_key_exists('bvnonce', $_REQUEST) &&
				wp_verify_nonce($_REQUEST['bvnonce'], "bvnonce") &&
				array_key_exists('blogvaultkey', $_REQUEST) &&
				(strlen(BVAccount::sanitizeKey($_REQUEST['blogvaultkey'])) == 64) &&
				(array_key_exists('page', $_REQUEST) &&
				$_REQUEST['page'] == $this->bvinfo->plugname)) {
			$keys = str_split($_REQUEST['blogvaultkey'], 32);
			BVAccount::addAccount($this->settings, $keys[0], $keys[1]);
			if (array_key_exists('redirect', $_REQUEST)) {
				$location = $_REQUEST['redirect'];
				wp_redirect($this->bvinfo->appUrl()."/dash/redir?q=".urlencode($location));
				exit();
			}
		}
		if ($this->bvinfo->isActivateRedirectSet()) {
			$this->settings->updateOption($this->bvinfo->plug_redirect, 'no');
			$apipubkey = BVAccount::getApiPublicKey($this->settings);
			if ($apipubkey && isset($apipubkey)) {
				$siteurl = base64_encode($this->siteinfo->siteurl());
				$adminurl = base64_encode($this->mainUrl());
				wp_redirect($this->bvinfo->appUrl().'/plugin/activate?bvpublic=09f5175957f69a543cca1ae9d0c00274&siteurl='.$siteurl.'&adminurl='.$adminurl);
				exit();
			}

			wp_redirect($this->mainUrl());
		}
	}

	public function bvsecAdminMenu($hook) {
		if ($hook === 'toplevel_page_bvbackup' || preg_match("/bv_add_account$/", $hook) || preg_match("/bv_account_details$/", $hook)) {
			wp_enqueue_style( 'bootstrap', plugins_url('css/bootstrap.min.css', __FILE__));
			wp_enqueue_style( 'bvplugin', plugins_url('css/bvplugin.min.css', __FILE__));
		}
	}

	public function menu() {
		$brand = $this->bvinfo->getBrandInfo();
		if (!is_array($brand) || (!array_key_exists('hide', $brand) && !array_key_exists('hide_from_menu', $brand))) {
			$bname = $this->bvinfo->getBrandName();
			$icon = $this->bvinfo->getBrandIcon();
			add_menu_page($bname, $bname, 'manage_options', $this->bvinfo->plugname,
					array($this, 'adminPage'), plugins_url($icon,  __FILE__));
		}
	}

	public function hidePluginDetails($plugin_metas, $slug) {
		$brand = $this->bvinfo->getBrandInfo();
		$bvslug = $this->bvinfo->slug;

		if ($slug === $bvslug && is_array($brand) && array_key_exists('hide_plugin_details', $brand)) {
			foreach ($plugin_metas as $pluginKey => $pluginValue) {
				if (strpos($pluginValue, sprintf('>%s<', translate('View details')))) {
					unset($plugin_metas[$pluginKey]);
					break;
				}
			}
		}
		return $plugin_metas;
	}

	public function settingsLink($links, $file) {
		#XNOTE: Fix this
		if ( $file == plugin_basename( dirname(__FILE__).'/blogvault.php' ) ) {
			$brand = $this->bvinfo->getBrandInfo();
			if (!is_array($brand) || !array_key_exists('hide_plugin_details', $brand)) {
				$links[] = '<a href="'.$this->mainUrl().'">'.__( 'Settings' ).'</a>';
			}
		}
		return $links;
	}

	public function getPluginLogo() {
		$brand = $this->bvinfo->getBrandInfo();
		if ($brand && array_key_exists('logo', $brand)) {
			return $brand['logo'];
		}
		return $this->bvinfo->logo;
	}

	public function getWebPage() {
		$brand = $this->bvinfo->getBrandInfo();
		if ($brand && array_key_exists('webpage', $brand)) {
			return $brand['webpage'];
		}
		return $this->bvinfo->webpage;
	}

	public function siteInfoTags() {
		require_once dirname( __FILE__ ) . '/recover.php';
		$bvnonce = wp_create_nonce("bvnonce");
		$public = BVAccount::getApiPublicKey($this->settings);
		$secret = BVRecover::defaultSecret($this->settings);
		$tags = "<input type='hidden' name='url' value='".esc_attr($this->siteinfo->wpurl())."'/>\n".
				"<input type='hidden' name='homeurl' value='".esc_attr($this->siteinfo->homeurl())."'/>\n".
				"<input type='hidden' name='siteurl' value='".esc_attr($this->siteinfo->siteurl())."'/>\n".
				"<input type='hidden' name='dbsig' value='".esc_attr($this->siteinfo->dbsig(false))."'/>\n".
				"<input type='hidden' name='plug' value='".esc_attr($this->bvinfo->plugname)."'/>\n".
				"<input type='hidden' name='adminurl' value='".esc_attr($this->mainUrl())."'/>\n".
				"<input type='hidden' name='bvversion' value='".esc_attr($this->bvinfo->version)."'/>\n".
	 			"<input type='hidden' name='serverip' value='".esc_attr($_SERVER["SERVER_ADDR"])."'/>\n".
				"<input type='hidden' name='abspath' value='".esc_attr(ABSPATH)."'/>\n".
				"<input type='hidden' name='secret' value='".esc_attr($secret)."'/>\n".
				"<input type='hidden' name='public' value='".esc_attr($public)."'/>\n".
				"<input type='hidden' name='bvnonce' value='".esc_attr($bvnonce)."'/>\n";
		return $tags;
	}

	public function activateWarning() {
		global $hook_suffix;
		if (!BVAccount::isConfigured($this->settings) && $hook_suffix == 'index.php' ) {
?>
			<div id="message" class="updated" style="padding: 8px; font-size: 16px; background-color: #dff0d8">
						<a class="button-primary" href="<?php echo esc_url($this->mainUrl()); ?>">Activate BlogVault</a>
						&nbsp;&nbsp;&nbsp;<b>Almost Done:</b> Activate your BlogVault account to backup & secure your site.
			</div>
<?php
		}
	}

	public function showAddAccountPage() {
		require_once dirname( __FILE__ ) . "/admin/add_new_account.php";
	}

	public function showAccountDetailsPage() {
		require_once dirname( __FILE__ ) . "/admin/account_details.php";
	}

	public function adminPage() {
		if (isset($_REQUEST['bvnonce']) && wp_verify_nonce( $_REQUEST['bvnonce'], 'bvnonce' )) {
			$info = array();
			$this->siteinfo->basic($info);
			$this->bvapi->pingbv('/bvapi/disconnect', $info, BVAccount::sanitizeKey($_REQUEST['pubkey']));
			BVAccount::remove($this->settings, BVAccount::sanitizeKey($_REQUEST['pubkey']));
		}
		if (BVAccount::isConfigured($this->settings)) {
			if (isset($_REQUEST['add_account'])) {
				$this->showAddAccountPage();
			} else {
				$this->showAccountDetailsPage();
			}
		} else {
			$this->showAddAccountPage();
		}
	}

	public function initBranding($plugins) {
		$slug = $this->bvinfo->slug;

		if (!is_array($plugins) || !isset($slug, $plugins)) {
			return $plugins;
		}

		$brand = $this->bvinfo->getBrandInfo();
		if (is_array($brand)) {
			if (array_key_exists('hide', $brand)) {
				unset($plugins[$slug]);
			} else {
				if (array_key_exists('name', $brand)) {
					$plugins[$slug]['Name'] = $brand['name'];
				}
				if (array_key_exists('title', $brand)) {
					$plugins[$slug]['Title'] = $brand['title'];
				}
				if (array_key_exists('description', $brand)) {
					$plugins[$slug]['Description'] = $brand['description'];
				}
				if (array_key_exists('authoruri', $brand)) {
					$plugins[$slug]['AuthorURI'] = $brand['authoruri'];
				}
				if (array_key_exists('author', $brand)) {
					$plugins[$slug]['Author'] = $brand['author'];
				}
				if (array_key_exists('authorname', $brand)) {
					$plugins[$slug]['AuthorName'] = $brand['authorname'];
				}
				if (array_key_exists('pluginuri', $brand)) {
					$plugins[$slug]['PluginURI'] = $brand['pluginuri'];
				}
			}
		}
		return $plugins;
	}
}
endif;