<?php

/*
* BACKUPLY
* https://backuply.com
* (c) Backuply Team
*/

if(!defined('ABSPATH')) {
	die('HACKING ATTEMPT!');
}

function backuply_get_protocols(){
	
	$protocols['ftp'] = 'FTP';
	$protocols['gdrive'] = 'Google Drive';
	
	if(defined('BACKUPLY_PRO')) {
		
		if(!function_exists('backuply_get_pro_backups')) {
			include_once(BACKUPLY_DIR . '/premium.php');
		}
		
		$protocols += backuply_get_pro_backups();
	}
	
	return $protocols;
	
}

// Add the htaccess folder to protect us !
function backuply_add_htaccess(){
	
	if(!file_exists(BACKUPLY_BACKUP_DIR)) {
		@mkdir(BACKUPLY_BACKUP_DIR, 0777, true);
	}
	
	$htaccess = @fopen(BACKUPLY_BACKUP_DIR . '.htaccess', 'w');
	if(!$htaccess) {
		return false;
	}
	
	@fwrite($htaccess, 'deny from all');
	@fclose($htaccess);
	
	return true;
}


function backuply_kill_process() {
	delete_option('backuply_status');
	update_option('backuply_backup_stopped', true);
	
	if(file_exists(BACKUPLY_BACKUP_DIR . '/restoration/restoration.php')) {
		@unlink(BACKUPLY_BACKUP_DIR . '/restoration/restoration.php');
	}
	
	die();
}

// If there is a restore or backup task running
function backuply_active(){
	global $backuply;
	
	$backuply['status'] = get_option('backuply_status');
	
	// Nothing there
	if(empty($backuply['status']['last_time'])){
		return false;
	}
	
	// No updates since 5 min
	if((time() - BACKUPLY_TIMEOUT_TIME) > $backuply['status']['last_time']){
		return false;
	}
	
	return true;
	
}

// Verifies the backuply key
function backuply_verify_self($key) {
	
	if(empty($key)) {
		return false;
	}
	
	$config = backuply_get_config('BACKUPLY_KEY');
	
	if(urldecode($key) == $config['BACKUPLY_KEY']) {
		return true;
	}
	
	return false;
}

// Wp-Cron handle for timeout check i.e. clean dead processes
// Terminates process if no update for 30 min
function backuply_timeout_check($args) {	
	
	global $backuply;
	
	$log_file = BACKUPLY_BACKUP_DIR . 'backuply_log.php';
	
	$is_working = false;
	
	// Is it a restore check ?
	if(!empty($args['is_restore'])) {
		
		$file = BACKUPLY_BACKUP_DIR . '/restoration/restoration.php';
		
		if(file_exists($file)) {
			$is_working = true;
		}
		
		$fm_time = filemtime($file);
		
		if((time() - $fm_time) >= BACKUPLY_TIMEOUT_TIME) {
			backuply_kill_process();
		}
	
	// Its a backup process
	} else {
		
		if(empty($backuply['status']['last_update'])){
			backuply_kill_process();
		}
		
		if((time() - $backuply['status']['last_update']) >= BACKUPLY_TIMEOUT_TIME) {
			backuply_kill_process();
		}
		
	}
	
	// To check after 5 minutes again
	wp_schedule_single_event(time() + BACKUPLY_TIMEOUT_TIME, 'backuply_timeout_check', array('is_restore' => $args['is_restore']));
	
}

// Create a config file and sets it with a key
function backuply_set_config() {
	
	$key = backuply_csrf_get_token();
	
	$write['BACKUPLY_KEY'] = $key;
	
	$config_file = BACKUPLY_BACKUP_DIR . 'backuply_config.php';
	
	$fp = @fopen($config_file, 'w');

	if (0 == filesize($config_file)){
		// file is empty
		@fwrite($fp, "<?php exit();?>\n");
	}
	
	@fwrite($fp, json_encode($write, JSON_PRETTY_PRINT));
	@fclose($fp);
	
	@chmod($config_file, 0600);
}

// Sets Backup Location details in Restoration File
function backuply_set_restoration_file($loc) {
	$write['protocol'] = $loc['protocol'];
	$write['name'] = $loc['name'];
	
	$restoration_file = BACKUPLY_BACKUP_DIR . 'restoration/restoration.php';
	
	$fp = @fopen($restoration_file, 'w');

	if (0 == filesize($restoration_file)){
		// file is empty
		@fwrite($fp, "<?php exit();?>\n");
	}
	
	@fwrite($fp, json_encode($write, JSON_PRETTY_PRINT));
	@fclose($fp);
	
	@chmod($restoration_file, 0600);
}

// Sets Backup Location details in Restoration File
function backuply_get_restoration_data() {
	$restoration_file = BACKUPLY_BACKUP_DIR . 'restoration/restoration.php';
	
	$fp = @fopen($restoration_file, 'r');
	@fseek($fp, 16);
	
	if(filesize($restoration_file) == 0){
		return;
	}
	
	$content = @fread($fp, filesize($restoration_file));
	@fclose($fp);
	
	if(empty($content)) {
		return [];
	}
	
	$restro = json_decode($content, true);

	return $restro;
}

