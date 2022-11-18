<?php
/*
* BACKUPLY
* https://backuply.com
* (c) Backuply Team
*/

//PHP Options
set_time_limit(60);
error_reporting(E_ALL);
ignore_user_abort(true);

//Constants
define('ARCHIVE_TAR_ATT_SEPARATOR', 90001);
define('ARCHIVE_TAR_END_BLOCK', pack('a512', ''));

class backuply_tar{

	var $_tarname = '';
	var $_compress = false;
	var $_compress_type = 'none';
	var $_separator = ',';
	var $_file = 0;
	var $_temp_tarname = '';
	var $_ignore_regexp = '';
	var $error_object = null;
	
	var $_local_tar = ''; // The local file	
	var $_orig_tar = ''; // The remote file
	var $remote_fp = ''; // The remote file pointer	
	var $remote_fp_filter = NULL;
	var $remote_hctx = NULL;
	var $remote_content_size = 0;
	
	function __construct($p_tarname, $p_compress = null, $handle_remote = false){
		
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

	// {{{ destructor
	function _backuply_tar()
	{
		$this->_close();
		// ----- Look for a local copy to delete
		if ($this->_temp_tarname != '')
		   @unlink($this->_temp_tarname);
		
		// In case of REMOTE
		if(!empty($this->_orig_tar) && empty($GLOBALS['end_file'])){
			unlink($this->_local_tar);
		}
	}
	// }}}
	
	function __destruct(){
		$this->_backuply_tar();
	}

	// {{{ create()
	function create($p_filelist)
	{
		return $this->createModify($p_filelist, '', '');
	}
	// }}}

	// {{{ add()
	function add($p_filelist)
	{	
		return $this->addModify($p_filelist, '', '');
	}
	// }}}

	// {{{ extract()
	function extract($p_path='', $p_preserve=false)
	{
		return $this->extractModify($p_path, '', $p_preserve);
	}
	// }}}

	// {{{ listContent()
	function listContent()
	{
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
	function createModify($p_filelist, $p_add_dir, $p_remove_dir='')
	{
	
		$v_result = true;

		if (!$this->_openWrite())
			return false;
		
		// Backup the Softaculous pre list (e.g. softsql.sql)
		if(!empty($GLOBALS['pre_soft_list'])){
			$GLOBALS['doing_soft_files'] = 1;
			$v_result = $this->_addList($GLOBALS['pre_soft_list'], $p_add_dir, $p_remove_dir);
			$GLOBALS['doing_soft_files'] = 0;
		}
		
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
			// --- write footer only if end file is empty..
			if(empty($GLOBALS['end_file'])){ 
				
				// Add the Softaculous post files i.e. softperms.txt at the end
				if(!empty($GLOBALS['post_soft_list'])){
					$GLOBALS['doing_soft_files'] = 1;
					$v_result = $this->_addList($GLOBALS['post_soft_list'], $p_add_dir, $p_remove_dir);
					$GLOBALS['doing_soft_files'] = 0;
				}
				
				if($v_result){
					$this->_writeFooter();
				}
			}
			$this->_close();
		} else
			$this->_cleanFile();
	
		return $v_result;
	}
	// }}}

	// {{{ addModify()
	function addModify($p_filelist, $p_add_dir, $p_remove_dir='')
	{
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
	function addString($p_filename, $p_string)
	{
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
	function extractModify($p_path, $p_remove_path, $p_preserve=false)
	{
		$v_result = true;
		$v_list_detail = array();

		if ($v_result = $this->_openRead()) {
			$v_result = $this->_extractList($p_path, $v_list_detail,
				"complete", 0, $p_remove_path, $p_preserve);
			$this->_close();
		}

		return $v_result;
	}
	// }}}

	// {{{ extractInString()
	function extractInString($p_filename)
	{
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
	function extractList($p_filelist, $p_path='', $p_remove_path='', $p_preserve=false)
	{
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
	function setAttribute()
	{
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
	function setIgnoreRegexp($regexp)
	{
		$this->_ignore_regexp = $regexp;
	}
	// }}}

	// {{{ setIgnoreList()
	function setIgnoreList($list)
	{
		$regexp = str_replace(array('#', '.', '^', '$'), array('\#', '\.', '\^', '\$'), $list);
		$regexp = '#/'.join('$|/', $list).'#';
		$this->setIgnoreRegexp($regexp);
	}
	// }}}

	// {{{ _error()
	function _error($p_message)
	{
		//we have changed this since PEAR is not used
		//$this->error_object = &$this->raiseError($p_message); 
		backuply_status_log($p_message);
	}
	// }}}

	// {{{ _warning()
	function _warning($p_message)
	{
		//we have changed this since PEAR is not used
		//$this->error_object = &$this->raiseError($p_message); 
		backuply_status_log($p_message);
	}
	// }}}

	// {{{ _isArchive()
	function _isArchive($p_filename=null)
	{
		if ($p_filename == null) {
			$p_filename = $this->_tarname;
		}
		clearstatcache();
		return @is_file($p_filename) && !@is_link($p_filename);
	}
	// }}}

	// {{{ _openWrite()
	function _openWrite()
	{
		if ($this->_compress_type == 'gz' && function_exists('gzopen'))
		{
			$this->_file = @gzopen($this->_tarname, "ab9"); //added 'a' for append as 'w' mode truncated the file...
		}
		else if ($this->_compress_type == 'bz2' && function_exists('bzopen'))
		{
			$this->_file = @bzopen($this->_tarname, "w");
			echo 'bz+';
		}
		else if ($this->_compress_type == 'none')
		{
			$this->_file = @fopen($this->_tarname, "ab");
			echo 'ez+';
		}
		else
		{
			$this->_error('Unknown or missing compression type ('
						  .$this->_compress_type.')');
		}

		if ($this->_file == 0) {
			$this->_error('Unable to open in write mode \''
						  .$this->_tarname.'\'');
			return false;
		}

		return true;
	}
	// }}}

	// {{{ _openRead()
	function _openRead()
	{
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
			$this->_file = @gzopen($v_filename, "rb");
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
	function _openReadWrite()
	{
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
	function _close()
	{
		//if (isset($this->_file)) {
		if (is_resource($this->_file)) {
			if ($this->_compress_type == 'gz')
				@gzclose($this->_file);
			else if ($this->_compress_type == 'bz2')
				@bzclose($this->_file);
			else if ($this->_compress_type == 'none')
				@fclose($this->_file);
			else
				$this->_error('Unknown or missing compression type ('
							  .$this->_compress_type.')');

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
	function _cleanFile()
	{
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
	function _writeBlock($p_binary_data, $p_len=null, $finished = false)
	{
		if (is_resource($this->_file)) {
			if ($p_len === null) {
				if ($this->_compress_type == 'gz')
					$write = @gzputs($this->_file, $p_binary_data);
				else if ($this->_compress_type == 'bz2')
					$write = @bzwrite($this->_file, $p_binary_data);
				else if ($this->_compress_type == 'none')
					$write = @fputs($this->_file, $p_binary_data);
				else
					$this->_error('Unknown or missing compression type ('.$this->_compress_type.')');
			} else {
				if ($this->_compress_type == 'gz')
					$write = @gzputs($this->_file, $p_binary_data, $p_len);
				else if ($this->_compress_type == 'bz2')
					$write = @bzwrite($this->_file, $p_binary_data, $p_len);
				else if ($this->_compress_type == 'none')
					$write = @fputs($this->_file, $p_binary_data, $p_len);
				else
					$this->_error('Unknown or missing compression type ('.$this->_compress_type.')');
			}

			if(empty($write)){
				$this->_error('Failed to write to the backup file. Please check you have enough disk quota available.');
				return false;
			}

			// If there is anything to handle for remote uploads
			$this->remote_write_handle($finished);
		}
		return true;
	}
	// }}}
	
	function remote_write_handle($finished = false){
		global $error;

		// Do we have a remote file ?
		if(empty($this->_orig_tar)){
			return false;
		}
	
		clearstatcache();
		
		// Now is the size exceeding 2 MB
		if(!$finished && filesize($this->_local_tar) < 2097152){
			return false;
		}

		// Open the file pointer if not opened
		if(!is_resource($this->remote_fp)){
			
			$this->remote_fp = fopen($this->_orig_tar, 'ab');

			if($this->remote_fp == false){
				$error['fopen_failed'] = 'Unable to open in write mode';
				backuply_die('fopen_failed');
			}
			
			/*	// GZip Header
			fputs($this->remote_fp, "\x1F\x8B\x08\x08".pack("V", time())."\0\xFF");
			
			// Filename
			$oname = str_replace("\0", '', ltrim(basename($this->_orig_tar, '.gz'), '.'));
			fwrite($this->remote_fp, $oname."\0", 1+strlen($oname));
			
			// Create Stream
			$this->remote_fp_filter = stream_filter_append($this->remote_fp, "zlib.deflate", STREAM_FILTER_WRITE, -1);
			$this->remote_hctx = hash_init('crc32b');*/
			
			$this->remote_content_size = 0;
			if(!empty($GLOBALS['init_pos'])){
				$this->remote_content_size = $GLOBALS['init_pos'];
			}
			
			$GLOBALS['start_pos'] = $this->remote_content_size;
		}
		
		// Close the LOCAL file
		$this->_close();
		
		// Write to remote
		$content = file_get_contents($this->_local_tar);
		$clen = strlen($content);
		
		if(!empty($content)){
			//hash_update($this->remote_hctx, $content); // Update Hash
			fwrite($this->remote_fp, $content, $clen); // Write to the stream
			$this->remote_content_size += $clen; // Update Length
		}
		$content = '';
		
		// Delete Local file
		@unlink($this->_local_tar);
		
		// ReOpen the local tar
		$this->_openWrite();
		
		// If we are done, lets delete this file
		if($finished){
			
			/* // Remove Stream
			stream_filter_remove($this->remote_fp_filter);
			
			// Calculate Hash and write it
			$crc = hash_final($this->remote_hctx, true);
			@fwrite($this->remote_fp, $crc[3].$crc[2].$crc[1].$crc[0], 4);
			
			// Also the size
			@fwrite($this->remote_fp, pack("V", $this->remote_content_size), 4); */
			
			// Close
			@fclose($this->remote_fp);
		}
	}

	// {{{ _readBlock()
	function _readBlock()
	{
	  $v_block = null;
	  if (is_resource($this->_file)) {
		  if ($this->_compress_type == 'gz')
			  $v_block = @gzread($this->_file, 512);
		  else if ($this->_compress_type == 'bz2')
			  $v_block = @bzread($this->_file, 512);
		  else if ($this->_compress_type == 'none')
			  $v_block = @fread($this->_file, 512);
		  else
			  $this->_error('Unknown or missing compression type ('
							.$this->_compress_type.')');
	  }
	  return $v_block;
	}
	// }}}

	// {{{ _jumpBlock()
	function _jumpBlock($p_len=null)
	{
		if (is_resource($this->_file)) {
			if ($p_len === null)
				$p_len = 1;

			if ($this->_compress_type == 'gz') {
				@gzseek($this->_file, gztell($this->_file)+($p_len*512));
			}
			else if ($this->_compress_type == 'bz2') {
				// ----- Replace missing bztell() and bzseek()
				for ($i=0; $i<$p_len; $i++)
				$this->_readBlock();
			} else if ($this->_compress_type == 'none')
				@fseek($this->_file, $p_len*512, SEEK_CUR);
			else
				$this->_error('Unknown or missing compression type ('
				.$this->_compress_type.')');
		}
		return true;
	}
	// }}}

	// {{{ _writeFooter()
	function _writeFooter()
	{
		if (is_resource($this->_file)) {
			// ----- Write the last 0 filled block for end of archive
			$v_binary_data = pack('a1024', '');
			if(!$this->_writeBlock($v_binary_data, null, true)){
				return false;
			}
		}
		return true;
	}
	// }}}

	// {{{ _addList()
	function _addList($p_list, $p_add_dir, $p_remove_dir) {

		$v_result = true;
		$v_header = array();
		
		// ----- Remove potential windows directory separator
		$p_add_dir = $this->_translateWinPath($p_add_dir);
		$p_remove_dir = $this->_translateWinPath($p_remove_dir, false);

		if (!$this->_file) {
			$this->_error('Invalid file descriptor');
			return false;
		}

		if (sizeof($p_list) == 0)
			return true;
		
		foreach ($p_list as $v_filename) {
			
			if (!$v_result) {
				break;
			}
				
			// ----- Skip the current tar name
			if ($v_filename == $this->_tarname)
				continue;

			if ($v_filename == '')
				continue;

			// ----- ignore files and directories matching the ignore regular expression
			if ($this->_ignore_regexp && preg_match($this->_ignore_regexp, '/'.$v_filename)) {
				$this->_warning("File '$v_filename' ignored");
				continue;
			}

			if (!file_exists($v_filename) && !is_link($v_filename)) {
				$this->_warning("File '$v_filename' does not exist");
				continue;
			}

			// ----- break the loop once last file is found...
			if(!empty($GLOBALS['end_file'])){
				break;
			}
			
			// ----- Add the file or directory header
			if (!$this->_addFile($v_filename, $v_header, $p_add_dir, $p_remove_dir))
				return false;

			if (@is_dir($v_filename) && !@is_link($v_filename)) {
				if (!($p_hdir = opendir($v_filename))) {
					$this->_warning("Directory '$v_filename' can not be read");
					continue;
				}
				
				$p_temp_list = array();
				while (false !== ($p_hitem = readdir($p_hdir))) {
					if (($p_hitem != '.') && ($p_hitem != '..')) {
						if ($v_filename != "."){
							//Double slashes were added and caused issue when the backup directory is inside the installation directory.
							$v_filename = $this->cleanpath($v_filename);
							$p_temp_list[0] = $v_filename.'/'.$p_hitem;
						}else{
							$p_temp_list[0] = $p_hitem;
						}

						// ----- break the loop once last file is found...
						if(!empty($GLOBALS['end_file'])){
							break;
						}

						$v_result = $this->_addList($p_temp_list,
												$p_add_dir,
												$p_remove_dir);
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
	function _addFile($p_filename, &$p_header, $p_add_dir, $p_remove_dir) {
		
		global $backuply;
		
		// check last file and skip the files that have been already backed up...
		if(!empty($GLOBALS['last_file']) && $GLOBALS['start'] == 0){
			if(preg_match('#^'.$GLOBALS['last_file'].'$#', $p_filename)){
				
				$GLOBALS['start'] = 1; // give a jump start once the last backed up file is found..
			}
			return true; //return true to skip files
		}
	
		if (!$this->_file) {
		  $this->_error('Invalid file descriptor');
		  return false;
		}

		if ($p_filename == '') {
		  $this->_error('Invalid file name');
		  return false;
		}
		
		// ----- Calculate the stored filename
		$p_filename = $this->_translateWinPath($p_filename, false);
		$v_stored_filename = $p_filename;
		if (strcmp($p_filename, $p_remove_dir) == 0) {
		  return true;
		}
		
		// Match filename to be excluded as provided by script
		foreach($backuply['excludes']['exact'] as $ek => $ev) {
			if(empty($GLOBALS['doing_soft_files']) && !empty($ev) && preg_match('#^'.$ev.'#', $p_filename)) {
				return true;
			}
		}
		
		// Exclude the sql file if it has already been backedup
		if(strpos($p_filename, 'softsql.sql') !== FALSE && empty($GLOBALS['doing_soft_files'])){
			return true;
		}

		$home_path = backuply_cleanpath(WP_CONTENT_DIR);
		
		// Checks if the the file we are excluding is in WP CONTENT DIR
		if(strpos($p_filename, $home_path) !== FALSE){

			/* The str_replace below will change the Path to this /plugins/backuply-pro/init.php 
			*	as we just want to exclude the folders or files inside WP Content
			*/
			$rel_path = str_replace($home_path, '', $p_filename);
			
			// Excluding files with specific extension
			if(!empty($backuply['excludes']['extension'])){
				$ext = pathinfo($p_filename, PATHINFO_EXTENSION);
				
				if(in_array($ext, $backuply['excludes']['extension'])){
					return true;
				}
			}
			
			// Excluding a pattern that starts with
			if(!empty($backuply['excludes']['beginning'])){
		
				foreach($backuply['excludes']['beginning'] as $beginning){
					// Here it checks if the pattern we are looking for has slash(/) before it then its the start of the name of the folder or file
					preg_match('/\/'.preg_quote(trim($beginning)).'/', $rel_path, $matches);
					
					if(!empty($matches) && strpos($rel_path, 'softsql.sql') == FALSE){
						return true;
					}
				}
			}

			// Excluding a pattern that ends with
			if(!empty($backuply['excludes']['end'])){

				$matches = [];
				foreach($backuply['excludes']['end'] as $end){
					/* Here it checks if the pattern we are looking for has slash(/) after it then its the end of the name of the folder or file
					*	/(?:wp\/|wp$)/ this is the regex used and wp is the word that we are matching
					*/
					preg_match('/(?:' . preg_quote(trim($end)).'\/|' . preg_quote(trim($end)). '$)/', $rel_path, $matches);
					
					if(!empty($matches) && strpos($rel_path, 'softsql.sql') == FALSE){
						return true;
					}
				}
			}

			// Excluding a pattern that is anywhere in the path
			if(!empty($backuply['excludes']['anywhere'])){
				
				$matches = [];
				foreach($backuply['excludes']['anywhere'] as $pattern){
					// Here it checks if the pattern we are looking for is present anywhere in the path
					preg_match('/'.preg_quote(trim($pattern)). '/', $rel_path, $matches);
					
					if(!empty($matches) && strpos($rel_path, 'softsql.sql') == FALSE){
						return true;
					}
				}
			}
		}
		
		if ($p_remove_dir != '') {
			if (substr($p_remove_dir, -1) != '/')
			$p_remove_dir .= '/';

			if (substr($p_filename, 0, strlen($p_remove_dir)) == $p_remove_dir)
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
		
		backuply_backup_stop_checkpoint();
		
		// mt_rand to reduce the number of folder in log
		if(is_dir($p_filename) && mt_rand(0,1)) {
			backuply_status_log('Adding (L'.$backuply['status']['loop'].') : ' . $p_filename, 'adding', 65);
		}
		
		if ($this->_isArchive($p_filename)) {
			if (($v_file = @fopen($p_filename, 'rb')) == 0) {
				$this->_warning("Unable to open file '".$p_filename
							  ."' in binary read mode");
				return true;
			}

			if (!$this->_writeHeader($p_filename, $v_stored_filename))
				return false;

			while (($v_buffer = fread($v_file, 512)) != '') {
				$v_binary_data = pack('a512', "$v_buffer");
				if(!$this->_writeBlock($v_binary_data)){
					return false;
				}
			}

			fclose($v_file);

		} else {
			// ----- Only header for dir
			if (!$this->_writeHeader($p_filename, $v_stored_filename))
				return false;
		}
		
		// We can run the scripts for the end time already set
		if(time() >= $GLOBALS['end']){
			$GLOBALS['end_file'] = $p_filename; // set end file so that we know where to start from
		}

		return true;
	}
	// }}}

	// {{{ _addString()
	function _addString($p_filename, $p_string) {
		if (!$this->_file) {
			$this->_error('Invalid file descriptor');
			return false;
		}

		if ($p_filename == '') {
			$this->_error('Invalid file name');
			return false;
		}

		// ----- Calculate the stored filename
		$p_filename = $this->_translateWinPath($p_filename, false);

		if (!$this->_writeHeaderBlock($p_filename, strlen($p_string),
								  time(), 384, '', 0, 0))
			return false;

		$i=0;
		while (($v_buffer = substr($p_string, (($i++)*512), 512)) != '') {
			$v_binary_data = pack('a512', $v_buffer);
			if(!$this->_writeBlock($v_binary_data)) {
				return false;
			}
		}

		return true;
	}
	// }}}

	// {{{ _writeHeader()
	function _writeHeader($p_filename, $p_stored_filename)
	{
		if ($p_stored_filename == '')
			$p_stored_filename = $p_filename;
		$v_reduce_filename = $this->_pathReduction($p_stored_filename);
		
		
		//echo $v_reduce_filename." - ";
 
		$v_reduce_filename = str_replace($GLOBALS['replace']['from'], $GLOBALS['replace']['to'], $v_reduce_filename);
		
		//echo $v_reduce_filename."<br />";

		if (strlen($v_reduce_filename) > 99) {
		  if (!$this->_writeLongHeader($v_reduce_filename))
			return false;
		}
		
		// We have to write the entries in softperms.txt
		if (isset($GLOBALS['bfh']['softperms']) && preg_match('/'.preg_quote($GLOBALS['replace']['from']['softpath'], '/').'/is', $p_filename)) {
			fwrite($GLOBALS['bfh']['softperms'], trim($v_reduce_filename, '/')." ".(!empty($v_linkname) ? "linkto=".rtrim($v_linkname, '/')." " : ""). (substr(sprintf('%o', fileperms($p_filename)), -4)) ."\n"); 

		}
		
		// To make sure we have the correct data after the file is written above
		clearstatcache();
		
		$v_info = lstat($p_filename);
		$v_uid = sprintf("%07s", DecOct($v_info[4]));
		$v_gid = sprintf("%07s", DecOct($v_info[5]));
		$v_perms = sprintf("%07s", DecOct($v_info['mode'] & 000777));

		$v_mtime = sprintf("%011s", DecOct($v_info['mtime']));

		$v_linkname = '';

		if (@is_link($p_filename)) {
		  $v_typeflag = '2';
		  $v_linkname = readlink($p_filename);
		  $v_size = sprintf("%011s", DecOct(0));
		} elseif (@is_dir($p_filename)) {
		  $v_typeflag = '5';
		  $v_size = sprintf("%011s", DecOct(0));
		} else {
		  $v_typeflag = '0';
		  clearstatcache();
		  $v_size = sprintf("%011s", DecOct($v_info['size']));
		}
		
		$v_magic = 'ustar ';

		$v_version = ' ';
		
		if (function_exists('posix_getpwuid'))
		{
		  $userinfo = posix_getpwuid($v_info[4]);
		  $groupinfo = posix_getgrgid($v_info[5]);
		  
		  $v_uname = $userinfo['name'];
		  $v_gname = $groupinfo['name'];
		}
		else
		{
		  $v_uname = '';
		  $v_gname = '';
		}

		$v_devmajor = '';

		$v_devminor = '';

		$v_prefix = '';
		$v_linkname = ''; // This is empty because we will create symlinks with our restore utility using softperms.txt

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
		if(!$this->_writeBlock($v_binary_data_first, 148)){
			return false;
		}

		// ----- Write the calculated checksum
		$v_checksum = sprintf("%06s ", DecOct($v_checksum));
		$v_binary_data = pack("a8", $v_checksum);
		if(!$this->_writeBlock($v_binary_data, 8)){
			return false;
		}

		// ----- Write the last 356 bytes of the header in the archive
		if(!$this->_writeBlock($v_binary_data_last, 356)){
			return false;
		}

		return true;
	}
	// }}}

	// {{{ _writeHeaderBlock()
	function _writeHeaderBlock($p_filename, $p_size, $p_mtime=0, $p_perms=0,
							   $p_type='', $p_uid=0, $p_gid=0)
	{
		$p_filename = $this->_pathReduction($p_filename);

		if (strlen($p_filename) > 99) {
		  if (!$this->_writeLongHeader($p_filename))
			return false;
		}

		if ($p_type == '5') {
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

		if (function_exists('posix_getpwuid'))
		{
		  $userinfo = posix_getpwuid($p_uid);
		  $groupinfo = posix_getgrgid($p_gid);
		  
		  $v_uname = $userinfo['name'];
		  $v_gname = $groupinfo['name'];
		}
		else
		{
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
		if(!$this->_writeBlock($v_binary_data_first, 148)){
			return false;
		}

		// ----- Write the calculated checksum
		$v_checksum = sprintf("%06s ", DecOct($v_checksum));
		$v_binary_data = pack("a8", $v_checksum);
		if(!$this->_writeBlock($v_binary_data, 8)){
			return false;
		}

		// ----- Write the last 356 bytes of the header in the archive
		if(!$this->_writeBlock($v_binary_data_last, 356)){
			return false;
		}

		return true;
	}
	// }}}

	// {{{ _writeLongHeader()
	function _writeLongHeader($p_filename)
	{
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
		if(!$this->_writeBlock($v_binary_data_first, 148)){
			return false;
		}

		// ----- Write the calculated checksum
		$v_checksum = sprintf("%06s ", DecOct($v_checksum));
		$v_binary_data = pack("a8", $v_checksum);
		if(!$this->_writeBlock($v_binary_data, 8)){
			return false;
		}

		// ----- Write the last 356 bytes of the header in the archive
		if(!$this->_writeBlock($v_binary_data_last, 356)){
			return false;
		}

		// ----- Write the filename as content of the block
		$i=0;
		while (($v_buffer = substr($p_filename, (($i++)*512), 512)) != '') {
			$v_binary_data = pack("a512", "$v_buffer");
			if(!$this->_writeBlock($v_binary_data)){
				return false;
			}
		}

		return true;
	}
	// }}}

	// {{{ _readHeader()
	function _readHeader($v_binary_data, &$v_header)
	{
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
	function _maliciousFilename($file)
	{
		if (strpos($file, '/../') !== false) {
			return true;
		}
		if (strpos($file, '../') === 0) {
			return true;
		}
		return false;
	}
	// }}}

	// {{{ _readLongHeader()
	function _readLongHeader(&$v_header)
	{
	  $v_filename = '';
	  $n = floor($v_header['size']/512);
	  for ($i=0; $i<$n; $i++) {
		$v_content = $this->_readBlock();
		$v_filename .= $v_content;
	  }
	  if (($v_header['size'] % 512) != 0) {
		$v_content = $this->_readBlock();
		$v_filename .= trim($v_content);
	  }

	  // ----- Read the next header
	  $v_binary_data = $this->_readBlock();

	  if (!$this->_readHeader($v_binary_data, $v_header))
		return false;

	  $v_filename = trim($v_filename);
	  $v_header['filename'] = $v_filename;
		if ($this->_maliciousFilename($v_filename)) {
			$this->_error('Malicious .tar detected, file "' . $v_filename .
				'" will not install in desired directory tree');
			return false;
	  }

	  return true;
	}
	// }}}

	// {{{ _extractInString()
	function _extractInString($p_filename)
	{
		$v_result_str = "";

		While (strlen($v_binary_data = $this->_readBlock()) != 0)
		{
		  if (!$this->_readHeader($v_binary_data, $v_header))
			return null;

		  if ($v_header['filename'] == '')
			continue;

		  // ----- Look for long filename
		  if ($v_header['typeflag'] == 'L') {
			if (!$this->_readLongHeader($v_header))
			  return null;
		  }

		  if ($v_header['filename'] == $p_filename) {
			  if ($v_header['typeflag'] == "5") {
				  $this->_error('Unable to extract in string a directory '
								.'entry {'.$v_header['filename'].'}');
				  return null;
			  } else {
				  $n = floor($v_header['size']/512);
				  for ($i=0; $i<$n; $i++) {
					  $v_result_str .= $this->_readBlock();
				  }
				  if (($v_header['size'] % 512) != 0) {
					  $v_content = $this->_readBlock();
					  $v_result_str .= substr($v_content, 0,
											  ($v_header['size'] % 512));
				  }
				  return $v_result_str;
			  }
		  } else {
			  $this->_jumpBlock(ceil(($v_header['size']/512)));
		  }
		}

		return null;
	}
	// }}}

	// {{{ _extractList()
	function _extractList($p_path, &$p_list_detail, $p_mode,
						  $p_file_list, $p_remove_path, $p_preserve=false)
	{
	$v_result=true;
	$v_nb = 0;
	$v_extract_all = true;
	$v_listing = false;

	$p_path = $this->_translateWinPath($p_path, false);
	if ($p_path == '' || (substr($p_path, 0, 1) != '/'
		&& substr($p_path, 0, 3) != "../" && !strpos($p_path, ':'))) {
	  $p_path = "./".$p_path;
	}
	$p_remove_path = $this->_translateWinPath($p_remove_path);

	// ----- Look for path to remove format (should end by /)
	if (($p_remove_path != '') && (substr($p_remove_path, -1) != '/'))
	  $p_remove_path .= '/';
	$p_remove_path_size = strlen($p_remove_path);

	switch ($p_mode) {
	  case "complete" :
		$v_extract_all = true;
		$v_listing = false;
	  break;
	  case "partial" :
		  $v_extract_all = false;
		  $v_listing = false;
	  break;
	  case "list" :
		  $v_extract_all = false;
		  $v_listing = true;
	  break;
	  default :
		$this->_error('Invalid extract mode ('.$p_mode.')');
		return false;
	}

	clearstatcache();

	while (strlen($v_binary_data = $this->_readBlock()) != 0)
	{
	  $v_extract_file = FALSE;
	  $v_extraction_stopped = 0;

	  if (!$this->_readHeader($v_binary_data, $v_header))
		return false;

	  if ($v_header['filename'] == '') {
		continue;
	  }

	  // ----- Look for long filename
	  if ($v_header['typeflag'] == 'L') {
		if (!$this->_readLongHeader($v_header))
		  return false;
	  }

	  if ((!$v_extract_all) && (is_array($p_file_list))) {
		// ----- By default no unzip if the file is not found
		$v_extract_file = false;

		for ($i=0; $i<sizeof($p_file_list); $i++) {
		  // ----- Look if it is a directory
		  if (substr($p_file_list[$i], -1) == '/') {
			// ----- Look if the directory is in the filename path
			if ((strlen($v_header['filename']) > strlen($p_file_list[$i]))
				&& (substr($v_header['filename'], 0, strlen($p_file_list[$i]))
					== $p_file_list[$i])) {
			  $v_extract_file = true;
			  break;
			}
		  }

		  // ----- It is a file, so compare the file names
		  elseif ($p_file_list[$i] == $v_header['filename']) {
			$v_extract_file = true;
			break;
		  }
		}
	  } else {
		$v_extract_file = true;
	  }

	  // ----- Look if this file need to be extracted
	  if (($v_extract_file) && (!$v_listing))
	  {
		if (($p_remove_path != '')
			&& (substr($v_header['filename'], 0, $p_remove_path_size)
				== $p_remove_path))
		  $v_header['filename'] = substr($v_header['filename'],
										 $p_remove_path_size);
		if (($p_path != './') && ($p_path != '/')) {
		  while (substr($p_path, -1) == '/')
			$p_path = substr($p_path, 0, strlen($p_path)-1);

		  if (substr($v_header['filename'], 0, 1) == '/')
			  $v_header['filename'] = $p_path.$v_header['filename'];
		  else
			$v_header['filename'] = $p_path.'/'.$v_header['filename'];
		}
		if (file_exists($v_header['filename'])) {
		  if (   (@is_dir($v_header['filename']))
			  && ($v_header['typeflag'] == '')) {
			$this->_error('File '.$v_header['filename']
						  .' already exists as a directory');
			return false;
		  }
		  if (   ($this->_isArchive($v_header['filename']))
			  && ($v_header['typeflag'] == "5")) {
			$this->_error('Directory '.$v_header['filename']
						  .' already exists as a file');
			return false;
		  }
		  if (!is_writeable($v_header['filename'])) {
			//We cannot use $globals['ofc'] here and after restoring the files we are anyways changing the file's permissions according to the perms file. Therefore, using 0644/0755 directly here shouldn't be an issue.
			if(is_dir($v_header['filename'])){
				$chmod = chmod($v_header['filename'], 0755);
			}else{
				$chmod = chmod($v_header['filename'], 0644);
			}
			if (!is_writeable($v_header['filename'])) {
				$this->_error('File '.$v_header['filename']
						  .' already exists and is write protected');
				return false;
			}
		  }
		  if (filemtime($v_header['filename']) > $v_header['mtime']) {
			// To be completed : An error or silent no replace ?
		  }
		}

		// ----- Check the directory availability and create it if necessary
		elseif (($v_result
				 = $this->_dirCheck(($v_header['typeflag'] == "5"
									?$v_header['filename']
									:dirname($v_header['filename'])))) != 1) {
			$this->_error('Unable to create path for '.$v_header['filename']);
			return false;
		}

		if ($v_extract_file) {
		  if ($v_header['typeflag'] == "5") {
			if (!@file_exists($v_header['filename'])) {
				if (!@mkdir($v_header['filename'], 0777)) {
					$this->_error('Unable to create directory {'
								  .$v_header['filename'].'}');
					return false;
				}
			}
		  } elseif ($v_header['typeflag'] == "2") {
			  if (@file_exists($v_header['filename'])) {
				 @unlink($v_header['filename']);
			  }
			  if (!@symlink($v_header['link'], $v_header['filename'])) {
				  $this->_error('Unable to extract symbolic link {'
								.$v_header['filename'].'}');
				  return false;
			  }
		  } else {
			  if (($v_dest_file = @fopen($v_header['filename'], "wb")) == 0) {
				  $this->_error('Error while opening {'.$v_header['filename']
								.'} in write binary mode');
				  return false;
			  } else {
				  $n = floor($v_header['size']/512);
				  for ($i=0; $i<$n; $i++) {
					  $v_content = $this->_readBlock();
					  fwrite($v_dest_file, $v_content, 512);
				  }
			if (($v_header['size'] % 512) != 0) {
			  $v_content = $this->_readBlock();
			  fwrite($v_dest_file, $v_content, ($v_header['size'] % 512));
			}

			@fclose($v_dest_file);
			
			if ($p_preserve) {
				@chown($v_header['filename'], $v_header['uid']);
				@chgrp($v_header['filename'], $v_header['gid']);
			}

			// ----- Change the file mode, mtime
			@touch($v_header['filename'], $v_header['mtime']);
			if ($v_header['mode'] & 0111) {
				// make file executable, obey umask
				$mode = fileperms($v_header['filename']) | (~umask() & 0111);
				@chmod($v_header['filename'], $mode);
			}
		  }

		  // ----- Check the file size
		  clearstatcache();
		  if (!is_file($v_header['filename'])) {
			  $this->_error('Extracted file '.$v_header['filename']
							.'does not exist. Archive may be corrupted.');
			  return false;
		  }
		  
		  $filesize = filesize($v_header['filename']);
		  if ($filesize != $v_header['size']) {
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

	  if ($v_listing || $v_extract_file || $v_extraction_stopped) {
		// ----- Log extracted files
		if (($v_file_dir = dirname($v_header['filename']))
			== $v_header['filename'])
		  $v_file_dir = '';
		if ((substr($v_header['filename'], 0, 1) == '/') && ($v_file_dir == ''))
		  $v_file_dir = '/';

		// Only if we are to return the list i.e. in listContent() then we fill full $v_header else we just need the count
		$p_list_detail[$v_nb++] = (!empty($v_listing) ? $v_header : '');
		if (is_array($p_file_list) && (count($p_list_detail) == count($p_file_list))) {
			return true;
		}
	  }
	}

		return true;
	}
	// }}}

	// {{{ _openAppend()
	function _openAppend()
	{
		
		if (filesize($this->_tarname) == 0)
		  return $this->_openWrite();

		if ($this->_compress) {
			$this->_close();

			if (!@rename($this->_tarname, $this->_tarname.".tmp")) {
				$this->_error('Error while renaming \''.$this->_tarname
							  .'\' to temporary file \''.$this->_tarname
							  .'.tmp\'');
				return false;
			}

			if ($this->_compress_type == 'gz')
				$v_temp_tar = @gzopen($this->_tarname.".tmp", "rb");
			elseif ($this->_compress_type == 'bz2')
				$v_temp_tar = @bzopen($this->_tarname.".tmp", "r");

			if ($v_temp_tar == 0) {
				$this->_error('Unable to open file \''.$this->_tarname
							  .'.tmp\' in binary read mode');
				@rename($this->_tarname.".tmp", $this->_tarname);
				return false;
			}

			if (!$this->_openWrite()) {
				@rename($this->_tarname.".tmp", $this->_tarname);
				return false;
			}

			if ($this->_compress_type == 'gz') {
				$end_blocks = 0;
				
				while (!@gzeof($v_temp_tar)) {
					$v_buffer = @gzread($v_temp_tar, 512);
					if ($v_buffer == ARCHIVE_TAR_END_BLOCK || strlen($v_buffer) == 0) {
						$end_blocks++;
						// do not copy end blocks, we will re-make them
						// after appending
						continue;
					} elseif ($end_blocks > 0) {
						for ($i = 0; $i < $end_blocks; $i++) {
							if(!$this->_writeBlock(ARCHIVE_TAR_END_BLOCK)){
								return false;
						  }
						}
						$end_blocks = 0;
					}
					$v_binary_data = pack("a512", $v_buffer);
					if(!$this->_writeBlock($v_binary_data)){
						return false;
				  }
				}

				@gzclose($v_temp_tar);
			}
			elseif ($this->_compress_type == 'bz2') {
				$end_blocks = 0;
				
				while (strlen($v_buffer = @bzread($v_temp_tar, 512)) > 0) {
					if ($v_buffer == ARCHIVE_TAR_END_BLOCK || strlen($v_buffer) == 0) {
						$end_blocks++;
						// do not copy end blocks, we will re-make them
						// after appending
						continue;
					} elseif ($end_blocks > 0) {
						for ($i = 0; $i < $end_blocks; $i++) {
							if(!$this->_writeBlock(ARCHIVE_TAR_END_BLOCK)){
								return false;
							}
						}
						$end_blocks = 0;
					}
					$v_binary_data = pack("a512", $v_buffer);
					if(!$this->_writeBlock($v_binary_data)){
					return false;
				  }
				}

				@bzclose($v_temp_tar);
			}

			if (!@unlink($this->_tarname.".tmp")) {
				$this->_error('Error while deleting temporary file \''
							  .$this->_tarname.'.tmp\'');
			}

		} else {
			// ----- For not compressed tar, just add files before the last
			//	   one or two 512 bytes block
			if (!$this->_openReadWrite())
			   return false;

			clearstatcache();
			$v_size = filesize($this->_tarname);

			// We might have zero, one or two end blocks.
			// The standard is two, but we should try to handle
			// other cases.
			fseek($this->_file, $v_size - 1024);
			if (fread($this->_file, 512) == ARCHIVE_TAR_END_BLOCK) {
				fseek($this->_file, $v_size - 1024);
			}
			elseif (fread($this->_file, 512) == ARCHIVE_TAR_END_BLOCK) {
				fseek($this->_file, $v_size - 512);
			}
		}

		return true;
	}
	// }}}

	// {{{ _append()
	function _append($p_filelist, $p_add_dir = '', $p_remove_dir = '')
	{
		if (!$this->_openAppend())
			return false;

		if ($this->_addList($p_filelist, $p_add_dir, $p_remove_dir))
		   $this->_writeFooter();

		$this->_close();

		return true;
	}
	// }}}

	// {{{ _dirCheck()
	function _dirCheck($p_dir)
	{
		clearstatcache();
		if ((@is_dir($p_dir)) || ($p_dir == ''))
			return true;

		$p_parent_dir = dirname($p_dir);

		if (($p_parent_dir != $p_dir) &&
			($p_parent_dir != '') &&
			(!$this->_dirCheck($p_parent_dir)))
			 return false;

		if (!@mkdir($p_dir, 0777)) {
			$this->_error("Unable to create directory '$p_dir'");
			return false;
		}

		return true;
	}

	// }}}

	// {{{ _pathReduction()
	function _pathReduction($p_dir)
	{
		$v_result = '';

		// ----- Look for not empty path
		if ($p_dir != '') {
			// ----- Explode path by directory names
			$v_list = explode('/', $p_dir);

			// ----- Study directories from last to first
			for ($i=sizeof($v_list)-1; $i>=0; $i--) {
				// ----- Look for current path
				if ($v_list[$i] == ".") {
					// ----- Ignore this directory
					// Should be the first $i=0, but no check is done
				}
				else if ($v_list[$i] == "..") {
					// ----- Ignore it and ignore the $i-1
					$i--;
				}
				else if (   ($v_list[$i] == '')
						 && ($i!=(sizeof($v_list)-1))
						 && ($i!=0)) {
					// ----- Ignore only the double '//' in path,
					// but not the first and last /
				} else {
					$v_result = $v_list[$i].($i!=(sizeof($v_list)-1)?'/'
								.$v_result:'');
				}
			}
		}
		
		if (defined('OS_WINDOWS') && OS_WINDOWS) {
			$v_result = strtr($v_result, '\\', '/');
		}
		
		return $v_result;
	}

	// }}}

	// {{{ _translateWinPath()
	function _translateWinPath($p_path, $p_remove_disk_letter = true) {
		if (defined('OS_WINDOWS') && OS_WINDOWS) {
			// ----- Look for potential disk letter
			if (($p_remove_disk_letter) && (($v_position = strpos($p_path, ':')) != false)) {
				$p_path = substr($p_path, $v_position+1);
			}
			
			// ----- Change potential windows directory separator
			if ((strpos($p_path, '\\') > 0) || (substr($p_path, 0,1) == '\\')) {
				$p_path = strtr($p_path, '\\', '/');
			}
		}
		
		return $p_path;
	}
	// }}}

	function cleanpath($path){	
		$path = str_replace('\\\\', '/', $path);
		$path = str_replace('\\', '/', $path);
		return rtrim($path, '/');
	}
}
	
function backuply_can_create_file(){
	$file = BACKUPLY_BACKUP_DIR . '/soft.tmp';
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


function backuply_tar_archive($tarname, $file_list, $handle_remote = false){
	backuply_log('Archiving your WP INSTALL Now');
	$tar_archive = new backuply_tar($tarname, '', $handle_remote);
	
	$res = $tar_archive->createModify($file_list, '', '');
	
	if(!$res){
		return false;	
	}
	
	//backuply_log('Backup Process Completed !!');
	return true;
}

function backuply_resetfilelist(){
	global $directorylist;
	$directorylist = array();
}

// Back up the database !!!
function backuply_mysql_fn($shost, $suser, $spass, $sdb, $sdbfile){
	//echo $shost.' == '. $suser.' == '. $spass.' == '. $sdb.' == '. $sdbfile;
	
	global $data, $backuply;
	
	$link = backuply_mysql_connect($shost, $suser, $spass);
	
	backuply_mysql_query('SET CHARACTER SET utf8mb4', $link);
	
	// Open and create a file handle for sql.
	$handle = fopen($sdbfile,'w');
	
	$s_def = $alter_queries = $sresponse = '';
	$sql_alter = $tables = array();
	
	$ser_ver = backuply_PMA_sversion($link);
	$s_def = backuply_PMA_exportHeader($sdb, $ser_ver);
	
	fwrite($handle, $s_def);
		
	// We did not create the database ! So just backup the tables required for this database
	if(!empty($data['exclude_db'])){
		
		$thisdb_tables = $data['exclude_db'];
		
		if(!is_array($data['exclude_db'])){
			$thisdb_tables = unserialize($data['exclude_db']);
		}
		
		// This is just to remove the ` since we are not getting it in $tables below
		foreach($thisdb_tables as $tk => $tv){
			// There was a bug since Softaculous 4.7.2 that did not save exclude_db for ins causing empty array. Fixed in Softaculous 4.7.7
			if(empty($tv)) continue;
			$_thisdb_tables[trim($tk, '`')] = trim($tv, '`');
		}
	}

	//List Views
	$squery = backuply_mysql_query('SHOW TABLE STATUS FROM `' . $sdb . '` WHERE COMMENT = \'VIEW\'', $link);
	
	$views = array();	
	if(backuply_mysql_num_rows($squery) > 0){
		while($row = backuply_mysql_fetch_row($squery)){
			$views[] = $row[0];
		}
	}
	
	// Sort the views
	usort($views, 'strnatcasecmp');
	
	// List the tables
	$squery = backuply_mysql_query('SHOW TABLES FROM `' . $sdb . '`', $link);
	
	while($row = backuply_mysql_fetch_row($squery)){
		
		// We do not need to backup this table
		if(!empty($_thisdb_tables) && is_array($_thisdb_tables) && in_array($row[0], $_thisdb_tables)){
			continue;
		}
		
		if(in_array($row[0], $views)){
			continue;
		}
		
		$tables[] = $row[0];
	}
	
	// Sort the tables
	usort($tables, 'strnatcasecmp');	
	
	foreach($tables as $table => $v){
		backuply_backup_stop_checkpoint();
		backuply_status_log('Adding (L'.$backuply['status']['loop'].') : '. $v .' table', 'working', 50);
		// Get the table structure(table definition)
		$stable_defn = backuply_PMA_getTableDef($sdb, $v, "\n", false, true, $link);
		
		$s_def = $stable_defn['structure']."\n";
		fwrite($handle, $s_def);
		
		// Get the table data(table contents)
		// We have added $handle so that we can write the INSERT queries directly when we get it. 
		// Basically To avoid MEMORY EXHAUST FOR  BIG INSERTS
		backuply_PMA_exportData($sdb, $v, "\n", $handle, $link);
		
		// List of alter queries 
		// We have changed this because the OLD method was putting the ALTER queries after CREATE table query which was causing issues.
		if(!empty($stable_defn['alter'])){
			$alter_queries .= $stable_defn['alter'];
		}
	}
	
	//Save Views
	foreach($views as $view){
		
		$defn = backuply_PMA_getViews($sdb, $view, "\n", $link);
		
		$view_def = $defn['structure']."\n";
		fwrite($handle, $view_def);
	}
	
	fwrite($handle, $alter_queries);
	
	//List Triggers/Events/Procedures/Functions	
	//Triggers
	$triggers = backuply_PMA_getTriggers($sdb, $link);
	foreach($triggers as $trigger){
		fwrite($handle, "\n".$trigger['drop']."\nDELIMITER //\n");
		fwrite($handle, $trigger['create']."// \nDELIMITER ;\n\n");
	}
	
	//Events
	$events = backuply_PMA_getEvents($sdb, $link);
	foreach($events as $event){
		fwrite($handle, "\n".$event['drop']."\nDELIMITER $$ \n-- \n-- Events \n--\n");
		fwrite($handle, $event['create']);
		fwrite($handle, "\n$$ \nDELIMITER ;\n\n");
	}
	
	//Functions
	$functions = backuply_PMA_getProceduresOrFunctions($sdb, 'FUNCTION', $link);
	foreach($functions as $function){
		fwrite($handle, "\n".$function['drop']."\nDELIMITER $$ \n-- \n-- Functions \n--\n");
		fwrite($handle, $function['create']);
		fwrite($handle, "\n$$ \nDELIMITER ;\n\n");
	}
	
	//Procedures
	$procedures = backuply_PMA_getProceduresOrFunctions($sdb, 'PROCEDURE', $link);
	foreach($procedures as $procedure){
		fwrite($handle, "\n".$procedure['drop']."\nDELIMITER $$ \n-- \n-- Procedures \n--\n");
		fwrite($handle, $procedure['create']);
		fwrite($handle, "\n$$ \nDELIMITER ;\n\n");
	}	
	
	$sresponse = backuply_PMA_exportFooter(); // Just to add the finishing lines
	fwrite($handle, $sresponse);
	fclose($handle);
	
	backuply_backup_stop_checkpoint();
	// Just check that file is created or not ??
	if(file_exists($sdbfile)){
	
		return true;
	}
	
	return false;
	
} //End of database backup

function backuply_PMA_getViews($db, $view, $crlf, $link){
	
	$schema_create = $auto_increment = $dump = '';
	$new_crlf = $crlf;
	
	// This is for foreign language characters
	//To read the values from the old DB in UTF8 format
	//backuply_mysql_query('SET NAMES "utf8mb4"', $link);
	
	// Complete view dump,
	// Whether to quote view and fields names or not
	backuply_mysql_query('SET SQL_QUOTE_SHOW_CREATE = 1', $link);
	
	// Create view structure
	$result = backuply_mysql_query('SHOW CREATE VIEW `'.$db.'`.`'.$view.'`', $link);

	// Construct the dump for the view structure
	$dump .=  '--' . $crlf
			. '-- Structure for view ' . '`' . $view.'`' . $crlf
			. '--' . $crlf
			. 'DROP VIEW IF EXISTS `' . $view . '`;' . $crlf . $crlf;
	
	if ($row = backuply_mysql_fetch_assoc($result)) {
			
		$create_query = $row['Create View'];
		
		preg_match('/DEFINER=(.*?) SQL/is', $create_query, $matches);
		$create_query = str_replace($matches[1], 'CURRENT_USER', $create_query);

		$schema_create .= $new_crlf . $dump;

		// Convert end of line chars to one that we want (note that MySQL doesn't return query it will accept in all cases)
		if (strpos($create_query, "(\r\n ")) {
			$create_query = str_replace("\r\n", $crlf, $create_query);
		} elseif (strpos($create_query, "(\n ")) {
			$create_query = str_replace("\n", $crlf, $create_query);
		} elseif (strpos($create_query, "(\r ")) {
			$create_query = str_replace("\r", $crlf, $create_query);
		}
		
		$schema_create .= $create_query;
	}
		
	backuply_mysql_free_result($result);
		
	// Dump the structure !!!
	$return['structure'] = $schema_create . ';' . $crlf;
	
	return $return;
}

function backuply_PMA_getTriggers($db, $link){
	$query = backuply_mysql_query('SHOW TRIGGERS FROM `' . $db . '`', $link);
	$result = array(); //added as empty so don't give warning when data is empty..
	
	while($trigger = backuply_mysql_fetch_assoc($query)){
		
		$one_result = array();
		$one_result['name'] = $trigger['Trigger'];
		$one_result['table'] = $trigger['Table'];
		$one_result['action_timing'] = $trigger['Timing'];
		$one_result['event_manipulation'] = $trigger['Event'];
		$one_result['definition'] = $trigger['Statement'];
		$one_result['definer'] = $trigger['Definer'];
		$one_result['full_trigger_name'] = '`'.$trigger['Trigger'].'`';
		$one_result['drop'] = 'DROP TRIGGER IF EXISTS `' . $db .'`.'. $one_result['full_trigger_name'].';';
		$one_result['create'] = 'CREATE TRIGGER '
			. $one_result['full_trigger_name'] . ' '
			. $trigger['Timing'] . ' '
			. $trigger['Event']
			. ' ON ' . '`'. $trigger['Table'].'`'
			. "\n" . ' FOR EACH ROW '
			. $trigger['Statement'] . "\n" . $delimiter . "\n";
			
		$result[] = $one_result;
	}

	// Sort results by name
	$name = array();
	foreach ($result as $value) {
		$name[] = $value['name'];
	}
	array_multisort($name, SORT_ASC, $result);
	
	return($result);
	
}

function backuply_PMA_getEvents($db, $link){
	
	$query = backuply_mysql_query('SHOW EVENTS FROM `' . $db . '`', $link);

	$result = array();
	while ($event = backuply_mysql_fetch_assoc($query)) {
			$one_result = array();
			$one_result['name'] = $event['Name'];
			$one_result['type'] = $event['Type'];
			$one_result['status'] = $event['Status'];
			$one_result['drop'] = 'DROP EVENT IF EXISTS `' . $db .'`.`'. $one_result['name'].'`;';
			$one_result['create'] = backuply_PMA_getDefinition($db, 'EVENT', $one_result['name'], $link);
			
			$result[] = $one_result;
	}
	
	// Sort results by name
	$name = array();
	foreach ($result as $value) {
		$name[] = $value['name'];
	}
	array_multisort($name, SORT_ASC, $result);

	return $result;
}

/**
 * returns the array of PROCEDURE/FUNCTION names
 *
 * @param string $db	db name
 * @param string $which PROCEDURE | FUNCTION | EVENT
 * @param string $link  connection link to the database
 *
 * @return array names of Procedures/Functions
 */
function backuply_PMA_getProceduresOrFunctions($db, $which, $link)
{
	$query = backuply_mysql_query('SHOW ' . $which . ' STATUS;', $link);
	$result = array();
	
	while($one_show = backuply_mysql_fetch_assoc($query)) {
		if ($one_show['Db'] == $db && $one_show['Type'] == $which) {
			$one_show['drop'] = 'DROP '.$which.' IF EXISTS `' . $db .'`.`'. $one_show['Name'].'`;';
			$one_show['create'] = backuply_PMA_getDefinition($db, $which, $one_show['Name'], $link);
			
			$result[] = $one_show;
		}
	}
	
	return $result;
}

/**
 * returns the definition of a specific PROCEDURE, FUNCTION or EVENT
 *
 * @param string $db	db name
 * @param string $which PROCEDURE | FUNCTION | EVENT
 * @param string $name  the procedure|function|event name
 * @param string $link  connection link to the database
 *
 * @return string the definition
 */
function backuply_PMA_getDefinition($db, $which, $name, $link)
{
	$returned_field = array(
		'PROCEDURE' => 'Create Procedure',
		'FUNCTION'  => 'Create Function',
		'EVENT'	 => 'Create Event'
	);
	$query = backuply_mysql_query('SHOW CREATE '.$which.' `'.$db.'`.`'.$name.'`;', $link);
	
	if ($res = backuply_mysql_fetch_assoc($query)){
		return($res[$returned_field[$which]]);
	}
	
}

// Internal function to add slashes to row values 
function backuply_PMA_sqlAddslashes(&$a_string = '', $is_like = false, $crlf = false, $php_code = false) {

	if ($is_like) {
		$a_string = str_replace('\\', '\\\\\\\\', $a_string);
	} else {
		$a_string = str_replace('\\', '\\\\', $a_string);
	}

	if ($crlf) {
		$a_string = str_replace("\n", '\n', $a_string);
		$a_string = str_replace("\r", '\r', $a_string);
		$a_string = str_replace("\t", '\t', $a_string);
	}

	if ($php_code) {
		$a_string = str_replace('\'', '\\\'', $a_string);
	} else {
		$a_string = str_replace('\'', '\'\'', $a_string);
	}

	return $a_string;
} // end of the 'backuply_PMA_sqlAddslashes()' function


// Form the table structure && the alter queries if any !! 
function backuply_PMA_getTableDef($db, $table, $crlf, $show_dates = false, $add_semicolon = true, $link) {
	
	global $sql_drop_table, $sql_alter;
	global $sql_constraints;
	global $sql_constraints_query; // just the text of the query
	global $sql_drop_foreign_keys;

	$schema_create = $auto_increment = $sql_constraints = '';
	$new_crlf = $crlf;
	
	// Get the Status of the table so as to produce the auto increment value
	$qresult = backuply_mysql_query('SHOW TABLE STATUS FROM `'.$db.'` LIKE \''.$table.'\'', $link);

	// Handle auto-increment values
	if (backuply_mysql_num_rows($qresult) > 0) {
		
		$tmpres = backuply_mysql_fetch_assoc($qresult);
		
		// Is auto-increment value is set ??
		if(!empty($tmpres['Auto_increment'])){
			$auto_increment .= ' AUTO_INCREMENT=' . $tmpres['Auto_increment'] . ' ';
		}
	
	}
	// Free resourse
	backuply_mysql_free_result($qresult);
	
	//added as empty so don't give warning when data is empty..
	$dump = '';
	
	// Construct the dump for the table structure
	$dump .=  '--' . $crlf
			. '-- Table structure for table ' . '`' . $table.'`' . $crlf
			. '--' . $crlf . $crlf;
		 
	$schema_create .= $new_crlf . $dump;

	// Complete table dump,
	// Whether to quote table and fields names or not
	backuply_mysql_query('SET SQL_QUOTE_SHOW_CREATE = 1', $link);
	
	// Create table structure
	$result = backuply_mysql_query('SHOW CREATE TABLE `'.$db.'`.`'.$table.'`', $link);
	
	if ($row = backuply_mysql_fetch_assoc($result)) {
		
		$create_query = $row['Create Table'];
		unset($row);

		// Convert end of line chars to one that we want (note that MySQL doesn't return query it will accept in all cases)
		if (strpos($create_query, "(\r\n ")) {
			$create_query = str_replace("\r\n", $crlf, $create_query);
		} elseif (strpos($create_query, "(\n ")) {
			$create_query = str_replace("\n", $crlf, $create_query);
		} elseif (strpos($create_query, "(\r ")) {
			$create_query = str_replace("\r", $crlf, $create_query);
		}

		// are there any constraints to cut out?
		if (preg_match('@CONSTRAINT|FOREIGN[\s]+KEY@', $create_query)) {

			// Split the query into lines, so we can easily handle it.
			// We know lines are separated by $crlf (done few lines above).	
			$sql_lines = explode($crlf, $create_query);
			$sql_count = count($sql_lines);

			// Lets find first line with constraints
			for ($i = 0; $i < $sql_count; $i++) {
				if (preg_match('@^[\s]*(CONSTRAINT|FOREIGN[\s]+KEY)@', $sql_lines[$i])) {
				 	break;
				}
			}

			// If we really found a constraint
			if ($i != $sql_count) {
				
				// remove , from the end of create statement
				$sql_lines[$i - 1] = preg_replace('@,$@', '', $sql_lines[$i - 1]);

				// comments for current table
				$sql_constraints .= $crlf
								 . backuply_PMA_exportComment()
								 . backuply_PMA_exportComment('Constraints for table ' . '`' . $table.'`')
								 . backuply_PMA_exportComment();
				
				// Let's do the work
				$sql_constraints_query .= 'ALTER TABLE `'.$table.'`' . $crlf;
				$sql_constraints .= 'ALTER TABLE `'.$table.'`' . $crlf;
				$sql_drop_foreign_keys .= 'ALTER TABLE `'.$table.'` `'.$db.'`' . $crlf;

				$first = TRUE;
				for ($j = $i; $j < $sql_count; $j++) {
					if (preg_match('@CONSTRAINT|FOREIGN[\s]+KEY@', $sql_lines[$j])) {
						if (!$first) {
							$sql_constraints .= $crlf;
						}
						if (strpos($sql_lines[$j], 'CONSTRAINT') === FALSE) {
							$tmp_str = preg_replace('/(FOREIGN[\s]+KEY)/', 'ADD \1', $sql_lines[$j]);
							$sql_constraints_query .= $tmp_str;
							$sql_constraints .= $tmp_str;
						} else {
							$tmp_str = preg_replace('/(CONSTRAINT)/', 'ADD \1', $sql_lines[$j]);
							$sql_constraints_query .= $tmp_str;
							$sql_constraints .= $tmp_str;
							preg_match('/(CONSTRAINT)([\s])([\S]*)([\s])/', $sql_lines[$j], $matches);
							if (! $first) {
								$sql_drop_foreign_keys .= ', ';
							}
							$sql_drop_foreign_keys .= 'DROP FOREIGN KEY ' . $matches[3];
						}
						$first = FALSE;
					} else {
						break;
					}
				}
				$sql_constraints .= ';' . $crlf;
				$sql_constraints_query .= ';';
				
				// Dump the alter queries!!!
				$return['alter'] = $sql_constraints; 
				
				$create_query = implode($crlf, array_slice($sql_lines, 0, $i)) . $crlf . implode($crlf, array_slice($sql_lines, $j, $sql_count - 1));
				unset($sql_lines);
			}
		}
		$schema_create .= $create_query;
	}

	// remove a possible "AUTO_INCREMENT = value" clause
	// that could be there starting with MySQL 5.0.24
	$schema_create = preg_replace('/AUTO_INCREMENT\s*=\s*([0-9])+/', '', $schema_create);

	$schema_create .= $auto_increment;
		
	backuply_mysql_free_result($result);
		
	// Dump the structure !!!
	$return['structure'] = $schema_create . ($add_semicolon ? ';' . $crlf : '');
	
	return $return;
	 
} // end of the 'backuply_PMA_getTableDef()' function

// Internal function to get meta details about the database 
function backuply_PMA_DBI_get_fields_meta($sresult) {
	$fields	   = array();
	$num_fields   = mysql_num_fields($sresult);
	for ($i = 0; $i < $num_fields; $i++) {
		$field = mysql_fetch_field($sresult, $i);
		$field->flags = mysql_field_flags($sresult, $i);
		$field->orgtable = mysql_field_table($sresult, $i);
		$field->orgname = mysql_field_name($sresult, $i);
		$fields[] = $field;
	}
	return $fields;
}

// Export data - values 
function backuply_PMA_exportData($db, $table, $crlf, $handle, $link){
	
	global $current_row;
	$count = 10000;
	$limit = 0;

	// We have modified this code because we were getting error if inserts were >50000
	if(strpos($table, 'options') !== false){
		$cnt_qry = 'SELECT count(*) FROM `'.$db . '`.`' . $table . '` WHERE option_name != "backuply_status"';
	}else{
		$cnt_qry = 'SELECT count(*) FROM `'.$db . '`.`' . $table . '`';	
	}
	
	$cnt_res = backuply_mysql_fetch_row(backuply_mysql_query($cnt_qry, $link));
	
	if(strpos($table, 'options') !== false){
		$sql_query  = 'SELECT * FROM `'.$db . '`.`' . $table . '` WHERE option_name != "backuply_status" LIMIT 0,10000';
	}else{
		$sql_query  = 'SELECT * FROM `'.$db . '`.`' . $table . '` LIMIT 0,10000';	
	}
	
	$formatted_table_name = '`' . $table . '`';
	
	$squery = backuply_mysql_query($sql_query, $link);
	
	$fields_cnt = backuply_mysql_num_fields($squery);

	// Get field information
	if(extension_loaded('mysqli')){
		$fields_meta	= backuply_getFieldsMeta($squery);
	}else{
		$fields_meta	= backuply_PMA_DBI_get_fields_meta($squery);
	}
	
	$field_flags	= array();
	for ($j = 0; $j < $fields_cnt; $j++) {
		$field_flags[$j] = backuply_mysql_field_flags($squery, $j);
	}

	for ($j = 0; $j < $fields_cnt; $j++) {
		$field_set[$j] = '`'.$fields_meta[$j]->name . '`';
	}

	$sql_command = 'INSERT';
   
	$insert_delayed = '';
	$separator = ',';

	$schema_insert = $sql_command . $insert_delayed .' INTO `' . $table . '` VALUES';
	
	$search	   = array("\x00", "\x0a", "\x0d", "\x1a"); //\x08\\x09, not required
	$replace	  = array('\0', '\n', '\r', '\Z');
	$current_row  = 0;
	$new_query  = 0;
	$query_length = 0;

	$schema_insert .= $crlf;
	for($i = $cnt_res[0]; $i >= 0; $i--){
		
		// Now if 10000 rows has been processed than select next.
		if($count == 0){
			// Now free the result for preventing memory exhaust
			backuply_mysql_free_result($squery);
			$count = 10000;
			$limit = $limit+10000;
			$sql_query  = 'SELECT * FROM `'.$db . '`.`' . $table . '` LIMIT '.($limit).', 10000';
			$squery= backuply_mysql_query($sql_query, $link);
		}
		
		$row = backuply_mysql_fetch_array($squery);
		
		// If we get empty result than break the loop
		if(!$row){
			break;
		}
		
		if ($current_row == 0) {
			$head = backuply_PMA_exportComment()
				  . backuply_PMA_exportComment('Dumping data for table' . ' ' . $formatted_table_name)
				  . backuply_PMA_exportComment()
				  . $crlf;
			fwrite($handle, $head);
		}
		$current_row++;
		
		if ($current_row == 1 || $new_query == 1) {
			fwrite($handle, $schema_insert .'(');
		}else{
			fwrite($handle, ','.$crlf.'(');
		}
		
		$add_comma = 0;
		for ($j = 0; $j < $fields_cnt; $j++) {
			
			$separator = ($add_comma > 0 ? ', ' : '');
			
			// NULL
			if (!isset($row[$j]) || is_null($row[$j])) {
				fwrite($handle, $separator . 'NULL');
			// a number
			// timestamp is numeric on some MySQL 4.1, BLOBs are sometimes numeric
			} elseif ($fields_meta[$j]->numeric && $fields_meta[$j]->type != 'timestamp' 
					&& !$fields_meta[$j]->blob) {
				fwrite($handle, $separator . $row[$j]);
			} elseif ($fields_meta[$j]->type == 'bit') {
				fwrite($handle, $separator . backuply_PMA_printableBitValue($row[$j], $fields_meta[$j]->length));
			} else { 
				backuply_PMA_sqlAddslashes($row[$j]);
				fwrite($handle, $separator . '\'' . str_replace($search, $replace, $row[$j]) . '\'');				
			} // end if
			
			$query_length += strlen($row[$j]);
			
			$add_comma++;
			$new_query = 0;
		} // end for
		
		fwrite($handle, ')');
		
		// Stop extended insert after 50K chars and open a new INSERT
		if($query_length > 50000){
			$query_buffer = ';' . $crlf;
			fwrite($handle, $query_buffer);
			$add_comma = 0;
			$new_query = 1;
			$query_length = 0;
		}
	
		// Decrement till 0 so that next 10000 rows can be selected
		$count--;
		
	}// End of FOR
	
	if ($current_row > 0) {   
		$query_buffer = ';' . $crlf;
		fwrite($handle, $query_buffer);
	}
	
	// Free resourses
	backuply_mysql_free_result($squery);
	
	$end_line = (!empty($query_buffer) ? $crlf : '' ). backuply_PMA_exportComment('--------------------------------------------------------');
	fwrite($handle, $end_line);
	//return $query_buffer . $end_line;
		
} 

function backuply_PMA_exportComment($text = '')
{
	$crlf = "\n";
	$ret = '--' . (empty($text) ? '' : ' ') . $text . $crlf;
	return $ret;
}

function backuply_PMA_exportHeader($db, $ser_ver)
{
	$crlf = "\n";  

	$head  =  backuply_PMA_exportComment('Softaculous SQL Dump')
		   .  backuply_PMA_exportComment('http://www.softaculous.com')
		   .  backuply_PMA_exportComment()
		   .  backuply_PMA_exportComment('Host: localhost')
		   .  backuply_PMA_exportComment('Generation Time: '. date("F j, Y, g:i a") .'')
		   .  backuply_PMA_exportComment('Server version: '. $ser_ver .'')
		   .  backuply_PMA_exportComment('PHP Version' . ': ' . phpversion())
		   .  $crlf;

	/* We want exported AUTO_INCREMENT fields to have still same value, do this only for recent MySQL exports */
	$head .=  'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";' . $crlf;
	
	/* Change timezone if we should export timestamps in UTC */
	$head .= 'SET time_zone = "+00:00";' . $crlf . $crlf;
  
	// by default we use the connection charset
	$set_names = 'utf8mb4';
		
	$head .=  $crlf
		   . '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;' . $crlf
		   . '/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;' . $crlf
		   . '/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;' . $crlf
		   . '/*!40101 SET NAMES ' . $set_names . ' */;' . $crlf . $crlf;
	
	$head .= backuply_PMA_exportComment()
		  . backuply_PMA_exportComment('Database: `' . $db . '`')
		  . backuply_PMA_exportComment()
		  . $crlf
		  . backuply_PMA_exportComment('--------------------------------------------------------');

	return $head;

}

function backuply_PMA_exportFooter()
{
	$crlf = "\n";
	$foot = '';

	$foot .=  $crlf
	   . '/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;' . $crlf
	   . '/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;' . $crlf
	   . '/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;' . $crlf;
	
	return $foot;
}

function backuply_PMA_sversion($link){

	// Get version
	$version = backuply_mysql_query('SELECT VERSION()', $link);
	$version = backuply_mysql_fetch_assoc($version);
	
	// Explode to extract version
	$version = explode('-', $version['VERSION()']);
	return $version[0];
	
}

function backuply_PMA_printableBitValue($value, $length){
	// if running on a 64-bit server or the length is safe for decbin()
	if (PHP_INT_SIZE == 8 || $length < 33) {
		$printable = decbin($value);
	} else {
		// FIXME: does not work for the leftmost bit of a 64-bit value
		$i = 0;
		$printable = '';
		while ($value >= pow(2, $i)) {
			++$i;
		}
		if ($i != 0) {
			--$i;
		}

		while ($i >= 0) {
			if ($value - pow(2, $i) < 0) {
				$printable = '0' . $printable;
			} else {
				$printable = '1' . $printable;
				$value = $value - pow(2, $i);
			}
			--$i;
		}
		$printable = strrev($printable);
	}
	$printable = str_pad($printable, $length, '0', STR_PAD_LEFT);
	return $printable;
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

function backuply_mysql_fetch_array($result){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_fetch_array($result);
	}else{
		$return = @mysql_fetch_array($result);
	}
	
	return $return;
}

function backuply_mysql_fetch_assoc($result){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_fetch_assoc($result);
	}else{
		$return = @mysql_fetch_assoc($result);
	}
	
	return $return;
}

function backuply_mysql_fetch_row($result){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_fetch_row($result);
	}else{
		$return = @mysql_fetch_row($result);
	}
	
	return $return;
}


function backuply_mysql_field_flags($result, $i){
	
	if(!extension_loaded('mysqli')){
		return mysql_field_flags($result, $i);
	}
	
	$f = mysqli_fetch_field_direct($result, $i);
	$type = $f->type;
	$charsetnr = $f->charsetnr;
	$f = $f->flags;
	$flags = '';
	if ($f & MYSQLI_UNIQUE_KEY_FLAG) {
		$flags .= 'unique ';
	}
	if ($f & MYSQLI_NUM_FLAG) {
		$flags .= 'num ';
	}
	if ($f & MYSQLI_PART_KEY_FLAG) {
		$flags .= 'part_key ';
	}
	if ($f & MYSQLI_SET_FLAG) {
		$flags .= 'set ';
	}
	if ($f & MYSQLI_TIMESTAMP_FLAG) {
		$flags .= 'timestamp ';
	}
	if ($f & MYSQLI_AUTO_INCREMENT_FLAG) {
		$flags .= 'auto_increment ';
	}
	if ($f & MYSQLI_ENUM_FLAG) {
		$flags .= 'enum ';
	}
	// See http://dev.mysql.com/doc/refman/6.0/en/c-api-datatypes.html:
	// to determine if a string is binary, we should not use MYSQLI_BINARY_FLAG
	// but instead the charsetnr member of the MYSQL_FIELD
	// structure. Watch out: some types like DATE returns 63 in charsetnr
	// so we have to check also the type.
	// Unfortunately there is no equivalent in the mysql extension.
	if (($type == MYSQLI_TYPE_TINY_BLOB || $type == MYSQLI_TYPE_BLOB
		|| $type == MYSQLI_TYPE_MEDIUM_BLOB || $type == MYSQLI_TYPE_LONG_BLOB
		|| $type == MYSQLI_TYPE_VAR_STRING || $type == MYSQLI_TYPE_STRING)
		&& 63 == $charsetnr
	) {
		$flags .= 'binary ';
	}
	if ($f & MYSQLI_ZEROFILL_FLAG) {
		$flags .= 'zerofill ';
	}
	if ($f & MYSQLI_UNSIGNED_FLAG) {
		$flags .= 'unsigned ';
	}
	if ($f & MYSQLI_BLOB_FLAG) {
		$flags .= 'blob ';
	}
	if ($f & MYSQLI_MULTIPLE_KEY_FLAG) {
		$flags .= 'multiple_key ';
	}
	if ($f & MYSQLI_UNIQUE_KEY_FLAG) {
		$flags .= 'unique_key ';
	}
	if ($f & MYSQLI_PRI_KEY_FLAG) {
		$flags .= 'primary_key ';
	}
	if ($f & MYSQLI_NOT_NULL_FLAG) {
		$flags .= 'not_null ';
	}
	return trim($flags);
}


function backuply_mysql_num_rows($result){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_num_rows($result);
	}else{
		$return = @mysql_num_rows($result);
	}
	
	return $return;
}

function backuply_mysql_num_fields($result){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_num_fields($result);
	}else{
		$return = @mysql_num_fields($result);
	}
	
	return $return;
}

function backuply_mysql_free_result($result){
	
	if(extension_loaded('mysqli')){
		$return = @mysqli_free_result($result);
	}else{
		$return = @mysql_free_result($result);
	}
	
	return $return;
}

function backuply_getFieldsMeta($result){
	// Build an associative array for a type look up
	
	if(!defined('MYSQLI_TYPE_VARCHAR')){
		define('MYSQLI_TYPE_VARCHAR', 15);
	}
	
	$typeAr = array();
	$typeAr[MYSQLI_TYPE_DECIMAL]	 = 'real';
	$typeAr[MYSQLI_TYPE_NEWDECIMAL]  = 'real';
	$typeAr[MYSQLI_TYPE_BIT]		 = 'int';
	$typeAr[MYSQLI_TYPE_TINY]		= 'int';
	$typeAr[MYSQLI_TYPE_SHORT]	   = 'int';
	$typeAr[MYSQLI_TYPE_LONG]		= 'int';
	$typeAr[MYSQLI_TYPE_FLOAT]	   = 'real';
	$typeAr[MYSQLI_TYPE_DOUBLE]	  = 'real';
	$typeAr[MYSQLI_TYPE_NULL]		= 'null';
	$typeAr[MYSQLI_TYPE_TIMESTAMP]   = 'timestamp';
	$typeAr[MYSQLI_TYPE_LONGLONG]	= 'int';
	$typeAr[MYSQLI_TYPE_INT24]	   = 'int';
	$typeAr[MYSQLI_TYPE_DATE]		= 'date';
	$typeAr[MYSQLI_TYPE_TIME]		= 'time';
	$typeAr[MYSQLI_TYPE_DATETIME]	= 'datetime';
	$typeAr[MYSQLI_TYPE_YEAR]		= 'year';
	$typeAr[MYSQLI_TYPE_NEWDATE]	 = 'date';
	$typeAr[MYSQLI_TYPE_ENUM]		= 'unknown';
	$typeAr[MYSQLI_TYPE_SET]		 = 'unknown';
	$typeAr[MYSQLI_TYPE_TINY_BLOB]   = 'blob';
	$typeAr[MYSQLI_TYPE_MEDIUM_BLOB] = 'blob';
	$typeAr[MYSQLI_TYPE_LONG_BLOB]   = 'blob';
	$typeAr[MYSQLI_TYPE_BLOB]		= 'blob';
	$typeAr[MYSQLI_TYPE_VAR_STRING]  = 'string';
	$typeAr[MYSQLI_TYPE_STRING]	  = 'string';
	$typeAr[MYSQLI_TYPE_VARCHAR]	 = 'string'; // for Drizzle
	// MySQL returns MYSQLI_TYPE_STRING for CHAR
	// and MYSQLI_TYPE_CHAR === MYSQLI_TYPE_TINY
	// so this would override TINYINT and mark all TINYINT as string
	// https://sourceforge.net/p/phpmyadmin/bugs/2205/
	//$typeAr[MYSQLI_TYPE_CHAR]		= 'string';
	$typeAr[MYSQLI_TYPE_GEOMETRY]	= 'geometry';
	$typeAr[MYSQLI_TYPE_BIT]		 = 'bit';

	$fields = mysqli_fetch_fields($result);

	// this happens sometimes (seen under MySQL 4.0.25)
	if (!is_array($fields)) {
		return false;
	}

	foreach ($fields as $k => $field) {
		$fields[$k]->_type = $field->type;
		$fields[$k]->type = $typeAr[$field->type];
		$fields[$k]->_flags = $field->flags;
		$fields[$k]->flags = backuply_mysql_field_flags($result, $k);

		// Enhance the field objects for mysql-extension compatibilty
		//$flags = explode(' ', $fields[$k]->flags);
		//array_unshift($flags, 'dummy');
		$fields[$k]->multiple_key
			= (int) (bool) ($fields[$k]->_flags & MYSQLI_MULTIPLE_KEY_FLAG);
		$fields[$k]->primary_key
			= (int) (bool) ($fields[$k]->_flags & MYSQLI_PRI_KEY_FLAG);
		$fields[$k]->unique_key
			= (int) (bool) ($fields[$k]->_flags & MYSQLI_UNIQUE_KEY_FLAG);
		$fields[$k]->not_null
			= (int) (bool) ($fields[$k]->_flags & MYSQLI_NOT_NULL_FLAG);
		$fields[$k]->unsigned
			= (int) (bool) ($fields[$k]->_flags & MYSQLI_UNSIGNED_FLAG);
		$fields[$k]->zerofill
			= (int) (bool) ($fields[$k]->_flags & MYSQLI_ZEROFILL_FLAG);
		$fields[$k]->numeric
			= (int) (bool) ($fields[$k]->_flags & MYSQLI_NUM_FLAG);
		$fields[$k]->blob
			= (int) (bool) ($fields[$k]->_flags & MYSQLI_BLOB_FLAG);
	}
	return $fields;
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

// To delete any file created while backing up
function backuply_clean_on_stop() {
	global $data, $backuply;
	
	backuply_status_log('Stopping your backup', 'info');
	
	if(!isset($data['name'])) {
		return;
	}
	
	$ext = !empty($data['ext']) ? $data['ext'] : 'tar.gz';
	
	//backuply_rmdir_recursive_fn(BACKUPLY_BACKUP_DIR.'backups/tmp');
	
	// clean the tmp files
	backuply_clean($data);
	
	// Delete the tar file with dot at the start
	if(file_exists($data['path'] . '/.' . $data['name'] .'.'. $ext)) {
		@unlink($data['path'] . '/.' . $data['name'] .'.'. $ext);
	}
	
	// Deletes File from remote
	if(!empty($backuply['status']['remote_file_path'])){
		backuply_log('Removing remote file');
		@unlink($backuply['status']['remote_file_path']);
		@unlink($backuply['status']['successfile']);
	}
	
	backuply_status_log('Cleaning the backup folder', 'info', -1);
	
	// Delete the Info file
	if(file_exists(BACKUPLY_BACKUP_DIR . 'backups_info/' . $data['name'] . '.php')) {
		@unlink(BACKUPLY_BACKUP_DIR . 'backups_info/' . $data['name'] . '.php');
	}
}

// To check if backup has been stopped
function backuply_backup_stop_checkpoint() {
	global $data, $wpdb, $backuply;
	
	$query = 'SELECT * FROM `'.$wpdb->prefix.'options` WHERE `option_name` = "backuply_backup_stopped"';
	$result = $wpdb->get_results($query);
	
	if(!empty($result[0]->option_value)) {
		delete_option('backuply_backup_stopped');
		backuply_clean_on_stop();
		backuply_status_log('Backup Successfully Stopped', 'success');
		unset($backuply['status']['incomplete_upload']);
		
		backuply_die('stopped');
	}
}

//Updates the Status in the data base to be used when incomplete
function backuply_update_status(){
	global $data, $backuply;
	
	//An array to store only the required fields in sql.
	
	if(isset($backuply['status']['successfile']) && !isset($GLOBALS['successfile'])) {
		$GLOBALS['successfile'] = $backuply['status']['successfile'];
	}
	
	if(!isset($backuply['status']['incomplete_upload']) && empty($GLOBALS['end_file'])){
		return;
	}

	$backuply['status']['name'] = $data['name'];
	$backuply['status']['last_file'] = $GLOBALS['end_file'];
	$backuply['status']['backup_db'] = $data['backup_db'];
	$backuply['status']['backup_dir'] = $data['backup_dir'];
	$backuply['status']['successfile'] = isset($GLOBALS['successfile']) ? $GLOBALS['successfile'] : '';
	
	//Store the $backuply['status'] variable in sql.
	update_option('backuply_status', $backuply['status']);
}

function backuply_info_json(&$info_data = []){
	
	global $data, $can_write, $backuply;
	
	//Get the current Users Information
	$current_user = wp_get_current_user();
	
	if(!function_exists('get_home_path')){
		include_once backuply_cleanpath(ABSPATH) . '/wp-admin/includes/file.php';
	}
	
	//Store all needed information for the info file
	$info_data = array();
	$info_data['name'] = $GLOBALS['data']['name']; 
	$info_data['backup_dir'] = $GLOBALS['data']['backup_dir'];
	$info_data['backup_db'] = $GLOBALS['data']['backup_db'];
	$info_data['email'] = $current_user->user_email;
	$info_data['date_time'] = date("Y-m-d H:i:a");
	$info_data['btime'] = time();
	$info_data['auto_backup'] = isset($data['auto_backup']) ? $data['auto_backup'] : false;
	$info_data['ext'] = 'tar.gz';
	$info_data['size'] = isset($backuply['status']['remote_file_path']) ? filesize($backuply['status']['remote_file_path']) : filesize($GLOBALS['successfile']);
	$info_data['backup_site_url'] = get_site_url();
	$info_data['backup_site_path'] = backuply_cleanpath(get_home_path());
	
	if(isset($data['backup_location']) && !empty($data['backup_location'])){
		$info_data['backup_location'] = $data['backup_location'];
	}

	//Encode the Data and store it in a file
	return "<?php exit();?>\n".json_encode($info_data, JSON_PRETTY_PRINT);
	
}

// Uploads the backup log file
function backuply_upload_log() {
	global $backuply, $data;

	$backuply['status']['successfile'] = BACKUPLY_BACKUP_DIR . $data['name'] . '_log.php';
	
	// Upload the info file as well
	$GLOBALS['start_pos'] = 0;
	unset($backuply['status']['init_data']);
	unset($backuply['status']['proto']);
	$backuply['status']['proto_file_size'] = filesize($backuply['status']['successfile']);
	
	$remote_fp = fopen(dirname($backuply['status']['remote_file_path']).'/'.$data['name'].'.log', 'ab');

	fwrite($remote_fp, file_get_contents($backuply['status']['successfile']));
	fclose($remote_fp);
}

function backuply_die($txt){
	global $data, $can_write, $backuply;
	
	$email = get_option('backuply_notify_email_address');
	$site_url = get_site_url();
	backuply_update_status(); //Updates the Globals in the Status
	
	// Was there an error ?
	if(!empty($GLOBALS['error'])){
	
		// Deletes File from remote
		if(!empty($backuply['status']['remote_file_path'])){
			backuply_log('Removing remote file');
			@unlink($backuply['status']['remote_file_path']);
			@unlink($backuply['status']['successfile']);
		}
	
		$error = $GLOBALS['error'];
		$error_string = '<b>Below are the error(s) :</b> <br />';
	
		foreach($error as $ek => $ev){
			$error_string .= '* '.$ev.'<br />';
		}

		backuply_status_log($error_string, 'info', 100);
		
		
		// Notify user about the backup failure
		$mail = array();
		$mail['to'] = $email;   
		$mail['subject'] = 'Backup of your WordPress installation failed - Backuply';
		$mail['headers'] = "Content-Type: text/html; charset=UTF-8\r\n";
		$mail['message'] = 'Hi, <br><br>

The last backup of your WordPress installation was failed. <br>
Installation URL : '.$site_url.' <br>
'.$error_string.' <br><br>


Regards,<br>
Backuply';
		
		// Send Email
		wp_mail($mail['to'], $mail['subject'], $mail['message'], $mail['headers']);
		
		backuply_status_log('Backup failed', 'error', 100);
		backuply_report_error($GLOBALS['error']);		
		
		if($timestamp = wp_next_scheduled('backuply_timeout_check', array('is_restore' => false))) {
			wp_unschedule_event($timestamp, 'backuply_timeout_check', array('is_restore' => false));
		}
		
		delete_option('backuply_status');
		backuply_clean($data);
		backuply_copy_log_file(false); // For Last Log File

		die();
	}	
	
	if($txt == 'DONE'){
		backuply_backup_stop_checkpoint();
		
		//Create & store the file in the backups_info folder
		$file = fopen(BACKUPLY_BACKUP_DIR.'backups_info/'.$GLOBALS['data']['name'].'.php', 'w');
		fwrite($file, backuply_info_json($info_data));
		fclose($file);
		
		// Send the mail
		if(!empty($email)){
			//backuply_log(' email to : '.$email);
			//$backup_path = (!empty($GLOBALS['is_remote']) ? '/'.$GLOBALS['data']['name'] : $GLOBALS['successfile'] );

			if(!empty($GLOBALS['is_remote'])){
				$backup_location = 'Backup Location : '.$GLOBALS['is_remote_loc']['name'];
				$backup_path = '/'.$GLOBALS['data']['name'];
			}else{
				$backup_location = '';
				$backup_path = $GLOBALS['successfile'];
			}

			$mail = array();
				$mail['to'] = $email;   
				$mail['subject'] = 'Backup of your WordPress installation - Backuply';
				$mail['message'] = 'Hi,

The backup of your WordPress installation was completed successfully.
The details are as follows :
Installation Path : '.$GLOBALS['data']['softpath'].'
Installation URL : '.$site_url.'
Backup Path : '.$backup_path.'
'.$backup_location.'

Regards,
Backuply';

			wp_mail($mail['to'], $mail['subject'], $mail['message']);
			//backuply_log(' mail data : '. var_export($mail, 1));
		}
		
		backuply_status_log('Archive created with a file size of '. backuply_format_size($info_data['size']) , 'info', 100);
		update_option('backuply_last_backup', time());
		backuply_status_log('Backup Successfully Completed', 'success', 100);
		
		backuply_copy_log_file(false); // For Last Log File
		backuply_copy_log_file(false, $info_data['name']); // Log file for that specific backup
		
		if(isset($backuply['status']['remote_file_path'])) {
			backuply_upload_log();
		}
	}
	
	if(strpos($txt, 'INCOMPLETE') !== FALSE) {
		backuply_log('Going to next loop - '.($backuply['status']['loop'] + 1));
		backuply_backup_curl('backuply_curl_backup');
		die();
	}
	
	if($txt === 'incomplete_upload' || isset($backuply['status']['incomplete_upload'])) {
		update_option('backuply_status', $backuply['status']);
		backuply_backup_curl('backuply_curl_upload');
		die();
	}

	if($timestamp = wp_next_scheduled('backuply_timeout_check', array('is_restore' => false))) {
		wp_unschedule_event($timestamp, 'backuply_timeout_check', array('is_restore' => false));
	}
	
	delete_option('backuply_status');
	backuply_clean($data);
	die();
}

// Clean the Backup files
function backuply_clean($data){

	if(isset($GLOBALS['bfh']) && $GLOBALS['bfh']) {
		foreach($GLOBALS['bfh'] as $v){
			@fclose($v);
		}
	}
	
	// Delete tmp/ folder only if the process was completed
	if(empty($GLOBALS['end_file'])){
		backuply_rmdir_recursive_fn($data['path'].'/tmp/'.$data['name']);
	}
	
	return false;
}

// Requests backup via curl
function backuply_backup_curl($action) {
	$config = backuply_get_config();

	if(empty($config['BACKUPLY_KEY'])) {
		backuply_kill_process();
		return;
	}
	
	$config['BACKUPLY_KEY'] = urlencode($config['BACKUPLY_KEY']);

	$url = site_url() . '/?action='.$action.'&backuply_key='. $config['BACKUPLY_KEY'];

	wp_remote_get($url, array(
		'timeout' => 5,
		'blocking' => false,
	));
	
	die();
}

function backuply_remote_upload($finished = false){
	global $backuply, $error;

	// Do we have a remote file ?
	if(empty($backuply['status']['successfile']) || !file_exists($backuply['status']['successfile'])){
		$error['fopen_failed'] = 'Upload Failed! Because the file is not present on the server';
		unset($backuply['status']['incomplete_upload']);
		backuply_die('upload_failed');
	}
	
	if(empty($backuply['status']['init_pos'])){
		$backuply['status']['init_pos'] = 0;
	}	
	$GLOBALS['start_pos'] = $backuply['status']['init_pos'];
	$backuply['status']['proto_file_size'] = filesize($backuply['status']['successfile']);
	
	$remote_fp = fopen($backuply['status']['remote_file_path'], 'ab');

	if($remote_fp == false){
		$error['fopen_failed'] = 'Unable to open the remote location for writing the backup data. Please make sure the Backup Location details and credentials are correct !';
		unset($backuply['status']['incomplete_upload']);
		backuply_die('fopen_failed');
	}
	
	backuply_status_log('Upload Start Position (L'.$backuply['status']['loop'].') : '.$backuply['status']['init_pos']);
	
	$backuply['status']['chunk'] = 262144; // 2MB
	$file_size = filesize($backuply['status']['successfile']);
	$chunks = ceil($file_size / $backuply['status']['chunk']);
	$chunk_no = isset($backuply['status']['chunk_no']) ? $backuply['status']['chunk_no'] : 1;
	
	while($chunk_no <= $chunks) {
		$backuply['status']['chunk_no'] = $chunk_no;
		
		if(!empty($error)) {
			backuply_die('uploaderror');
		}
		
		// Timeout check
		if(time() + 5 > $GLOBALS['end']) {
			backuply_log('Upload: Short on time!');
			$backuply['status']['incomplete_upload'] = true;
			
			if(!isset($backuply['status']['init_data'])) {
				$backuply['status']['init_data'] = $backuply['status']['protocol'];
			};
			
			backuply_status_log('Upload Time Closing (L'.$backuply['status']['loop'].') : '.$backuply['status']['init_pos']);
			
			@fclose($remote_fp);
			
			backuply_die('incomplete_upload');
			die();
		}
		
		backuply_backup_stop_checkpoint();
		
		// For last chunk
		if($chunk_no == $chunks) {
			$backuply['status']['chunk'] = $file_size - $backuply['status']['init_pos'];
			unset($backuply['status']['incomplete_upload']);
		}
		
		$content = file_get_contents($backuply['status']['successfile'], false, null, $backuply['status']['init_pos'], $backuply['status']['chunk']);
		$clen = strlen($content);

		if(!empty($content)){
			fwrite($remote_fp, $content, $clen); // Write to the stream
			
			// If we had to retry then we should use the start_pos to update init_pos
			if(!empty($backuply['status']['upload_retry'])){
				$backuply['status']['init_pos'] = $GLOBALS['start_pos']; // Update length
				$backuply['status']['upload_retry'] = false;
			} else {
				$backuply['status']['init_pos'] += $clen; // Update Length
			}
			
			
		}
		$content = '';
		
		backuply_status_log('Uploaded till (L'.$backuply['status']['loop'].') : '.$backuply['status']['init_pos'].' / '.$file_size);
		
		//Updating the UI status log
		$percentage = ($chunk_no / $chunks) * 100;
		backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'uploading', 73);
		
		$chunk_no++;
	}
	
	@fclose($remote_fp);
	
	// If we are done, lets delete this file
	if(!isset($backuply['status']['incomplete_upload'])){
		
		// Delete local file
		@unlink($backuply['status']['successfile']);
		
		if(empty($error)){
		
			$info_file = backuply_info_json();
			
			// Upload the info file as well
			$GLOBALS['start_pos'] = 0;
			unset($backuply['status']['init_data']);
			unset($backuply['status']['proto']);
			$backuply['status']['proto_file_size'] = strlen($info_file);
			
			$remote_fp = fopen(dirname($backuply['status']['remote_file_path']).'/'.$GLOBALS['data']['name'].'.info', 'ab');
			fwrite($remote_fp, $info_file);
			fclose($remote_fp);
		
		}
		
		backuply_die('DONE');
	}
	
	backuply_die('incomplete_upload');
}

#####################################################
# BACKUP LOGIC STARTS HERE !
#####################################################

global $user, $globals, $can_write, $error;

// Check if we can write
$can_write = backuply_can_create_file();

if(empty($can_write)){
	$error[] = __('Cannot write a temporary file !', 'backuply');
	backuply_die('cannot_write');
}

// Retrieve all the information from the form
$data = array();

//Exclude the "backuply" folder
$backuply['excludes']['exact'][] = backuply_cleanpath(BACKUPLY_BACKUP_DIR);

//Exclude the "ai1wm-backups" & the "updraft" folder
$backuply['excludes']['exact'][] = backuply_cleanpath(WP_CONTENT_DIR . '/ai1wm-backups');
$backuply['excludes']['exact'][] = backuply_cleanpath(WP_CONTENT_DIR . '/updraft');

if(!empty($backuply['excludes']['exact'])) {
	foreach($backuply['excludes']['exact'] as $exact_path){
		$backuply['excludes']['exact'][] = backuply_cleanpath($exact_path);
	}
}

//Create the filename
$server_name = !empty($_SERVER['SERVER_NAME']) ? wp_kses_post(wp_unslash($_SERVER['SERVER_NAME'])) : '';
$data['name'] =  !isset($backuply['status']['name']) ? (defined('SITEPAD') ? 'sp_' : 'wp_').$server_name.'_'.date('Y-m-d_H-i-s') : $backuply['status']['name'];

//The path where all backups are stored
$data['path'] = BACKUPLY_BACKUP_DIR . 'backups';

//Create the tmp folder
if(!is_dir($data['path'].'/tmp/'.$data['name'])) {
	mkdir($data['path'].'/tmp/'.$data['name'], 0755, true);
}

//Check if the user wants to backup the database
//$data['backup_db'] = isset($backuply['status']['backup_db']) ? 1 : 0;
$data['backup_db'] = !empty($backuply['status']['backup_db']) ? $backuply['status']['backup_db'] : false;
$data['auto_backup'] = isset($backuply['status']['auto_backup']) ? $backuply['status']['auto_backup'] : false;

// Setting upload try
if(empty($backuply['status']['upload_try'])){
	$backuply['status']['upload_try'] = 0;
}
$backuply['status']['upload_retry'] = false;

//Database Information
$data['softdb'] = $wpdb->dbname;
$data['softdbhost'] = $wpdb->dbhost;
$data['softdbuser'] = $wpdb->dbuser;
$data['softdbpass'] = $wpdb->dbpassword;

//Check if the user wants to backup the directories
$data['backup_dir'] = $backuply['status']['backup_dir'];

//The directory path that needs to be backed up
$data['softpath'] = backuply_cleanpath(ABSPATH);

// Get backuply core file index as well as additional files for backup
$data['fileindex'] = backuply_core_fileindex();
$data['additional_files_for_backup'] = get_option('backuply_additional_fileindex');

$data['exclude_db'] = !empty($backuply['excludes']['db']) ? $backuply['excludes']['db'] : array();

backuply_backup_stop_checkpoint();

// We need to stop execution in 25 secs.. We will be called again if the process is incomplete
// Set default value
$keepalive = 25;
$GLOBALS['end'] = (int) time() + $keepalive;

$name = $data['name'];
$tmpdir = $data['path'].'/tmp';

// For libraries which are creating copies here and then uploading !
$GLOBALS['local_dest'] = $data['path'];
$GLOBALS['is_remote'] = 0;

$backuply['status']['loop'] = (empty($backuply['status']['loop'])) ? 1 : ($backuply['status']['loop'] + 1);

if(!empty($remote_location)){
	$GLOBALS['is_remote'] = 1;
	$GLOBALS['is_remote_loc'] = $remote_location;
	
	$path = $remote_location['full_backup_loc'];
	$backuply['status']['remote_file_path'] = $path.'/'.$name.'.tar.gz';
	$backuply['status']['protocol'] = $remote_location['protocol'];

	$data['backup_location'] = $remote_location['id'];
	
	// Server Side Encryption for AWS
	if('aws' == $remote_location['protocol'] && isset($remote_location['aws_sse'])){
		$backuply['status']['aws_sse'] = $remote_location['aws_sse'];
	}

	backuply_stream_wrapper_register($remote_location['protocol'], $remote_location['protocol']);
}

$path = $data['path'];
$zipfile = $path.'/.'.$name.'.tar.gz';
$successfile = $path.'/'.$name.'.tar.gz';

$GLOBALS['doing_soft_files'] = 0;

$f_list = $pre_soft_list = $post_soft_list = array(); // Files/Folder which has to be added to the tar.gz

// Empty last file everytime as a precaution
$GLOBALS['last_file'] = '';
$GLOBALS['last_file'] = !empty($backuply['status']['last_file']) ? $backuply['status']['last_file'] : '';
if(!empty($GLOBALS['last_file'])){
	$GLOBALS['last_file'] = rawurldecode($GLOBALS['last_file']);
}

$GLOBALS['init_pos'] = 0;

//Sets the Position of pointer in the file
if(isset($backuply['status']['init_pos']) && $backuply['status']['init_pos']) {
	$GLOBALS['init_pos'] = (int) $backuply['status']['init_pos'];
}

// Resume uploads - Calls for upload start in the remote upload option. This is subsequent loops
if(!empty($backuply['status']['init_data'])) {
	backuply_log('Resuming upload');
	backuply_remote_upload();
	die();
}

// Save the version
@file_put_contents($data['path'].'/tmp/'.$data['name'].'/softver.txt', BACKUPLY_VERSION);	
$GLOBALS['replace']['from']['softver'] = $data['path'].'/tmp/'.$data['name'].'/softver.txt';
$GLOBALS['replace']['to']['softver'] = 'softver.txt';

//Backup the DATABASE
if(!empty($data['backup_db']) && !empty($data['softdb']) && empty($backuply['status']['backup_db_done'])){
	// Store the progress
	//soft_progress($data['ssk'], 15, $l['backingup_db']);
	//$GLOBALS['progress'] = 15;
	backuply_status_log('Starting to Backup Database', 'info', 20);
	
	$dbfile = $data['path'].'/tmp/'.$data['name'].'/softsql.sql';
	backuply_status_log('Creating softsql', 'working', 23);

	$pre_soft_list[] = $dbfile;
	
	$GLOBALS['replace']['from']['softsql'] = $dbfile;
	$GLOBALS['replace']['to']['softsql'] = 'softsql.sql';
	
	$dbuser = $data['softdbuser'];
	$dbpass = $data['softdbpass'];
	
	$sql_conn = backuply_mysql_connect($data['softdbhost'], $dbuser, $dbpass);
		
	if(!$sql_conn){
		//$error['mysql_connect'] = 'Cannot connect mysql.';
		$GLOBALS['error']['mysql_connect'] = __('Cannot connect to mysql.', 'backuply');
		backuply_die('conn');
	}
	
	$sel = backuply_mysql_select_db($data['softdb'], $sql_conn);
	
	if(!$sel){
		//$error['mysql_sel_db'] = 'Could not select the database';
		$GLOBALS['error']['mysql_sel_db'] = __('Could not select the database.', 'backuply');
		backuply_die('conn');
	}
	
	$host = $data['softdbhost'];
	$user = $data['softdbuser'];
	$pass = $data['softdbpass'];
	$db = $data['softdb'];

	//include_once('mysql_functions.php');
	backuply_backup_stop_checkpoint();

	if(!backuply_mysql_fn($host, $user, $pass, $db, $dbfile)){
		//$error[] = 'Back up was not successful';
		$GLOBALS['error'][] = __('Back up was unsuccessful.', 'backuply');
		backuply_die('conn');
	}
	
	if(!file_exists($dbfile)){
		//$error['backup_db'] = 'Could not create sql file from database.';
		$GLOBALS['error']['backup_db'] = __('Could not create sql file from database.', 'backuply');

		backuply_die('error');
	}
	
	$backuply['status']['backup_db_done'] = 1;
}

//Backup the DIRECTORY
if(!empty($data['backup_dir'])){
	
	// Store the progress
	backuply_status_log('Backing up your Wordpress Install', 'info', empty($data['backup_db']) ? 31 : 39);	
	backuply_backup_stop_checkpoint();
	
	if(!empty($data['fileindex'])){
		$_root_filelist = backuply_filelist_fn(backuply_cleanpath($data['softpath']), 0);
		$root_filelist = array();

		// Lets get the full paths in fileindex
		$full_fileindex = array();
		foreach($data['fileindex'] as $sfk => $sfv){
			$full_fileindex[] = trim(backuply_cleanpath($data['softpath'])).'/'.$sfv;
		}
		
		// Add additional files in fileindex if selected by user
		if(!empty($data['additional_files_for_backup'])){
			foreach($data['additional_files_for_backup'] as $sfk => $sfv){
				$full_fileindex[] = trim(backuply_cleanpath($data['softpath'])).'/'.$sfv;
			}
		}
		
		foreach($_root_filelist as $rk => $rv){
			$tmp_rk = backuply_cleanpath($rk);
			$tmp_rv = $rv;

			// Do we need to exclude the files ? 
			if(!in_array(trim($tmp_rk), $full_fileindex)){
				continue;
			}
			
			$tmp_rv['path'] = backuply_cleanpath($rv['path']);
			$root_filelist[$tmp_rk] = $tmp_rv;
		}
		
		$final_filelist = array_keys($root_filelist);
		
		foreach($final_filelist as $fk => $fv){
			$f_list[] = $fv;
		}
		
	}else{
		// Adding the directory in $f_list to add to tar
		$f_list[] = $data['softpath'].'/';
	}
	
	// File Permission
	$GLOBALS['bfh']['softperms'] = @fopen($data['path'].'/tmp/'.$data['name'].'/softperms.txt', 'a');
	
	$GLOBALS['replace']['from']['softperms'] = $data['path'].'/tmp/'.$data['name'].'/softperms.txt';
	$GLOBALS['replace']['to']['softperms'] = 'softperms.txt';
	
	//Did it open the File Stream
	if(!$GLOBALS['bfh']['softperms']){
		$GLOBALS['error'][] = __('There were errors while trying to make a file of permissions', 'backuply');
		backuply_die('permdir');
	}
	
	backuply_backup_stop_checkpoint();
	
	// The directory itself
	@fwrite($GLOBALS['bfh']['softperms'], '/ '.@substr(sprintf('%o', fileperms($data['softpath'])), -4)."\n");
}

// This is done at the end to make sure we have added all possible replace paths before the softpath
if(!empty($data['backup_dir'])){
	$GLOBALS['replace']['from']['softpath'] = $data['softpath'].'/';
	$GLOBALS['replace']['to']['softpath'] = '';
}

// Now we will have to add the permission file to the end os an array of directory list.
if(!empty($GLOBALS['bfh']['softperms'])){
	$GLOBALS['post_soft_list'][] = $data['path'].'/tmp/'.$data['name'].'/softperms.txt';
}

$GLOBALS['post_soft_list'][] = $data['path'].'/tmp/'.$data['name'].'/softver.txt';

if(empty($GLOBALS['error']) && (!empty($f_list) || !empty($post_soft_list) || !empty($pre_soft_list))){
	
	// Set default values
	$GLOBALS['start'] = 0;
	$GLOBALS['end_file'] = '';
	$GLOBALS['pre_soft_list'] = $pre_soft_list;
	
	backuply_backup_stop_checkpoint();
	backuply_status_log('Starting to create archive', 'info', 60);
	
	if(!backuply_tar_archive($zipfile, $f_list, true)){
		backuply_clean($data);
		
		//backuply_log('The backup utility could not back up the files.');
		$GLOBALS['error']['backup_dir'] = __('The backup utility could not back up the files.', 'backuply');
		@unlink($zipfile);
		backuply_die('failbackup');
	}
}

if(!empty($GLOBALS['error'])){
	backuply_die('failbackup');
}

//@print_r($GLOBALS['error']);

// CHMOD it to something Safe
@chmod($zipfile, 0600);

// if(empty($remote_location)){
// 	schown($zipfile);
// }

backuply_clean($data);

// Is the backup tar process INCOMPLETE ?
if(!empty($GLOBALS['end_file'])){
	
	//fwrite($file, "\n end file ke check me gya  \n");
	//echo $data['name']."+".$GLOBALS['end_file']."+".$GLOBALS['progress'];
	$data['last_file'] = $GLOBALS['end_file'];
	
	//Let the script know that the process is still incomplete.
	backuply_die('INCOMPLETE');

// Backup tar is created, lets upload if its a remote backup OR simple finish the whole process for a local backup
}else{
	
	// Rename the ZIP file
	@rename($zipfile, $successfile);
	
	backuply_backup_stop_checkpoint();
	$backuply['status']['successfile'] = $successfile;
	
	//Send the users email address & the plugin directory path
	$GLOBALS['data'] = $data;
	
	// Lets upload as this is a remote backup
	if(isset($backuply['status']['remote_file_path'])) {
		backuply_log('Starting to upload file to the selected remote location');
		backuply_remote_upload();
		die();
	}
	
	//Delete the backup information from sql
	if(!isset($backuply['status']['incomplete_upload'])){
		delete_option('backuply_status');
	}
	//fwrite($file, "\n deleted backuply_status  \n");

	backuply_die('DONE', $l_file = '', BACKUPLY_BACKUP_DIR);
}
