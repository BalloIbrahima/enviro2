<?php

if(!defined('BACKUPLY_CRLF')) define('BACKUPLY_CRLF',"\r\n");
if(!defined('BACKUPLY_FTP_AUTOASCII')) define('BACKUPLY_FTP_AUTOASCII', -1);
if(!defined('BACKUPLY_FTP_FORCE')) define('BACKUPLY_FTP_FORCE', TRUE);
define('BACKUPLY_FTP_OS_Unix','u');
define('BACKUPLY_FTP_OS_Windows','w');
define('BACKUPLY_FTP_OS_Mac','m');

class ftp_base {
	/* Public variables */
	var $LocalEcho;
	var $Verbose;
	var $OS_local;
	var $OS_remote;
	
	/* Private variables */
	var $_lastaction;
	var $_errors;
	var $_type;
	var $_umask;
	var $_timeout;
	var $_passive;
	var $_host;
	var $_fullhost;
	var $_port;
	var $_datahost;
	var $_dataport;
	var $_ftp_control_sock;
	var $_ftp_data_sock;
	var $_ftp_temp_sock;
	var $_ftp_buff_size;
	var $_login;
	var $_password;
	var $_connected;
	var $_ready;
	var $_code;
	var $_message;
	var $_can_restore;
	var $_port_available;
	var $_curtype;
	var $_features;

	var $_error_array;
	var $AuthorizedTransferMode;
	var $OS_FullName;
	var $_eol_code;
	var $AutoAsciiExt;
	var $error;

	/* Constructor */
	function ftp_base($port_mode=FALSE) {
		$this->__construct($port_mode);
	}

	function __construct($port_mode=FALSE, $verb=FALSE, $le=FALSE) {
		$this->LocalEcho=$le;
		$this->Verbose=$verb;
		$this->_lastaction=NULL;
		$this->_error_array=array();
		$this->_eol_code=array(BACKUPLY_FTP_OS_Unix=>"\n", BACKUPLY_FTP_OS_Mac=>"\r", BACKUPLY_FTP_OS_Windows=>"\r\n");
		$this->AuthorizedTransferMode=array(BACKUPLY_FTP_AUTOASCII, FTP_ASCII, FTP_BINARY);
		$this->OS_FullName=array(BACKUPLY_FTP_OS_Unix => 'UNIX', BACKUPLY_FTP_OS_Windows => 'WINDOWS', BACKUPLY_FTP_OS_Mac => 'MACOS');
		$this->AutoAsciiExt=array("ASP","BAT","C","CPP","CSS","CSV","JS","H","HTM","HTML","SHTML","INI","LOG","PHP3","PHTML","PL","PERL","SH","SQL","TXT");
		$this->_port_available=($port_mode==TRUE);
		$this->SendMSG("Staring FTP client class".($this->_port_available?"":" without PORT mode support"));
		$this->_connected=FALSE;
		$this->_ready=FALSE;
		$this->_can_restore=true;
		$this->_code=0;
		$this->_message="";
		$this->_ftp_buff_size=4096;
		$this->_curtype=NULL;
		$this->SetUmask(0022);
		$this->SetType(BACKUPLY_FTP_AUTOASCII);
		$this->SetTimeout(25);
		$this->Passive(!$this->_port_available);
		$this->_login="anonymous";
		$this->_password="anon@ftp.com";
		$this->_features=array();
	    $this->OS_local=BACKUPLY_FTP_OS_Unix;
		$this->OS_remote=BACKUPLY_FTP_OS_Unix;
		$this->features=array();
		$this->error=array();
		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') $this->OS_local=BACKUPLY_FTP_OS_Windows;
		elseif(strtoupper(substr(PHP_OS, 0, 3)) === 'MAC') $this->OS_local=BACKUPLY_FTP_OS_Mac;
	}

// <!-- --------------------------------------------------------------------------------------- -->
// <!--       Public functions                                                                  -->
// <!-- --------------------------------------------------------------------------------------- -->
	function parselisting($list) {
//	Parses 1 line like:		"drwxrwx---  2 owner group 4096 Apr 23 14:57 text"
		if(preg_match("/^([-ld])([rwxst-]+)\s+(\d+)\s+([^\s]+)\s+([^\s]+)\s+(\d+)\s+(\w{3})\s+(\d+)\s+([\:\d]+)\s+(.+)$/i", $list, $ret)) {
			$v=array(
				"type"	=> ($ret[1]=="-"?"f":$ret[1]),
				"perms"	=> 0,
				"inode"	=> $ret[3],
				"owner"	=> $ret[4],
				"group"	=> $ret[5],
				"size"	=> $ret[6],
				"date"	=> $ret[7]." ".$ret[8]." ".$ret[9],
				"name"	=> $ret[10]
			);
			$bad=array("(?)");
			if(in_array($v["owner"], $bad)) $v["owner"]=NULL;
			if(in_array($v["group"], $bad)) $v["group"]=NULL;
			$v["perms"]+=00400*(int)($ret[2][0]=="r");
			$v["perms"]+=00200*(int)($ret[2][1]=="w");
			$v["perms"]+=00100*(int)in_array($ret[2][2], array("x","s"));
			$v["perms"]+=00040*(int)($ret[2][3]=="r");
			$v["perms"]+=00020*(int)($ret[2][4]=="w");
			$v["perms"]+=00010*(int)in_array($ret[2][5], array("x","s"));
			$v["perms"]+=00004*(int)($ret[2][6]=="r");
			$v["perms"]+=00002*(int)($ret[2][7]=="w");
			$v["perms"]+=00001*(int)in_array($ret[2][8], array("x","t"));
			$v["perms"]+=04000*(int)in_array($ret[2][2], array("S","s"));
			$v["perms"]+=02000*(int)in_array($ret[2][5], array("S","s"));
			$v["perms"]+=01000*(int)in_array($ret[2][8], array("T","t"));
		} 
		return $v;
	}
	