// Get Config Array
function backuply_get_config() {
	$config_file = BACKUPLY_BACKUP_DIR . 'backuply_config.php';
	
	if(!file_exists($config_file) || 0 == filesize($config_file)) {
		backuply_set_config();
		//backuply_get_config();
	}

	$fp = @fopen($config_file, 'r');
	@fseek($fp, 16);
	
	$content = @fread($fp, filesize($config_file));
	@fclose($fp);
	
	if(empty($content)) {
		return [];
	}
	
	$config = json_decode($content, true);

	return $config;
}

// Create or updates the log file
function backuply_status_log($log, $status = 'working', $percentage = 0){
	$log_file = BACKUPLY_BACKUP_DIR . 'backuply_log.php';
	
	$logs = [];
	
	$file = file($log_file);
	
	if(0 == filesize($log_file)) {
		$log = "<?php exit();?>\n" . $log; //Prepend php exit
	}
	
	$this_log = $log . '|' . $status . '|' . $percentage . "\n";
	
	file_put_contents($log_file, $this_log, FILE_APPEND);
}

// Returns array of logs
function backuply_get_status($last_log = 0){
	$log_file = BACKUPLY_BACKUP_DIR. 'backuply_log.php';
	$logs = [];
	
	if(!file_exists($log_file)){
		$logs[] = 'Something went wrong!|error';
		delete_option('backuply_status');
		update_option('backuply_backup_stopped', 1);
		return $logs;
	}
	
	$fh = fopen($log_file, 'r');
	
	$seek_to = $last_log;
	@fseek($fh, $seek_to);
	
	$lines = fread($fh, fstat($fh)['size']);
	fclose($fh);
	$fh = null;
	return $lines;
}

// A compulsory POST which issues a error if the POST[$name] is not there
function backuply_POST($name, $e){

	global $error;

	//Check the POSTED NAME was posted
	if(!isset($_POST[$name]) || strlen(trim($_POST[$name])) < 1){
	
		$error[] = $e;
		
	}else{
		return backuply_inputsec(backuply_htmlizer(trim($_POST[$name])));
	}
}

// Used for the checkboxes which have the same names (i.e. name=SOMENAME[])
function backuply_POSTmulticheck($name, $value, $default = array()){
	
	if(isset($_POST[$name]) && is_array($_POST[$name])){
		if(in_array($value, $_POST[$name])){
			return 'checked="checked"';
		}
	}else{
		if(in_array($value, $default)){
			return 'checked="checked"';
		}
	}
	
	return true;
}

// A compulsory REQUEST which issues a error if the REQUEST[$name] is not there
function backuply_REQUEST($name, $e){

	global $error;

	//Check the POSTED NAME was posted
	if(!isset($_REQUEST[$name]) || strlen(trim($_REQUEST[$name])) < 1){
	
		$error[$name] = $e;
		
	}else{
		return backuply_inputsec(backuply_htmlizer(trim($_REQUEST[$name])));
	}
}

// Check if a field is posted via POST else return default value
function backuply_optpost($name, $default = ''){
	
	if(!empty($_POST[$name])){
		return backuply_inputsec(backuply_htmlizer(trim($_POST[$name])));
	}
	
	return $default;	
}

// Check if a field is posted via GET else return default value
function backuply_optget($name, $default = ''){
	
	if(!empty($_GET[$name])){
		return backuply_inputsec(backuply_htmlizer(trim($_GET[$name])));
	}
	
	return $default;	
}

// Check if a field is posted via GET or POST else return default value
function backuply_optreq($name, $default = ''){
	
	if(!empty($_REQUEST[$name])){
		return backuply_inputsec(backuply_htmlizer(trim($_REQUEST[$name])));
	}
	
	return $default;	
}

// For filling in posted values
function backuply_POSTval($name, $default = ''){
	
	return (!empty($_POST) ? (!isset($_POST[$name]) ? '' : backuply_optpost($name)) : $default);

}

function backuply_POSTchecked($name, $default = false, $submit_name = ''){
	
	if(!empty($submit_name)){
		$post_to_check = isset($_POST[$submit_name]) ? backuply_optpost($submit_name) : '';
	}else{
		$post_to_check = $_POST;
	}
	
	return (!empty($post_to_check) ? (isset($_POST[$name]) ? 'checked="checked"' : '') : (!empty($default) ? 'checked="checked"' : ''));

}

function backuply_POSTselect($name, $value, $default = false){
	
	if(empty($_POST)){
		if(!empty($default)){
			return 'selected="selected"';
		}
	}else{
		if(isset($_POST[$name])){
			if(trim($_POST[$name]) == $value){
				return 'selected="selected"';
			}
		}
	}
}

