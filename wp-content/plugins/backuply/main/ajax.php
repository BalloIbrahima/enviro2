<?php
/*
* BACKUPLY
* https://backuply.com
* (c) Backuply Team
*/

// Are we being accessed directly ?
if(!defined('BACKUPLY_VERSION')) {
	exit('Hacking Attempt !');
}

set_time_limit(60);
ignore_user_abort(true);

// Is the nonce there ?
if(empty($_REQUEST['security'])){
	return;
}

// AJAX Actions
add_action('wp_ajax_backuply_create_backup', 'backuply_create_backup');
add_action('wp_ajax_backuply_stop_backup', 'backuply_stop_backup');
add_action('wp_ajax_backuply_download_backup', 'backuply_download_backup');
add_action('wp_ajax_backuply_multi_backup_delete', 'backuply_multi_backup_delete');
add_action('wp_ajax_backuply_check_status', 'backuply_check_status');
add_action('wp_ajax_backuply_check_backup_status', 'backuply_backup_progress_status');
add_action('wp_ajax_backuply_checkrestorestatus_action', 'backuply_checkrestorestatus_action');
add_action('wp_ajax_backuply_restore_curl_query', 'backuply_restore_curl_query');
add_action('wp_ajax_backuply_retry_htaccess', 'backuply_retry_htaccess');
add_action('wp_ajax_backuply_kill_proccess', 'backuply_force_stop');
add_action('wp_ajax_backuply_get_loc_details', 'backuply_get_loc_details');
add_action('wp_ajax_backuply_sync_backups', 'backuply_sync_backups');
add_action('wp_ajax_nopriv_backuply_restore_response', 'backuply_restore_response');
add_action('wp_ajax_nopriv_backuply_update_serialization', 'backuply_update_serialization_ajax');
add_action('wp_ajax_backuply_creating_session', 'backuply_creating_session');
add_action('wp_ajax_nopriv_backuply_creating_session', 'backuply_creating_session');
add_action('wp_ajax_backuply_last_logs', 'backuply_get_last_logs');
add_action('wp_ajax_backuply_save_excludes', 'backuply_save_excludes');
add_action('wp_ajax_backuply_exclude_rule_delete', 'backuply_exclude_rule_delete');
add_action('wp_ajax_backuply_get_jstree', 'backuply_get_jstree');
add_action('wp_ajax_backuply_hide_backup_nag', 'backuply_hide_backup_nag');

function backuply_ajax_nonce_verify($key = 'security'){
	
	if(!wp_verify_nonce($_REQUEST[$key], 'backuply_nonce')) {
		wp_send_json(array('success' => false, 'message' => 'Security Check Failed.'));
	}
	
	// Check capability
	if(!current_user_can( 'activate_plugins' )){
		wp_send_json(array('success' => false, 'message' => 'You cannot create backups as per your capabilities !'));
	}
	
	return true;
}

// AJAX handle to download backup
function backuply_download_backup() {
	
	backuply_ajax_nonce_verify();
	
	if(empty($_GET['backup_name'])) {
		wp_send_json(array('success' => false, 'message' => 'Wrong File name provided.'));
	}
	
	$filename = backuply_optget('backup_name');
	$filename = backuply_clean_file_name($filename);
	$file = BACKUPLY_BACKUP_DIR . 'backups/'. $filename;
	
	if(!file_exists($file)) {
		wp_send_json(array('success' => false, 'message' => 'File not found.'));
	}
	
	ob_start();
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
	header('Content-Transfer-Encoding: binary');
	header('Content-Length: ' . filesize($file));
	wp_ob_end_flush_all();

	readfile($file);
	wp_die();
}