	function _parselisting($line) {
		$is_windows = ($this->OS_remote == BACKUPLY_FTP_OS_Windows);
		if ($is_windows && preg_match("/([0-9]{2})-([0-9]{2})-([0-9]{2}) +([0-9]{2}):([0-9]{2})(AM|PM) +([0-9]+|<DIR>) +(.+)/",$line,$lucifer)) {
			$b = array();
			if ($lucifer[3]<70) { $lucifer[3]+=2000; } else { $lucifer[3]+=1900; } // 4digit year fix
			$b['isdir'] = ($lucifer[7]=="<DIR>");
			if ( $b['isdir'] )
				$b['type'] = 'd';
			else
				$b['type'] = 'f';
			$b['size'] = $lucifer[7];
			$b['month'] = $lucifer[1];
			$b['day'] = $lucifer[2];
			$b['year'] = $lucifer[3];
			$b['hour'] = $lucifer[4];
			$b['minute'] = $lucifer[5];
			$b['time'] = @mktime($lucifer[4]+(strcasecmp($lucifer[6],"PM")==0?12:0),$lucifer[5],0,$lucifer[1],$lucifer[2],$lucifer[3]);
			$b['am/pm'] = $lucifer[6];
			$b['name'] = $lucifer[8];
		} else if (!$is_windows && $lucifer=preg_split("/[ ]/",$line,9,PREG_SPLIT_NO_EMPTY)) {
			//echo $line."\n";
			$lcount=count($lucifer);
			if ($lcount<8) return '';
			$b = array();
			$b['isdir'] = $lucifer[0][0] === "d";
			$b['islink'] = $lucifer[0][0] === "l";
			if ( $b['isdir'] )
				$b['type'] = 'd';
			elseif ( $b['islink'] )
				$b['type'] = 'l';
			else
				$b['type'] = 'f';
			$b['perms'] = $lucifer[0];
			$b['number'] = $lucifer[1];
			$b['owner'] = $lucifer[2];
			$b['group'] = $lucifer[3];
			$b['size'] = $lucifer[4];
			if ($lcount==8) {
				sscanf($lucifer[5],"%d-%d-%d",$b['year'],$b['month'],$b['day']);
				sscanf($lucifer[6],"%d:%d",$b['hour'],$b['minute']);
				$b['time'] = @mktime($b['hour'],$b['minute'],0,$b['month'],$b['day'],$b['year']);
				$b['name'] = $lucifer[7];
			} else {
				$b['month'] = $lucifer[5];
				$b['day'] = $lucifer[6];
				if (preg_match("/([0-9]{2}):([0-9]{2})/",$lucifer[7],$l2)) {
					$b['year'] = date("Y");
					$b['hour'] = $l2[1];
					$b['minute'] = $l2[2];
					
					// The LS command gives time for the files which are modified in the last 6 months
					// Hence if the time is there it can also be created within the last year but within 6 months
					// e.g. if today is 21st Jan, 2016, LS will show time for files created from 22ND July onwards
					// So we cannot assume the year to be the current year. We simply take the last year
					if(strtotime(sprintf("%d %s %d %02d:%02d",$b['day'],$b['month'],$b['year'],$b['hour'],$b['minute'])) > time()){
						$b['year'] = $b['year'] - 1;
					}
					
				} else {
					$b['year'] = $lucifer[7];
					$b['hour'] = 0;
					$b['minute'] = 0;
				}
				$b['time'] = strtotime(sprintf("%d %s %d %02d:%02d",$b['day'],$b['month'],$b['year'],$b['hour'],$b['minute']));
				$b['name'] = $lucifer[8];
			}
		}
		
		$bad=array("(?)");
		if(in_array($b["owner"], $bad)) $b["owner"]=NULL;
		if(in_array($b["group"], $bad)) $b["group"]=NULL;
		
		$orig_perm = substr($b["perms"], 1);
		$b["perms"] = 0;
		//echo $orig_perm.'-'.(int)($orig_perm[4]=="w").' - '.$b["perms"].'<br>';
		$b["perms"]+=00400*(int)($orig_perm[0]=="r");
		$b["perms"]+=00200*(int)($orig_perm[1]=="w");
		$b["perms"]+=00100*(int)in_array($orig_perm[2], array("x","s"));
		$b["perms"]+=00040*(int)($orig_perm[3]=="r");
		$b["perms"]+=00020*(int)($orig_perm[4]=="w");
		$b["perms"]+=00010*(int)in_array($orig_perm[5], array("x","s"));
		$b["perms"]+=00004*(int)($orig_perm[6]=="r");
		$b["perms"]+=00002*(int)($orig_perm[7]=="w");
		$b["perms"]+=00001*(int)in_array($orig_perm[8], array("x","t"));
		$b["perms"]+=04000*(int)in_array($orig_perm[2], array("S","s"));
		$b["perms"]+=02000*(int)in_array($orig_perm[5], array("S","s"));
		$b["perms"]+=01000*(int)in_array($orig_perm[8], array("T","t"));

		return $b;
	}
	
	function SendMSG($message = "", $crlf=true) {
		if ($this->Verbose) {
			echo $message.($crlf?BACKUPLY_CRLF:"");
			flush();
		}
		return TRUE;
	}

	function SetType($mode=BACKUPLY_FTP_AUTOASCII) {
		if(!in_array($mode, $this->AuthorizedTransferMode)) {
			$this->SendMSG("Wrong type");
			return FALSE;
		}
		$this->_type=$mode;
		$this->SendMSG("Transfer type: ".($this->_type==FTP_BINARY?"binary":($this->_type==FTP_ASCII?"ASCII":"auto ASCII") ) );
		return TRUE;
	}

	function _settype($mode=FTP_ASCII) {
		if($this->_ready) {
			if($mode==FTP_BINARY) {
				if($this->_curtype!=FTP_BINARY) {
					if(!$this->_exec("TYPE I", "SetType")) return FALSE;
					$this->_curtype=FTP_BINARY;
				}
			} elseif($this->_curtype!=FTP_ASCII) {
				if(!$this->_exec("TYPE A", "SetType")) return FALSE;
				$this->_curtype=FTP_ASCII;
			}
		} else return FALSE;
		return TRUE;
	}

	function Passive($pasv=NULL) {
		if(is_null($pasv)) $this->_passive=!$this->_passive;
		else $this->_passive=$pasv;
		if(!$this->_port_available and !$this->_passive) {
			$this->SendMSG("Only passive connections available!");
			$this->_passive=TRUE;
			return FALSE;
		}
		$this->SendMSG("Passive mode ".($this->_passive?"on":"off"));
		return TRUE;
	}