///TODO:: Not being used
function backuply_POSTradio($name, $val, $default = null){
	
	return (!empty($_POST) ? (@$_POST[$name] == $val ? 'checked="checked"' : '') : (!is_null($default) && $default == $val ? 'checked="checked"' : ''));

}

function backuply_inputsec($string){

	$string = addslashes($string);
	
	// This is to replace ` which can cause the command to be executed in exec()
	$string = str_replace('`', '\`', $string);
	
	return $string;

}

function backuply_htmlizer($string){

	$string = htmlentities($string, ENT_QUOTES, 'UTF-8');
	
	preg_match_all('/(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)/', $string, $matches);//backuply_print($matches);
	
	foreach($matches[1] as $mk => $mv){		
		$tmp_m = backuply_entity_check($matches[2][$mk]);
		$string = str_replace($matches[1][$mk], $tmp_m, $string);
	}
	
	return $string;
	
}

function backuply_entity_check($string){
	
	//Convert Hexadecimal to Decimal
	$num = ((substr($string, 0, 1) === 'x') ? hexdec(substr($string, 1)) : (int) $string);
	
	//Squares and Spaces - return nothing 
	$string = (($num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num < 0x20) ? '' : '&#'.$num.';');
	
	return $string;
}

// Check if a checkbox is selected
function backuply_is_checked($post){

	if(!empty($_POST[$post])){
		return true;
	}	
	return false;
}

// A Function that lists files and folders in a folder.
function backuply_sfilelist($startdir='./', $searchSubdirs=1, $directoriesonly=0, $maxlevel='all', $level=1){	
	return backuply_filelist_fn($startdir, $searchSubdirs, $directoriesonly, $maxlevel, $level);
}

// The below function will list all folders and files within a directory. It is a recursive function that uses a global array.
function backuply_filelist_fn($startdir='./', $searchSubdirs=1, $directoriesonly=0, $maxlevel='all', $level=1, $reset = 1){
	
   //list the directory/file names that you want to ignore
   $ignoredDirectory = array();
   $ignoredDirectory[] = '.';
   $ignoredDirectory[] = '..';
   $ignoredDirectory[] = '_vti_cnf';
   global $directorylist;	//initialize global array
   
	if(substr($startdir, -1) != '/'){
		$startdir = $startdir.'/';
	}
   
	if (is_dir($startdir)) {
		if ($dh = opendir($startdir)) {
			while (($file = readdir($dh)) !== false) {
				if (!(array_search($file,$ignoredDirectory) > -1)) {
					if (@filetype($startdir . $file) == 'dir') {

						//build your directory array however you choose;
						//add other file details that you want.

						$directorylist[$startdir . $file]['level'] = $level;
						$directorylist[$startdir . $file]['dir'] = 1;
						$directorylist[$startdir . $file]['name'] = $file;
						$directorylist[$startdir . $file]['path'] = $startdir;
						if ($searchSubdirs) {
							if ((($maxlevel) == 'all') or ($maxlevel > $level)) {
							   backuply_filelist_fn($startdir . $file . "/", $searchSubdirs, $directoriesonly, $maxlevel, ($level + 1), 0);
							}
						}
					} else {
						if (!$directoriesonly) {
							//if you want to include files; build your file array 
							//however you choose; add other file details that you want.
							$directorylist[$startdir . $file]['level'] = $level;
							$directorylist[$startdir . $file]['dir'] = 0;
							$directorylist[$startdir . $file]['name'] = $file;
							$directorylist[$startdir . $file]['path'] = $startdir;
						}
					}
				}
			}
			closedir($dh);
		}
	}

	if(!empty($reset)){
		$r = $directorylist;
		$directorylist = array();
		return($r);
	}
}

// Report an error
function backuply_report_error($error = array()){

	if(empty($error)){
		return true;
	}
	
	$error_string = '<b>Please fix the below error(s) :</b> <br />';

	foreach($error as $ek => $ev){
		$error_string .= '* '.$ev.'<br />';
	}
	
	echo '<div id="message" class="error"><p>'. wp_kses_post($error_string). '</p></div><br>';
	
}

// Report a success
function backuply_report_success($msg){

	if(empty($msg)){
		return true;
	}
	
	echo '<div id="message" class="notice updated is-dismissible"><p>'. wp_kses_post($msg) . '</p></div><br />';
}

// Generate a random string
function backuply_random_string($length = 10){
	$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
	$charactersLength = strlen($characters);
	$randomString = '';
	for($i = 0; $i < $length; $i++){
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}
	return $randomString;
}

function backuply_print($array){

	echo '<pre>';
	print_r($array);
	echo '</pre>';

}

function backuply_cleanpath($path){
	$path = str_replace('\\\\', '/', $path);
	$path = str_replace('\\', '/', $path);
	$path = str_replace('//', '/', $path);
	return rtrim($path, '/');
}

