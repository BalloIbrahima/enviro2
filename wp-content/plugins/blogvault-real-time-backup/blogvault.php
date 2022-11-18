<?php
/*
Plugin Name: WordPress Backup & Security Plugin - BlogVault
Plugin URI: https://blogvault.net
Description: Easiest way to backup & secure your WordPress site
Author: Backup by BlogVault
Author URI: https://blogvault.net
Version: 4.82
Network: True
 */

/*  Copyright 2017  BlogVault  (email : support@blogvault.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Global response array */

if (!defined('ABSPATH')) exit;
##OLDWPR##

require_once dirname( __FILE__ ) . '/wp_settings.php';
require_once dirname( __FILE__ ) . '/wp_site_info.php';
require_once dirname( __FILE__ ) . '/wp_db.php';
require_once dirname( __FILE__ ) . '/wp_api.php';
require_once dirname( __FILE__ ) . '/wp_actions.php';
require_once dirname( __FILE__ ) . '/info.php';
require_once dirname( __FILE__ ) . '/account.php';
##WPCACHEMODULE##


$bvsettings = new BVWPSettings();
$bvsiteinfo = new BVWPSiteInfo();
$bvdb = new BVWPDb();


$bvapi = new BVWPAPI($bvsettings);
$bvinfo = new BVInfo($bvsettings);
$wp_action = new BVWPAction($bvsettings, $bvsiteinfo, $bvapi);

register_uninstall_hook(__FILE__, array('BVWPAction', 'uninstall'));
register_activation_hook(__FILE__, array($wp_action, 'activate'));
register_deactivation_hook(__FILE__, array($wp_action, 'deactivate'));

add_action('wp_footer', array($wp_action, 'footerHandler'), 100);
add_action('clear_bv_services_config', array($wp_action, 'clear_bv_services_config'));
##SOADDUNINSTALLACTION##

##WPCLIMODULE##
if (is_admin()) {
	require_once dirname( __FILE__ ) . '/wp_admin.php';
	$wpadmin = new BVWPAdmin($bvsettings, $bvsiteinfo);
	add_action('admin_init', array($wpadmin, 'initHandler'));
	add_filter('all_plugins', array($wpadmin, 'initBranding'));
	add_filter('plugin_row_meta', array($wpadmin, 'hidePluginDetails'), 10, 2);
	if ($bvsiteinfo->isMultisite()) {
		add_action('network_admin_menu', array($wpadmin, 'menu'));
	} else {
		add_action('admin_menu', array($wpadmin, 'menu'));
	}
	add_filter('plugin_action_links', array($wpadmin, 'settingsLink'), 10, 2);
	add_action('admin_head', array($wpadmin, 'removeAdminNotices'), 3);
	add_action('admin_notices', array($wpadmin, 'activateWarning'));
	add_action('admin_enqueue_scripts', array($wpadmin, 'bvsecAdminMenu'));
	##ALPURGECACHEFUNCTION##
	##ALADMINMENU##
}

if ((array_key_exists('bvreqmerge', $_POST)) || (array_key_exists('bvreqmerge', $_GET))) {
	$_REQUEST = array_merge($_GET, $_POST);
}

