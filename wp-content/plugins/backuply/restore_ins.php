<?php
/*
* BACKUPLY
* https://backuply.com
* (c) Backuply Team
*/

set_time_limit(60);
ignore_user_abort(true); // Dont abort if user aborts
//error_reporting(E_ALL);

//Constants
define('ARCHIVE_TAR_ATT_SEPARATOR', 90001);
define('ARCHIVE_TAR_END_BLOCK', pack('a512', ''));

include_once __DIR__ .'/lib/Curl/Curl.php';
use Curl\Curl;

function backuply_died() {
	backuply_log(serialize(error_get_last()));
	
	$last_error = error_get_last();
		
	if(!$last_error){
		return false;
	}
	
	if(strpos($last_error['message'], 'Maximum execution time') !== FALSE){
		//backuply_log('Didnt Come inside max execution');
		backuply_status_log('The Restore Failed because the script reached Maximum Execution waiting for response from remote server', 'error');
		backuply_kill_process();
	}
}
register_shutdown_function('backuply_died');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
	backuply_die('restoreerror');
	die();
}

if(!backuply_verify_self()) {
	$GLOBALS['error'] = 'Security Check Failed!';
	backuply_die('securityerror');
	die();
}

// Update Backuply logs as per action
function backuply_log($info){
	global $data;
	
	if(empty($data['debug_mode'])){
		return;
	}
	
	$write = '';
	$write .= '['.date('Y-m-d H:i:s', time()).'] ';
	
	$write .= $info."\n\n";
	
	$log_file = dirname(__FILE__, 3).'/backuply/backups_info/debug.php';
	
	$fp = @fopen($log_file, 'ab');

	if (0 == filesize($log_file)){
		// file is empty
		@fwrite($fp, "<?php exit();?>\n");
	}
	
	@fwrite($fp, $write);
	@fclose($fp);
	
	@chmod($log_file, 0600);
}

// Returns the Security key
function backuply_get_config() {
	$config_file = dirname(__FILE__, 3) . '/backuply/backuply_config.php';
	
	if(!file_exists($config_file) || 0 == filesize($config_file)) {
		return false;
	}

	$fp = @fopen($config_file, 'r');
	@fseek($fp, 16);
	
	$content = @fread($fp, filesize($config_file));
	@fclose($fp);
	
	$config = json_decode($content, true);
	
	return $config;
}

function backuply_kill_process() {
	global $error;
	
	if(file_exists(dirname(__FILE__, 3).'/backuply/restoration/restoration.php')) {
		@unlink(dirname(__FILE__, 3).'/backuply/restoration/restoration.php');
	}
	
	restore_clean();
	$error[] = 'The Restore has been killed Successfully';
	backuply_die('restore_killed');
}

function restore_got_killed() {
	if(!file_exists(dirname(__FILE__, 3).'/backuply/restoration/restoration.php')) {
		restore_clean();
		backuply_die('got_killed');
	}
}


function backuply_preg_replace($pattern, $file, &$var, $valuenum, $stripslashes = ''){	
	preg_match($pattern, $file, $matches);
	if(empty($stripslashes)){
		$var = trim($matches[$valuenum]);
	}else{
		$var = stripslashes(trim($matches[$valuenum]));
	}
}

// Verifies the backuply key
function backuply_verify_self() {
	//backuply_log($_REQUEST['backuply_key']);
	if(empty($_REQUEST['backuply_key'])) {
		return false;
	}
	
	$config = backuply_get_config();
	
	if(!$config) {
		return false;
	}
	
	if(urldecode($_REQUEST['backuply_key']) == $config['BACKUPLY_KEY']) {
		return true;
	}
	
	return false;	
}

function backuply_inputsec($string){
	
	//get_magic_quotes_gpc is depricated in php 7.4
	if(version_compare(PHP_VERSION, '7.4', '<')){
		if(!get_magic_quotes_gpc()){
		
			$string = addslashes($string);
		
		}else{
		
			$string = stripslashes($string);
			$string = addslashes($string);
		
		}
	}else{
		$string = addslashes($string);
	}
	
	// This is to replace ` which can cause the command to be executed in exec()
	$string = str_replace('`', '\`', $string);
	
	return $string;
}

function backuply_htmlizer($string){

global $globals;

	$string = htmlentities($string, ENT_QUOTES, 'UTF-8');
	
	preg_match_all('/(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)/', $string, $matches);//backuply_print($matches);
	
	foreach($matches[1] as $mk => $mv){	 
		$tmp_m = backuply_entity_check($matches[2][$mk]);
		$string = str_replace($matches[1][$mk], $tmp_m, $string);
	}
	
	return $string;
	
}

class softtar{

	var $_tarname='';
	var $_compress=false;
	var $_compress_type='none';
	var $_separator=',';
	var $_file=0;
	var $_temp_tarname='';
	var $_ignore_regexp='';
	var $error_object=null;
	
	var $_local_tar=''; // The local file	
	var $_orig_tar=''; // The remote file
	var $remote_fp=''; // The remote file pointer	
	var $remote_fp_filter = NULL;
	var $remote_hctx = NULL;
	var $remote_content_size = 0;
	
	function __construct($p_tarname, $p_compress = null, $handle_remote = false){
		// $tmpdir is mainly used for REMOTE protocols so that we can write locally and append on the remote server
		if(preg_match('/\:\/\//', $p_tarname) && $handle_remote){
			$tmpdir = $GLOBALS['_POST']['backuly_backup_dir'].'/backups/tmp';
			$this->_orig_tar = $p_tarname;
			$p_tarname = $tmpdir.'/'.md5($p_tarname);
			
			$this->_local_tar = $p_tarname;
			$GLOBALS['local_tarname'] = $this->_local_tar;
			
		}
		
		$this->_compress = false;
		$this->_compress_type = 'none';
		if (($p_compress === null) || ($p_compress == '')) {
			if (@file_exists($p_tarname)) {
				if ($fp = @fopen($p_tarname, "rb")) {
					// look for gzip magic cookie
					$data = fread($fp, 2);
					fclose($fp);
					if ($data == "\37\213") {
						$this->_compress = true;
						$this->_compress_type = 'gz';
						// No sure it's enought for a magic code ....
					} elseif ($data == "BZ") {
						$this->_compress = true;
						$this->_compress_type = 'bz2';
					}
				}
			} else {
				// probably a remote file or some file accessible
				// through a stream interface
				if (substr($p_tarname, -2) == 'gz') {
					$this->_compress = true;
					$this->_compress_type = 'gz';
				} elseif ((substr($p_tarname, -3) == 'bz2') ||
						  (substr($p_tarname, -2) == 'bz')) {
					$this->_compress = true;
					$this->_compress_type = 'bz2';
				}
			}
		} else {
			if (($p_compress === true) || ($p_compress == 'gz')) {
				$this->_compress = true;
				$this->_compress_type = 'gz';
			} else if ($p_compress == 'bz2') {
				$this->_compress = true;
				$this->_compress_type = 'bz2';
			} else {
				$this->_error("Unsupported compression type '$p_compress'\n".
					"Supported types are 'gz' and 'bz2'.\n");
				return false;
			}
		}
		$this->_tarname = $p_tarname;
		if ($this->_compress) { // assert zlib or bz2 extension support
			if ($this->_compress_type == 'gz')
				$extname = 'zlib';
			else if ($this->_compress_type == 'bz2')
				$extname = 'bz2';

			if (!extension_loaded($extname)) {
				PEAR::loadExtension($extname);
			}
			if (!extension_loaded($extname)) {
				$this->_error("The extension '$extname' couldn't be found.\n".
					"Please make sure your version of PHP was built ".
					"with '$extname' support.\n");
				return false;
			}
		}
	}
	// }}}
	
	function __destruct(){
		$this->_softtar();
	}

	// {{{ destructor
	function _softtar(){
		$this->_close();
		// ----- Look for a local copy to delete
		if ($this->_temp_tarname != '')
			@unlink($this->_temp_tarname);
	}
	// }}}

	// {{{ create()
	function create($p_filelist){
		return $this->createModify($p_filelist, '', '');
	}
	// }}}

	// {{{ add()
	function add($p_filelist){
		return $this->addModify($p_filelist, '', '');
	}
	// }}}

	// {{{ extract()
	function extract($p_path='', $p_preserve=false){
		return $this->extractModify($p_path, '', $p_preserve);
	}
	// }}}

	// {{{ listContent()
	function listContent(){
		$v_list_detail = array();

		if ($this->_openRead()) {
			if (!$this->_extractList('', $v_list_detail, "list", '', '')) {
				unset($v_list_detail);
				$v_list_detail = 0;
			}
			$this->_close();
		}

		return $v_list_detail;
	}
	// }}}

	// {{{ createModify()
	function createModify($p_filelist, $p_add_dir, $p_remove_dir=''){
		$v_result = true;

		if (!$this->_openWrite())
			return false;

		if ($p_filelist != '') {
			if (is_array($p_filelist))
				$v_list = $p_filelist;
			elseif (is_string($p_filelist))
				$v_list = explode($this->_separator, $p_filelist);
			else {
				$this->_cleanFile();
				$this->_error('Invalid file list');
				return false;
			}

			$v_result = $this->_addList($v_list, $p_add_dir, $p_remove_dir);
		}

		if ($v_result) {
			$this->_writeFooter();
			$this->_close();
		} else
			$this->_cleanFile();

		return $v_result;
	}
	// }}}

	// {{{ addModify()
	function addModify($p_filelist, $p_add_dir, $p_remove_dir=''){
		$v_result = true;

		if (!$this->_isArchive())
			$v_result = $this->createModify($p_filelist, $p_add_dir,
											$p_remove_dir);
		else {
			if (is_array($p_filelist))
				$v_list = $p_filelist;
			elseif (is_string($p_filelist))
				$v_list = explode($this->_separator, $p_filelist);
			else {
				$this->_error('Invalid file list');
				return false;
			}

			$v_result = $this->_append($v_list, $p_add_dir, $p_remove_dir);
		}

		return $v_result;
	}
	// }}}

	// {{{ addString()
	function addString($p_filename, $p_string){
		$v_result = true;

		if (!$this->_isArchive()) {
			if (!$this->_openWrite()) {
				return false;
			}
			$this->_close();
		}

		if (!$this->_openAppend())
			return false;

		// Need to check the get back to the temporary file ? ....
		$v_result = $this->_addString($p_filename, $p_string);

		$this->_writeFooter();

		$this->_close();

		return $v_result;
	}
	// }}}

	// {{{ extractModify()
	function extractModify($p_path, $p_remove_path, $p_preserve=false){
		$v_result = true;
		$v_list_detail = array();
		
		// Download the archive if its remote
		// if(!empty($this->_orig_tar)){
			// $this->remote_archive_download_loop();
		// }

		if ($v_result = $this->_openRead()) {
			$v_result = $this->_extractList($p_path, $v_list_detail,
				'complete', 0, $p_remove_path, $p_preserve);
			$this->_close();
		}

		return $v_result;
	}
	// }}}

	// {{{ extractInString()
	function extractInString($p_filename){
		if ($this->_openRead()) {
			$v_result = $this->_extractInString($p_filename);
			$this->_close();
		} else {
			$v_result = null;
		}

		return $v_result;
	}
	// }}}

	// {{{ extractList()
	function extractList($p_filelist, $p_path='', $p_remove_path='', $p_preserve=false){
		$v_result = true;
		$v_list_detail = array();

		if (is_array($p_filelist))
			$v_list = $p_filelist;
		elseif (is_string($p_filelist))
			$v_list = explode($this->_separator, $p_filelist);
		else {
			$this->_error('Invalid string list');
			return false;
		}

		if ($v_result = $this->_openRead()) {
			$v_result = $this->_extractList($p_path, $v_list_detail, "partial",
				$v_list, $p_remove_path, $p_preserve);
			$this->_close();
		}

		return $v_result;
	}
	// }}}

	// {{{ setAttribute()
	function setAttribute(){
		$v_result = true;

		// ----- Get the number of variable list of arguments
		if (($v_size = func_num_args()) == 0) {
			return true;
		}

		// ----- Get the arguments
		$v_att_list = func_get_args();

		// ----- Read the attributes
		$i=0;
		while ($i<$v_size) {

			// ----- Look for next option
			switch ($v_att_list[$i]) {
				// ----- Look for options that request a string value
				case ARCHIVE_TAR_ATT_SEPARATOR :
					// ----- Check the number of parameters
					if (($i+1) >= $v_size) {
						$this->_error('Invalid number of parameters for '
									  .'attribute ARCHIVE_TAR_ATT_SEPARATOR');
						return false;
					}

					// ----- Get the value
					$this->_separator = $v_att_list[$i+1];
					$i++;
					break;

				default :
					$this->_error('Unknow attribute code '.$v_att_list[$i].'');
					return false;
			}

			// ----- Next attribute
			$i++;
		}

		return $v_result;
	}
	// }}}

	// {{{ setIgnoreRegexp()
	function setIgnoreRegexp($regexp){
		$this->_ignore_regexp = $regexp;
	}
	// }}}