//TODO:: Not being used
// Returns the Numeric Value of results Per Page
function backuply_get_page($get = 'page', $resperpage = 50){

	$resperpage = (!empty($_REQUEST['reslen']) && is_numeric($_REQUEST['reslen']) ? (int) backuply_optreq('reslen') : $resperpage);
	
	if(backuply_optget($get)){
		$pg = (int) backuply_optget($get);
		$pg = $pg - 1;		
		$page = ($pg * $resperpage);
		$page = ($page <= 0 ? 0 : $page);
	}else{	
		$page = 0;		
	}	
	return $page;
}

// This function just redirects to the Location specified and dies
function backuply_redirect($location, $header = true){
	//$prefix = (empty($raw) ? $globals['index'] : '');
	
	if($header){
	
		//Redirect
		header('Location: '.$location);
		
	}else{
		echo '<meta http-equiv="Refresh" content="0;url='.esc_url($location).'">';
	}
}

// Returns the CSRF Token, generates one if it does not exist
function backuply_csrf_get_token(){

	if(function_exists('mcrypt_create_iv')){
		$csrf_token = bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM));
	}else{
		$csrf_token = bin2hex(openssl_random_pseudo_bytes(32));
	}
	
	return $csrf_token;
}

// Update Backuply logs as per action
function backuply_log($data){
	global $backuply;
	
	if(empty($backuply['debug_mode'])){
		return;
	}

	$write = '';
	$write .= '['.date('Y-m-d H:i:s', time()).'] ';

	$write .= $data."\n\n";
	
	$log_file = BACKUPLY_BACKUP_DIR.'backups_info/debug.php';
	
	$fp = @fopen($log_file, 'ab');
	
	if (0 == @filesize($log_file)){
		// file is empty
		@fwrite($fp, "<?php exit();?>\n");
	}
	
	@fwrite($fp, $write);
	@fclose($fp);
	
	@chmod($log_file, 0600);
}

/**
 * This function will preg_match the pattern and return the respective values in $var
 * @package	  Backuply 
 * @param		 $pattern This should be the pattern to be matched
 * @param		 $file This should have the data to search from
 * @param		 $var This will be the variable which will have the preg matched data
 * @param		 $valuenum This should be the no of regular expression to be returned in $var
 * @param		 $stripslashes 0 or 1 depending upon whether the stripslashes function is to be applied (1) or not (0)
 * @return	   string Will pass value by reference in $var
 * @since	 	 4.5.4
 */
function backuply_preg_replace($pattern, $file, &$var, $valuenum, $stripslashes = ''){	
	preg_match($pattern, $file, $matches);
	if(empty($stripslashes)){
		$var = trim($matches[$valuenum]);
	}else{
		$var = stripslashes(trim($matches[$valuenum]));
	}
}

//Function to return a file's size with their appropriate formats.
function backuply_format_size($size){
	$sizes = array(' Bytes', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB');
	if ($size == 0) { return('n/a'); } else {
	return (round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizes[$i]); }
}

// Handles submission of license
function backuply_license(){
	global $backuply;

	check_admin_referer('backuply_license_form', 'backuply_license_nonce');
	
	$license = sanitize_key($_POST['backuply_license']);
	
	if(empty($license)) {
		add_settings_error('backuply-notice', esc_attr( 'settings_updated' ), esc_html__('The license key was not submitted', 'backuply'), 'error');
		
		return;
	}

	$resp = wp_remote_get(BACKUPLY_API.'/license.php?license='.$license, array('timeout' => 30));

	if(is_array($resp)){
		$json = json_decode($resp['body'], true);
	}else{
		add_settings_error('backuply-notice', esc_attr( 'settings_updated' ), esc_html__('The response was malformed', 'backuply').'<br>'.var_export($resp, true), 'error');
		return;
	}
	
	// Save the License
	if(empty($json['license'])){
		add_settings_error('backuply-notice', esc_attr( 'settings_updated' ), esc_html__('The license key is invalid', 'backuply'), 'error');
		
		return;
	} else{
		if(get_option('backuply_license')) {
			update_option('backuply_license', $json);
		} else{
			add_option('backuply_license', $json);
		}
		
		$backuply['license'] = $json;
	}
}

// Load license data
function backuply_load_license(){
	global $backuply;
	
	// Load license
	$backuply['license'] = get_option('backuply_license');
	
	// Update license details as well
	if(!empty($backuply['license']) && (time() - @$backuply['license']['last_update']) >= 86400){
		
		$resp = wp_remote_get(BACKUPLY_API.'/license.php?license='.$backuply['license']['license']);

		//Did we get a response ?
		if(is_array($resp)){
			
			$tosave = json_decode($resp['body'], true);
			
			//Is it the license ?
			if(!empty($tosave['license'])){
				$tosave['last_update'] = time();
				update_option('backuply_license', $tosave);
			}
		}
	}	
}

// Install the Pro version
function backuply_install_pro(){
	global $backuply;
	
	// Include the necessary stuff
	include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );

	// Includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
	include_once( ABSPATH . 'wp-admin/includes/file.php' );
	include_once( ABSPATH . 'wp-admin/includes/misc.php' );
	include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

	// Filter to prevent the activate text
	add_filter('install_plugin_complete_actions', 'backuply_install_plugin_complete_actions', 10, 3);

	$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin() );
	$installed = $upgrader->install(BACKUPLY_API.'/download.php?version=latest&license='.$backuply['license']['license']);
	
	if ( !is_wp_error( $installed ) && $installed ) {
		echo 'Activating Backuply Pro !';
		$activate = activate_plugin(BACKUPLY_PRO_BASE);
		
		if ( is_null($activate) ) {
			echo '<div id="message" class="updated"><p>'. esc_html__('Done! Backuply Pro is now installed and activated.', 'backuply'). '</p></div><br /><br><br><b>'. esc_html__('Done! Backuply Pro is now installed and activated.', 'backuply').'</b>';
		}
	}
	
	return $installed;
}