// AJAX handle to delete Multiple backups
function backuply_multi_backup_delete() {
	global $error;
	
	backuply_ajax_nonce_verify();
	
	if(empty($_POST['backup_name'])) {
		wp_send_json(array('success' => false, 'message' => 'No Backup selected for deletion.'));
	}
	
	$backup = backuply_optpost('backup_name');
	
	if(empty($backup)) {
		wp_send_json(array('success' => false, 'message' => 'No File was provided to be deleted'));
	}
	
	if(!backuply_delete_backup($backup)) {
		$error_string = __('Below are the errors faced', 'backuply');
		
		foreach($error as $e) {
			$error_string .= $e . "\n";
		}
		
		wp_send_json(array('success' => false, 'message' => $error_string));
	}
	
	wp_send_json(array('success' => true));
}

// AJAX handle to create backup
function backuply_create_backup() {
	
	backuply_ajax_nonce_verify();
	
	$bak_options = json_decode(sanitize_text_field(wp_unslash($_POST['values'])), true);

	backuply_create_log_file();
	
	update_option('backuply_backup_stopped', false);
	update_option('backuply_status', $bak_options);
	backuply_status_log('Initializing...', 'info', 13);
	
	if(wp_schedule_single_event(time(), 'backuply_backup_cron')) {
		wp_schedule_single_event(time() + BACKUPLY_TIMEOUT_TIME, 'backuply_timeout_check', array('is_restore' => false));
		backuply_status_log('Creating a job to start Backup', 'info', 17);
		spawn_cron(); //To call the cron immediately
		wp_send_json(array('success' => true));
	}
	
	// Failed !
	wp_send_json(array('success' => false, 'message' => 'Unable to start a backup at this moment, Please try again later'));
}

// Returns status for backup and restore
function backuply_backup_progress_status() {
	// Security Check
	backuply_ajax_nonce_verify();
	
	$last_status = !empty($_POST['last_status']) ? backuply_optpost('last_status') : 0;
	
	// negative progress means backup is being stopped
	wp_send_json(array(
		'success' => true,
		'is_stoppable' => true,
		'progress_log' => backuply_get_status($last_status)
		)
	);
}

function backuply_stop_backup() {
	
	backuply_ajax_nonce_verify();
	
	backuply_status_log('Stopping the Backup', 'info', -1);
	update_option('backuply_backup_stopped', true);
	wp_send_json(array('success' => true));
}

// Retries creating htaccess
function backuply_retry_htaccess() {
	
	backuply_ajax_nonce_verify();

	$res = backuply_add_htaccess();
	
	if($res) {
		wp_send_json(array('success' => true));
	}
	
	wp_send_json(array('success' => false, 'message' => 'We are not able to create the htaccess file, So please use the manual way.'));
}

// AJAX handle to restore backupp
function backuply_restore_curl_query(){
	
	global $wpdb;
	
	backuply_ajax_nonce_verify();

	if(!empty($_POST['fname'])) {
		$backuply_backup_dir = backuply_cleanpath(BACKUPLY_BACKUP_DIR);
		
		if(!is_dir($backuply_backup_dir.'/restoration')) {
			mkdir($backuply_backup_dir.'/restoration', 0755, true);
		}
		
		$myfile = fopen($backuply_backup_dir.'/restoration/restoration.php', 'w') or die('Unable to open restoaration.php file !');
		$txt = time();
		fwrite($myfile, $txt);
		fclose($myfile);
		
		$_POST['plugin_dir'] = backuply_cleanpath(BACKUPLY_DIR);
		$_POST['backuly_backup_dir'] = $backuply_backup_dir;
		$_POST['softdb'] = $wpdb->dbname;
		$_POST['softdbhost'] = $wpdb->dbhost;
		$_POST['softdbuser'] = $wpdb->dbuser;
		$_POST['softdbpass'] = $wpdb->dbpassword;
		
		backuply_create_log_file(); // Create a log file.
		backuply_status_log('Starting Restoring your backup', 'info', 10);
		wp_schedule_single_event(time() + BACKUPLY_TIMEOUT_TIME, 'backuply_timeout_check', array('is_restore' => true));
		backuply_restore_curl($_POST);
		
		exit();
	}
}

