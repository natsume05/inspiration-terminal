<?php

include_once('ftps.php');

class softftpes {
	
	var $ftps_conn = false;
	var $position;
	var $remotefile;
	var $readsize = 0;
	var $ftps;
	var $orig_path = '';
	var $tmpsize = 0;
	var $tpfile = 'php://memory';
	var $writepos = 0;
	var $wp = NULL; // Memory Write Pointer
	var $mode;
	
	function __construct(){
		$this->softftpes();
	}
	
	function softftpes(){
		$this->ftps = new ftps();
	}
	
	function __destruct(){
		$this->position = 0;
		$this->remotefile = '';
	}
	
	// Used to test a connection to the remote server
	function connect($host, $port, $user, $pass, $pri = 0, $passphrase = 0){	//cannot put this code inside constructor since we need to pass the URL, the constructor takes void(no) parameters
		
		global $error;
		
		// There is a bug in PHP versions less than 5.6.0 causing the ftp_put/ftp_fput to fail with error Accepted Data Connection
		// Tested with 5.5.38 and 5.4.45
		if(version_compare(phpversion(), '5.6.0', '<')){
			$error['no_support_php56'] = 'FTPS is not supported for PHP version less than 5.6 your current PHP version is '.phpversion();
			return false;
		}
		
		//__construct is called only before stream_open() in older versions of PHP (< 5.6). In newer versions, it is called before all the stream functions.
		if(!is_object($this->ftps)){
			$this->ftps = new ftps();
		}
		
		//backuply_log($host.' - '.$port.' - '.$user.' - '.$pass);
		$this->ftps_conn = $this->ftps->connect($host, $port, $user, $pass);
		
		if(!empty($this->ftps->error)){
			foreach($this->ftps->error as $k => $v){
				$error[] = $v;
			}
		}
		
		return $this->ftps_conn;
	}
	
	// Just so that we can connect everywhere
	function init($path, &$url = array()){		
		
		if(!preg_match('/softftpes:\/\//i', $path)){
			return false;
		}
		
		$url = parse_url($path);
		
		// By default the port is 21
		if(empty($url['port'])){
			$url['port'] = 21;
		}
		
		// Are we to connect
		if(empty($this->ftps_conn)){
			$this->connect($url['host'], $url['port'], rawurldecode($url['user']), rawurldecode($url['pass']));
		}
		
		if(empty($this->ftps_conn)){
			return false;
		}
		
		return $this->ftps_conn;
	}
	
	// For fopen
	function stream_open($path, $mode){
		
		if(!$init = $this->init($path, $url)){
			return $init;
		}
		
		//echo 'IN OPEN : '.$this->ftps_conn.' - '.$path."\n";
		//backuply_print($this->ftps->error);
		//echo 'HERE';
		//die();
		
		//backuply_log('[stream_open] '.$path);
		
		$this->orig_path = $path;
		$this->mode = $mode;
		$this->remotefile = $url['path'];
		$this->position = 0;
		
		if(strpos($this->mode, 'r') !== FALSE){
			$this->readsize = filesize($this->orig_path);
			
			if(empty($this->readsize)){
				return false;
			}
			
		}
		
		
		return $this->ftps_conn;
	}
	
	// AS of now not used
	function stream_read($count){
		
		if(!$this->ftps_conn){
			return false;
		}
		
		$fp = fopen('php://temp/maxmemory:'.$count, 'r+');		
		
		if(!ftp_fget($this->ftps->ftp_conn, $fp, $this->remotefile, FTP_BINARY, $this->position)){
			$this->error[] = "Remote File $remotefile could not be accessed";
			$this->error[] = "It looks like FTPS protocol is not supported with your PHP, Please try FTP/SFTP protocol";
			return false;
		}
		
		$contents = '';
		rewind($fp);
		
		while (!feof($fp)) {
			$contents .= fread($fp, 8192);
		}
		
		fclose($fp);
		
		if(empty($contents)){
			return false;
		}
		
		$this->position = $this->position + $count;
		
		return $contents;
		
	}

	function stream_write($data){
		
		$strlen = strlen($data);
		
		//echo 'IN WRITE : '.$strlen."\n";
		
		//echo $this->remotefile.' - '.strlen($data);die();
		
		if(!$this->ftps_conn){
			return false;
		}
		
		if(!is_resource($this->wp)){
			$this->wp = fopen($this->tpfile, 'w+');
		}
		
		//Initially store the data in a variable
		fwrite($this->wp, $data);
		$this->tmpsize += strlen($data);
		
		$ret = $strlen;
		
		$this->position += $strlen;
		
		// Are we already more than 4 MB ?
		if($this->tmpsize >= 2000000){
			
			rewind($this->wp);
			
			if(!empty($GLOBALS['start_pos'])){
				$this->writepos = $GLOBALS['start_pos'];
			}
			
			if(!$this->ftps->backup_softput($this->remotefile, $this->wp, $this->writepos)){
				$ret = false;
			}
			
			$GLOBALS['start_pos'] += $this->tmpsize;
			
			// Close the temp file and reset the variables
			if(is_resource($this->wp)){
				fclose($this->wp);
			}
			$this->wp = NULL;
			$this->tmpsize = 0;
		
		}
		
		return $ret;
		
	}
	
	function stream_close(){
		
		$ret = true;
		
		if(preg_match('/w|a/is', $this->mode)){
		
			// Are we already more than 4 MB ?
			if($this->tmpsize > 0 && is_resource($this->wp)){
				
				rewind($this->wp);
				
				$this->writepos = $GLOBALS['start_pos'];
			
				if(!$this->ftps->backup_softput($this->remotefile, $this->wp, $this->writepos)){
					$ret = false;
				}
				
				$GLOBALS['start_pos'] += $this->tmpsize;
				
				// Close the temp file and reset the variables
				if(is_resource($this->wp)){
					fclose($this->wp);
				}
				$this->wp = NULL;
				$this->tmpsize = 0;
				
			}
			
		}
		
		return $ret;
	}
	