// Prevent pro activate text for installer
function backuply_install_plugin_complete_actions($install_actions, $api, $plugin_file){
	
	if($plugin_file == BACKUPLY_PRO_BASE){
		return array();
	}
	
	return $install_actions;
}

function backuply_get_backups_info(){
	
	// Get all Backups Information from the "backups_info" folder.
	$all_backup_info_files = BACKUPLY_BACKUP_DIR.'backups_info';
	$backup_files_location = BACKUPLY_BACKUP_DIR.'backups';
	
	$backup_infos = array();

	$info_files = @scandir($all_backup_info_files);
	
	if(empty($info_files)){
		return $backup_infos;
	}
	
	foreach($info_files as $files){

		if($files != '.' && $files != '..'){
			
			$i = 0;
			$check_for_file = basename($files, '.php');

			$file = file($all_backup_info_files."/".$files);
			unset($file[0]);
			$all_info = json_decode(implode('', $file));

			$backup_file_location = $backup_files_location.'/'.$check_for_file.'.tar.gz';
			if(file_exists($backup_file_location) || isset($all_info->backup_location)){

				//Store all the files information in an array
				$backup_infos[] = $all_info;
			}
		}
	}

	return $backup_infos;
}

// Deletes backups
function backuply_delete_backup($tar_file) {
	
	global $error;
	
	$_file = $tar_file;
	$bkey = basename(basename($_file, '.tar.gz'), '.tar');
	$backup_info_dir = BACKUPLY_BACKUP_DIR.'backups_info';
	$deleted = false;
	
	// Load Backuply's remote backup locations.
	$backuply_remote_backup_locs = get_option('backuply_remote_backup_locs');
	
	$files_exists = 0;
	$backup_infos = backuply_get_backups_info();

	foreach($backup_infos as $backup_info){
		// Is the backup on a remote location ? 
		if(!empty($backup_info->backup_location) && $backup_info->name == $bkey && array_key_exists($backup_info->backup_location, $backuply_remote_backup_locs)){

			$backup_dir = $backuply_remote_backup_locs[$backup_info->backup_location]['full_backup_loc'];
			$remote_stream_wrappers = array('dropbox', 'gdrive', 'softftpes', 'softsftp', 'webdav', 'aws', 'caws', 'onedrive');

			if(in_array($backuply_remote_backup_locs[$backup_info->backup_location]['protocol'], $remote_stream_wrappers)){
				
				if(!defined('BACKUPLY_PRO')) {
					$error[] = esc_html__('You are trying to access a PRO feature through FREE version', 'backuply');
					return false;
				}
				
				if(!function_exists('backuply_stream_wrapper_register')) {
					include_once BACKUPLY_DIR . '/premium.php';
				}
				
				backuply_stream_wrapper_register($backuply_remote_backup_locs[$backup_info->backup_location]['protocol'], $backuply_remote_backup_locs[$backup_info->backup_location]['protocol']);
			}
			
			//echo "<br> checked for file existance : ".$backup_dir.'/'.$_file;
			
			if(file_exists($backup_dir.'/'.$_file)){
				//echo "<br> checked for file existance : ".$backup_dir.'/'.$_file;
				$files_exists = 1;
				break;
			}
		}else{
			$backup_dir = BACKUPLY_BACKUP_DIR.'backups';
		
			if(file_exists($backup_dir.'/'.$_file)){
				//echo "local file : ".$backup_dir.'/'.$_file;
				$files_exists = 1;
				break;
			}
		}
	}

	if(!empty($files_exists)){
		// Delete the backup
		@unlink($backup_dir.'/'.$_file);
		//backuply_log($_file.' Backup deleted successfully');
		
		// Delete the backup_info file
		@unlink($backup_info_dir.'/'.$bkey.'.php');
		//backuply_log($_file.' Backupi info file deleted successfully');
		
		// If the Location is remote
		@unlink($backup_dir.'/'.$bkey.'.php');
		@unlink($backup_dir.'/'.$bkey.'.info'); // Changed to info file since 1.0.2
		
		// Deleting log files from remote and local
		@unlink($backup_dir.'/'.$bkey.'.log');
		@unlink(BACKUPLY_BACKUP_DIR . $bkey.'_log.php');
		
		$deleted = true;
	}else{

		// Delete the backup_info file
		@unlink($backup_info_dir.'/'.$bkey.'.php');
		
		// If the Location is remote
		@unlink($backup_dir.'/'.$bkey.'.php');
		@unlink($backup_dir.'/'.$bkey.'.info'); // Changed to info file since 1.0.2
		
		// Deleting log files from remote and local
		@unlink($backup_dir.'/'.$bkey.'.log');
		@unlink(BACKUPLY_BACKUP_DIR . $bkey.'_log.php');
		
		$deleted = true;
	}
	
	if(file_exists($backup_dir.'/'.$_file)) {
		$deleted = false;
		$error[] = __('Unable to delete ', 'backuply') . esc_html($_file);
	}

	return $deleted;
}