function backuply_restore_curl($info = array()) {
	global $wpdb, $backuply;
	
	$backup_file_loc = backuply_optpost('backup_file_loc');
	$info['site_url'] = site_url();
	$info['to_email'] = get_option('backuply_notify_email_address');
	$info['admin_email'] = get_option('admin_email');
	$info['ajax_url'] = admin_url('admin-ajax.php');
	$info['debug_mode'] = $backuply['debug_mode'];
	$info['user_id'] = get_current_user_id();
	$info['exclude_db'] = !empty($backuply['excludes']['db']) ? $backuply['excludes']['db'] : array();

	$config = backuply_get_config();
	
	if(empty($config['BACKUPLY_KEY'])) {
		return;
	}
	
	$info['backup_dir'] = BACKUPLY_BACKUP_DIR . 'backups';

	// Setting backup_dir if its remote location
	if(!empty(backuply_optpost('loc_id'))){
		$backuply_remote_backup_locs = get_option('backuply_remote_backup_locs');
		$loc_id = backuply_optpost('loc_id');
		$info['backup_dir'] = $backuply_remote_backup_locs[$loc_id]['full_backup_loc'];
		backuply_set_restoration_file($backuply_remote_backup_locs[$loc_id]);
	}

	$info['backuply_key'] = urlencode($config['BACKUPLY_KEY']);
	$info['restore_curl_url'] = BACKUPLY_URL . '/restore_ins.php';
	
	$args = array(
		'body' => $info,
		'timeout' => 5,
		'blocking' => false
	);
	
	wp_remote_post($info['restore_curl_url'], $args);
	die();
}

// Kills process
function backuply_force_stop() {
	backuply_ajax_nonce_verify();
	
	delete_option('backuply_status');
	update_option('backuply_backup_stopped', true);
	
	if(file_exists(BACKUPLY_BACKUP_DIR . 'restoration/restoration.php')){
		@unlink(BACKUPLY_BACKUP_DIR . 'restoration/restoration.php');
	}
	
	wp_send_json(['success' => true]);
}

// Fetches details of a specific location
function backuply_get_loc_details() {
	
	//Security check
	backuply_ajax_nonce_verify();
	
	$loc_id = backuply_optpost('loc_id');
	
	if(empty($loc_id)){
		wp_send_json(array('success' => false, 'message' => __('No location id specified', 'backuply')));
	}
	
	
	$filter = ['ftp_pass', 'aws_secretKey', 'aws_accessKey'];
	$loc_info = backuply_get_loc_by_id($loc_id, $filter);
	
	if(empty($loc_info)) {
		wp_send_json(array('success' => false, 'message' => __('The specified location not found', 'backuply')));
	}
	
	wp_send_json(array('success' => true, 'data' => $loc_info));
}

// Syncs the backups in the remote location with us
function backuply_sync_backups(){
	//Security check
	backuply_ajax_nonce_verify();
	
	$loc_id = backuply_optget('id');
	
	if(empty($loc_id)){
		wp_send_json(array('success' => false, 'message' => __('No location id specified', 'backuply')));
	}
	
	$sync = backuply_sync_remote_backup_infos($loc_id);
	
	wp_send_json(array('success' => true));
}

// Deletes all remote info files on Restore Success
function backuply_delete_rinfo_on_restore() {
	$backup_info_f = BACKUPLY_BACKUP_DIR.'backups_info/';
	$backup_info = backuply_get_backups_info();

	foreach($backup_info as $info){
		if(!isset($info->backup_location)){
			continue;
		}
		
		@unlink($backup_info_f . $info->name . '.php');
	}
}