	// {{{ setIgnoreList()
	function setIgnoreList($list){
		$regexp = str_replace(array('#', '.', '^', '$'), array('\#', '\.', '\^', '\$'), $list);
		$regexp = '#/'.join('$|/', $list).'#';
		$this->setIgnoreRegexp($regexp);
	}
	// }}}

	// {{{ _error()
	function _error($p_message){
		//we have changed this since PEAR is not used
		//$this->error_object = &$this->raiseError($p_message); 
		trigger_error($p_message, E_USER_WARNING);
	}
	// }}}

	// {{{ _warning()
	function _warning($p_message){
		//we have changed this since PEAR is not used
		//$this->error_object = &$this->raiseError($p_message); 
		trigger_error($p_message, E_USER_NOTICE);
	}
	// }}}

	// {{{ _isArchive()
	function _isArchive($p_filename=null){
		if ($p_filename == null) {
			$p_filename = $this->_tarname;
		}
		clearstatcache();
		return @is_file($p_filename) && !@is_link($p_filename);
	}
	// }}}

	// {{{ _openWrite()
	function _openWrite(){
		
		if ($this->_compress_type == 'gz' && function_exists('gzopen'))
			$this->_file = @gzopen($this->_tarname, "wb9");
		else if ($this->_compress_type == 'bz2' && function_exists('bzopen'))
			$this->_file = @bzopen($this->_tarname, "w");
		else if ($this->_compress_type == 'none')
			$this->_file = @fopen($this->_tarname, "wb");
		else
			$this->_error('Unknown or missing compression type ('
						  .$this->_compress_type.')');

		if ($this->_file == 0) {
			$this->_error('Unable to open in write mode \''
						  .$this->_tarname.'\'');
			return false;
		}

		return true;
	}
	// }}}

	// {{{ _openRead()
	function _openRead(){
		global $ftp, $can_write;
		
		if (strtolower(substr($this->_tarname, 0, 7)) == 'http://') {

		  // ----- Look if a local copy need to be done
		  if ($this->_temp_tarname == '') {
			  $this->_temp_tarname = uniqid('tar').'.tmp';
			  if (!$v_file_from = @fopen($this->_tarname, 'rb')) {
				$this->_error('Unable to open in read mode \''
							  .$this->_tarname.'\'');
				$this->_temp_tarname = '';
				return false;
			  }
			  if (!$v_file_to = @fopen($this->_temp_tarname, 'wb')) {
				$this->_error('Unable to open in write mode \''
							  .$this->_temp_tarname.'\'');
				$this->_temp_tarname = '';
				return false;
			  }
			  while ($v_data = @fread($v_file_from, 1024))
				  @fwrite($v_file_to, $v_data);
			  @fclose($v_file_from);
			  @fclose($v_file_to);
		  }

		  // ----- File to open if the local copy
		  $v_filename = $this->_temp_tarname;

		} else
		  // ----- File to open if the normal Tar file
		  $v_filename = $this->_tarname;


		if ($this->_compress_type == 'gz')
			$this->_file = @gzopen($v_filename, 'rb');
		else if ($this->_compress_type == 'bz2')
			$this->_file = @bzopen($v_filename, "r");
		else if ($this->_compress_type == 'none')
			$this->_file = @fopen($v_filename, "rb");
		else
			$this->_error('Unknown or missing compression type ('
						  .$this->_compress_type.')');

		if ($this->_file == 0) {
			$this->_error('Unable to open in read mode \''.$v_filename.'\'');
			return false;
		}

		return true;
	}
	// }}}

	// {{{ _openReadWrite()
	function _openReadWrite(){
		if ($this->_compress_type == 'gz')
			$this->_file = @gzopen($this->_tarname, "r+b");
		else if ($this->_compress_type == 'bz2') {
			$this->_error('Unable to open bz2 in read/write mode \''
						  .$this->_tarname.'\' (limitation of bz2 extension)');
			return false;
		} else if ($this->_compress_type == 'none')
			$this->_file = @fopen($this->_tarname, "r+b");
		else
			$this->_error('Unknown or missing compression type ('
						  .$this->_compress_type.')');

		if ($this->_file == 0) {
			$this->_error('Unable to open in read/write mode \''
						  .$this->_tarname.'\'');
			return false;
		}

		return true;
	}
	// }}}

	// {{{ _close()
	function _close(){
		//if (isset($this->_file)) {
		if (is_resource($this->_file)) {
			if ($this->_compress_type == 'gz')
				@gzclose($this->_file);
			else if ($this->_compress_type == 'bz2')
				@bzclose($this->_file);
			else if ($this->_compress_type == 'none')
				@fclose($this->_file);
			else
				$this->_error('Unknown or missing compression type (' . $this->_compress_type.')');

			$this->_file = 0;
		}

		// ----- Look if a local copy need to be erase
		// Note that it might be interesting to keep the url for a time : ToDo
		if ($this->_temp_tarname != '') {
			@unlink($this->_temp_tarname);
			$this->_temp_tarname = '';
		}

		return true;
	}
	// }}}

	// {{{ _cleanFile()
	function _cleanFile(){
		$this->_close();

		// ----- Look for a local copy
		if ($this->_temp_tarname != '') {
			// ----- Remove the local copy but not the remote tarname
			@unlink($this->_temp_tarname);
			$this->_temp_tarname = '';
		} else {
			// ----- Remove the local tarname file
			@unlink($this->_tarname);
		}
		$this->_tarname = '';

		return true;
	}
	// }}}

	// {{{ _writeBlock()
	function _writeBlock($p_binary_data, $p_len=null, $finished = false){
		if(is_resource($this->_file)){
			if($p_len === null){
				if ($this->_compress_type == 'gz')
					@gzputs($this->_file, $p_binary_data);
				else if ($this->_compress_type == 'bz2')
					@bzwrite($this->_file, $p_binary_data);
				else if ($this->_compress_type == 'none')
					@fputs($this->_file, $p_binary_data);
				else
					$this->_error('Unknown or missing compression type (' . $this->_compress_type.')');
			} else {
				if ($this->_compress_type == 'gz')
					@gzputs($this->_file, $p_binary_data, $p_len);
				else if ($this->_compress_type == 'bz2')
					@bzwrite($this->_file, $p_binary_data, $p_len);
				else if ($this->_compress_type == 'none')
					@fputs($this->_file, $p_binary_data, $p_len);
				else
					$this->_error('Unknown or missing compression type (' . $this->_compress_type.')');
			}
		}
		
		return true;
	}
	// }}}

	// {{{ _readBlock()
	function _readBlock(){
		$v_block = null;
		if(is_resource($this->_file)){
			if($this->_compress_type == 'gz'){
				$v_block = @gzread($this->_file, 512);
			}else if($this->_compress_type == 'bz2'){
				$v_block = @bzread($this->_file, 512);
			}else if ($this->_compress_type == 'none'){
				$cur = ftell($this->_file);
				$v_block = @fread($this->_file, 512);

				// We always need to read 512 blocks and in some cases we do not get complete 512 blocks due to network issues
				// This is a fix to retry if we did not get 512 blocks
				$retry = 0;
				$read = strlen($v_block); // How much did we read ? 
				while($read != 512 && !feof($this->_file)){
					if($retry >= 3){ // We can try max for 3 times
						break;
					}
					$toread = 512 - $read; // How many blocks did we miss ? 
					$tmp_block = @fread($this->_file, $toread); // Read the missing blocks
					$v_block .= $tmp_block; // Add it to the existing content
					$read = strlen($v_block); // Update the length of updated content
					$retry++;
				}
			}else{
				$this->_error('Unknown or missing compression type (' . $this->_compress_type.')');
			}
		}
		return $v_block;
	}
	// }}}

	// {{{ _jumpBlock()
	function _jumpBlock($p_len=null){
		if(is_resource($this->_file)){
			if ($p_len === null)
				$p_len = 1;

			if($this->_compress_type == 'gz'){
				@gzseek($this->_file, gztell($this->_file)+($p_len*512));
			}
			else if($this->_compress_type == 'bz2'){
				// ----- Replace missing bztell() and bzseek()
				for ($i=0; $i<$p_len; $i++)
					$this->_readBlock();
			} else if ($this->_compress_type == 'none')
				@fseek($this->_file, $p_len*512, SEEK_CUR);
			else
				$this->_error('Unknown or missing compression type (' . $this->_compress_type.')');
		}
		return true;
	}
	// }}}

	// {{{ _writeFooter()
	function _writeFooter(){
		if(is_resource($this->_file)){
			// ----- Write the last 0 filled block for end of archive
			$v_binary_data = pack('a1024', '');
			$this->_writeBlock($v_binary_data);
		}
		return true;
	}
	// }}}

	// {{{ _addList()
	function _addList($p_list, $p_add_dir, $p_remove_dir){
		$v_result=true;
		$v_header = array();

		// ----- Remove potential windows directory separator
		$p_add_dir = $this->_translateWinPath($p_add_dir);
		$p_remove_dir = $this->_translateWinPath($p_remove_dir, false);

		if(!$this->_file){
			$this->_error('Invalid file descriptor');
			return false;
		}

		if(sizeof($p_list) == 0)
			return true;

		foreach($p_list as $v_filename){
			if (!$v_result) {
				break;
			}

			// ----- Skip the current tar name
			if ($v_filename == $this->_tarname)
				continue;

			if ($v_filename == '')
				continue;

			// ----- ignore files and directories matching the ignore regular expression
			if($this->_ignore_regexp && preg_match($this->_ignore_regexp, '/'.$v_filename)){
				$this->_warning("File '$v_filename' ignored");
				continue;
			}

			if(!file_exists($v_filename) && !is_link($v_filename)){
				$this->_warning("File '$v_filename' does not exist");
				continue;
			}

			// ----- Add the file or directory header
			if (!$this->_addFile($v_filename, $v_header, $p_add_dir, $p_remove_dir))
				return false;

			if (@is_dir($v_filename) && !@is_link($v_filename)) {
				if(!($p_hdir = opendir($v_filename))){
					$this->_warning("Directory '$v_filename' can not be read");
					continue;
				}
				
				while(false !== ($p_hitem = readdir($p_hdir))){
					if(($p_hitem != '.') && ($p_hitem != '..')){
						if ($v_filename != ".")
							$p_temp_list[0] = $v_filename.'/'.$p_hitem;
						else
							$p_temp_list[0] = $p_hitem;

						$v_result = $this->_addList($p_temp_list, $p_add_dir, $p_remove_dir);
					}
				}

				unset($p_temp_list);
				unset($p_hdir);
				unset($p_hitem);
			}
		}

		return $v_result;
	}
	// }}}

	// {{{ _addFile()
	function _addFile($p_filename, &$p_header, $p_add_dir, $p_remove_dir){
		if(!$this->_file){
			$this->_error('Invalid file descriptor');
			return false;
		}

		if($p_filename == ''){
			$this->_error('Invalid file name');
			return false;
		}

		// ----- Calculate the stored filename
		$p_filename = $this->_translateWinPath($p_filename, false);
		$v_stored_filename = $p_filename;
		if(strcmp($p_filename, $p_remove_dir) == 0){
			return true;
		}
		if ($p_remove_dir != ''){
			if(substr($p_remove_dir, -1) != '/')
				$p_remove_dir .= '/';

			if(substr($p_filename, 0, strlen($p_remove_dir)) == $p_remove_dir)
				$v_stored_filename = substr($p_filename, strlen($p_remove_dir));
		}
		
		$v_stored_filename = $this->_translateWinPath($v_stored_filename);
		if ($p_add_dir != '') {
			if (substr($p_add_dir, -1) == '/')
				$v_stored_filename = $p_add_dir.$v_stored_filename;
			else
				$v_stored_filename = $p_add_dir.'/'.$v_stored_filename;
		}

		$v_stored_filename = $this->_pathReduction($v_stored_filename);

		if($this->_isArchive($p_filename)){
			if (($v_file = @fopen($p_filename, "rb")) == 0) {
			$this->_warning("Unable to open file '".$p_filename
						  ."' in binary read mode");
			return true;
			}

			if(!$this->_writeHeader($p_filename, $v_stored_filename))
				return false;
		
			while(($v_buffer = fread($v_file, 512)) != ''){
				$v_binary_data = pack("a512", "$v_buffer");
				$this->_writeBlock($v_binary_data);
			}

			fclose($v_file);

		} else {
			// ----- Only header for dir
			if (!$this->_writeHeader($p_filename, $v_stored_filename))
				return false;
		}

		return true;
	}
	// }}}

	// {{{ _addString()
	function _addString($p_filename, $p_string){
		if(!$this->_file){
			$this->_error('Invalid file descriptor');
			return false;
		}

		if($p_filename == ''){
			$this->_error('Invalid file name');
			return false;
		}

		// ----- Calculate the stored filename
		$p_filename = $this->_translateWinPath($p_filename, false);

		if (!$this->_writeHeaderBlock($p_filename, strlen($p_string), time(), 384, "", 0, 0))
			return false;

		$i = 0;
		while(($v_buffer = substr($p_string, (($i++)*512), 512)) != ''){
			$v_binary_data = pack("a512", $v_buffer);
			$this->_writeBlock($v_binary_data);
		}

		return true;
	}
	// }}}