// Creates log file
function backuply_create_log_file() {
	@unlink(BACKUPLY_BACKUP_DIR.'backuply_log.php');
	@touch(BACKUPLY_BACKUP_DIR.'backuply_log.php');
}

/**
 * Connect to the ftp server
 *
 * @param	string $host The hostname of the ftp server
 * @param	string $username The username Login detail
 * @param	string $pass The Login password
 * @param	string $cd The path of the file or directory to be changed
 * @returns 	bool
 */
function backuply_sftp_connect($host, $username, $pass, $protocol = 'ftp', $port = 21, $cd = false, $pri = '', $passphrase = ''){
	
	$port = (int) $port; // Converting to INT as FTP class requires an integer

	backuply_include_lib($protocol);
	
	if($protocol == 'ftp'){
		$ftp = new ftp(FALSE, FALSE);
		
		if(!$ftp->SetServer($host, $port)) {
			$ftp->quit();
			return 0;
		}
		
		if (!$ftp->connect()) {
			return -1;
		}
		
		if (!$ftp->login($username, $pass)) {
			$ftp->quit();
			return -2;
		}
		
		if(!empty($cd)){
			if(!$ftp->chdir($cd)){
				if(!$ftp->chdir(trim($cd, '/'))){
					return -3;
				}
				//return -3;
			}
		}
		
		if(!$ftp->SetType(BACKUPLY_FTP_AUTOASCII)){
		}
		
		if(!$ftp->Passive(TRUE)){
		}
	}

	// Class other than FTP
	if(empty($ftp)){
	
		// Initialize a Class
		$ftp = new $protocol();
		
		// Return if Class not found
		if(!is_object($ftp)){
			return -1;
		}
		
		// For SFTP authentication with keys or password
		if($protocol == 'softftpes' && !empty($pri)){
			$ftp->auth_pass = 0;
		}else{
			$ftp->auth_pass = 1;
		}

		// Can connect ?
		$ret = $ftp->connect($host, $port, $username, $pass, $pri, $passphrase);
		
		if(!$ret){
			return -2;
		}
		
		// Is directory present
		if(!empty($cd)){
			if(!$ftp->is_dir($cd)){
				return -3;
			}
		}
	}

	return $ftp;
}

// Creates stream wrapper and includes the associated class
function backuply_stream_wrapper_register($protocol, $classname){
	
	$protocols = array('dropbox', 'aws', 'caws', 'gdrive', 'softftpes', 'softsftp', 'webdav', 'onedrive');
	
	if(!in_array($protocol, $protocols)){
		return true;
	}

	backuply_include_lib($protocol);
	
	$existing = stream_get_wrappers();
	if(in_array($protocol, $existing)){
		return true;
	}

	if(!stream_wrapper_register($protocol, $classname)){
		return false;
	}

	return true;
}

// Includes Lib Files
function backuply_include_lib($protocol) {
	
	if(!class_exists($protocol)){
		
		if(file_exists(BACKUPLY_DIR.'/lib/'.$protocol.'.php')) {
			include_once(BACKUPLY_DIR.'/lib/'.$protocol.'.php');
			return true;
		}
		
		if(defined('BACKUPLY_PRO') && defined('BACKUPLY_PRO_DIR') && file_exists(BACKUPLY_PRO_DIR . '/' .$protocol . '.php')) {
			include_once(BACKUPLY_PRO_DIR . '/' .$protocol . '.php');
			return true;
		}
		
		return false;
	}
	
	return true;
}

// Load Remote Backup class and initialize the class object
function backuply_load_remote_backup($backup_proto){
	
	if(!array_key_exists($backup_proto, backuply_get_protocols())){
		return false;
	}

	backuply_include_lib($backup_proto);
	
	$init_obj = new $backup_proto([]);
	
	return $init_obj;
}