	function stream_eof(){
		return $this->position >= $this->readsize;
	}
	
	function stream_tell(){
		return $this->position;
	}
	
	function dir_opendir($path, $options){
		
		if(!$init = $this->init($path, $url)){
			return $init;
		}
		
		$this->orig_path = $path;
		$this->remotefile = $url['path'];
		
		$this->filelist = $this->ftps->filelist($this->remotefile);
		//backuply_print($this->filelist);
		
		return true;
	}

	function dir_readdir(){
		$key = key($this->filelist);
		if(is_null($key)){
			return false;
		}
		$val = $this->filelist[$key];
		unset($this->filelist[$key]);
		return pathinfo($val, PATHINFO_BASENAME);
	}

	function dir_closedir(){
		// Do nothing
	}
	
	// Download a remote file to the local file
	function download_file_loop($path, $localfile, $startpos = 0){
		
		global $data, $error;
		
		if(!$init = $this->init($path, $url)){
			return $init;
		}
		
		backuply_log($url['path'].' - '.$startpos);
		//die();
		
		$lfp = @fopen($localfile, 'ab');
		$ret = ftp_nb_fget($this->ftps->ftp_conn, $lfp, $url['path'], FTP_BINARY, $startpos);
		$loop_break = false;
		$size_tracker = $startpos;
		
		while ($ret == FTP_MOREDATA){
			
			clearstatcache();
			
			if((filesize($localfile) - $size_tracker) > 1000000){
				backuply_status_log('Downloaded File (L'.$data['restore_loop'].') : '.filesize($localfile));
				$size_tracker = filesize($localfile);
			}
			
			if((time() + 5) >= $GLOBALS['end']){
				backuply_status_log('Breaking Loop (L'.$data['restore_loop'].') at FileSize : '.filesize($localfile));
				$loop_break = true;
				break;
			}
			
			/* // For testing
			if((filesize($localfile) - $startpos) > 50000000){
				backuply_status_log('Breaking Loop (L'.$data['restore_loop'].') at FileSize : '.filesize($localfile));
				$loop_break = true;
				break;
			}*/
			
			// Continue downloading...
			$ret = ftp_nb_continue($this->ftps->ftp_conn);
			
		}
		
		if (empty($loop_break) && $ret != FTP_FINISHED) {
			$error[] = 'There was an error downloading the file...';
			return false;
		}
		
		backuply_status_log('Downloaded FileSize in L'.$data['restore_loop'].' : '.filesize($localfile));
		
		$percentage = (filesize($localfile) / $data['size']) * 100;
		
		backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'downloading', 22);
		
		$GLOBALS['l_readbytes'] = filesize($localfile);
		
		return $ret;
	}
	
	function mkdir($path, $mode, $options){
		
		if(!$init = $this->init($path, $url)){
			return $init;
		}
		
		$ret = $this->ftps->mmkdir($url['path'], $mode);
		
		return $ret;
			
	}
	
	function rmdir($path, $options){
		
		if(!$init = $this->init($path, $url)){
			return $init;
		}
		
		$res = $this->ftps->rmdir($url['path']);
		return $res;
		
	}	
	
	function url_stat($path, $flags){
		
		if(!$init = $this->init($path, $url)){
			return $init;
		}
		
		$url['path'] = $this->cleanpath($url['path']);
		
		if(empty($url['path'])){
			$url['path'] = '/';
		}
		
		if($url['path'] == '/'){
			$file = '.';
			$dir = $url['path'];
		}else{
			$file = basename($url['path']);
			$dir = dirname($url['path']);
		}
		
		$dir = $this->cleanpath($dir);
		if(empty($dir)){
			$dir = '/';
		}
		
		//echo $file.' - '.$dir." -- - - -\n";die();
		
		$list = $this->ftps->rawlist($dir);
		
		foreach($list as $k => $v){
			
			$list[$k] = $this->ftps->_parselisting($v);
			
			if($list[$k]['name'] != $file){
				continue;
			}
			
			
			$stat = array('dev' => 0,
						'ino' => 0,
						'mode' => (!empty($list[$k]['mode']) ? octdec($list[$k]['mode']) : 0),
						'nlink' => 0,
						'uid' => 0,
						'gid' => 0,
						'rdev' => 0,
						'size' => $list[$k]['size'],
						'atime' => $list[$k]['time'],
						'mtime' => $list[$k]['time'],
						'ctime' => $list[$k]['time'],
						'blksize' => -1,
						'blocks' => -1
					);
					
			return $stat + array_values($stat);			
			
		}
		
		return false;

	}
	
	function unlink($path){
		
		if(!$init = $this->init($path, $url)){
			return $init;
		}
		
		$res = $this->ftps->delete($url['path']);
		return $res;
	}
	
	function rename($from, $to){
		
		if(!$init = $this->init($from, $url)){
			return $init;
		}
		
		$url_from = parse_url($from);
		$url_to = parse_url($to);
		
		//echo 'Rename : '.$url_from['path'].' - '.$url_to['path']."\n";
		
		return $this->ftps->rename($url_from['path'], $url_to['path']);
		
	}

	function cleanpath($path){
		$path = str_replace('\\\\', '/', $path);
		$path = str_replace('\\', '/', $path);
		$path = str_replace('//', '/', $path);
		return rtrim($path, '/');
	}

}