	// {{{ _writeHeader()
	function _writeHeader($p_filename, $p_stored_filename){
		
		if($p_stored_filename == '')
			$p_stored_filename = $p_filename;
		$v_reduce_filename = $this->_pathReduction($p_stored_filename);

		$v_reduce_filename = str_replace($GLOBALS['replace']['from'], $GLOBALS['replace']['to'], $v_reduce_filename);

		//echo $v_reduce_filename."<br />";

		if(strlen($v_reduce_filename) > 99){
			if(!$this->_writeLongHeader($v_reduce_filename))
				return false;
		}

		$v_info = lstat($p_filename);
		$v_uid = sprintf("%07s", DecOct($v_info[4]));
		$v_gid = sprintf("%07s", DecOct($v_info[5]));
		$v_perms = sprintf("%07s", DecOct($v_info['mode'] & 000777));

		$v_mtime = sprintf("%011s", DecOct($v_info['mtime']));

		$v_linkname = '';

		if(@is_link($p_filename)){
			$v_typeflag = '2';
			$v_linkname = readlink($p_filename);
			$v_size = sprintf("%011s", DecOct(0));
		} elseif (@is_dir($p_filename)){
			$v_typeflag = "5";
			$v_size = sprintf("%011s", DecOct(0));
		} else {
			$v_typeflag = '0';
			clearstatcache();
			$v_size = sprintf("%011s", DecOct($v_info['size']));
		}

		// We have to write the entries for datadir permissions softdatadir/softperms.txt
		if(isset($GLOBALS['bfh']['softperms']) && preg_match('/'.preg_quote($GLOBALS['replace']['from']['softpath'], '/').'/is', $p_filename)){
			fwrite($GLOBALS['bfh']['softperms'], trim($v_reduce_filename, '/')." ". (substr(sprintf('%o', fileperms($p_filename)), -4)) ."\n");
		}

		$v_magic = 'ustar ';

		$v_version = ' ';

		if (function_exists('posix_getpwuid')){
			$userinfo = posix_getpwuid($v_info[4]);
			$groupinfo = posix_getgrgid($v_info[5]);

			$v_uname = $userinfo['name'];
			$v_gname = $groupinfo['name'];
		} else {
			$v_uname = '';
			$v_gname = '';
		}

		$v_devmajor = '';

		$v_devminor = '';

		$v_prefix = '';

		$v_binary_data_first = pack("a100a8a8a8a12a12",
								$v_reduce_filename, $v_perms, $v_uid,
								$v_gid, $v_size, $v_mtime);
		$v_binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12",
								$v_typeflag, $v_linkname, $v_magic,
								$v_version, $v_uname, $v_gname,
								$v_devmajor, $v_devminor, $v_prefix, '');

		// ----- Calculate the checksum
		$v_checksum = 0;
		// ..... First part of the header
		for ($i=0; $i<148; $i++)
			$v_checksum += ord(substr($v_binary_data_first,$i,1));
		// ..... Ignore the checksum value and replace it by ' ' (space)
		for ($i=148; $i<156; $i++)
			$v_checksum += ord(' ');
		// ..... Last part of the header
		for ($i=156, $j=0; $i<512; $i++, $j++)
			$v_checksum += ord(substr($v_binary_data_last,$j,1));

		// ----- Write the first 148 bytes of the header in the archive
		$this->_writeBlock($v_binary_data_first, 148);

		// ----- Write the calculated checksum
		$v_checksum = sprintf("%06s ", DecOct($v_checksum));
		$v_binary_data = pack("a8", $v_checksum);
		$this->_writeBlock($v_binary_data, 8);

		// ----- Write the last 356 bytes of the header in the archive
		$this->_writeBlock($v_binary_data_last, 356);