function backuply_load_remote_backup_info($protocol) {
	$remote = get_option('backuply_remote_backup_locs');
	
	foreach($remote as $k => $r) {
		if($r['protocol'] == $protocol) {
			return $r;
		}
	}
}

// Returns Remote loc details by id
function backuply_get_loc_by_id($id, $filter = array()){
	$remote = get_option('backuply_remote_backup_locs');

	foreach($remote as $k => $r) {
		if($k == $id) {
			// Removes the indexes that matches the filter
			foreach($r as $i => $k){
				if(in_array($i, $filter)) {
					unset($r[$i]);
				}
			}

			return $r;
		}
	}
	
	return false;
}

// Syncs available backup infos on a remote backup location to your site
function backuply_sync_remote_backup_infos($location_id){
	
	$locs = get_option('backuply_remote_backup_locs');
	
	if(empty($locs[$location_id])){
		return false;
	}
	
	$loc = $locs[$location_id];
	//backuply_print($loc);
	
	backuply_stream_wrapper_register($loc['protocol'], $loc['protocol']);

	$list = @scandir($loc['full_backup_loc']);
	
	if(empty($list)){
		return false;
	}
	
	foreach($list as $k => $v){
		
		$ext = pathinfo($v, PATHINFO_EXTENSION);
		
		$allowed_ext = array('php', 'info');
		$fexists = $v;

		if(strpos($v, '.info') !== FALSE){
			$fexists = basename($v, '.info') . '.php';
		}
		
		if(!in_array($ext, $allowed_ext) || file_exists(BACKUPLY_BACKUP_DIR.'backups_info/'. $fexists)){
			//echo 'Continuing : '.$v.'<br>';
			continue;
		}
		//echo $v.'<br>';
		
		// Get the contents
		$fh = fopen($loc['full_backup_loc']. '/' . trim($v, '/'), 'rb');
		
		if(empty($fh)){
			continue;
		}
		
		$file = fread($fh, 8192);
		@fclose($fh);
		//backuply_print($file);
		
		if(empty($file)){
			continue;
		}
		
		$lines = explode("\n", $file);
		unset($lines[0]);
		
		$info = json_decode(implode("\n", $lines), true);
		//backuply_print($info);
		
		if(empty($info)){
			continue;
		}
		
		// Set the backup location ID
		$info['backup_location'] = $location_id;
		//backuply_print($info);
		
		$v = str_replace('.info', '.php', $v);
		
		// Write the file
		file_put_contents(BACKUPLY_BACKUP_DIR.'backups_info/'.$v, "<?php exit();?>\n".json_encode($info, JSON_PRETTY_PRINT));
		
	}
	
	return true;
	
}

// Syncs available backup logs on a remote backup location to your site
function backuply_sync_remote_backup_logs($location_id, $fname){
	
	if(empty($location_id)){
		return false;
	}
	
	$locs = get_option('backuply_remote_backup_locs');
	
	if(empty($locs[$location_id])){
		return false;
	}
	
	$loc = $locs[$location_id];
	
	backuply_stream_wrapper_register($loc['protocol'], $loc['protocol']);

	$list = @scandir($loc['full_backup_loc']);
	
	if(empty($list)){
		return false;
	}
	
	foreach($list as $k => $v){
		
		// Changing log name to log file for remote loc
		$fname = str_replace('_log.php', '.log', $fname);
		$ext = pathinfo($v, PATHINFO_EXTENSION);

		if($ext != 'log' || $fname != trim($v, '/') || file_exists(BACKUPLY_BACKUP_DIR . $v)){
			continue;
		}
		
		// Get the contents
		$fh = fopen($loc['full_backup_loc'].'/'.trim($v, '/'), 'rb');
		
		if(empty($fh)){
			return false;
		}

		while(!feof($fh)){
			$file .= fread($fh, 8192);
		}
		
		@fclose($fh);

		if(empty($file)){
			return false;
		}

		// Changing log to php file for local
		$v = str_replace('.log', '_log.php', $v);
		
		// Write the file
		file_put_contents(BACKUPLY_BACKUP_DIR . $v, $file);
	
	}
	
	return true;
	
}



