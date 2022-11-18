<?php
/*
* BACKUPLY
* https://backuply.com
* (c) Backuply Team
*/

if(!function_exists('add_action')){
	echo 'You are not allowed to access this page directly.';
	exit;
}

define('BACKUPLY_VERSION', '1.0.4');
define('BACKUPLY_DIR', dirname(BACKUPLY_FILE));
define('BACKUPLY_PRO_DIR', dirname(BACKUPLY_FILE) . '/lib/premium');
define('BACKUPLY_URL', plugins_url('', BACKUPLY_FILE));
define('BACKUPLY_PRO_BASE', 'backuply-pro/backuply-pro.php');
define('BACKUPLY_BACKUP_DIR', str_replace('\\' , '/', WP_CONTENT_DIR).'/backuply/');
define('BACKUPLY_API', 'https://api.backuply.com');
define('BACKUPLY_DOCS', 'https://backuply.com/docs/');
define('BACKUPLY_WWW_URL', 'https://backuply.com/');
define('BACKUPLY_PRO_URL', 'https://backuply.com/pricing?from=plugin');
define('BACKUPLY_TIMEOUT_TIME', 300);
define('BACKUPLY_DEV', file_exists(dirname(__FILE__).'/dev.php') ? 1 : 0);

include_once(BACKUPLY_DIR.'/functions.php');

function backuply_died() {
	//backuply_log(serialize(error_get_last()));
	
	$last_error = error_get_last();
		
	if(!$last_error){
		return false;
	}
	
	if(strpos($last_error['message'], 'Maximum execution time') !== FALSE){
		backuply_status_log('The Backup Failed because the script reached Maximum Execution time while waiting for response from remote server', 'error');
		backuply_kill_process();
	}

}
register_shutdown_function('backuply_died');

// Ok so we are now ready to go
register_activation_hook(BACKUPLY_FILE, 'backuply_activation');

// Is called when the ADMIN enables the plugin
function backuply_activation(){
	global $wpdb, $error;

	add_option('backuply_version', BACKUPLY_VERSION);
	
	backuply_add_htaccess();
	backuply_set_config();
}

// The function that will be called when the plugin is loaded
add_action('plugins_loaded', 'backuply_load_plugin');