if ((array_key_exists('bvplugname', $_REQUEST)) && ($_REQUEST['bvplugname'] == "bvbackup")) {
	require_once dirname( __FILE__ ) . '/callback/base.php';
	require_once dirname( __FILE__ ) . '/callback/response.php';
	require_once dirname( __FILE__ ) . '/callback/request.php';
	require_once dirname( __FILE__ ) . '/recover.php';

	$pubkey = BVAccount::sanitizeKey($_REQUEST['pubkey']);

	if (array_key_exists('rcvracc', $_REQUEST)) {
		$account = BVRecover::find($bvsettings, $pubkey);
	} else {
		$account = BVAccount::find($bvsettings, $pubkey);
	}

	$request = new BVCallbackRequest($account, $_REQUEST);
	$response = new BVCallbackResponse($request->bvb64cksize);

	if ($account && (1 === $account->authenticate($request))) {
		define('BVBASEPATH', plugin_dir_path(__FILE__));


		require_once dirname( __FILE__ ) . '/callback/handler.php';

		$params = $request->processParams($_REQUEST);
		if ($params === false) {
			$resp = array(
				"account_info" => $account->info(),
				"request_info" => $request->info(),
				"bvinfo" => $bvinfo->info(),
				"statusmsg" => "BVPRMS_CORRUPTED"
			);
			$response->terminate($resp);
		}
		$request->params = $params;
		$callback_handler = new BVCallbackHandler($bvdb, $bvsettings, $bvsiteinfo, $request, $account, $response);
		if ($request->is_afterload) {
			add_action('wp_loaded', array($callback_handler, 'execute'));
		} else if ($request->is_admin_ajax) {
			add_action('wp_ajax_bvadm', array($callback_handler, 'bvAdmExecuteWithUser'));
			add_action('wp_ajax_nopriv_bvadm', array($callback_handler, 'bvAdmExecuteWithoutUser'));
		} else {
			$callback_handler->execute();
		}
	} else {
		$resp = array(
			"account_info" => $account ? $account->info() : array("error" => "ACCOUNT_NOT_FOUND"),
			"request_info" => $request->info(),
			"bvinfo" => $bvinfo->info(),
			"statusmsg" => "FAILED_AUTH",
			"api_pubkey" => substr(BVAccount::getApiPublicKey($bvsettings), 0, 8),
			"def_sigmatch" => substr(BVAccount::getSigMatch($request, BVRecover::getDefaultSecret($bvsettings)), 0, 8)
		);
		$response->terminate($resp);
	}
} else {
	if ($bvinfo->hasValidDBVersion()) {
		if ($bvinfo->isProtectModuleEnabled()) {
		require_once dirname( __FILE__ ) . '/protect/wp/protect.php';
		$bvprotect = new BVProtect($bvdb, $bvsettings);
		$bvprotect->init();
		if ($bvinfo->isActivePlugin() && !(defined( 'WP_CLI' ) && WP_CLI)) {
			$bvprotect->run();
		}
	}

		if ($bvinfo->isDynSyncModuleEnabled()) {
		require_once dirname( __FILE__ ) . '/wp_dynsync.php';
		$bvconfig = $bvinfo->config;
		$dynsync = new BVWPDynSync($bvdb, $bvsettings, $bvconfig['dynsync']);
		$dynsync->init();
	}

		if ($bvinfo->isServiceActive('activity_log')) {
		require_once dirname( __FILE__ ) . '/wp_actlog.php';
		$bvconfig = $bvinfo->config;
		$actlog = new BVWPActLog($bvdb, $bvsettings, $bvinfo, $bvconfig['activity_log']);
		$actlog->init();
	}

	}
	$bv_site_settings = $bvsettings->getOption('bv_site_settings');
	if (isset($bv_site_settings)) {
		if (isset($bv_site_settings['wp_auto_updates'])) {
			$wp_auto_updates = $bv_site_settings['wp_auto_updates'];
			if (array_key_exists('block_auto_update_core', $wp_auto_updates)) {
				add_filter('auto_update_core', '__return_false' );
			}
			if (array_key_exists('block_auto_update_theme', $wp_auto_updates)) {
				add_filter('auto_update_theme', '__return_false' );
				add_filter('themes_auto_update_enabled', '__return_false' );
			}
			if (array_key_exists('block_auto_update_plugin', $wp_auto_updates)) {
				add_filter('auto_update_plugin', '__return_false' );
				add_filter('plugins_auto_update_enabled', '__return_false' );
			}
			if (array_key_exists('block_auto_update_translation', $wp_auto_updates)) {
				add_filter('auto_update_translation', '__return_false' );
			}
		}
	}

}