// Handles WP related functionality after restore has happened Restore
function backuply_restore_response($is_last = false) {
	
	$keepalive = (int) time() + 25;

	if(!backuply_verify_self(backuply_optreq('security'))){
		backuply_status_log('Security Check Failed', 'error');
		die();
	}

	if(!$is_last && !empty($_REQUEST['restore_db']) && !empty($_REQUEST['is_migrating'])){
		$session_data = array('time' => time(), 'key' => backuply_optreq('sess_key'), 'user_id' => backuply_optreq('user_id'));
	
		update_option('backuply_restore_session_key', $session_data);
		
		backuply_status_log('Repairing database serialization', 'info', 78);
		$clones = ['options' => 'option', 'postmeta' => 'meta', 'commentmeta' => 'meta'];
		backuply_update_serialization($clones, $keepalive);
	}
	
	
	// Clears WP Cron for timeout
	if($timestamp = wp_next_scheduled('backuply_timeout_check', array('is_restore' => true))) {
		wp_unschedule_event($timestamp, 'backuply_timeout_check', array('is_restore' => true));
	}
	
	$email = get_option('backuply_notify_email_address');
	$site_url = get_site_url();
	$dir_path = backuply_cleanpath(ABSPATH);
	$backuply_backup_dir = backuply_cleanpath(BACKUPLY_BACKUP_DIR);
	
	// Was there an error ?
	if(backuply_optget('error')){
	
		$error_string = html_entity_decode(backuply_optget('error_string'));
		// Send mail
		$mail = array();
		$mail['to'] = $email;   
		$mail['subject'] = 'Restore of your WordPress installation failed - Backuply';
		$mail['headers'] = "Content-Type: text/html; charset=UTF-8\r\n";
		$mail['message'] = 'Hi, <br><br>

The last restore operation of your WordPress installation was failed. <br>
Installation URL : '.$site_url.' <br>
'.$error_string.' <br><br>


Regards,<br>
Backuply';

		wp_mail($mail['to'], $mail['subject'], $mail['message'], $mail['headers']);
		backuply_report_error(backuply_optget('error_string'));

		unlink($backuply_backup_dir.'/restoration/restoration.php');
		rmdir($backuply_backup_dir.'/restoration');
		
		backuply_status_log('Restore Failed!.','error');
		backuply_copy_log_file(true);

		exit(1);
	}

	backuply_status_log('Sending an Email notification.','working', 84);
	
	// Send mail
	$mail = array();
	$mail['to'] = $email;   
	$mail['subject'] = 'Restore of your WordPress - Backuply';
	$mail['headers'] = "Content-Type: text/html; charset=UTF-8\r\n";
	$mail['message'] = 'Hi, <br><br>

The restore of your WordPress backup was completed successfully. <br>
The details are as follows : <br><br>

Installation Path : '.$dir_path.'<br>
Installation URL : '.$site_url.'<br>
Regards,<br>
Backuply';

	wp_mail($mail['to'], $mail['subject'], $mail['message'], $mail['headers']);
	backuply_status_log('Restore performed successfully.', 'success', 100);
	update_option('backuply_last_restore', time());
	backuply_delete_rinfo_on_restore();
	backuply_copy_log_file(true);
	
	die();
}

// Loops(based on timeout) through the fixing serialized
function backuply_update_serialization($options = array(), $keepalive, $i = null) {
	
	if(!function_exists('backuply_wp_clone_sql')){
		include_once BACKUPLY_DIR . '/functions.php';
	}
	

	$keys = array_keys($options);
	$repair_log = get_option('backuply_sql_repair_log');
	
	if(empty($repair_log)) {
		$repair_log = array('table' => $keys[0], 'try' => 0);
		update_option('backuply_sql_repair_log', $repair_log);
	}

	$res = backuply_wp_clone_sql($keys[0], $options[$keys[0]], $keepalive, $i);

	if(!is_numeric($res)) {
		delete_option('backuply_sql_repair_log');
		unset($options[$keys[0]]);
	}
	
	if($res === false){
		backuply_status_log('Something went wrong while repairing database', 'error');
		die();
	}
	
	if(empty($options)){
		backuply_status_log('Successfully repaired the database', 'info', 81);
		backuply_restore_response(true);
		die();
	}
	
	$config = backuply_get_config();
	if(empty($config['BACKUPLY_KEY'])){
		backuply_status_log('Backuply key not found!', 'error');
		die();
	}
	
	$body = array('options' => $options);
	
	if(is_numeric($res)){
		
		if($keys[0] == $repair_log['table'] && $repair_log['try'] > 1){
			backuply_status_log('Repairing of the database serialization failed, Restoration has completed but some plugins may work weirdly', 'error', 100);
		}
		
		if(!empty($restore_log['i']) && $repair_log['i'] == $res && $keys[0] == $repair_log['table']){
			$repair_log['try'] += 1;
		}
		
		$repair_log['i'] = $res;
		
		update_option('backuply_sql_repair_log', $repair_log);
		
		
		$body['i'] = $res;
	}
	
	$url = admin_url('admin-ajax.php'). '?action=backuply_update_serialization&security='. $config['BACKUPLY_KEY'];
	
	wp_remote_post($url, array(
		'body' => $body,
		'timeout' => 2,
	));
	
	die();
}