function backuply_load_plugin(){
	global $backuply;
	
	// Set the array
	$backuply = array();
	$backuply['settings'] = get_option('backuply_settings') ? get_option('backuply_settings') : [];
	$backuply['cron'] = get_option('backuply_cron_settings') ? get_option('backuply_cron_settings') : [];
	$backuply['auto_backup'] = false;
	$backuply['license'] = get_option('backuply_license') ? get_option('backuply_license') : [];
	$backuply['status'] = get_option('backuply_status');
	$backuply['excludes'] = get_option('backuply_excludes');
	$backuply['htaccess_error'] = true;
	$backuply['debug_mode'] = !empty(get_option('backuply_debug')) ? true : false;
	
	// Auto Backup using custom cron
	if(isset($_GET['action'])  && $_GET['action'] == 'backuply_custom_cron'){
		if(!defined('BACKUPLY_PRO')) {
			backuply_status_log('You are trying to access PRO feature with FREE version', 'error');
		}
	
		if(!backuply_verify_self(sanitize_text_field(wp_unslash($_REQUEST['backuply_key'])))){
			backuply_status_log('Security Check Failed', 'error');
			die();
		}
		
		if(!function_exists('backuply_auto_backup_execute')) {
			include_once BACKUPLY_DIR . '/premium.php';
		}
		
		backuply_auto_backup_execute();
	}
	
	// CURL call for bacukup when its incomplete
	if(isset($_GET['action'])  && ($_GET['action'] == 'backuply_curl_backup' || $_GET['action'] == 'backuply_curl_upload')) {
	
		if(!backuply_verify_self(sanitize_text_field(wp_unslash($_REQUEST['backuply_key'])))){
			backuply_status_log('Security Check Failed', 'error');
			die();
		}

		backuply_backup_execute();
		wp_send_json(array('success' => true));
	}
	
	/*if(isset($_GET['action'])  && ($_GET['action'] == 'backuply_restore_response')) {
	
		if(!backuply_verify_self(backuply_optreq('security'))){
			backuply_status_log('Security Check Failed', 'error');
			die();
		}
		
		if(!function_exists('backuply_restore_response')){
			include_once(BACKUPLY_DIR.'/main/ajax.php');
		}

		backuply_restore_response();
	}*/
	
	if(file_exists(BACKUPLY_BACKUP_DIR . '.htaccess')) {
		$backuply['htaccess_error'] = false;
	}
	
	add_action('admin_menu', 'backuply_admin_menu');
	add_filter('cron_schedules', 'backuply_add_cron_interval');
	
	// Are we pro ?
	if(defined('BACKUPLY_PRO')) {
		
		include_once(BACKUPLY_DIR.'/premium.php');
		
		// Cron for Calling Auto Backup
		add_action('backuply_auto_backup_cron', 'backuply_auto_backup_execute');
		
		backuply_premium_init();
		
	} else {
		// The promo time
		$promo_time = get_option('backuply_promo_time');
		if(empty($promo_time)){
			$promo_time = time();
			update_option('backuply_promo_time', $promo_time);
		}
		
		// Are we to show the backuply promo
		if(!empty($promo_time) && $promo_time > 0 && $promo_time < (time() - (7 * 86400))){
			add_action('admin_notices', 'backuply_promo');
		}
		
		// Are we to disable the promo
		if(isset($_GET['backuply_promo']) && (int)$_GET['backuply_promo'] == 0 ){
			if(!wp_verify_nonce(backuply_optreq('security'), 'backuply_nonce')) {
				die('Security Check Failed');
			}

			update_option('backuply_promo_time', (0 - time()) );
			die('DONE');
		}
	}
	
	// Backup notice for user to backup the if its been a week user took a backup
	$last_backup = get_option('backuply_last_backup');
	$backup_nag = get_option('backuply_backup_nag');
	
	if(current_user_can( 'activate_plugins' ) && (time() - $last_backup) >= 604800 && (time() - $backup_nag) >= 604800){
		add_action('admin_notices', 'backuply_backup_nag');
	}
	
	// Cron for Backing Up Files/Database
	add_action('backuply_backup_cron', 'backuply_backup_execute');
	
	// Cron to check for timeout
	add_action('backuply_timeout_check', 'backuply_timeout_check');
}

// If we are doing ajax and its a backuply ajax
if(wp_doing_ajax()){	
	include_once(BACKUPLY_DIR.'/main/ajax.php');
}