		return true;
	}
	// }}}

	// {{{ _writeHeaderBlock()
	function _writeHeaderBlock($p_filename, $p_size, $p_mtime=0, $p_perms=0, $p_type='', $p_uid=0, $p_gid=0){
		$p_filename = $this->_pathReduction($p_filename);

		if(strlen($p_filename) > 99){
			if (!$this->_writeLongHeader($p_filename))
				return false;
		}

		if($p_type == "5"){
			$v_size = sprintf("%011s", DecOct(0));
		} else {
			$v_size = sprintf("%011s", DecOct($p_size));
		}

		$v_uid = sprintf("%07s", DecOct($p_uid));
		$v_gid = sprintf("%07s", DecOct($p_gid));
		$v_perms = sprintf("%07s", DecOct($p_perms & 000777));

		$v_mtime = sprintf("%11s", DecOct($p_mtime));

		$v_linkname = '';

		$v_magic = 'ustar ';

		$v_version = ' ';

		if(function_exists('posix_getpwuid')){
			$userinfo = posix_getpwuid($p_uid);
			$groupinfo = posix_getgrgid($p_gid);

			$v_uname = $userinfo['name'];
			$v_gname = $groupinfo['name'];
		}else{
			$v_uname = '';
			$v_gname = '';
		}
		
		$v_devmajor = '';

		$v_devminor = '';

		$v_prefix = '';

		$v_binary_data_first = pack("a100a8a8a8a12A12",
									$p_filename, $v_perms, $v_uid, $v_gid,
									$v_size, $v_mtime);
		$v_binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12",
								   $p_type, $v_linkname, $v_magic,
								   $v_version, $v_uname, $v_gname,
								   $v_devmajor, $v_devminor, $v_prefix, '');

		// ----- Calculate the checksum
		$v_checksum = 0;
		// ..... First part of the header
		for ($i=0; $i<148; $i++)
			$v_checksum += ord(substr($v_binary_data_first,$i,1));
		// ..... Ignore the checksum value and replace it by ' ' (space)
		for ($i=148; $i<156; $i++)
			$v_checksum += ord(' ');
		// ..... Last part of the header
		for ($i=156, $j=0; $i<512; $i++, $j++)
			$v_checksum += ord(substr($v_binary_data_last,$j,1));

		// ----- Write the first 148 bytes of the header in the archive
		$this->_writeBlock($v_binary_data_first, 148);

		// ----- Write the calculated checksum
		$v_checksum = sprintf("%06s ", DecOct($v_checksum));
		$v_binary_data = pack("a8", $v_checksum);
		$this->_writeBlock($v_binary_data, 8);

		// ----- Write the last 356 bytes of the header in the archive
		$this->_writeBlock($v_binary_data_last, 356);

		return true;
	}
	// }}}

	// {{{ _writeLongHeader()
	function _writeLongHeader($p_filename){
		$v_size = sprintf("%11s ", DecOct(strlen($p_filename)));

		$v_typeflag = 'L';

		$v_linkname = '';

		$v_magic = '';

		$v_version = '';

		$v_uname = '';

		$v_gname = '';

		$v_devmajor = '';

		$v_devminor = '';

		$v_prefix = '';

		$v_binary_data_first = pack("a100a8a8a8a12a12",
									'././@LongLink', 0, 0, 0, $v_size, 0);
		$v_binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12",
								   $v_typeflag, $v_linkname, $v_magic,
								   $v_version, $v_uname, $v_gname,
								   $v_devmajor, $v_devminor, $v_prefix, '');

		// ----- Calculate the checksum
		$v_checksum = 0;
		// ..... First part of the header
		for ($i=0; $i<148; $i++)
			$v_checksum += ord(substr($v_binary_data_first,$i,1));
		// ..... Ignore the checksum value and replace it by ' ' (space)
		for ($i=148; $i<156; $i++)
			$v_checksum += ord(' ');
		// ..... Last part of the header
		for ($i=156, $j=0; $i<512; $i++, $j++)
			$v_checksum += ord(substr($v_binary_data_last,$j,1));

		// ----- Write the first 148 bytes of the header in the archive
		$this->_writeBlock($v_binary_data_first, 148);

		// ----- Write the calculated checksum
		$v_checksum = sprintf("%06s ", DecOct($v_checksum));
		$v_binary_data = pack("a8", $v_checksum);
		$this->_writeBlock($v_binary_data, 8);

		// ----- Write the last 356 bytes of the header in the archive
		$this->_writeBlock($v_binary_data_last, 356);

		// ----- Write the filename as content of the block
		$i=0;
		while (($v_buffer = substr($p_filename, (($i++)*512), 512)) != '') {
			$v_binary_data = pack("a512", "$v_buffer");
			$this->_writeBlock($v_binary_data);
		}

		return true;
	}
	// }}}

	// {{{ _readHeader()
	function _readHeader($v_binary_data, &$v_header){
		if (strlen($v_binary_data)==0) {
			$v_header['filename'] = '';
			return true;
		}

		if (strlen($v_binary_data) != 512) {
			$v_header['filename'] = '';
			$this->_error('Invalid block size : '.strlen($v_binary_data));
			return false;
		}

		if (!is_array($v_header)) {
			$v_header = array();
		}
		// ----- Calculate the checksum
		$v_checksum = 0;
		// ..... First part of the header
		for ($i=0; $i<148; $i++)
			$v_checksum+=ord(substr($v_binary_data,$i,1));
		// ..... Ignore the checksum value and replace it by ' ' (space)
		for ($i=148; $i<156; $i++)
			$v_checksum += ord(' ');
		// ..... Last part of the header
		for ($i=156; $i<512; $i++)
		   $v_checksum+=ord(substr($v_binary_data,$i,1));

		if (version_compare(PHP_VERSION, "5.5.0-dev") < 0) {
			$fmt = "a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/" .
				"a8checksum/a1typeflag/a100link/a6magic/a2version/" .
				"a32uname/a32gname/a8devmajor/a8devminor/a131prefix";
		} else {
			$fmt = "Z100filename/Z8mode/Z8uid/Z8gid/Z12size/Z12mtime/" .
				"Z8checksum/Z1typeflag/Z100link/Z6magic/Z2version/" .
				"Z32uname/Z32gname/Z8devmajor/Z8devminor/Z131prefix";
		}
		$v_data = unpack($fmt, $v_binary_data);
						 
		if (strlen($v_data["prefix"]) > 0) {
			$v_data["filename"] = "$v_data[prefix]/$v_data[filename]";
		}

		// ----- Extract the checksum
		$v_header['checksum'] = OctDec(trim($v_data['checksum']));
		if ($v_header['checksum'] != $v_checksum) {
			$v_header['filename'] = '';

			// ----- Look for last block (empty block)
			if (($v_checksum == 256) && ($v_header['checksum'] == 0))
				return true;
			
			// There was a bug in the tar created by Softaculous where it did not set the correct filesize of softperms.txt causing the tar to read incomplete data and reading the remaining data as header for next file
			if(preg_match('/softperms\.txt/is', $v_data["filename"])){
				$v_binary_data = $this->_readBlock();
				if(strlen($v_binary_data) != 0){
					return $this->_readHeader($v_binary_data, $v_header);
				}else{
					// We have reached end of file softperms.txt was the last file
					return true;
				}
			}

			$this->_error('Invalid checksum for file "'.$v_data['filename']
						  .'" : '.$v_checksum.' calculated, '
						  .$v_header['checksum'].' expected');
			return false;
		}

		// ----- Extract the properties
		$v_header['filename'] = $v_data['filename'];
		if ($this->_maliciousFilename($v_header['filename'])) {
			$this->_error('Malicious .tar detected, file "' . $v_header['filename'] .
				'" will not install in desired directory tree');
			return false;
		}
		$v_header['mode'] = OctDec(trim($v_data['mode']));
		$v_header['uid'] = OctDec(trim($v_data['uid']));
		$v_header['gid'] = OctDec(trim($v_data['gid']));
		$v_header['size'] = OctDec(trim($v_data['size']));
		$v_header['mtime'] = OctDec(trim($v_data['mtime']));
		if (($v_header['typeflag'] = $v_data['typeflag']) == "5") {
		  $v_header['size'] = 0;
		}
		$v_header['link'] = trim($v_data['link']);

		return true;
	}
	// }}}

	// {{{ _maliciousFilename()
	function _maliciousFilename($file){
		if(strpos($file, '/../') !== false){
			return true;
		}
		if (strpos($file, '../') === 0) {
			return true;
		}
		return false;
	}
	// }}}

	// {{{ _readLongHeader()
	function _readLongHeader(&$v_header){
		$v_filename = '';
		$n = floor($v_header['size']/512);
		for($i=0; $i<$n; $i++){
			$v_content = $this->_readBlock();
			$v_filename .= $v_content;
		}
		if(($v_header['size'] % 512) != 0){
			$v_content = $this->_readBlock();
			$v_filename .= trim($v_content);
		}

		// ----- Read the next header
		$v_binary_data = $this->_readBlock();

		if(!$this->_readHeader($v_binary_data, $v_header))
			return false;

		$v_filename = trim($v_filename);
		$v_header['filename'] = $v_filename;
		if($this->_maliciousFilename($v_filename)){
			$this->_error('Malicious .tar detected, file "' . $v_filename . '" will not install in desired directory tree');
			return false;
		}

		return true;
	}
	// }}}

	// {{{ _extractInString()
	function _extractInString($p_filename){
		$v_result_str = "";

		while(strlen($v_binary_data = $this->_readBlock()) != 0){
			if(!$this->_readHeader($v_binary_data, $v_header))
				return null;

			if($v_header['filename'] == '')
				continue;

			// ----- Look for long filename
			if($v_header['typeflag'] == 'L'){
				if(!$this->_readLongHeader($v_header))
				return null;
			}

			if($v_header['filename'] == $p_filename){
				if($v_header['typeflag'] == "5"){
					$this->_error('Unable to extract in string a directory ' . 'entry {'.$v_header['filename'].'}');
					return null;
				} else {
					$n = floor($v_header['size']/512);
					for($i=0; $i<$n; $i++){
						$v_result_str .= $this->_readBlock();
					}
					if(($v_header['size'] % 512) != 0){
						$v_content = $this->_readBlock();
						$v_result_str .= substr($v_content, 0, ($v_header['size'] % 512));
					}
					return $v_result_str;
				}
			}else{
				$this->_jumpBlock(ceil(($v_header['size']/512)));
			}
		}

		return null;
	}
	// }}}

	// {{{ _extractList()
	function _extractList($p_path, &$p_list_detail, $p_mode, $p_file_list, $p_remove_path, $p_preserve=false){
		global $globals, $ftp, $can_write, $data;
		
		$v_result=true;
		$v_nb = 0;
		$v_extract_all = true;
		$v_listing = false;

		$p_path = $this->_translateWinPath($p_path, false);
		if($p_path == '' || (substr($p_path, 0, 1) != '/'
		&& substr($p_path, 0, 3) != '../' && !strpos($p_path, ':'))){
			$p_path = './'.$p_path;
		}
		
		$p_remove_path = $this->_translateWinPath($p_remove_path);

		// ----- Look for path to remove format (should end by /)
		if(($p_remove_path != '') && (substr($p_remove_path, -1) != '/'))
			$p_remove_path .= '/';
		$p_remove_path_size = strlen($p_remove_path);
		
		switch($p_mode){
			case 'complete' :
				$v_extract_all = true;
				$v_listing = false;
				break;
			case 'partial' :
				$v_extract_all = false;
				$v_listing = false;
				break;
			case 'list' :
				$v_extract_all = false;
				$v_listing = true;
				break;
			default :
				$this->_error('Invalid extract mode ('.$p_mode.')');
				return false;
		}

		clearstatcache();

		while(strlen($v_binary_data = $this->_readBlock()) != 0){
			$v_extract_file = FALSE;
			$wildcard_list = FALSE;

			$v_extraction_stopped = 0;

			if(!$this->_readHeader($v_binary_data, $v_header))
				return false;

			if($v_header['filename'] == ''){
				continue;
			}

			// ----- Look for long filename
			if($v_header['typeflag'] == 'L'){
				if(!$this->_readLongHeader($v_header))
					return false;
			}

			$last_file = $v_header['filename'];

			// check last file and skip the files that have been already backed up...
			if(!empty($GLOBALS['last_file']) && $GLOBALS['start'] == 0){
				if(preg_match('#^'.$GLOBALS['last_file'].'$#', $v_header['filename'])){
					$GLOBALS['start'] = 1; // give a jump start once the last backed up file is found..
				}
				$this->_jumpBlock(ceil(($v_header['size']/512)));
				continue; //return true to skip files
			}
			
			// Exclude Wp-Config when we are migrating to other site location
			// if($v_header['filename'] == 'wp-config.php' && !empty($data['is_migrating'])){
				// $this->_jumpBlock(ceil(($v_header['size']/512)));
				// continue; //return true to skip files
			// }
			
			// Skip the plugin itself
			if(preg_match('/'.preg_quote('wp-content/plugins/backuply', '/').'/is', $v_header['filename'])){
				$this->_jumpBlock(ceil(($v_header['size']/512)));
				continue; //return true to skip files
			}

			if((!$v_extract_all) && (is_array($p_file_list))){
				// ----- By default no unzip if the file is not found
				$v_extract_file = false;

				for($i=0; $i<sizeof($p_file_list); $i++){
					// ----- Look if it is a directory
					if (substr($p_file_list[$i], -1) == '/'){
						// ----- Look if the directory is in the filename path
						if ((strlen($v_header['filename']) > strlen($p_file_list[$i]))
						&& (substr($v_header['filename'], 0, strlen($p_file_list[$i]))
						== $p_file_list[$i])){
							$v_extract_file = true;
							break;
						}
					}
					//----------- It is a directory specified with the pattern dir/*
					elseif(substr($p_file_list[$i], -1) == '*'){
						if((strlen($v_header['filename']) >= (strlen($p_file_list[$i]) - 1))
						&& (substr($v_header['filename'], 0, (strlen($p_file_list[$i]) - 1))
						== substr($p_file_list[$i], 0, (strlen($p_file_list[$i]) - 1)))){
							$wildcard_list = true;
							$v_extract_file = true;
							break;
						}
					}

					// ----- It is a file, so compare the file names
					elseif($p_file_list[$i] == $v_header['filename']){
						$v_extract_file = true;
						break;
					}
				}
			} else {
				$v_extract_file = true;
			}

			// ----- Look if this file need to be extracted
			if(($v_extract_file) && (!$v_listing)){
				if(($p_remove_path != '') && (substr($v_header['filename'], 0, $p_remove_path_size) == $p_remove_path))
					$v_header['filename'] = substr($v_header['filename'], $p_remove_path_size);
				if(($p_path != './') && ($p_path != '/')){
					while(substr($p_path, -1) == '/')
						$p_path = substr($p_path, 0, strlen($p_path)-1);

					if(substr($v_header['filename'], 0, 1) == '/')
						$v_header['filename'] = $p_path.$v_header['filename'];
					else
						$v_header['filename'] = $p_path.'/'.$v_header['filename'];
				}
				if(file_exists($v_header['filename'])){
					if((@is_dir($v_header['filename'])) && ($v_header['typeflag'] == '')){
						$this->_error('File '.$v_header['filename'] .' already exists as a directory');
						return false;
					}
					if(($this->_isArchive($v_header['filename'])) && ($v_header['typeflag'] == "5")){
						$this->_error('Directory '.$v_header['filename'] .' already exists as a file');
						return false;
					}
					if(!is_writeable($v_header['filename'])){
						//We cannot use $globals['ofc'] here and after restoring the files we are anyways changing the file's permissions according to the perms file. Therefore, using 0644/0755 directly here shouldn't be an issue.

						if(!empty($can_write)){
							if(is_dir($v_header['filename'])){
								$chmod = chmod($v_header['filename'], 0755);
							}else{
								$chmod = chmod($v_header['filename'], 0644);
							}

							//The is_writable function always returns false for non-suphp servers and we are passing the FTP stream path which gives us writable permission
							if(!is_writeable($v_header['filename'])){
								$this->_error('File '.$v_header['filename'] .' already exists and is write protected');
								return false;
							}
						}else{
							if(is_dir($v_header['filename'])){
								$chmod = @chmod($v_header['filename'], $globals['odc']);
							}else{
								$chmod = @chmod($v_header['filename'], $globals['ofc']);
							}
						}
					}
					if(filemtime($v_header['filename']) > $v_header['mtime']){
					// To be completed : An error or silent no replace ?
					}
				}

				// ----- Check the directory availability and create it if necessary
				elseif(($v_result
				= $this->_dirCheck(($v_header['typeflag'] == "5"
				?$v_header['filename']
				:dirname($v_header['filename'])))) != 1){
					$this->_error('Unable to create path for '.$v_header['filename']);
					return false;
				}

				if($v_extract_file){
					if($v_header['typeflag'] == '5'){
						if(mt_rand(0,1) == 1){
							backuply_status_log('Extracting (L'.$data['restore_loop'].'): '. $v_header['filename'], 'extracting', 61);
						}
						
						if(!@file_exists($v_header['filename'])){
							if(!@mkdir($v_header['filename'], 0777)){
								$this->_error('Unable to create directory {' . $v_header['filename'].'}');
								return false;
							}
						}
					} elseif ($v_header['typeflag'] == '2'){
						if(@file_exists($v_header['filename'])){
							@unlink($v_header['filename']);
						}
						// Symlinks will be created by us using softperms.txt
						/*if (!@symlink($v_header['link'], $v_header['filename'])) {
						$this->_error('Unable to extract symbolic link {'
						.$v_header['filename'].'}');
						return false;
						}*/
					} else {
						
						if(empty($can_write) && preg_match('/^(ftp:\/\/)/is', $v_header['filename'])){
							// Allows overwriting of existing files on the remote FTP server
							$stream_options = array('ftp' => array('overwrite' => true));

							// Creates a stream context resource with the defined options
							$stream_context = stream_context_create($stream_options);

							// Opens the file for writing and truncates it to zero length
							$v_dest_file = fopen($v_header['filename'], "wb", 0, $stream_context);

						}else{
							$v_dest_file = fopen($v_header['filename'], "wb");
						}

						if($v_dest_file == 0){
							$this->_error('Error while opening {'.$v_header['filename'] . '} in write binary mode');
							return false;
						} else {
							$n = floor($v_header['size']/512);
							for($i=0; $i<$n; $i++){
								$v_content = $this->_readBlock();
								fwrite($v_dest_file, $v_content, 512);
							}
							if(($v_header['size'] % 512) != 0){
								$v_content = $this->_readBlock();
								fwrite($v_dest_file, $v_content, ($v_header['size'] % 512));
							}

							@fclose($v_dest_file);

							if($p_preserve){
								@chown($v_header['filename'], $v_header['uid']);
								@chgrp($v_header['filename'], $v_header['gid']);
							}

							// ----- Change the file mode, mtime
							@touch($v_header['filename'], $v_header['mtime']);
							if($v_header['mode'] & 0111){
								// make file executable, obey umask
								$mode = fileperms($v_header['filename']) | (~umask() & 0111);
								@chmod($v_header['filename'], $mode);
							}
						}

						// ----- Check the file size
						clearstatcache();
						if(!is_file($v_header['filename'])){
							$this->_error('Extracted file '.$v_header['filename'] . 'does not exist. Archive may be corrupted.');
							return false;
						}

						$filesize = filesize($v_header['filename']);
						if($filesize != $v_header['size']){
							$this->_error('Extracted file '.$v_header['filename']
							.' does not have the correct file size \''
							.$filesize
							.'\' ('.$v_header['size']
							.' expected). Archive may be corrupted.');
							return false;
						}
					}
				} else {
					$this->_jumpBlock(ceil(($v_header['size']/512)));
				}
			} else {
				$this->_jumpBlock(ceil(($v_header['size']/512)));
			}

			if($v_listing || $v_extract_file || $v_extraction_stopped){
				// ----- Log extracted files
				if(($v_file_dir = dirname($v_header['filename']))
				== $v_header['filename'])
					$v_file_dir = '';
				if((substr($v_header['filename'], 0, 1) == '/') && ($v_file_dir == ''))
					$v_file_dir = '/';

				// Only if we are to return the list i.e. in listContent() then we fill full $v_header else we just need the count
				$p_list_detail[$v_nb++] = (!empty($v_listing) ? $v_header : '');
				if(is_array($p_file_list) && empty($wildcard_list) && (count($p_list_detail) == count($p_file_list))){
					return true;
				}
			}

			// We can run the scripts for the end time already set
			if(time() >= $GLOBALS['end']){
				$GLOBALS['end_file'] = $last_file; // set end file so that we know where to start from
				break;
			}
		}
		return true;
	}
	// }}}

	// {{{ _openAppend()
	function _openAppend(){
		if(filesize($this->_tarname) == 0)
			return $this->_openWrite();

		if($this->_compress){
			$this->_close();

			if(!@rename($this->_tarname, $this->_tarname.".tmp")){
				$this->_error('Error while renaming \''.$this->_tarname
							  .'\' to temporary file \''.$this->_tarname
							  .'.tmp\'');
				return false;
			}

			if($this->_compress_type == 'gz')
				$v_temp_tar = @gzopen($this->_tarname.".tmp", "rb");
			elseif($this->_compress_type == 'bz2')
				$v_temp_tar = @bzopen($this->_tarname.".tmp", "r");

			if($v_temp_tar == 0){
				$this->_error('Unable to open file \''.$this->_tarname
							  .'.tmp\' in binary read mode');
				@rename($this->_tarname.".tmp", $this->_tarname);
				return false;
			}

			if(!$this->_openWrite()){
				@rename($this->_tarname.".tmp", $this->_tarname);
				return false;
			}

			if($this->_compress_type == 'gz'){
				$end_blocks = 0;
				
				while(!@gzeof($v_temp_tar)){
					$v_buffer = @gzread($v_temp_tar, 512);
					if($v_buffer == ARCHIVE_TAR_END_BLOCK || strlen($v_buffer) == 0){
						$end_blocks++;
						// do not copy end blocks, we will re-make them
						// after appending
						continue;
					}elseif($end_blocks > 0){
						for($i = 0; $i < $end_blocks; $i++){
							$this->_writeBlock(ARCHIVE_TAR_END_BLOCK);
						}
						$end_blocks = 0;
					}
					$v_binary_data = pack("a512", $v_buffer);
					$this->_writeBlock($v_binary_data);
				}

				@gzclose($v_temp_tar);
			}
			elseif($this->_compress_type == 'bz2'){
				$end_blocks = 0;
				
				while(strlen($v_buffer = @bzread($v_temp_tar, 512)) > 0){
					if($v_buffer == ARCHIVE_TAR_END_BLOCK || strlen($v_buffer) == 0){
						$end_blocks++;
						// do not copy end blocks, we will re-make them
						// after appending
						continue;
					}elseif($end_blocks > 0){
						for($i = 0; $i < $end_blocks; $i++){
							$this->_writeBlock(ARCHIVE_TAR_END_BLOCK);
						}
						$end_blocks = 0;
					}
					$v_binary_data = pack("a512", $v_buffer);
					$this->_writeBlock($v_binary_data);
				}

				@bzclose($v_temp_tar);
			}

			if(!@unlink($this->_tarname.".tmp")){
				$this->_error('Error while deleting temporary file \''
							  .$this->_tarname.'.tmp\'');
			}

		}else{
			// ----- For not compressed tar, just add files before the last
			//	   one or two 512 bytes block
			if(!$this->_openReadWrite())
			   return false;

			clearstatcache();
			$v_size = filesize($this->_tarname);

			// We might have zero, one or two end blocks.
			// The standard is two, but we should try to handle
			// other cases.
			fseek($this->_file, $v_size - 1024);
			if(fread($this->_file, 512) == ARCHIVE_TAR_END_BLOCK){
				fseek($this->_file, $v_size - 1024);
			}
			elseif(fread($this->_file, 512) == ARCHIVE_TAR_END_BLOCK){
				fseek($this->_file, $v_size - 512);
			}
		}

		return true;
	}
	// }}}

	// {{{ _append()
	function _append($p_filelist, $p_add_dir='', $p_remove_dir=''){
		if(!$this->_openAppend())
			return false;

		if($this->_addList($p_filelist, $p_add_dir, $p_remove_dir))
		   $this->_writeFooter();

		$this->_close();

		return true;
	}
	// }}}

	// {{{ _dirCheck()
	function _dirCheck($p_dir){
		clearstatcache();
		if((@is_dir($p_dir)) || ($p_dir == ''))
			return true;

		$p_parent_dir = dirname($p_dir);

		if(($p_parent_dir != $p_dir) &&
			($p_parent_dir != '') &&
			(!$this->_dirCheck($p_parent_dir)))
				return false;

		if(!@mkdir($p_dir, 0777)){
			$this->_error("Unable to create directory '$p_dir'");
			return false;
		}

		return true;
	}

	// }}}

	// {{{ _pathReduction()
	function _pathReduction($p_dir){
		$v_result = '';

		// ----- Look for not empty path
		if($p_dir != ''){
			// ----- Explode path by directory names
			$v_list = explode('/', $p_dir);

			// ----- Study directories from last to first
			for($i=sizeof($v_list)-1; $i>=0; $i--){
				// ----- Look for current path
				if($v_list[$i] == "."){
					// ----- Ignore this directory
					// Should be the first $i=0, but no check is done
				}
				else if($v_list[$i] == ".."){
					// ----- Ignore it and ignore the $i-1
					$i--;
				}
				else if(($v_list[$i] == '')
						 && ($i!=(sizeof($v_list)-1))
						 && ($i!=0)) {
					// ----- Ignore only the double '//' in path,
					// but not the first and last /
				}else{
					$v_result = $v_list[$i].($i!=(sizeof($v_list)-1)?'/'
								.$v_result:'');
				}
			}
		}
		
		if(defined('OS_WINDOWS') && OS_WINDOWS){
			$v_result = strtr($v_result, '\\', '/');
		}
		
		return $v_result;
	}

	// }}}

	// {{{ _translateWinPath()
	function _translateWinPath($p_path, $p_remove_disk_letter=true){
		if(defined('OS_WINDOWS') && OS_WINDOWS){
			// ----- Look for potential disk letter
			if(($p_remove_disk_letter)
			&& (($v_position = strpos($p_path, ':')) != false)){
				$p_path = substr($p_path, $v_position+1);
			}
			// ----- Change potential windows directory separator
			if((strpos($p_path, '\\') > 0) || (substr($p_path, 0,1) == '\\')){
				$p_path = strtr($p_path, '\\', '/');
			}
		}
		
		return $p_path;
	}
	// }}}
}