// Ajax to handle the loop of fixing serialized
function backuply_update_serialization_ajax() {
	
	$keepalive = time() + 25;
	
	//Security Check
	if(!backuply_verify_self(backuply_optreq('security'))){
		backuply_status_log('Security Check Failed', 'error');
		die();
	}
	
	$i = !empty(backuply_optpost('i')) ? backuply_optpost('i') : null;
	
	backuply_update_serialization(sanitize_post($_POST['options']), $keepalive, $i);
}


// Creates a WP session if user is not logged in
function backuply_creating_session(){
	
	// Security Check
	if(!backuply_verify_self(backuply_optreq('security'))){
		backuply_status_log('Security Check Failed', 'error');
		die();
	}
	
	$key = get_option('backuply_restore_session_key');
	
	// Search for the user ID
	if(empty($key)){
		wp_send_json(array('success' => false, 'error' => true, 'message' => 'Session Key has not been updated yet!'));
	}
	
	if((time() - $key['time']) > 600 || (isset($_REQUEST['sess_key']) && $_REQUEST['sess_key'] != $key['key'])){
		//backuply_status_log('Session key verification failed', 'error');
		die();
	}
	
	if(is_user_logged_in()){
		wp_send_json(array('success' => false, 'message' => 'Already logged in'));
	}
	
	// Setting Session
	if(!empty($key['user_id'])){
		$user = get_user_by('id', $key['user_id']);
	}else{
		$user = get_userdata(1);
		
		// Try to find an admin if we do not have any admin with ID => 1
		if(empty($user) || empty($user->user_login)){
			$admin_id = get_users(array('role__in' => array('administrator'), 'number' => 1, 'fields' => array('ID')));
			$user = get_userdata($admin_id[0]->ID);
		}
		
		$username = $user->user_login;
		$user = get_user_by('login', $username);
	}
	
	if(isset($user) && is_object($user) && property_exists($user, 'ID')){
		clean_user_cache(get_current_user_id());
		clean_user_cache($user->ID);
		wp_clear_auth_cookie();
		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID, 1, is_ssl());
		do_action('wp_login', $user->user_login, $user);
		update_user_caches($user);

		wp_send_json(array('success' => true));
	}
}

// Returns logs of last backup/restore
function backuply_get_last_logs(){
	
	backuply_ajax_nonce_verify();
	
	$is_restore = backuply_optget('is_restore');
	$backup_log_name = !empty($_GET['file_name']) ? backuply_optget('file_name') : 'backuply_backup_log.php';
	$location_id = !empty($_GET['proto_id']) ? backuply_optget('proto_id') : '';	
	$backup_log_name = backuply_clean_file_name($backup_log_name);
	
	$log_fname = !empty($is_restore) ? 'backuply_restore_log.php' : $backup_log_name;
	$log_file = BACKUPLY_BACKUP_DIR . $log_fname;
	$logs = array();
	
	if(!file_exists($log_file)){
		if(!backuply_sync_remote_backup_logs($location_id, $log_fname)){
			wp_send_json(array('success' => false, 'progress_log' => 'No log found!|writing\n'));
		}
	}
	
	$fh = fopen($log_file, 'r');
	
	$seek_to = $last_log;
	@fseek($fh, 16);
	
	$lines = fread($fh, fstat($fh)['size']);
	fclose($fh);
	$fh = null;
	
	wp_send_json(array('success' => true, 'progress_log' => $lines));
}