	function SetServer($host, $port=21, $reconnect=true) {
		if(!is_long($port)) {
	        $this->verbose=true;
    	    $this->SendMSG("Incorrect port syntax");
			return FALSE;
		} else {
			$ip=@gethostbyname($host);
	        $dns=@gethostbyaddr($host);
	        if(!$ip) $ip=$host;
	        if(!$dns) $dns=$host;
			// Validate the IPAddress PHP4 returns -1 for invalid, PHP5 false
			// -1 === "255.255.255.255" which is the broadcast address which is also going to be invalid
			$ipaslong = ip2long($ip);
			if ( ($ipaslong == false) || ($ipaslong === -1) ) {
				$this->SendMSG("Wrong host name/address \"".$host."\"");
				return FALSE;
			}
	        $this->_host=$ip;
	        $this->_fullhost=$dns;
	        $this->_port=$port;
	        $this->_dataport=$port-1;
		}
		$this->SendMSG("Host \"".$this->_fullhost."(".$this->_host."):".$this->_port."\"");
		if($reconnect){
			if($this->_connected) {
				$this->SendMSG("Reconnecting");
				if(!$this->quit(BACKUPLY_FTP_FORCE)) return FALSE;
				if(!$this->connect()) return FALSE;
			}
		}
		return TRUE;
	}

	function SetUmask($umask=0022) {
		$this->_umask=$umask;
		umask($this->_umask);
		$this->SendMSG("UMASK 0".decoct($this->_umask));
		return TRUE;
	}

	function SetTimeout($timeout=30) {
		$this->_timeout=$timeout;
		$this->SendMSG("Timeout ".$this->_timeout);
		if($this->_connected)
			if(!$this->_settimeout($this->_ftp_control_sock)) return FALSE;
		return TRUE;
	}

	function connect($server=NULL) {
		if(!empty($server)) {
			if(!$this->SetServer($server)) return false;
		}
		if($this->_ready) return true;
	    $this->SendMsg('Local OS : '.$this->OS_FullName[$this->OS_local]);
		if(!($this->_ftp_control_sock = $this->_connect($this->_host, $this->_port))) {
			$this->SendMSG("Error : Cannot connect to remote host \"".$this->_fullhost." :".$this->_port."\"");
			return FALSE;
		}
		$this->SendMSG("Connected to remote host \"".$this->_fullhost.":".$this->_port."\". Waiting for greeting.");
		do {
			if(!$this->_readmsg()) return FALSE;
			if(!$this->_checkCode()) return FALSE;
			$this->_lastaction=time();
		} while($this->_code<200);
		$this->_ready=true;
		$syst=$this->systype();
		if(!$syst) $this->SendMSG("Can't detect remote OS");
		else {
			if(preg_match("/win|dos|novell/i", $syst[0])) $this->OS_remote=BACKUPLY_FTP_OS_Windows;
			elseif(preg_match("/os/i", $syst[0])) $this->OS_remote=BACKUPLY_FTP_OS_Mac;
			elseif(preg_match("/(li|u)nix/i", $syst[0])) $this->OS_remote=BACKUPLY_FTP_OS_Unix;
			else $this->OS_remote=BACKUPLY_FTP_OS_Mac;
			$this->SendMSG("Remote OS: ".$this->OS_FullName[$this->OS_remote]);
		}
		if(!$this->features()) $this->SendMSG("Can't get features list. All supported - disabled");
		else $this->SendMSG("Supported features: ".implode(", ", array_keys($this->_features)));
		return TRUE;
	}

	function quit($force=false) {
		if($this->_ready) {
			if(!$this->_exec("QUIT") and !$force) return FALSE;
			if(!$this->_checkCode() and !$force) return FALSE;
			$this->_ready=false;
			$this->SendMSG("Session finished");
		}
		$this->_quit();
		return TRUE;
	}