function cleanpath($path){	
	$path = str_replace('\\\\', '/', $path);
	$path = str_replace('\\', '/', $path);
	return rtrim($path, '/');
}

function backuply_can_create_file(){
	$file = dirname(__FILE__).'/soft.tmp';
	$fp = @fopen($file, 'wb');
	if($fp === FALSE){
		return false;
	}
	
	if(@fwrite($fp, 'backuply') === FALSE){
		return false;
	}
	
	@fclose($fp);
	
	// Check if the file exists
	if(file_exists($file)){
		@unlink($file);
		return true;
	}
	
	return false;	
}

/**
 * This function will import the huge database files (.sql) into database.
 *
 * @package		restore
 * @author		Chirag Nagda
 * @param		string $import_file Name of the file with full path
 * @param		resource $conn It is resouceID of the database connection
 * @return		bool true/false On success TRUE otherwise FALSE
 * @since		4.1.5
 */
function backuply_import($import_file, $conn){
	
	global $import_handle, $read_limit, $error, $offset, $finished;
	
	$buffer = '';
	// Defaults for parser
	$offset = isset($GLOBALS['import_offset']) ?  $GLOBALS['import_offset'] : 0;
	$sql = '';
	$start_pos = isset($GLOBALS['import_spos']) ?  $GLOBALS['import_spos'] : 0;
	$i = isset($GLOBALS['import_spos']) ?  $GLOBALS['import_spos'] : 0;
	$len = isset($GLOBALS['import_len']) ?  $GLOBALS['import_len'] : 0;
	$big_value = 2147483647;
	$delimiter_keyword = 'DELIMITER '; // include the space because it's mandatory
	$sql_delimiter = ';';
	
	$import_handle = @fopen($import_file, 'r');	
	@fseek($import_handle, $offset);

	// We can not read all at once, otherwise we can run out of memory
	$memory_limit = trim(@ini_get('memory_limit'));
	
	// 2 MB as default
	if (empty($memory_limit)) {
		$memory_limit = 2 * 1024 * 1024;
	}
	// In case no memory limit we work on 10MB chunks
	if ($memory_limit == -1) {
		$memory_limit = 10 * 1024 * 1024;
	}
	
	// Calculate value of the limit
	if (strtolower(substr($memory_limit, -1)) == 'm') {
		$memory_limit = (int)substr($memory_limit, 0, -1) * 1024 * 1024;
	} elseif (strtolower(substr($memory_limit, -1)) == 'k') {
		$memory_limit = (int)substr($memory_limit, 0, -1) * 1024;
	} elseif (strtolower(substr($memory_limit, -1)) == 'g') {
		$memory_limit = (int)substr($memory_limit, 0, -1) * 1024 * 1024 * 1024;
	} else {
		$memory_limit = (int)$memory_limit;
	}
	
	$read_limit = $memory_limit / 8; // Just to be sure, there might be lot of memory needed for uncompression
	
	$finished = false; //will be set in backuply_PMA_importGetNextChunk()

	while (!($finished && $i >= $len)) {
		
		// Check for php timeout
		if(time() + 5 >= $GLOBALS['end']){
			$GLOBALS['import_len'] = $len;
			$GLOBALS['import_offset'] = $offset - $len;
			$GLOBALS['import_spos'] = $start_pos;
			$GLOBALS['status'] = 1;
			backuply_die('INCOMPLETE');
			return;
		}

		$data = backuply_PMA_importGetNextChunk();
		
		if ($data === false) {
			// subtract data we didn't handle yet and stop processing
			$offset -= strlen($buffer);
			break;
		} elseif ($data === true) {
			// Handle rest of buffer
		} else {
			// Append new data to buffer
			$buffer .= $data;
			
			// free memory
			unset($data);
			// Do not parse the string unless we are at the end and have ; inside
			if ((strpos($buffer, $sql_delimiter, $i) === false) && !$finished) {
				continue;
			}
		}
		// Current length of our buffer
		$len = strlen($buffer);
		
		// Grab some SQL queries out of it
		while ($i < $len) {
			$found_delimiter = false;
			// Find first interesting character
			$old_i = $i;
			// this is about 7 times faster that looking for each sequence i
			// one by one with strpos()
			if (preg_match('/(\'|"|#|-- |\/\*|`|(?i)(?<![A-Z0-9_])' . $delimiter_keyword . ')/', $buffer, $matches, PREG_OFFSET_CAPTURE, $i)) {
				// in $matches, index 0 contains the match for the complete
				// expression but we don't use it
				$first_position = $matches[1][1];
			} else {
				$first_position = $big_value;
			}
			/**
			 * @todo we should not look for a delimiter that might be
			 *	   inside quotes (or even double-quotes)
			 */
			// the cost of doing this one with preg_match() would be too high
			$first_sql_delimiter = strpos($buffer, $sql_delimiter, $i);
			if ($first_sql_delimiter === false) {
				$first_sql_delimiter = $big_value;
			} else {
				$found_delimiter = true;
			}
	
			// set $i to the position of the first quote, comment.start or delimiter found
			$i = min($first_position, $first_sql_delimiter);
	
			if ($i == $big_value) {
				// none of the above was found in the string
	
				$i = $old_i;
				if (!$finished) {
					break;
				}
				// at the end there might be some whitespace...
				if (trim($buffer) == '') {
					$buffer = '';
					$len = 0;
					break;
				}
				// We hit end of query, go there!
				$i = strlen($buffer) - 1;
			}
	
			// Grab current character
			$ch = $buffer[$i];
	
			// Quotes
			if (strpos('\'"`', $ch) !== false) {
				$quote = $ch;
				$endq = false;

				while (!$endq) {
					
					// Find next quote
					$pos = strpos($buffer, $quote, $i + 1);
					/*
					 * Behave same as MySQL and accept end of query as end of backtick.
					 * I know this is sick, but MySQL behaves like this:
					 *
					 * SELECT * FROM `table
					 *
					 * is treated like
					 *
					 * SELECT * FROM `table`
					 */
					if ($pos === false && $quote == '`' && $found_delimiter) {
						$pos = $first_sql_delimiter - 1;
					// No quote? Too short string
					} elseif ($pos === false) {
						// We hit end of string => unclosed quote, but we handle it as end of query
						if ($finished) {
							$endq = true;
							$i = $len - 1;
						}
						$found_delimiter = false;
						break;
					}
					// Was not the quote escaped?
					$j = $pos - 1;
					while ($buffer[$j] == '\\') $j--;
					// Even count means it was not escaped
					$endq = (((($pos - 1) - $j) % 2) == 0);
					// Skip the string
					$i = $pos;
	
					if ($first_sql_delimiter < $pos) {
						$found_delimiter = false;
					}
				}
				if (!$endq) {
					break;
				}
				$i++;
				// Aren't we at the end?
				if ($finished && $i == $len) {
					$i--;
				} else {
					continue;
				}
			}
	
			// Not enough data to decide
			if ((($i == ($len - 1) && ($ch == '-' || $ch == '/'))
			  || ($i == ($len - 2) && (($ch == '-' && $buffer[$i + 1] == '-')
				|| ($ch == '/' && $buffer[$i + 1] == '*')))) && !$finished) {
				break;
			}
	
			// Comments
			if ($ch == '#'
			 || ($i < ($len - 1) && $ch == '-' && $buffer[$i + 1] == '-'
			  && (($i < ($len - 2) && $buffer[$i + 2] <= ' ')
			   || ($i == ($len - 1)  && $finished)))
			 || ($i < ($len - 1) && $ch == '/' && $buffer[$i + 1] == '*')
					) {
				// Copy current string to SQL
				if ($start_pos != $i) {
					$sql .= substr($buffer, $start_pos, $i - $start_pos);
				}
				// Skip the rest
				$start_of_comment = $i;
				// do not use PHP_EOL here instead of "\n", because the export
				// file might have been produced on a different system
				$i = strpos($buffer, $ch == '/' ? '*/' : "\n", $i);
				// didn't we hit end of string?
				if ($i === false) {
					if ($finished) {
						$i = $len - 1;
					} else {
						break;
					}
				}
				// Skip *
				if ($ch == '/') {
					$i++;
				}
				// Skip last char
				$i++;
				// We need to send the comment part in case we are defining
				// a procedure or function and comments in it are valuable
				$sql .= substr($buffer, $start_of_comment, $i - $start_of_comment);
				// Next query part will start here
				$start_pos = $i;
				// Aren't we at the end?
				if ($i == $len) {
					$i--;
				} else {
					continue;
				}
			}
			
			// Change delimiter, if redefined, and skip it (don't send to server!)
			if (strtoupper(substr($buffer, $i, 10)) == $delimiter_keyword
			 && ($i + 10 < $len)) {
				 // look for EOL on the character immediately after 'DELIMITER '
				 // (see previous comment about PHP_EOL)
			   $new_line_pos = strpos($buffer, "\n", $i + 10);
			   // it might happen that there is no EOL
			   if (false === $new_line_pos) {
				   $new_line_pos = $len;
			   }
			   $sql_delimiter = substr($buffer, $i + 10, $new_line_pos - $i - 10);
			   $i = $new_line_pos + 1;
			   // Next query part will start here
			   $start_pos = $i;
			   continue;
			}
	
			// End of SQL
			if ($found_delimiter || ($finished && ($i == $len - 1))) {
				$tmp_sql = $sql;
				if ($start_pos < $len) {
					$length_to_grab = $i - $start_pos;
	
					if (! $found_delimiter) {
						$length_to_grab++;
					}
					$tmp_sql .= substr($buffer, $start_pos, $length_to_grab);
					unset($length_to_grab);
				}
				// Do not try to execute empty SQL
				if (! preg_match('/^([\s]*;)*$/', trim($tmp_sql))) {
					
					$sql = update_urls_in_db($tmp_sql);
					
					$res = backuply_mysql_query($sql, $conn);
					if(!$res){
						//$error[] = backuply_mysql_error($conn);
						backuply_status_log('<strong style="color:orange;">Warning:</strong> ' . backuply_mysql_error($conn), 'warning');
						//return false;
					}
					//PMA_importRunQuery($sql, substr($buffer, 0, $i + strlen($sql_delimiter)));
					$buffer = substr($buffer, $i + strlen($sql_delimiter));
					// Reset parser:
					$len = strlen($buffer);
					$sql = '';
					$i = 0;
					$start_pos = 0;
					// Any chance we will get a complete query?
					//if ((strpos($buffer, ';') === false) && !$finished) {
					if ((strpos($buffer, $sql_delimiter) === false) && !$finished) {
						break;
					}
				} else {
					$i++;
					$start_pos = $i;
				}
			}
		} // End of parser loop
	} // End of import loop
	return true;
} // End of backuply_import