function backuply_save_excludes() {
	
	global $backuply;
	
	backuply_ajax_nonce_verify();
	
	$type = backuply_optpost('type');
	$pattern = backuply_optpost('pattern'); // Pattern is also used as path when the type is specific
	
	// Clean the pattern in case its a full path
	if('exact' == $type){
		$pattern = str_replace(['..', './'], '', $pattern);
		$pattern = backuply_cleanpath($pattern);
	}
	
	if(!empty($_POST['key'])) {
		$key = backuply_optpost('key');
	}
	
	// Remove dot in case the user adds it
	if($type == 'extension'){
		$pattern = trim($pattern, '.');
	}
	
	if(empty($backuply['excludes'][$type])){
		$backuply['excludes'][$type] = array();
	}
	
	$this_type = &$backuply['excludes'][$type];

	if(in_array($pattern, $this_type)){
		wp_send_json(array('success' => false, 'message' => 'Exclude rule is already present'));
	}

	if(empty($key)){
		do{
			$key = wp_generate_password(6, false);
		} while(array_key_exists($key, $this_type));
	}
	$this_type[$key] = $pattern;

	update_option('backuply_excludes', $backuply['excludes']);
	
	wp_send_json(array('success' => true, 'key' => $key));
	
}

function backuply_exclude_rule_delete() {
	
	global $backuply;
	
	backuply_ajax_nonce_verify();
	
	$type = backuply_optget('type');
	$key = backuply_optget('key');
	
	if(empty($type) || empty($key)){
		wp_send_json(array('success' => false, 'message' => 'Unable to delete this Exclude rule'));
	}
	
	$this_type = &$backuply['excludes'][$type];

	// Deleting the rule
	if(empty($this_type)){
		wp_send_json(array('success' => false, 'message' => 'Rule not found'));
	}
	
	unset($this_type[$key]);
	update_option('backuply_excludes', $backuply['excludes']);
	
	wp_send_json(array('success' => true));
}


function backuply_get_jstree() {
	
	backuply_ajax_nonce_verify();
	
	$node = map_deep($_POST['nodeid'], 'sanitize_text_field');
	$scan_path = $node['id'];

	if('#' == $node['id']){
		$scan_path = WP_CONTENT_DIR;
	}
	
	// Cleaning the path to prevent directory traversal
	$scan_path = str_replace('..', '', $scan_path);
	$scan_path = backuply_cleanpath($scan_path);

	$nodes = array();

	if(empty($scan_path)){
		wp_send_json(array('success' => true, 'nodes' => $nodes));
	}

	$contents = @scandir($scan_path);
	
	if(empty($contents)){
		wp_send_json(array('success' => true, 'nodes' => $nodes));
	}
	
	foreach($contents as $con){
		if(in_array($con, array('.', '..'))){
			continue;
		}
		
		if(is_dir($scan_path . DIRECTORY_SEPARATOR . $con)){
			$nodes[] = array(
				'text' => $con,
				'children' => true,
				'id' => backuply_cleanpath($scan_path . DIRECTORY_SEPARATOR . $con),
				'type' => 'folder',
				'icon' => 'jstree-folder'
			);
		} else {
			$ext = pathinfo($con, PATHINFO_EXTENSION);
			
			$icon = 'jstree-file';
			
			if('php' == $ext){
				$icon = BACKUPLY_URL . '/assets/images/php-logo32.svg';
			}
			
			$nodes[] = array(
				'text' => $con,
				'children' => false,
				'id' => backuply_cleanpath($scan_path . DIRECTORY_SEPARATOR . $con),
				'type' => 'file',
				'icon' => $icon
			);
		}
	}

	wp_send_json(array('success' => true, 'nodes' => $nodes));
	
}

// Updates the backuply_backup_nag to current timestamp
function backuply_hide_backup_nag(){

	backuply_ajax_nonce_verify();
	
	update_option('backuply_backup_nag', time());
	
	wp_send_json(true);
}