//Fix the serialization in Cloned DB Tables
function backuply_wp_clone_sql($table, $field_prefix, $keepalive, $i = null){

	global $wpdb;
	
	if(empty($wpdb)){
		backuply_log('Fix Serialization failed: unable to connect to the database');
		return false;
	}
	
	$cnt_qry = "SELECT count('".$field_prefix."_id') as count_".$field_prefix." FROM `".$wpdb->prefix.$table . "`;";

	$result = $wpdb->get_results($cnt_qry);
	$result = json_decode(json_encode($result[0]), true);
	$cnt_res = $result['count_'.$field_prefix];
	
	$count = 10000;
	$limit = 0;

	$org_query = "SELECT `".$field_prefix."_id`, `".$field_prefix."_value` FROM `".$wpdb->prefix.$table."` ORDER BY ".$field_prefix."_id";
	
	
	if(is_null($i)){
		$i = $cnt_res;
		
		backuply_status_log('Repairing '. $wpdb->prefix.$table, 'repairing', 80);
	}
	
	backuply_status_log($i . ' Rows left to repair in ' . $wpdb->prefix.$table);
	
	while($i >= 0){
		
		if(time() > $keepalive){
			return (int) $i;
		}
		
		$query = $org_query.' LIMIT '.$limit.', '.$count.';';
		
		$result = $wpdb->get_results($query);
		
		
		// If there are no more rows we need to break the loop
		if(empty($result[0])){
			break;
		}
		
		foreach($result as $rk => $rv){
			$rv = json_decode(json_encode($rv), true);
			
			$update_query = '';
			$sresult = '';
			// This data should be serialized
			if(preg_match('/^a:(.*?):{/is', $rv[$field_prefix.'_value']) || preg_match('/^O:(.*?):{/is', $rv[$field_prefix.'_value'])){
				
				if(preg_match('/^utf8(.*?)/is', $wpdb->charset) && empty($conn)){
					$_unserialize = backuply_unserialize(utf8_encode($rv[$field_prefix.'_value']));
					$updated_data = (!function_exists('get_magic_quotes_gpc') || !get_magic_quotes_gpc()) ? addslashes(utf8_decode(serialize($_unserialize))) : utf8_decode(serialize($_unserialize));
				}else{
					$_unserialize = backuply_unserialize($rv[$field_prefix.'_value']);
					$updated_data = (!function_exists('get_magic_quotes_gpc') || !get_magic_quotes_gpc()) ? addslashes(serialize($_unserialize)) : serialize($_unserialize);
				}
				
				$update_query = "UPDATE `".$wpdb->prefix.$table."` SET `".$field_prefix."_value` = '".$updated_data."' WHERE `".$field_prefix."_id` = '".$rv[$field_prefix.'_id']."';";
				
				$sresult = $wpdb->query($update_query);
			}
		}
		
		$i--;
	}

	$limit = $limit + $count;
	
	return true;
}

/**
 * Callback for fixing any broken serialized string
 *
 * @package      string 
 * @author       Brijesh Kothari
 * @param        array $matches
 * @return       string Returns the fixed serialize content
 * @since     	 5.3.1
 * NOTE : Any changes in this function will affect anywhere this function is used as a callback
 */
function backuply_fix_serialize_callback($matches){
	
	//r_print($matches);
	
	// We are not using soft_is_serialized_str() because that checks for ; or } at the end and our data can be a:2:{s:3:"three
	if(preg_match('/^a:(.*?):{/is', $matches[2]) || preg_match('/^O:(.*?):/is', $matches[2])){
		return $matches[0];
	}
	
	return 's:'.strlen($matches[2]).':"'.$matches[2].'";';
}

/**
 * Unserialize a string and also fixes any broken serialized string before unserializing
 *
 * @package      string 
 * @author       Pulkit Gupta
 * @param        string $str
 * @return       array Returns an array if successful otherwise false 
 * @since     	 1.0
 */
// Note : This will not work for a serialized string in an array key
function backuply_unserialize($str){

	$var = @unserialize($str);
	
	if(empty($var)){
		
		// NOTE : Any changes in this pattern will need to be handled in callback function as well
		$str = preg_replace_callback('/s:(\d+):"(.*?)";(?=([:]|(?<=;)\}(?!\.)|a:|s:|S:|b:|d:|i:|o:|O:|C:|r:|R:|N;))/s', 'backuply_fix_serialize_callback', $str);
		
		$var = @unserialize($str);
	
	}
	
	//If it is still empty false
	if($var === false){
	
		return false;
	
	}else{
	
		return $var;
	
	}

}

// Renames backuply_log file to show last logs.
function backuply_copy_log_file($is_restore = false, $file_name = ''){
	
	if(empty($file_name)){
		$file_name = 'backuply_backup';
	}
	
	$copy_to = empty($is_restore) ? $file_name . '_log.php' : 'backuply_restore_log.php';
	copy(BACKUPLY_BACKUP_DIR . 'backuply_log.php', BACKUPLY_BACKUP_DIR . $copy_to);

}

function backuply_clean_file_name($file){
	return str_replace(['..', '/'], '', $file);
}


function backuply_pattern_type_text($type) {
		
	if(empty($type)){
		return esc_html__('No type found!', 'backuply');
	}
		
	$types = array(
		'extension' => esc_html__('With specific extension', 'backuply'),
		'beginning' => esc_html__('At beginning', 'backuply'),
		'end' => esc_html__('At end', 'backuply'),
		'anywhere' => esc_html__('Anywhere', 'backuply')
	);
		
	return $types[$type];
}