/**
 * Returns next part of imported file/buffer
 *
 * @param int  $size  size of buffer to read (this is maximal size function will return)
 * @return string part of file/buffer
 * @access public
 */
function backuply_PMA_importGetNextChunk($size = 32768)
{
	global $compression, $import_handle, $charset_conversion, $charset_of_file,$read_multiply, $read_limit, $offset, $finished;
		
	$read_multiply = 1;
	
	// Add some progression while reading large amount of data
	if ($read_multiply <= 8) {
		$size *= $read_multiply;
	} else {
		$size *= 8;
	}

	$read_multiply++;

	// We can not read too much
	if ($size > $read_limit) {
		$size = $read_limit;
	}

	if ($finished) {
		return true;
	}

  	$result = fread($import_handle, $size);
	$finished = feof($import_handle);
	$offset += $size;

	/**
	 * Skip possible byte order marks (I do not think we need more
	 * charsets, but feel free to add more, you can use wikipedia for
	 * reference: <http://en.wikipedia.org/wiki/Byte_Order_Mark>)
	 *
	 * @todo BOM could be used for charset autodetection
	 */
	if ($offset == $size) {
		// UTF-8
		if (strncmp($result, "\xEF\xBB\xBF", 3) == 0) {
			$result = substr($result, 3);
		// UTF-16 BE, LE
		} elseif (strncmp($result, "\xFE\xFF", 2) == 0 || strncmp($result, "\xFF\xFE", 2) == 0) {
			$result = substr($result, 2);
		}
	}
	
	return $result;
}

function untar_archive($tarname, $untar_path, $file_list = array(), $handle_remote = false){
	global $globals, $can_write, $ftp;
	
	// Create directory if not there
	if(!is_dir($untar_path)){
		@mkdir($untar_path);
	}
	$tar_archive = new softtar($tarname, '', $handle_remote);
	
	if(empty($file_list)){
		$res = $tar_archive->extractModify($untar_path, '');
	}else{
		$res = $tar_archive->extractList($file_list, $untar_path);
	}
	
	if(!$res){
		return false;	
	}
	
	return true;	
}

function backuply_optPOST($name, $default = ''){

global $error;

	//Check the GETED NAME was GETed
	if(isset($_POST[$name])){
	
		return $_POST[$name];
		
	}else{
		
		return $default;

	}
}

function backuply_entity_check($string){
	
	//Convert Hexadecimal to Decimal
	$num = ((substr($string, 0, 1) === 'x') ? hexdec(substr($string, 1)) : (int) $string);
	
	//Squares and Spaces - return nothing 
	$string = (($num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num < 0x20) ? '' : '&#'.$num.';');
	
	return $string;
}

function backuply_rmdir_recursive_fn($path){
	
	$path = (substr($path, -1) == '/' || substr($path, -1) == '\\' ? $path : $path.'/');
	
	backuply_resetfilelist();
	
	$files = backuply_filelist_fn($path, 1, 0, 'all');
	$files = (!is_array($files) ? array() : $files);
	
	//First delete the files only
	foreach($files as $k => $v){
		@chmod($k, 0777);
		if(file_exists($k) && is_file($k) && @filetype($k) == "file"){
			@unlink($k);
		}
	}
	
	@clearstatcache();
	
	$folders = backuply_filelist_fn($path, 1, 1, 'all');
	$folders = (!is_array($folders) ? array() : $folders);
	@krsort($folders);

	//Now Delete the FOLDERS
	foreach($folders as $k => $v){
		@chmod($k, 0777);
		if(is_dir($k)){
			@rmdir($k);
		}
	}
	
	@rmdir($path);
	
	@clearstatcache();
}

function backuply_filelist_fn($startdir="./", $searchSubdirs=1, $directoriesonly=0, $maxlevel="all", $level=1, $reset = 1) {
   //list the directory/file names that you want to ignore
   $ignoredDirectory[] = ".";
   $ignoredDirectory[] = "..";
   $ignoredDirectory[] = "_vti_cnf";
   global $directorylist;	//initialize global array
   
   if(substr($startdir, -1) != '/'){
		$startdir = $startdir.'/';
	}
   
   if (is_dir($startdir)) {
	   if ($dh = opendir($startdir)) {
		   while (($file = readdir($dh)) !== false) {
			   if (!(array_search($file,$ignoredDirectory) > -1)) {
				 if (@filetype($startdir . $file) == "dir") {
					 
					   //build your directory array however you choose;
					   //add other file details that you want.
					   
					   $directorylist[$startdir . $file]['level'] = $level;
					   $directorylist[$startdir . $file]['dir'] = 1;
					   $directorylist[$startdir . $file]['name'] = $file;
					   $directorylist[$startdir . $file]['path'] = $startdir;
					   if ($searchSubdirs) {
						   if ((($maxlevel) == "all") or ($maxlevel > $level)) {
							   backuply_filelist_fn($startdir . $file . "/", $searchSubdirs, $directoriesonly, $maxlevel, ($level + 1), 0);
						   }
					   }
					  
					   
				   } else {
					   if (!$directoriesonly) {
						 
					  //  echo substr(strrchr($file, "."), 1);
						   //if you want to include files; build your file array 
						   //however you choose; add other file details that you want.
						 $directorylist[$startdir . $file]['level'] = $level;
						 $directorylist[$startdir . $file]['dir'] = 0;
						 $directorylist[$startdir . $file]['name'] = $file;
						 $directorylist[$startdir . $file]['path'] = $startdir;
						  
					 
	 }}}}
		   closedir($dh);
	}}

	if(!empty($reset)){
		$r = $directorylist;
		$directorylist = array();
		return($r);
	}
}


function backuply_resetfilelist(){
global $directorylist;
	$directorylist = array();
}

function backuply_die($txt, $l_file = '', $backuly_backup_dir = ''){
	
	global $data, $can_write;
	
	$array = array();
	$array['result'] = $txt;
	$array['data'] = $GLOBALS['data'];
	
	$globals = ['l_readbytes', 'import_i', 'import_len', 'import_offset', 'status', 'current_status', 'import_spos', 'part_no'];
	
	// Updating data with $GLOBALS
	foreach($globals as $global){
		if(!empty($GLOBALS[$global])){
			$data[$global] = $GLOBALS[$global];
		}
	}
	
	// Add last backed up file to the array if the process is still INCOMPLETE
	if(!empty($l_file)){
		$array['l_file'] = $l_file;
	}
	
	// Send the current status of the operation performed according to which the next operation will be performed.
	if(!empty($GLOBALS['current_status'])){
		$array['current_status'] = $GLOBALS['current_status'];
	}
	
	// Was there an error ?
	if(!empty($GLOBALS['error'])){
		$array['local_tarname'] = $data['local_tar'];
		$array['restore_error'] = $GLOBALS['error'];
		//backuply_log(' restore error : '. var_export($array['restore_error'], 1));
		
		restore_clean(1);
		restore_curl($array);
		die();
	}
	
	if($txt == 'DONE'){
		$array = array();
		$array['status'] = 0;
		$array['backuly_backup_dir'] = $backuly_backup_dir;
		$array['softpath'] = $data['softpath'];
		$array['fname'] = $data['fname'];
		$array['dbexist'] = $data['dbexist'];
		$array['is_migrating'] = $data['is_migrating'];
		$data = $array;

		restore_clean(1);
		restore_curl($data);
		die();
	}
	
	// In case the restore is incomplete we call clean without force so it will delete files that are not required anymore
	//restore_clean();
	
	restore_curl($data);
}

// Copy from source to destination
function backuply_copydir_fn($source, $destination){
	
	$source = (substr($source, -1) == '/' || substr($source, -1) == '\\' ? $source : $source.'/');
	$destination = (substr($destination, -1) == '/' || substr($destination, -1) == '\\' ? $destination : $destination.'/');
	$source_ = substr($source, 0, -1);
	$destination_ = substr($destination, 0, -1);
	
	if(!is_dir($destination)){
		mkdir($destination);
	}
	
	backuply_resetfilelist();	
	$files = backuply_filelist_fn($source, 1, 1, 'all');
	$files = (!is_array($files) ? array() : $files);
	
	// Make the folders
	foreach($files as $k => $v){
		mkdir(str_replace($source_, $destination_, $k), $globals['dirchmod'], 1);
		@chmod(str_replace($source_, $destination_, $k), fileperms($k));
	}
	
	@clearstatcache();	
	backuply_resetfilelist();
	
	$files = backuply_filelist_fn($source, 1, 0, 'all');
	$files = (!is_array($files) ? array() : $files);
	
	// Copy the files
	foreach($files as $k => $v){
		if(file_exists($k) && is_file($k) && @filetype($k) == "file"){
 			if(!empty($GLOBALS['last_file']) && $GLOBALS['start'] == 0){
				if(preg_match('#^'.$GLOBALS['last_file'].'$#', $k)){
					$GLOBALS['start'] = 1; // give a jump start once the last backed up file is found..
				}
				
				continue; //return true to skip files	
			}  
			
			copy($k, str_replace($source_, $destination_, $k));
			@chmod(str_replace($source_, $destination_, $k), fileperms($k));
			
			// We can run the scripts for the end time already set
			if(time() >= $GLOBALS['end']){ 
				$GLOBALS['end_file'] = $last_file; // set end file so that we know where to start from
				break;
			}  
		}
	}
	
	@clearstatcache();	
	backuply_resetfilelist();
	
	return true;
}

function backuply_mysql_connect($host, $user, $pass, $newlink = false){
	
	if(extension_loaded('mysqli')){
		//echo 'mysqli';
		//To handle connection if user passes a custom port along with the host as localhost:6446
		$exh = explode(':', $host);
		if(!empty($exh[1])){
			$sconn = @mysqli_connect($exh[0], $user, $pass, '', $exh[1]);
		}else{
			$sconn = @mysqli_connect($host, $user, $pass);
		}
	}else{
		//echo 'mysql';
		$sconn = @mysql_connect($host, $user, $pass, $newlink);
	}
	
	return $sconn;
}

function backuply_mysql_select_db($db, $conn){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_select_db($conn, $db);
	}else{
		$return = @mysql_select_db($db, $conn);
	}
	
	return $return;
}

function backuply_mysql_query($query, $conn){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_query($conn, $query);
	}else{
		$return = @mysql_query($query, $conn);
	}
	
	return $return;
}

function backuply_mysql_error($conn){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_error($conn);
		
		// In mysqli if connection  is not made then we will get connection error using the following function.
		if(empty($conn)){
			$return = @mysqli_connect_error();
		}
		
	}else{
		$return = @mysql_error($conn);
	}
	
	return $return;
}

function backuply_mysql_num_rows($result){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_num_rows($result);
	}else{
		$return = @mysql_num_rows($result);
	}
	
	return $return;
}