// List of core files to backup
function backuply_core_fileindex(){
	$default_fileindex = array('index.php', 'license.txt', 'readme.html', 'wp-activate.php', 'wp-admin', 'wp-blog-header.php', 'wp-comments-post.php', 'wp-config-sample.php', 'wp-content', 'wp-cron.php', 'wp-includes', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php', 'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', '.htaccess', 'wp-config.php');

	return $default_fileindex;
}

// Shows the admin menu of Backuply
function backuply_admin_menu(){

	$capability = 'activate_plugins';
	
	// Add the menu page
	add_menu_page(__('Backuply Dashboard', 'backuply'), __('Backuply', 'backuply'), $capability, 'backuply', 'backuply_settings_page_handle', BACKUPLY_URL .'/assets/images/icon.svg');
	
	// Dashboard
	add_submenu_page('backuply', __('Backuply Dashboard', 'backuply'), __('Dashboard', 'backuply'), $capability, 'backuply', 'backuply_settings_page_handle');
	
	add_submenu_page('backuply', __('License', 'backuply'), __('License', 'backuply'), $capability, 'backuply-license', 'backuply_license_page_handle');

	// Its Free
	if(!defined('BACKUPLY_PRO')){

		// Go Pro link
		add_submenu_page('backuply', __('Backuply Go Pro'), __('Go Pro'), $capability, BACKUPLY_PRO_URL);

	}
}

// Backuply - Backup Page
function backuply_settings_page_handle(){
	include_once BACKUPLY_DIR . '/main/settings.php';
	backuply_page_backup();
	backuply_page_theme();
}

// Backuply - License Page
function backuply_license_page_handle(){
	include_once BACKUPLY_DIR . '/main/license.php';
	backuply_license_page();
}

// Shows a nag to the user, 1 week after last backup
function backuply_backup_nag(){
	
	$last_backup = get_option('backuply_last_backup');

	echo 
	'<div class="notice notice-error is-dismissible backuply-backup-nag">';
	
	if(!empty($last_backup)){
		$time_diff = time() - $last_backup;
		$days = floor(abs($time_diff / 86400));
		
		echo '<p>'. sprintf(esc_html__( 'It\'s been %1$s days you took a backup, would you like to take a backup with Backuply and secure your website!', 'backuply' ), $days).'&nbsp; <a href="'.menu_page_url('backuply', false).'" class="button button-primary">Backup Now</a></p>';
	} else{
		echo '<p>'. esc_html__( 'You haven\'t taken a backup since you activated Backuply, Take a backup and secure your website!', 'backuply' ).'&nbsp; <a href="'.menu_page_url('backuply', false).'" class="button button-primary">Backup Now</a></p>';
	}

	echo '</div>';
	
	wp_register_script('backuply_time_nag', '', array('jquery'), '', true);
	wp_enqueue_script('backuply_time_nag');
	
	wp_add_inline_script('backuply_time_nag' ,'

		jQuery(document).ready(function(){
			jQuery(".backuply-backup-nag .notice-dismiss").click(function(){
			
				jQuery.ajax({
					method : "GET",
					url : "' . admin_url('admin-ajax.php') .'?action=backuply_hide_backup_nag&security=' . wp_create_nonce('backuply_nonce'). '",
					success : function(res){
						console.log(res);
					}
				});
			});
		});'
	);
	
}

// Cron Schedules for WordPress cron
function backuply_add_cron_interval($schedules){
	// 30 Min
	$schedules['backuply_thirty_min'] = array(
		'interval' => 1800,
		'display'  => esc_html__( 'Every 30 Minutes' )
	);
	
	$schedules['backuply_one_hour'] = array(
		'interval' => 3600,
		'display'  => esc_html__( 'Every One Hour' )
	);

	$schedules['backuply_two_hours'] = array(
		'interval' => 7200,
		'display'  => esc_html__( 'Every Two Hours' )
	);
	
	$schedules['backuply_daily'] = array(
		'interval' => 86400,
		'display'  => esc_html__( 'Once a day' )
	);

	$schedules['backuply_weekly'] = array(
		'interval' => 604800,
		'display' => esc_html__('Once a Week')
	);
	
	$schedules['backuply_monthly'] = array(
		'interval' => 2635200,
		'display' => esc_html__('Once a month')
	);
	
	return $schedules;
}

// Initiates the backup
function backuply_backup_execute(){
	global $wpdb, $backuply, $data;
	
	// Updates the $backuply['status'] var
	$is_active = backuply_active();
	
	if(empty($backuply['status'])){
		return;
	}

	// Update the last active time
	$backuply['status']['last_update'] = time();
	update_option('backuply_status', $backuply['status']);
	
	// Informaton regarding remote location
	$remote_location = '';

	if(!empty($backuply['status']['backup_location'])){
		$backuply_remote_backup_locs = get_option('backuply_remote_backup_locs');
		$backup_location_id = $backuply['status']['backup_location'];
		$remote_location = $backuply_remote_backup_locs[$backup_location_id];
	}

	include(BACKUPLY_DIR.'/backup_ins.php');
	
}


// Show the promo
function backuply_promo(){
	include_once(BACKUPLY_DIR.'/main/promo.php');
}


// Sorry to see you going
register_uninstall_hook(BACKUPLY_FILE, 'backuply_deactivation');

function backuply_deactivation(){	
	delete_option('backuply_version');
	delete_option('backuply_cron_schedules');
	delete_option('backuply_cron_settings');
	delete_option('backuply_remote_backup_locs');
	delete_option('backuply_notify_email_address');
	delete_option('backuply_settings');
	delete_option('backuply_license');
}