	function login($user=NULL, $pass=NULL) {
		if(!is_null($user)) $this->_login=$user;
		else $this->_login="anonymous";
		if(!is_null($pass)) $this->_password=$pass;
		else $this->_password="anon@anon.com";
		if(!$this->_exec("USER ".$this->_login, "login")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		if($this->_code!=230) {
			if(!$this->_exec((($this->_code==331)?"PASS ":"ACCT ").$this->_password, "login")) return FALSE;
			if(!$this->_checkCode()) return FALSE;
		}
		$this->SendMSG("Authentication succeeded");
		if(empty($this->_features)) {
			if(!$this->features()) $this->SendMSG("Can't get features list. All supported - disabled");
			else $this->SendMSG("Supported features: ".implode(", ", array_keys($this->_features)));
		}
		return TRUE;
	}

	function pwd() {
		if(!$this->_exec("PWD", "pwd")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		//return ereg_replace("^[0-9]{3} \"(.+)\" .+".BACKUPLY_CRLF, "\\1", $this->_message);
		return trim(preg_replace("/^[0-9]{3} \"(.+)\" .+".BACKUPLY_CRLF."/", "\\1", $this->_message));
	}

	function cdup() {
		if(!$this->_exec("CDUP", "cdup")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return true;
	}

	function chdir($pathname) {
		if(!$this->_exec("CWD ".$pathname, "chdir")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return TRUE;
	}

	function rmdir($pathname) {
		if(!$this->_exec("RMD ".$pathname, "rmdir")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return TRUE;
	}

	function mkdir($pathname) {
		if($pathname == "/" ) return TRUE;
		if(!$this->_exec("MKD ".$pathname, "mkdir")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return TRUE;
	}

	function rename($from, $to) {
		if($pathname == "/") return TRUE;
		if(!$this->_exec("RNFR ".$from, "rename")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		if($this->_code==350) {
			if(!$this->_exec("RNTO ".$to, "rename")) return FALSE;
			if(!$this->_checkCode()) return FALSE;
		} else return FALSE;
		return TRUE;
	}

	function filesize($pathname) {
		if(!isset($this->_features["SIZE"])) {
			$this->PushError("filesize", "not supported by server");
			return FALSE;
		}
		if(!$this->_exec("SIZE ".$pathname, "filesize")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return preg_replace("/^[0-9]{3} ([0-9]+).*$/s", "\\1", $this->_message);
	}

	function abort() {
		if(!$this->_exec("ABOR", "abort")) return FALSE;
		if(!$this->_checkCode()) {
			if($this->_code!=426) return FALSE;
			if(!$this->_readmsg("abort")) return FALSE;
			if(!$this->_checkCode()) return FALSE;
		}
		return true;
	}

	function mdtm($pathname) {
		if(!isset($this->_features["MDTM"])) {
			$this->PushError("mdtm", "not supported by server");
			return FALSE;
		}
		if(!$this->_exec("MDTM ".$pathname, "mdtm")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		$mdtm = preg_replace("/^[0-9]{3} ([0-9]+).*$/s", "\\1", $this->_message);
		$date = sscanf($mdtm, "%4d%2d%2d%2d%2d%2d");
		$timestamp = mktime($date[3], $date[4], $date[5], $date[1], $date[2], $date[0]);
		return $timestamp;
	}

	function systype() {
		if(!$this->_exec("SYST", "systype")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		$DATA = explode(" ", $this->_message);
		return array($DATA[1], $DATA[3]);
	}

	function delete($pathname) {
		if(!$this->_exec("DELE ".$pathname, "delete")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return TRUE;
	}

	function site($command, $fnction="site") {
		if(!$this->_exec("SITE ".$command, $fnction)) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return TRUE;
	}

	function chmod($pathname, $mode) {
		if(!$this->site("CHMOD ".decoct($mode)." ".$pathname, "chmod")) return FALSE;
		return TRUE;
	}
	
	function utime($pathname, $mtime) {
		$this->site("UTIME ".$mtime." ".$pathname, "utime"); // Try to set the mtime if UTIME command is supported by FTP
		return TRUE;
	}

	function restore($from) {
		if(!isset($this->_features["REST"])) {
			$this->PushError("restore", "not supported by server");
			return FALSE;
		}
		if($this->_curtype!=FTP_BINARY) {
			$this->PushError("restore", "can't restore in ASCII mode");
			return FALSE;
		}
		if(!$this->_exec("REST ".$from, "resore")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return TRUE;
	}

	function features() {
		if(!$this->_exec("FEAT", "features")) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		$f=array_slice(preg_split("/[".BACKUPLY_CRLF."]+/", $this->_message, -1, PREG_SPLIT_NO_EMPTY), 1, -1);
		array_walk($f, create_function('&$a', '$a=preg_replace("/[0-9]{3}[\s-]+/", "", trim($a));'));
		$this->_features=array();
		foreach($f as $k=>$v) {
			$v=explode(" ", trim($v));
			$this->_features[array_shift($v)]=$v;
		}
		return true;
	}

	function rawlist($pathname="", $arg="") {
		return $this->_list(($arg?" ".$arg:"").($pathname?" ".$pathname:""), "LIST", "rawlist");
	}

	function nlist($pathname="") {
		return $this->_list(($arg?" ".$arg:"").($pathname?" ".$pathname:""), "NLST", "nlist");
	}

	function is_exists($pathname) {
		if($pathname == "/") return TRUE;
		return $this->file_exists($pathname);
	}

	function file_exists($pathname) {
		if($pathname == "/") return TRUE;
		$exists=true;
		if(!$this->_exec("RNFR ".$pathname, "rename")) $exists=FALSE;
		else {
		
			if($this->_code==350) {
				$this->_exec("RNTO ".$pathname, "rename");
			}
			
			if(!$this->_checkCode()) $exists=FALSE;
			//$this->abort();
		}
		if($exists) $this->SendMSG("Remote file ".$pathname." exists");
		else $this->SendMSG("Remote file ".$pathname." does not exist");
		return $exists;
	}
	
	function is_dir($dir){
			
		////////////////////////////////////////////
		// Change Directory to the path in question
		// To find if its a directory or not
		////////////////////////////////////////////
		
		// Get current directory	
		$original_directory = $this->pwd();
			
		// If it is a directory
		if($this->chdir($dir)){
			
			// Change the directory back to the original directory
			$this->chdir($original_directory);	
			return true;
			
		// It must be a non existing DIR
		}else{
			return false;
		}
		
	}

	function get($remotefile, $localfile=NULL, $rest=0) {
		if(is_null($localfile)) $localfile=$remotefile;
		if (@file_exists($localfile)) $this->SendMSG("Warning : local file will be overwritten");
		$fp = @fopen($localfile, 'ab');
		if (!$fp) {
			$this->PushError("get","Can't open local file. Cannot create \"".$localfile."\"", "Cannot create \"".$localfile."\"");
			return FALSE;
		}
		if($this->_can_restore and $rest!=0) fseek($fp, $rest);
		$pi=pathinfo($remotefile);
		if($this->_type==FTP_ASCII or ($this->_type==BACKUPLY_FTP_AUTOASCII and in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) $mode=FTP_ASCII;
		else $mode=FTP_BINARY;
		if(!$this->_data_prepare($mode)) {
			fclose($fp);
			return FALSE;
		}
		if($this->_can_restore and $rest!=0) $this->restore($rest);
		if(!$this->_exec("RETR ".$remotefile, "get")) {
			$this->_data_close();
			fclose($fp);
			return FALSE;
		}
		if(!$this->_checkCode()) {
			$this->_data_close();
			fclose($fp);
			return FALSE;
		}
		$out=$this->_data_read($mode, $fp);
		fclose($fp);
		$this->_data_close();
		if(!$this->_readmsg()) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return $out;
	}

	function pget($remotefile, $localfile=NULL, $rest=0) {
		
		if(is_null($localfile)) $localfile=$remotefile;
		if (@file_exists($localfile)) $this->SendMSG("Warning : local file will be overwritten");
		$fp = @fopen($localfile, 'ab');
		if (!$fp) {
			$this->PushError("get","Can't open local file. Cannot create \"".$localfile."\"", "Cannot create \"".$localfile."\"");
			return FALSE;
		}
		if($this->_can_restore and $rest!=0) fseek($fp, $rest);
		$pi=pathinfo($remotefile);
		if($this->_type==FTP_ASCII or ($this->_type==BACKUPLY_FTP_AUTOASCII and in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) $mode=FTP_ASCII;
		else $mode=FTP_BINARY;
		if(!$this->_data_prepare($mode)) {
			fclose($fp);
			return FALSE;
		}
		if($this->_can_restore and $rest!=0) $this->restore($rest);
		if(!$this->_exec("RETR ".$remotefile, "get")) {
			$this->_data_close();
			fclose($fp);
			return FALSE;
		}
		if(!$this->_checkCode()) {
			$this->_data_close();
			fclose($fp);
			return FALSE;
		}
		$out=$this->_pdata_read($mode, $fp);
		fclose($fp);backuply_log('Here 1');
		$this->_data_close();
		if(!$this->_readmsg()) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return $out;
	}

	function put($localfile, $remotefile=NULL, $rest=0) {
		
		// Check last file and skip the files that have been already transferred...
		if(!empty($GLOBALS['last_file']) && $GLOBALS['start'] == 0){
			if(preg_match('#^'.$GLOBALS['last_file'].'$#', $localfile)){
				$GLOBALS['start'] = 1; // give a jump start once the last transferred file is found..
			}
			return true; //return true to skip files
		}
		
		if(is_null($remotefile)) $remotefile=$localfile;
		if (!@file_exists($localfile)) {
			$this->PushError("put","Can't open local file. No such file or directory \"".$localfile."\"", "No such file or directory \"".$localfile."\"");
			return FALSE;
		}
		$fp = @fopen($localfile, "r");
		if (!$fp) {
			$this->PushError("put","Can't open local file. Cannot read file \"".$localfile."\"", "Cannot read file \"".$localfile."\"");
			return FALSE;
		}
		if($this->_can_restore and $rest!=0) fseek($fp, $rest);
		$pi=pathinfo($localfile);
		if($this->_type==FTP_ASCII or ($this->_type==BACKUPLY_FTP_AUTOASCII and in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) $mode=FTP_ASCII;
		else $mode=FTP_BINARY;
		if(!$this->_data_prepare($mode)) {
			fclose($fp);
			return FALSE;
		}
		if($this->_can_restore and $rest!=0) $this->restore($rest);
		if(!$this->_exec("STOR ".$remotefile, "put")) {
			$this->_data_close();
			fclose($fp);
			return FALSE;
		}
		if(!$this->_checkCode()) {
			$this->_data_close();
			fclose($fp);
			return FALSE;
		}
		$ret=$this->_data_write($mode, $fp);
		fclose($fp);
		$this->_data_close();
		if(!$this->_readmsg()) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		
		//We can run the scripts for the end time already set
		if(time() >= $GLOBALS['end']){
			$GLOBALS['end_file'] = $localfile; // set end file so that we know where to start from
		}
		
		return $ret;
	}

	function mput($local=".", $remote=NULL, $continious=false, $include_only=array()) {
		
		global $__settings, $ftp;
		
		 if(!@$ftp->is_dir($remote)) {
			if(!@$ftp->mkdir($remote)) {
				$this->PushError("mput", "Can't create folder. Can't create remote file \"".$remote."\"", "Can't create remote file \"".$remote."\"");
				return FALSE;
			}
		}

		$list = filelist_fn($local, 0);
		
		if($list===false) {
			$this->PushError("mput", "Can't retrive directory listing of local folder \"".$local."\"", "Can't retrive directory listing of local folder \"".$local."\"");
			return FALSE;
		}
		
		if(empty($list)) return TRUE;
		
		foreach($list as $k=>$v) {
			$stat = stat($k);			
			if($v['dir'] == '0'){
				$list[$k]['type'] = 'f';
			}else{
				$list[$k]['type'] = 'd';
			}
			$list[$k]['name'] = $v['name'];
			$list[$k]['size'] = $stat['size'];
			$list[$k]['perms'] = octdec(substr(sprintf('%o', $stat['mode']), -4));
			$list[$k]['time'] = date('YmdHis', $stat['mtime']);
			
			if($list[$k]["name"]=="." or $list[$k]["name"]=="..") unset($list[$k]);
		}
		$ret=true;
		
		foreach($list as $ek => $el) {
			
			// ----- break the loop once last file is found...
			if(!empty($GLOBALS['end_file'])){
				break;
			}
				
			if($el['size'] == '0'){
				if($el['type'] == 'f'){
					//Create an empty file
					if(!$this->put($local."/".$el["name"], $remote."/".$el["name"])) {
						$this->PushError("mput", "Can't copy local file \"".$local."/".$el["name"]."\" to remote \"".$remote."/".$el["name"]."\"", "Can't copy local file \"".$local."/".$el["name"]."\" to remote \"".$remote."/".$el["name"]."\"");
						$ret=false;
						if(!$continious) break;
					}
				}elseif($el['type'] == 'd'){
					$ftp->mkdir($remote.'/'.$el['name']);
				}
				@$ftp->chmod($remote.'/'.$el['name'], $el['perms']);
			
				//Set modified time 
				$t=$el["time"];
				if($t!==-1 and $t!==false){
					$ftp->utime($remote.'/'.$el['name'], $el["time"]);
				}
			
				unset($list[$ek]);
				continue;
			}
			
			$extension = get_extension($el['name']);
			if(!empty($include_only) && !in_array($el['name'], $include_only) && $extension !== 'sql'){
				unset($list[$ek]);
				continue;
			}
			
			$current_file = $el["path"].$el["name"];
			//To exclude some files/folder
			if(!empty($__settings['exclude_files']) && in_array($current_file, $__settings['exclude_files'])){
				unset($list[$ek]);
				continue;
			}
			
			if($el["type"]=="d") {
				if(!$this->mput($local."/".$el["name"], $remote."/".$el["name"], $continious)){
					$this->PushError("mput", "Can't copy local folder \"".$local."/".$el["name"]."\" to remote \"".$remote."/".$el["name"]."\"", "Can't copy local folder \"".$local."/".$el["name"]."\" to remote \"".$remote."/".$el["name"]."\"");
					$ret=false;
					if(!$continious) break;
				}
			}else{
				//To exclude some files if set in script's clone.php's __pre_unzip() function
				if(!empty($__settings['exclude_ext']) && in_array($extension, $__settings['exclude_ext'])){
					unset($list[$ek]);
					continue;
				}
				
				if(!$this->put($local."/".$el["name"], $remote."/".$el["name"])) {
					$this->PushError("mput", "Can't copy local file \"".$local."/".$el["name"]."\" to remote \"".$remote."/".$el["name"]."\"", "Can't copy local file \"".$local."/".$el["name"]."\" to remote \"".$remote."/".$el["name"]."\"");
					$ret=false;
					if(!$continious) break;
				}
				
				
			}
			
			@$ftp->chmod($remote."/".$el["name"], $el["perms"]);
			
			//Set modified time 
			$t=$el["time"];
			if($t!==-1 and $t!==false){
				$ftp->utime($remote.'/'.$el['name'], $el["time"]);
			}
			
		}
		return $ret;
		
	}

	function mget($remote, $local=".", $continious=false, $include_only=array()) {
		
		global $__settings;
		
		if(!@file_exists($local)) {
			if(!@mkdir($local)) {
				$this->PushError("mget","Can't create local folder \"".$local."\"", "Cannot create folder \"".$local."\"");
				return FALSE;
			}
		}
		
		$list=$this->rawlist($remote, "-lA");
		
		if($list===false) {
			$this->PushError("mget","Can't read remote folder list. Can't read remote folder \"".$remote."\" contents", "Can't read remote folder \"".$remote."\" contents");
			return FALSE;
		}
		
		if(empty($list)) return true;
		
		foreach($list as $k=>$v) {
			$list[$k]=$this->_parselisting($v);
			if($list[$k]["name"]=="." or $list[$k]["name"]=="..") unset($list[$k]);
		}
		$ret=true;
		
		foreach($list as $ek => $el) {
		
			if($el['size'] == '0'){
				if($el['type'] == 'f'){
					$empty_file = @fopen($local.'/'.$el['name'], "w");
					@fclose($empty_file);
				}elseif($el['type'] == 'd'){
					mkdir($local.'/'.$el['name']);
				}
				@chmod($local.'/'.$el['name'], $el['perms']);
				
				$t=$el["time"];
				//echo 'Path :'.$local."/".$el["name"].' Date : '.$el["date"].' Strtotime : '.$t.'<br />';
				if($t!==-1 and $t!==false) @touch($local."/".$el["name"], $t);
			
				unset($list[$ek]);
				continue;
			}
			
			$extension = get_extension($el['name']);
			if(!empty($include_only) && !in_array($el['name'], $include_only) && $extension !== 'sql'){
				unset($list[$ek]);
				continue;
			}
			
			$current_file = $el["path"].$el["name"];
			//To exclude some files/folder
			if(!empty($__settings['exclude_files']) && in_array($current_file, $__settings['exclude_files'])){
				unset($list[$ek]);
				continue;
			}
			
			if($el["type"]=="d") {
				if(!$this->mget($remote."/".$el["name"], $local."/".$el["name"], $continious)) {
					$this->PushError("mget", "Can't copy remote folder \"".$remote."/".$el["name"]."\" to local \"".$local."/".$el["name"]."\"", "Can't copy remote folder \"".$remote."/".$el["name"]."\" to local \"".$local."/".$el["name"]."\"");
					$ret=false;
					if(!$continious) break;
				}
			} else {
				
				//To exclude some files if set in script's clone.php's __pre_unzip() function
				if(!empty($__settings['exclude_ext']) && in_array($extension, $__settings['exclude_ext'])){
					unset($list[$ek]);
					continue;
				}
			
				if(!$this->get($remote."/".$el["name"], $local."/".$el["name"])) {
					$this->PushError("mget", "Can't copy remote file \"".$remote."/".$el["name"]."\" to local \"".$local."/".$el["name"]."\"", "Can't copy remote file \"".$remote."/".$el["name"]."\" to local \"".$local."/".$el["name"]."\"");
					$ret=false;
					if(!$continious) break;
				}
			}
			@chmod($local."/".$el["name"], $el["perms"]);
			
			$t=$el["time"];
			//echo 'Path :'.$local."/".$el["name"].' Date : '.$el["date"].' Strtotime : '.$t.'<br />';
			if($t!==-1 and $t!==false) @touch($local."/".$el["name"], $t);
		}
		return $ret;
	}

	function mdel($remote, $continious=false) {
		$list=$this->rawlist($remote, "-la");
		if($list===false) {
			$this->PushError("mdel","Can't read remote folder list. Can't read remote folder \"".$remote."\" contents", "Can't read remote folder \"".$remote."\" contents");
			return false;
		}
	
		foreach($list as $k=>$v) {
			$list[$k]=$this->parselisting($v);
			if($list[$k]["name"]=="." or $list[$k]["name"]=="..") unset($list[$k]);
		}
		$ret=true;
	
		foreach($list as $el) {
			if($el["type"]=="d") {
				if(!$this->mdel($remote."/".$el["name"], $continious)) {
					$ret=false;
					if(!$continious) break;
				}
			} else {
				if (!$this->delete($remote."/".$el["name"])) {
					$this->PushError("mdel", "Can't delete remote file \"".$remote."/".$el["name"]."\"", "Can't delete remote file \"".$remote."/".$el["name"]."\"");
					$ret=false;
					if(!$continious) break;
				}
			}
		}
	
		if(!$this->rmdir($remote)) {
			$this->PushError("mdel", "Can't delete remote folder \"".$remote."/".$el["name"]."\"", "Can't delete remote folder \"".$remote."/".$el["name"]."\"");
			$ret=false;
		}
		return $ret;
	}

	function mmkdir($dir, $mode = 0777) {
		if(empty($dir)) return FALSE;
		if($this->is_exists($dir) or $dir == "/" ) return TRUE;
		if(!$this->mmkdir(dirname($dir), $mode)) return false;
		$r=$this->mkdir($dir, $mode);
		$this->chmod($dir,$mode);
		return $r;
	}

	function glob($pattern, $handle=NULL) {
		$path=$output=null;
		if(PHP_OS=='WIN32') $slash='\\';
		else $slash='/';
		$lastpos=strrpos($pattern,$slash);
		if(!($lastpos===false)) {
			$path=substr($pattern,0,-$lastpos-1);
			$pattern=substr($pattern,$lastpos);
		} else $path=getcwd();
		if(is_array($handle) and !empty($handle)) {
			while($dir=each($handle)) {
				if($this->glob_pattern_match($pattern,$dir))
				$output[]=$dir;
			}
		} else {
			$handle=@opendir($path);
			if($handle===false) return false;
			while($dir=readdir($handle)) {
				if($this->glob_pattern_match($pattern,$dir))
				$output[]=$dir;
			}
			closedir($handle);
		}
		if(is_array($output)) return $output;
		return false;
	}

	function glob_pattern_match($pattern,$string) {
		$out=null;
		$chunks=explode(';',$pattern);
		foreach($chunks as $pattern) {
			$escape=array('$','^','.','{','}','(',')','[',']','|');
			while(strpos($pattern,'**')!==false)
				$pattern=str_replace('**','*',$pattern);
			foreach($escape as $probe)
				$pattern=str_replace($probe,"\\$probe",$pattern);
			$pattern=str_replace('?*','*',
				str_replace('*?','*',
					str_replace('*',".*",
						str_replace('?','.{1,1}',$pattern))));
			$out[]=$pattern;
		}
		if(count($out)==1) return($this->glob_regexp("^$out[0]$",$string));
		else {
			foreach($out as $tester)
				if($this->my_regexp("^$tester$",$string)) return true;
		}
		return false;
	}

	function glob_regexp($pattern,$probe) {
		$sensitive=(PHP_OS!='WIN32');
		return ($sensitive?
			ereg($pattern,$probe):
			eregi($pattern,$probe)
		);
	}
// <!-- --------------------------------------------------------------------------------------- -->
// <!--       Private functions                                                                 -->
// <!-- --------------------------------------------------------------------------------------- -->
	function _checkCode() {
		return ($this->_code<400 and $this->_code>0);
	}

	function _list($arg="", $cmd="LIST", $fnction="_list") {
		if(!$this->_data_prepare()) return false;
		if(!$this->_exec($cmd.$arg, $fnction)) {
			$this->_data_close();
			return FALSE;
		}
		if(!$this->_checkCode()) {
			$this->_data_close();
			return FALSE;
		}
		$out="";
		if($this->_code<200) {
			$out=$this->_data_read();
			$this->_data_close();
			if(!$this->_readmsg()) return FALSE;
			if(!$this->_checkCode()) return FALSE;
			if($out === FALSE ) return FALSE;
			$out=preg_split("/[".BACKUPLY_CRLF."]+/", $out, -1, PREG_SPLIT_NO_EMPTY);
//			$this->SendMSG(implode($this->_eol_code[$this->OS_local], $out));
		}
		return $out;
	}

// <!-- --------------------------------------------------------------------------------------- -->
// <!-- Partie : gestion des erreurs                                                            -->
// <!-- --------------------------------------------------------------------------------------- -->
// Génère une erreur pour traitement externe à la classe
	function PushError($fctname,$msg,$desc=false){
		$error=array();
		$error['msg'] = '';
		//FTP Session Timeout
		if($this->_code == 421){
			$error['msg'] .= 'FTP Session Timeout!! ';
		}
		$error['time']=time();
		$error['fctname']=$fctname;
		$error['msg'] .= $msg;
		$error['desc']=$desc;		
		if($desc) $tmp=' ('.$desc.')'; else $tmp='';
		$this->SendMSG($fctname.': '.$msg.$tmp);
		array_push($this->_error_array,$error);
		return(array_push($this->error, $error['msg']));
	}
	
// Récupère une erreur externe
	function PopError(){
		if(count($this->_error_array)) return(array_pop($this->_error_array));
			else return(false);
	}
}


class ftp extends ftp_base {
	
	var $offset = 0;
	var $filesize = 0;
	var $orig_path = '';

	function ftp($verb=FALSE, $le=FALSE) {
		$this->__construct($verb, $le);
	}

	function __construct($verb=FALSE, $le=FALSE) {
		parent::__construct(false, $verb, $le);
	}

// <!-- --------------------------------------------------------------------------------------- -->
// <!--       Private functions                                                                 -->
// <!-- --------------------------------------------------------------------------------------- -->

	function _settimeout($sock) {
		if(!@stream_set_timeout($sock, $this->_timeout)) {
			$this->PushError('_settimeout','socket set send timeout');
			$this->_quit();
			return FALSE;
		}
		return TRUE;
	}

	function _connect($host, $port) {
		$this->SendMSG("Creating socket");
		$sock = @fsockopen($host, $port, $errno, $errstr, $this->_timeout);
		if (!$sock) {
			$this->PushError("_connect","Socket connect failed. ".$errstr." (".$errno.")", $errstr." (".$errno.")");
			return FALSE;
		}
		$this->_connected=true;
		return $sock;
	}

	function _readmsg($fnction="_readmsg"){
		if(!$this->_connected) {
			$this->PushError($fnction, 'Connect first');
			return FALSE;
		}
		$result=true;
		$this->_message="";
		$this->_code=0;
		$go=true;
		do {
			$tmp=@fgets($this->_ftp_control_sock, 512);
			if($tmp===false) {
				$go=$result=false;
				$this->PushError($fnction,'Read failed');
			} else {
				$this->_message.=$tmp;
				if(preg_match("/^([0-9]{3})(-(.*[".BACKUPLY_CRLF."]{1,2})+\\1)? [^".BACKUPLY_CRLF."]+[".BACKUPLY_CRLF."]{1,2}$/", $this->_message, $regs)) $go=false;
			}
		} while($go);
		if($this->LocalEcho) echo "GET < ".rtrim($this->_message, BACKUPLY_CRLF).BACKUPLY_CRLF;
		$this->_code=(int)$regs[1];
		return $result;
	}

	function _exec($cmd, $fnction="_exec") {
		if(!$this->_ready) {
			$this->PushError($fnction,'Connect first');
			return FALSE;
		}
		if($this->LocalEcho) echo "PUT > ",$cmd,BACKUPLY_CRLF;
		$status=@fputs($this->_ftp_control_sock, $cmd.BACKUPLY_CRLF);
		if($status===false) {
			$this->PushError($fnction,'socket write failed');
			return FALSE;
		}
		$this->_lastaction=time();
		if(!$this->_readmsg($fnction)) return FALSE;
		return TRUE;
	}

	function _data_prepare($mode=FTP_ASCII) {
		if(!$this->_settype($mode)) return FALSE;
		if($this->_passive) {
			if(!$this->_exec("PASV", "pasv")) {
				$this->_data_close();
				return FALSE;
			}
			if(!$this->_checkCode()) {
				$this->_data_close();
				return FALSE;
			}
			
			//$ip_port = explode(",", ereg_replace("^.+ \\(?([0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]+,[0-9]+)\\)?.*".BACKUPLY_CRLF."$", "\\1", $this->_message));
			
			$ip_port = explode(",", preg_replace("/^.+ \\(?([0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]+,[0-9]+)\\)?.*".BACKUPLY_CRLF."$/s", "\\1", $this->_message));
			
			$this->_datahost=$ip_port[0].".".$ip_port[1].".".$ip_port[2].".".$ip_port[3];
            $this->_dataport=(((int)$ip_port[4])<<8) + ((int)$ip_port[5]);
			$this->SendMSG("Connecting to ".$this->_datahost.":".$this->_dataport);
			$this->_ftp_data_sock=@fsockopen($this->_datahost, $this->_dataport, $errno, $errstr, $this->_timeout);
			if(!$this->_ftp_data_sock) {
				$this->PushError("_data_prepare","fsockopen fails. ".$errstr." (".$errno.")", $errstr." (".$errno.")");
				$this->_data_close();
				return FALSE;
			}
			else $this->_ftp_data_sock;
		} else {
			$this->SendMSG("Only passive connections available!");
			return FALSE;
		}
		return TRUE;
	}
	
	// Just so that we can connect everywhere
	function init($path){
		global $error;

		if(!preg_match('/ftp:\/\//i', $path)){
			return false;
		}
		
		$url = parse_url($path);
		
		// By default the port is 21
		if(empty($url['port'])){
			$url['port'] = 21;
		}
		
		if(!$this->SetServer($url['host'], $url['port'])) {
			$this->quit();
			$error[] = 'There was an issue Connecting to the FTP server!';
			return false;
		}
		
		if(!$this->connect()){
			$error[] = 'Unable to connect to FTP Server';
			return false;
		}
		
		if(!$this->login(rawurldecode($url['user']), rawurldecode($url['pass']))){
			$this->quit();
			$error[] = 'There was an issue logging in to FTP!';
			return false;
		}
		
		$this->Passive(TRUE);

		return true;
	}
	
	//Downloads files to local server
	function download_file_loop($source, $dest, $startpos = 0){
		global $data, $error;

		if(!$this->init($source)){
			return false;
		}

		$file_size = $data['size'];
		$url = parse_url($source);
		
		//backuply_log('Downloaded (L'.$data['restore_loop'].') : '.$startpos.' / '.$data['size']);

		if($startpos < $file_size) {
			
			$block = $this->pget($url['path'], $dest, $startpos);
			backuply_log('BLOCK : '.$block);
			
			$startpos += $block;
		
			$percentage = ($startpos / $data['size']) * 100;
			backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'uploading', 22);
		}
		
		$GLOBALS['l_readbytes'] = filesize($dest);
		
		return;
	}

	function _data_read($mode=FTP_ASCII, $fp=NULL) {
		if(is_resource($fp)) $out=0;
		else $out="";
		if(!$this->_passive) {
			$this->SendMSG("Only passive connections available!");
			return FALSE;
		}
		while (!feof($this->_ftp_data_sock)) {
			$block=fread($this->_ftp_data_sock, $this->_ftp_buff_size);
			if($mode!=FTP_BINARY) $block=preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_local], $block);
			if(is_resource($fp)){
				$out += fwrite($fp, $block, strlen($block));
			}
			else{
				$out.=$block;
			}
		}
		
		return $out;
	}

	function _pdata_read($mode=FTP_ASCII, $fp=NULL) {
		if(is_resource($fp)) $out=0;
		else $out="";
		if(!$this->_passive) {
			$this->SendMSG("Only passive connections available!");
			return FALSE;
		}
		while (!feof($this->_ftp_data_sock)) {
			
			//leave if about to hit timeout
			if(time() + 5 >= $GLOBALS['end']){
				backuply_log('FTP Download: Short on Time!');
				break;
			}
			
			$block=fread($this->_ftp_data_sock, $this->_ftp_buff_size);
			
			if(empty($block)){
				global $error;
				$error[] = 'Download failed! Not receiving any data from the Server';
				break;
			}
			
			if($mode!=FTP_BINARY) $block=preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_local], $block);
			if(is_resource($fp)){
				$out += fwrite($fp, $block, strlen($block));
			}
			else{
				$out.=$block;
			}
			
		}
		
		return $out;
	}
	
	function _data_write($mode=FTP_ASCII, $fp=NULL) {
		if(is_resource($fp)) $out=0;
		else $out="";
		if(!$this->_passive) {
			$this->SendMSG("Only passive connections available!");
			return FALSE;
		}
		if(is_resource($fp)) {
			while(!feof($fp)) {
				$block=fread($fp, $this->_ftp_buff_size);
				if(!$this->_data_write_block($mode, $block)) return false;
			}
		} elseif(!$this->_data_write_block($mode, $fp)) return false;
		return TRUE;
	}

	function _data_write_block($mode, $block) {
		if($mode!=FTP_BINARY) $block=preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_remote], $block);
		do {
			if(($t=@fwrite($this->_ftp_data_sock, $block))===FALSE) {
				$this->PushError("_data_write","Can't write to socket");
				return FALSE;
			}
			$block=substr($block, $t);
		} while(!empty($block));
		return true;
	}

	function _data_close() {
		@fclose($this->_ftp_data_sock);
		$this->SendMSG("Disconnected data from remote host");
		return TRUE;
	}

	function _quit($force=FALSE) {
		if($this->_connected or $force) {
			@fclose($this->_ftp_control_sock);
			$this->_connected=false;
			$this->SendMSG("Socket closed");
		}
	}
	
	// Takes the DATA rather than the LOCAL file name
	function softput($remotefile=NULL, $softdata, $rest=0) {
		$pi=pathinfo($remotefile);
		if($this->_type==FTP_ASCII or ($this->_type==BACKUPLY_FTP_AUTOASCII and in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) $mode=FTP_ASCII;
		else $mode=FTP_BINARY;
		if(!$this->_data_prepare($mode)) {
			return FALSE;
		}
		if($this->_can_restore and $rest!=0) $this->restore($rest);
		if(!$this->_exec("STOR ".$remotefile, "put")) {
			$this->_data_close();
			return FALSE;
		}
		if(!$this->_checkCode()) {
			$this->_data_close();
			return FALSE;
		}
		$ret=$this->_data_write($mode, $softdata);
		$this->_data_close();
		if(!$this->_readmsg()) return FALSE;
		if(!$this->_checkCode()) return FALSE;
		return $ret;
	}
	
}