function backuply_mysql_fetch_array($result){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_fetch_array($result);
	}else{
		$return = @mysql_fetch_array($result);
	}
	
	return $return;
}

function backuply_stream_wrapper_register($protocol, $classname){
	
	$protocols = array('dropbox', 'aws', 'gdrive', 'softftpes', 'softsftp', 'ftp', 'webdav', 'onedrive');
	
	if(!in_array($protocol, $protocols)){
		return true;
	}
	
	backuply_include_lib($protocol);

	if(!stream_wrapper_register($protocol, $classname)){
		return false;
	}
	
	return true;
}

function backuply_include_lib($protocol) {
	if(!class_exists($protocol)){
		if(file_exists(__DIR__ .'/lib/'.$protocol.'.php')) {
			include_once(__DIR__ .'/lib/'.$protocol.'.php');
			return;
		}
		
		if(file_exists(__DIR__ . '/lib/premium/' .$protocol . '.php')) {
			include_once(__DIR__ . '/lib/premium/' .$protocol . '.php');
			return;
		}
		
		return false;
	}
	
	return true;
}

function soft_preg_replace($pattern, $file, &$var, $valuenum, $stripslashes = ''){	
	preg_match($pattern, $file, $matches);
	if(empty($stripslashes)){
		$var = @trim($matches[$valuenum]);
	}else{
		$var = @stripslashes(trim($matches[$valuenum]));
	}
}

function backuply_print($array){

	echo '<pre>';
	print_r($array);
	echo '</pre>';

}

// Create or updates the log file
function backuply_status_log($log, $status = 'working', $percentage = 0 ){
	$log_file = dirname(__FILE__, 3).'/backuply/backuply_log.php';
		
	$logs = [];
	
	$file = file($log_file);
	if(0 == filesize($log_file)) {
		$log = "<?php exit();?>\n" . $log; //Prepend php exit
	}
	
	$this_log = $log . '|' . $status . '|' . $percentage . "\n";
	
	file_put_contents($log_file, $this_log, FILE_APPEND);
}

function restore_clean($force = 0){
	global $data;
	
	//Delete the temporarily downloaded archive if some error occur in the restore
	if($GLOBALS['current_status'] >= 4 || !empty($force)){
		@unlink($data['local_tar']);
	}
	
	// Delete these always, we will unzip these files everytime
	@unlink($data['softpath'].'/softperms.txt');
	@unlink($data['softpath'].'/softver.txt');
	
	//Delete the softsql.sql file
	if((file_exists($data['softpath'].'/'.$data['dbexist']) && $GLOBALS['current_status'] >= 2) || !empty($force)){
		
		if(file_exists($data['softpath'].'/'.$data['dbexist']) && !is_dir($data['softpath'].'/'.$data['dbexist'])){
			@unlink($data['softpath'].'/'.$data['dbexist']);
		}
		
		if(file_exists($data['softpath'].'/'.$data['fname']) && !is_dir($data['softpath'].'/'.$data['fname'])){
			@unlink($data['softpath'].'/'.$data['fname']);
		}
	}
	
	if(file_exists($data['backuly_backup_dir'].'/restoration/restoration.php')) {
		@unlink($data['backuly_backup_dir'].'/restoration/restoration.php');
	}
	
	if(is_dir($data['backuly_backup_dir'].'/restoration')) {
		@rmdir($data['backuly_backup_dir'].'/restoration');
	}
	
}

function handle_restore_status($output) {
	
	if(!empty($output['restore_error'])){
		// Send restore failure mail
		$error = $output['restore_error'];

		$error_string = '<b>Below are the error(s) :</b> <br />';

		foreach($error as $ek => $ev){
			$error_string .= '* '.$ev.'<br />';
		}
		
		backuply_status_log($error_string, 'info');
		backuply_status_log('Restore of your WordPress installation failed.', 'error', 100);
		restore_clean(1);
		final_restore_response($output, $error_string);
		
		return false;
	}
	
	if($output['status'] == 0) {
		backuply_log('Restore complete called !');
		final_restore_response($output, '');
	}
}

function final_restore_response($output, $error_str = '') {
	global $data;
	
	$config = backuply_get_config();
	$url = $output['ajax_url'];

	if(empty($config['BACKUPLY_KEY'])) {
		backuply_status_log('Unable to find security key', 'error');
		backuply_kill_process();
		return;
	}

	$url .= '?action=backuply_restore_response&security='. $config['BACKUPLY_KEY'].'&user_id='.$output['user_id']. '&sess_key='.$output['sess_key'];
	if(!empty($output['restore_db'])){
		$url .= '&restore_db=true';
	}
	
	if(!empty($output['is_migrating'])){
		$url .= '&is_migrating=true';
	}

	if(!empty($error_str)) {
		$url .= '&error=1&error_string=' . htmlentities($error_str);
	}
	
	$curl = new Curl();

	$curl->setConnectTimeout(5);
	$curl->setTimeout(2);
	$curl->setOpt(CURLOPT_SSL_VERIFYPEER, FALSE);
	$curl->setOpt(CURLOPT_SSL_VERIFYHOST, FALSE);
	$curl->get($url);

	die();
}

// CURL call to call self to prevent timeout
function restore_curl($data) {
	$config = backuply_get_config();

	if(empty($config['BACKUPLY_KEY'])) {
		backuply_kill_process();
		return;
	}
	
	$data['backuply_key'] = urlencode($config['BACKUPLY_KEY']);
	$data['site_url'] = backuply_optPOST('site_url');
	$data['backup_site_url'] = backuply_optPOST('backup_site_url');
	$data['backup_site_path'] = backuply_optPOST('backup_site_path');
	$data['ajax_url'] = backuply_optPOST('ajax_url');
	$data['backuly_backup_dir'] = backuply_optPOST('backuly_backup_dir');
	$data['restore_db'] = backuply_optPOST('restore_db');
	$data['debug_mode'] = backuply_optPOST('debug_mode');
	$data['sess_key'] = backuply_optPOST('sess_key');
	$data['user_id'] = backuply_optPOST('user_id');
	$data['exclude_db'] = backuply_optPOST('exclude_db');
	
	handle_restore_status($data);
	
	$curl = new Curl();
	
	$curl->setConnectTimeout(5);
	$curl->setTimeout(2);
	$curl->setOpt(CURLOPT_SSL_VERIFYPEER, FALSE);
	$curl->setOpt(CURLOPT_SSL_VERIFYHOST, FALSE);
	
	$curl->post(backuply_optPOST('restore_curl_url'), $data);	
	
	die();
}

// Download the remote archive
function remote_archive_download_loop(){
	global $data;

	// Do we have a remote file ?
	if(empty($data['remote_tar'])){
		return false;
	}
	
	clearstatcache();
	restore_got_killed(); //stops process if restore gets killed

	// For untar and backward compatibiility of .tar files
	// if(substr($this->_orig_tar, -2) == 'gz'){
		// $this->_compress = true;
		// $this->_compress_type = 'gz';
	// }
	
	$url = parse_url($data['remote_tar']);
	
	if(!class_exists($url['scheme'])){
		backuply_include_lib($url['scheme']);
		backuply_stream_wrapper_register($url['scheme'], $url['scheme']);
	}
	
	//Create tmp directory if not present.
	$localdir = pathinfo($data['local_tar'], PATHINFO_DIRNAME);
	if(!is_dir($localdir)){
		//create recursively
		@mkdir($localdir, 0755, 1);
	}
		
	// Lets ensure file data is proper
	if($data['restore_loop'] == 1){
		// $data['size'] = filesize($data['remote_tar']);
		// backuply_log('Size : ' . $data['size']);
		@unlink($data['local_tar']);
		$data['l_readbytes'] = 0;
	}
	
	if(empty($data['l_readbytes']) && $data['restore_loop'] == 1) {
		backuply_status_log('Downloading the backup(' . $data['fname'] . ') from remote location.', 'info', 13);
	}
	
	if(method_exists($url['scheme'], 'download_file_loop')){
		
		$obj = new $url['scheme'];
		
		//Delete the local file if the process is starting afresh and the file already exists
		if(file_exists($data['local_tar']) && empty($data['l_readbytes'])){
			@unlink($data['local_tar']);
		}
		
		//backuply_log('invoked download function, org_tar : '.$this->_orig_tar.' , local : '.$this->_local_tar);
		$obj->download_file_loop($data['remote_tar'], $data['local_tar'], $data['l_readbytes']);
		
	}else{
		
		// Open the file pointer if not opened
		$remote_fp = @fopen($data['remote_tar'], 'rb');
		$fp = @fopen($data['local_tar'], 'ab');
		$GLOBALS['l_readbytes'] = $data['l_readbytes'];
		
		//backuply_log($data['remote_tar']);
		
		if(empty($remote_fp)){
			$error[] = 'Unable to open remote file for reading';
			backuply_log('Unable to open remote file for reading');
			backuply_die('download_error');
		}
		
		$meta = stream_get_meta_data($remote_fp);
		$chunk = 1048576;
		
		// Seek to the location
		if(!fseek($remote_fp, $GLOBALS['l_readbytes'])){
			$error[] = 'Unable to seek file pointer';
			backuply_die('download_error');
		}
		
		while($GLOBALS['l_readbytes'] < $data['size']){
			restore_got_killed();
			
			if(time() + 5 >= $GLOBALS['end']){
				$GLOBALS['l_readbytes'] = filesize($data['local_tar']);
				break;
			}
			
			if(($data['size'] - $GLOBALS['l_readbytes']) < $chunk){
				$chunk = (int) $data['size'] - $GLOBALS['l_readbytes'];
				backuply_log('Last Chunk '. $chunk);
			}

			// Read a block
			$block = fread($remote_fp, $chunk);

			$GLOBALS['l_readbytes'] += strlen($block);
			
			backuply_log('Downloaded (L'.$data['restore_loop'].') : '.$GLOBALS['l_readbytes'].' / '.$data['size']);	
			
			// Write the block to the local file
			fwrite($fp, $block);
		
			$percentage = (filesize($data['local_tar']) / $data['size']) * 100;

			backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'uploading', 22);
			
		}
		
		// Close
		@fclose($remote_fp);
		@fclose($fp);
	}
	
	backuply_log('File Size ---->' . $data['size']);
	
	if($GLOBALS['l_readbytes'] <= $data['size']){
		$data['status'] = 1;
		backuply_die('INCOMPLETE');
		die();
	} else {
		backuply_status_log('TAR has been successfully downloaded, now we will untar the file', 'info', 20);
	}
}

// Updates current time
function update_active_time($bak_dir) {
	
	$ret = touch($bak_dir . '/restoration/restoration.php');

	if($ret === FALSE) {
		$error[] = 'Unable to create a restore session';
		backuply_die('restoreerror');
	}
}

// Updates Site Config file if its migration
function updating_config_file(){
	
	global $data;
	
	if(!file_exists($data['softpath'] . '/wp-config.php')){
		backuply_log('Updating Wp-Config File: Unable to find wp-config.php');
		return false;
	}
	
	if(!is_writable($data['softpath'] . '/wp-config.php')){
		backuply_log('Updating Wp-Config File: wp-config.php is not writable!');
		return false;
	}

	$config_cont = file_get_contents($data['softpath'] . '/wp-config.php');
	
	$replace_list = [
		'DB_NAME' => $data['softdb'],
		'DB_USER' => $data['softdbuser'],
		'DB_PASSWORD' => $data['softdbpass'],
		'DB_HOST' => $data['softdbhost']
	];
	
	$matches = [];
	
	foreach($replace_list as $con => $val){
		preg_match_all('/\ndefine\((\s*?)("|\')'.preg_quote($con).'("|\')(\s*?),(\s*?)("|\')(.*?)("|\')(\s*?)\);/is', $config_cont, $match);
		$replacement = str_replace($match[7], $val, $match[0]);

		$config_cont = str_replace($match[0], $replacement, $config_cont);
		
	}

	file_put_contents($data['softpath'] . '/wp-config.php', $config_cont);
	
	return true;
}

function update_urls_in_db($sql){
	global $data;
	
	// We dont need to change the url if it's just a restore
	if(empty($data['is_migrating'])){
		return $sql;
	}
	
	// What is the data to be replaced ?		
	$replace_data[$data['backup_site_path']] = $data['softpath'];
	
	if(preg_match('/^http:\/\/www\./is', $data['backup_site_url'])){
		$source_url =  preg_replace('/^http:\/\/www\./is', '', $data['backup_site_url']);
	}elseif(preg_match('/^http:\/\//is', $data['backup_site_url'])){
		$source_url =  preg_replace('/^http:\/\//is', '', $data['backup_site_url']);
	}elseif(preg_match('/^https:\/\/www\./is', $data['backup_site_url'])){
		$source_url =  preg_replace('/^https:\/\/www\./is', '', $data['backup_site_url']);
	}elseif(preg_match('/^https:\/\//is', $data['backup_site_url'])){
		$source_url =  preg_replace('/^https:\/\//is', '', $data['backup_site_url']);
	}
	
	$replace_data['http://'.$source_url] = $data['site_url'];
	$replace_data['http://www.'.$source_url] = $data['site_url'];
	$replace_data['https://'.$source_url] = $data['site_url'];
	$replace_data['https://www.'.$source_url] = $data['site_url'];
	$replace_data[$data['backup_site_url']] = $data['site_url'];
	
	if(preg_match('/^http:\/\/www\./is', $data['site_url'])){
		$dest_url =  preg_replace('/^http:\/\/www\./is', '', $data['site_url']);
	}elseif(preg_match('/^http:\/\//is', $data['site_url'])){
		$dest_url =  preg_replace('/^http:\/\//is', '', $data['site_url']);
	}elseif(preg_match('/^https:\/\/www\./is', $data['site_url'])){
		$dest_url =  preg_replace('/^https:\/\/www\./is', '', $data['site_url']);
	}elseif(preg_match('/^https:\/\//is', $data['site_url'])){
		$dest_url =  preg_replace('/^https:\/\//is', '', $data['site_url']);
	}
	
	$replace_data['//www.'.$source_url] = '//www.'.$dest_url;
	$replace_data['//'.$source_url] = '//'.$dest_url;
	
	//Replace encoded softurls
	$replace_data[rawurlencode('http://'.$source_url)] = rawurlencode($data['site_url']);
	$replace_data[rawurlencode('http://www.'.$source_url)] = rawurlencode($data['site_url']);
	$replace_data[rawurlencode('https://'.$source_url)] = rawurlencode($data['site_url']);
	$replace_data[rawurlencode('https://www.'.$source_url)] = rawurlencode($data['site_url']);
	
	//If enabled then it will replace all directory characters in database e.g if user has installation in subdir e. "/a" then the anchor and every word which has "a" will be replaced in database.
	//$replace_data[$data['relativeurl']] = $__settings['relativeurl'];

	// Just to be safe
	foreach($replace_data as $rk => $rv){
		if(empty($rk) || empty($rv)){
			unset($replace_data[$rk]);
		}

		$sql = preg_replace('/'.preg_quote($rk, '/').'/im', $rv, $sql);
	}
	
	return $sql;
	
}

#####################################################
# RESTORE LOGIC STARTS HERE !
#####################################################
	
global $globals, $error, $can_write, $ftp;

$data = array();
$data['restore_dir'] = backuply_optPOST('restore_dir');
$data['restore_db'] = backuply_optPOST('restore_db');
$data['backup_dir'] = backuply_optPOST('backup_dir');
$data['exclude_db'] = backuply_optPOST('exclude_db');
$data['fname'] = backuply_optPOST('fname');
$data['softpath'] = backuply_optPOST('softpath'); 
$data['softdbhost'] = backuply_optPOST('softdbhost');
$data['softdbuser'] = backuply_optPOST('softdbuser');
$data['softdbpass'] = backuply_optPOST('softdbpass');
$data['softdb'] = backuply_optPOST('softdb');
$data['dbexist'] = backuply_optPOST('dbexist');
$data['soft_version'] = backuply_optPOST('soft_version');
$data['plugin_dir'] = backuply_optPOST('plugin_dir');
$data['backuly_backup_dir'] = backuply_optPOST('backuly_backup_dir');
$data['l_readbytes'] = backuply_optPOST('l_readbytes');
$data['size'] = backuply_optPOST('size');
$data['restore_loop'] = (empty(backuply_optPOST('restore_loop'))) ? 1 : ( (int) backuply_optPOST('restore_loop') + 1);
$data['site_url'] = backuply_optPOST('site_url');
$data['backup_site_url'] = backuply_optPOST('backup_site_url');
$data['backup_site_path'] = backuply_optPOST('backup_site_path');
$data['tbl_prefix'] = backuply_optPOST('tbl_prefix');
$data['db_prefix'] = backuply_optPOST('db_prefix');
$data['restore_curl_url'] = backuply_optPOST('restore_curl_url');
$data['ajax_url'] = backuply_optPOST('ajax_url');
$data['is_migrating'] = false;
$data['debug_mode'] = backuply_optPOST('debug_mode');
$data['sess_key'] = backuply_optPOST('sess_key');
$data['user_id'] = backuply_optPOST('user_id');
$data['part_no'] = backuply_optPOST('part_no');

// For DB restore loop
if(!empty($data['restore_db'])){
	$GLOBALS['import_i'] = !empty(backuply_optPOST('import_i')) ? backuply_optPOST('import_i') : 0;
	$GLOBALS['import_len'] = !empty(backuply_optPOST('import_len')) ? backuply_optPOST('import_len') : 0;
	$GLOBALS['import_offset'] = !empty(backuply_optPOST('import_offset')) ? backuply_optPOST('import_offset') : 0;
	$GLOBALS['import_spos'] = !empty(backuply_optPOST('import_spos')) ? backuply_optPOST('import_spos') : 0;
}

if($data['site_url'] !== $data['backup_site_url']){
	$data['is_migrating'] = true;
}

// Check if we can write
$can_write = backuply_can_create_file();

update_active_time($data['backuly_backup_dir']);

if(empty($can_write)){
	$error[] = 'Cannot write a temporary file !';
	backuply_die('cannot_write');
}

// We need to stop execution in 25 secs.. We will be called again if the process is incomplete
// Set default value
$keepalive = 25;
$GLOBALS['end'] = (int) time() + $keepalive;

// Empty last file everytime as a precaution
$GLOBALS['last_file'] = '';
$GLOBALS['last_file'] = backuply_optPOST('last_file');
if(!empty($GLOBALS['last_file'])){			
	$GLOBALS['last_file'] = rawurldecode($GLOBALS['last_file']);
}

$GLOBALS['current_status'] = 0;
$GLOBALS['current_status'] = (int) backuply_optPOST('current_status');
if(!empty($GLOBALS['current_status'])){			
	$GLOBALS['current_status'] = $GLOBALS['current_status'];
}

$local_file = $data['backuly_backup_dir'] . '/backups/' . $data['fname'];

if(preg_match('/\:\/\//', $data['backup_dir'])){
	if(empty($data['l_readbytes'])) {
		$data['l_readbytes'] = 0;
	}

	//$data['is_remote'] = true;
	$data['local_tar'] = $data['backuly_backup_dir'] . '/backups/' . $data['fname'];
	$data['remote_tar'] = $data['backup_dir'].'/'.$data['fname'];
	
	//Download the file if its on remote location
	if($data['size'] > $data['l_readbytes']) {
		remote_archive_download_loop();
	}
}

// Restore files
if(!empty($data['restore_dir']) && empty($GLOBALS['current_status'])){
	//backuply_log('restore files start');

	// Store the progress
	backuply_status_log('Restoring directories', 'working', 27);

	// Set default values
	$GLOBALS['start'] = 0;
	$GLOBALS['end_file'] = '';

	if(!untar_archive($local_file, $data['softpath'], array(), true)){
		$error[] = 'There was some error while unzipping the backup files';
		backuply_die('restoreerror');
	}

	// Is the backup process INCOMPLETE ? 
	if(!empty($GLOBALS['end_file'])){
		$data['last_file'] = $GLOBALS['end_file'];
		$data['status'] = 1;
		backuply_status_log('Restoring: ' .$GLOBALS['end_file']);
		backuply_die('INCOMPLETE', $GLOBALS['end_file']);
		///echo serialize($data);
	}
	
	// See if a permission list is there ?
	$perms = @file($data['softpath'].'/softperms.txt');
	if(is_array($perms)){
		foreach($perms as $k => $v){
			$link = $target = $dest_file = '';
			$v = trim($v);
			$perm = substr($v, -4);
			
			// Do this only if the restore of files is already completed
			if(empty($GLOBALS['end_file'])){
				if(preg_match('/(.*?)linkto=(.*?)('.$perm.')/', $v, $out)){
					$link = trim($out[1]);
					$target = trim($out[2]);
					if (substr($link, 0, 1) == '/'){
						$link = $data['softpath'].$link;
					}else{
						$link = $data['softpath'].'/'.$link;
					}
					if(!empty($target)){				
						if(!@symlink($target, $link)){
							$error[] = 'Unable to extract symbolic link {' . $link . '}';
							backuply_die('restoresymlink');
						}
					}
				}
			}
			if(is_numeric($perm)){
				
				if(empty($link)){
					$dest_file = $data['softpath'].'/'.substr($v, 0, -5);
				}else{
					$dest_file = $link;
				}
				@chmod($dest_file, octdec($perm));
			}
		}
	}
	
	$GLOBALS['current_status'] = 1;
	//backuply_log('restore files ends');
}


// Restore Database	
if(!empty($data['restore_db']) && $GLOBALS['current_status'] < 2){
	//backuply_log('restore db start');
	
	// Updating wp-config
	if($data['is_migrating']){
		updating_config_file();
	}
	
	// Store the progress
	backuply_status_log('Working on restoring Database', 'working', !empty($data['restore_dir']) ? 67 : 24);

	$dbuser = $data['softdbuser'];
	$dbpass = $data['softdbpass'];
	
	// Does the USER exist ?
	$mysql = @backuply_mysql_connect($data['softdbhost'], $dbuser, $dbpass, true);
	
	// Try to select the DB if connection was successful
	if($mysql){
		$_mysql = @backuply_mysql_select_db($data['softdb'], $mysql);
	}
	
	// Untaring the backup file
	if(!file_exists($data['softpath'].'/'.$data['dbexist'])){
		backuply_status_log('Untaring the backup file', 'working', !empty($data['restore_dir']) ? 67 : 24);
		if(!untar_archive($local_file, $data['softpath'], array($data['dbexist']), true)){
			$error[] = 'There was some error while unzipping the backup files';
			//backuply_log('There was some error while unzipping the backup files');
			backuply_die('restoreerror');
		}
	}
	
	//$sql_data = implode('', file($data['softpath'].'/'.$data['dbexist']));
	
	//Make the Connection
	$__conn = @backuply_mysql_connect($data['softdbhost'], $dbuser, $dbpass, true);
	
	backuply_mysql_query('SET CHARACTER SET utf8mb4', $__conn);
	
	//CHECK Errors and SELECT DATABASE
	if(!empty($__conn)){
		backuply_status_log('Successfully connected to the database L('. $data['restore_loop'] .')', 'working', !empty($data['restore_dir']) ? 68 : 25);
		if(!(@backuply_mysql_select_db($data['softdb'], $__conn))){
			//$softpanel->deldb($dbuser, $dbpass);
			$error[] = 'Could not select the database to restore'.'
'.backuply_mysql_error($__conn);
			backuply_die('res_err_selectmy');
			//backuply_log('Could not select the database to restore');
		}
	}else{
		$error[] = 'Could not connect to the database'.'
'.backuply_mysql_error($__conn);
		backuply_die('err_myconn');
		//backuply_log('Could not connect to the database');
	}
		
	// We did not create the database ! So just backup the tables required for this database
	if(!empty($data['exclude_db'])){
		
		// Do we have to get the tables list from backup info ?
		$thisdb_tables = $data['exclude_db'];
		
		
		if(!is_array($data['exclude_db'])){
			$thisdb_tables = unserialize($data['exclude_db']);
		}
		
		
		// This is just to remove the ` since we are not getting it in $tables below
		foreach($thisdb_tables as $tk => $tv){
			$_thisdb_tables[trim($tk, '`')] = trim($tv, '`');
		}
	}
	
	if(empty($GLOBALS['import_len'])) {
		$res = backuply_mysql_query("SHOW TABLES", $__conn);
		
		for($i=0; $i < backuply_mysql_num_rows($res); $i++){
			$row = backuply_mysql_fetch_array($res);
			
			// We do not need to backup this table
			if(isset($_thisdb_tables) && is_array($_thisdb_tables) && in_array($row[0], $_thisdb_tables)){
				continue;
			}
			
			$tables[] = $row[0];
		}
		
		// Some tables cause problem
		$res = backuply_mysql_query("SET foreign_key_checks = 0", $__conn);
		
		foreach($tables as $k => $v){
			backuply_status_log('Dropping old table : '.$v, 'writing');
			$res = backuply_mysql_query("DROP TABLE `$v`", $__conn);
		}
		
		//Softaculous Function to import Data
		backuply_status_log('Starting to import database', 'working', !empty($data['restore_dir']) ? 69 : 26);
	}
	
	backuply_import($data['softpath'].'/'.$data['dbexist'], $__conn);
	
	//backuply_log('restore db complete');
	if(!empty($error)){
		return false;
	}
	
	backuply_status_log('Import has been completed', 'working', !empty($data['restore_dir']) ? 70 : 28);
	@unlink($data['softpath'].'/'.$data['dbexist']);
	if(file_exists($data['softpath'].'/'.$data['fname'])){
		@unlink($data['softpath'].'/'.$data['fname']);
	}
	
	$GLOBALS['current_status'] = 2;	
}

$GLOBALS['current_status'] = 5;

// The process is complete. Send a NULL Value
backuply_die('DONE','',$data['backuly_backup_dir']);
