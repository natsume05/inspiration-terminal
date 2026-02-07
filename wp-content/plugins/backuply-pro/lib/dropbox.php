<?php

#[\AllowDynamicProperties]
class dropbox{

	var $access_token;
	var $backup_loc;
	var $path;
	var $filename;
	var $filesize = 0;
	var $session_id = '';
	var $offset = 0;
	var $tmpsize = 0;
	var $tpfile = '';
	var $mode = '';
	var $wp = NULL; // Memory Write Pointer
	var $chunk = 2097152;	//1MB
	var $range_lower_limit = 0;
	var $range_upper_limit = 0;
	var $refresh_token = '';
	var $filelist = [];
	var $readsize = 0;
	var $orig_path = '';
	
	// APP name is Softaculous auto installer and is assigned to sales@softaculous.com Dropbox account
	var $app_key = '';
	var $app_secret = '';
	var $token_url = 'https://api.backuply.com/dropbox/token.php';
	
	function stream_open($path, $mode, $options, &$opened_path){
		global $error, $backuply;

		$stream = parse_url($path);
		
		$this->refresh_token = $stream['host'];
		
		$this->access_token = $this->refresh_access_token();
		$this->path = $stream['path'];
		$this->mode = $mode;
		$this->orig_path = $path;
		
		$pathinfo = pathinfo($this->path);
		$this->filename = $pathinfo['basename'];
		$dir = trim($pathinfo['dirname'], '/\\');
		$this->backup_loc = (empty($dir) ? '' : $pathinfo['dirname']);
		//php://memory not working on localhost
		$this->tpfile = 'php://temp';		
		$ret = true;
		
		// if its a read mode the check if the file exists
		if(strpos($this->mode, 'r') !== FALSE){
			$this->url_stat($path, '');
			
			if(empty($this->filesize)){
				return false;
			}
		}
		
		if(preg_match('/w|a/is', $this->mode)){
			$this->offset = 0;
			$tfp = fopen($this->tpfile, $this->mode);
			
			if(empty($backuply['status']['init_data'])){
				backuply_log('----------Creating a new session-------');
				$ret = $this->upload_start($tfp);
			}else{
				$ret = true;
			}
			
			fclose($tfp);
		}
		
		return $ret;		
	}
	
	// Dropbox Create a file
	function upload_start($fp){
		global $error, $backuply;

		$upload_url = 'https://content.dropboxapi.com/2/files/upload_session/start';
		$headers = array('Authorization: Bearer '.$this->access_token,
						'Dropbox-API-Arg: {"close": false}',
						'Content-Type: application/octet-stream');
		
		$resp = $this->__curl($upload_url, $headers, $fp, 0);
		
		if(empty($resp) || empty($resp['session_id'])){
			$error[] = 'Was unable to create session with dropbox';
			backuply_log('--------Was unable to create a session with dropbox-------');
			return false;
		}
		
		$this->session_id = $resp['session_id'];
		$backuply['status']['init_data'] = $this->session_id;
		
		return true;
	}
	
	function stream_write($data){
		global $error;
		
		if(!is_resource($this->wp)){
			$this->wp = fopen($this->tpfile, 'w+');
		}
		
		//Initially store the data in a memory
		fwrite($this->wp, $data);		
		$this->tmpsize += strlen($data);
		$data_size = strlen($data);
		
		// Are we already more than 2 MB ?
		if($this->tmpsize >= $this->chunk){
			rewind($this->wp);
			
			//Call upload append function to write the data from PHP Memory stream to Dropbox
			$this->upload_append($this->session_id, $this->wp, $this->tmpsize);
			
			// Close the temp file and reset the variables
			fclose($this->wp);
			$this->wp = NULL;
			$this->tmpsize = 0;
		}
		
		return $data_size;	
	}
	
	// Dropbox API to upload
	function upload_append($session_id, $filep, $data_size){
		global $error, $backuply;
		
		if(!empty($backuply['status']['init_data'])){
			$this->session_id = $backuply['status']['init_data'];
		}
		
		if(!empty($GLOBALS['start_pos'])){
			$this->offset = $GLOBALS['start_pos'];
		}
		
		$args = json_encode(array('cursor' => array('session_id' => $this->session_id, 
			'offset' => $this->offset), 
			'close' => false)
		);
		
		$upload_url = 'https://content.dropboxapi.com/2/files/upload_session/append_v2';
		$headers = array('Authorization: Bearer '.$this->access_token,
						'Dropbox-API-Arg: '.$args,
						'Content-Type: application/octet-stream');
		
		$resp = $this->__curl($upload_url, $headers, $filep, $data_size);

		if(is_null($resp)){
			$this->offset += $data_size;
			$GLOBALS['start_pos'] = $this->offset;
			return $data_size;
		}
		
		return false;
	}
	
	function stream_close(){
		global $backuply, $error;
		
		if(preg_match('/w|a/is', $this->mode)){
			// Is there still some data left to be written ?
			if($this->tmpsize > 0){
				
				rewind($this->wp);
				
				// Call upload append function to write the data from PHP Memory stream to Dropbox
				$data_size = $this->upload_append($this->session_id, $this->wp, $this->tmpsize);
				
				// Close the temp file and reset the variables
				fclose($this->wp);
				$this->wp = NULL;
				$this->tmpsize = 0;
			}
			
			if(empty($backuply['status']['incomplete_upload'])){
				$upload_url = 'https://content.dropboxapi.com/2/files/upload_session/finish';
				$headers = array('Authorization: Bearer '.$this->access_token,
								'Dropbox-API-Arg: {"cursor":{"session_id":"'.$this->session_id.'","offset":'.$this->offset.'},"commit":{"path":"'.$this->path.'","mode":"add","autorename": true,"mute": false}}',
								'Content-Type: application/octet-stream');
				
				$resp = $this->__curl($upload_url, $headers);
			}
		}
		
		return true;
	}
	
	//In response to file_exists(), is_file(), is_dir()
	function url_stat($path , $flags){
		global $error;

		$stream = parse_url($path);
		$this->refresh_token = $stream['host'];
		
		if(empty($this->access_token)) {
			$this->access_token = $this->refresh_access_token();
		}
		
		$pathinfo = pathinfo($stream['path']);
		
		$filename = $pathinfo['basename'];
		$dir = trim($pathinfo['dirname'], '/\\');
		$path = (empty($dir) ? '' : $pathinfo['dirname']);
		
		//Metadata for the root folder is unsupported
		if(!empty($filename)){
			$data = json_encode(array('path' => $path.'/'.$filename, 'include_media_info' => false, 'include_deleted' => false, 'include_has_explicit_shared_members' => false));
			$url = 'https://api.dropboxapi.com/2/files/get_metadata';
			$headers = array('Authorization: Bearer '.$this->access_token,
							'Content-Type: application/json');
			
			$resp = $this->__curl($url, $headers, '', 0, $data, '', 1);
			
			if($resp['.tag'] == 'file'){
				$mode = 0100000;	//For File
			}elseif($resp['.tag'] == 'folder'){
				$mode = 0040000;	//For DIR
			}
			
			if(!empty($resp['id'])){
				$stat = array('dev' => 0,
					'ino' => 0,
					'mode' => $mode,
					'nlink' => 0,
					'uid' => 0,
					'gid' => 0,
					'rdev' => 0,
					'size' => $resp['size'],
					'atime' => strtotime($resp['client_modified']),
					'mtime' => strtotime($resp['client_modified']),
					'ctime' => strtotime($resp['client_modified']),
					'blksize' => 0,
					'blocks' => 0
				);
					
				$this->filesize = $stat['size'];
				return $stat;
			}
		}
		return false;
	}
	
	// AS of now not used
	function stream_read($count){
	
		if($count == ''){
			return false;
		}
		
		if(empty($this->range_lower_limit)){
			$this->range_lower_limit = 0;
		}

		$this->range_upper_limit = ($this->range_lower_limit + $count) - 1;
		
		if($this->range_upper_limit >= $this->filesize){
			$this->range_upper_limit = $this->filesize - 1;
		}
		
		$tmp_file = backuply_glob('backups_info') . '/test.tmp';
		
		$this->__write($this->path, $tmp_file, $this->range_lower_limit, $this->range_upper_limit);

		$resp = file_get_contents($tmp_file);

		@unlink($tmp_file);

		$this->offset = $this->range_upper_limit + 1;
		$this->range_lower_limit = $this->range_upper_limit + 1;
		
		return $resp;
		
	}
	
	//Download Backup File from Dropbox to local server
	function download_file_loop($source, $dest, $startpos = 0){
		global $error, $data;
		
		$stream = parse_url($source);
		$this->refresh_token = $stream['host'];
		
		if(empty($this->access_token)) {
			$this->access_token = $this->refresh_access_token();
		}
		
		$path = $stream['path'];
		
		//Set $this->filesize variable to remote tar file's size
		$this->url_stat($source, '');
		
		$this->range_lower_limit = $startpos;
		$this->range_upper_limit = ($this->range_lower_limit + $this->chunk) - 1;
		
		while(!$this->__eof()){
			
			if(time() >= $GLOBALS['end']){
				//$GLOBALS['l_readbytes'] = filesize($dest);
				break;
			}			
			
			if($this->range_upper_limit >= $this->filesize){
				$this->range_upper_limit = $this->filesize - 1;
			}
			
			$this->__write($path, $dest, $this->range_lower_limit, $this->range_upper_limit);
			
			$this->offset = $this->range_upper_limit + 1;
			$this->range_lower_limit = $this->range_upper_limit + 1;
			$this->range_upper_limit = ($this->range_lower_limit + $this->chunk) - 1;
			
			
			$percentage = (filesize($dest) / $this->filesize) * 100;
			
			backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'downloading', 22);
			
		}
		
		$GLOBALS['l_readbytes'] = filesize($dest);
		
		return true;
	}
	
	function __write($path, $dest, $lower_limit, $upper_limit){
		global $error;
		
		$data = json_encode(array('path' => $path));
		$url = 'https://content.dropboxapi.com/2/files/download';
		$headers = array('Authorization: Bearer '.$this->access_token,
						'Dropbox-API-Arg: '.$data,
						'Range: bytes='.$lower_limit.'-'.$upper_limit,
						'Content-Type:');
		
		$sfp = fopen($dest, 'ab');
		$resp = $this->__curl($url, $headers, '', 0, '', $sfp);
		fclose($sfp);
		
		if(empty($resp) && !empty($error)){
			return false;
		}
		return $resp['result'];
	}
	
	function stream_eof(){
		if($this->offset < $this->filesize){
			return false;
		}
		return true;
	}
	
	function __eof(){
		if($this->offset < $this->filesize){
			return false;
		}
		return true;
	}
	
	function dir_opendir($path, $options){
		$stream = parse_url($path);

		$this->refresh_token = $stream['host'];
		//Google Drive access token expires in an hour so we need to refresh
		$this->access_token = $this->refresh_access_token($this->refresh_token);
		
		$data = json_encode(
			array('path' => isset($stream['path']) ? $stream['path'] : '' ,
					'include_deleted' => false,
					'include_has_explicit_shared_members' => false,
					'include_media_info' => false,
					'include_mounted_folders' => true,
					'include_non_downloadable_files' => FALSE,
					'recursive' => false));

			$url = 'https://api.dropboxapi.com/2/files/list_folder';
			$headers = array('Authorization: Bearer '.$this->access_token,
							'Content-Type: application/json');
			
		$resp = $this->__curl($url, $headers, '', 0, $data, '');

		if(!empty($resp['error'])){
			$error[] = 'Dropbox : '.$resp['error'];
			return false;
		}
		
		$this->filelist = $resp['entries'];
		
		if(empty($this->filelist)){
			$error[] = 'Dropbox : No File Found';
			return false;
		}
		
		foreach($this->filelist as $i => $file) {
			$this->filelist[$i] = $file['name'];
		}

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
	
	
	//Delete the backup from Dropbox
	function unlink($path){
		global $error;
		
		$stream = parse_url($path);
		$this->refresh_token = $stream['host'];
		
		if(empty($this->access_token)) {
			$this->access_token = $this->refresh_access_token();
		}
		
		$this->path = $stream['path'];
		
		$data = json_encode(array('path' => $this->path));
		$url = 'https://api.dropboxapi.com/2/files/delete_v2';
		$headers = array('Authorization: Bearer '.$this->access_token,
						'Content-Type: application/json');
		
		$resp = $this->__curl($url, $headers, '', 0, $data);
		
		if(empty($resp)){
			return false;
		}
		return true;
	}	
	
	function rename($from, $to){
		global $error;
		
		$stream_from = parse_url($from);
		$this->refresh_token = $stream_from['host'];
		
		if(empty($this->access_token)) {
			$this->access_token = $this->refresh_access_token();
		}

		$from_path = $stream_from['path'];
		
		$stream_to = parse_url($to);
		$to_path = $stream_to['path'];
		
		$data = json_encode(array('from_path' => $from_path, 'to_path' => $to_path, 'allow_shared_folder' => false, 'autorename' => false, 'allow_ownership_transfer' => false));
		$url = 'https://api.dropboxapi.com/2/files/move_v2';
		$headers = array('Authorization: Bearer '.$this->access_token,
						'Content-Type: application/json');
		
		$resp = $this->__curl($url, $headers, '', 0, $data);
		
		if(empty($resp)){
			return false;
		}		
		return true;
	}

	/**
	 * Generate Dropbox Access Token from the Authorization Code provided
	 *
	 * @package	softaculous 
	 * @author	Priya Mittal
	 * @param	string $auth_code The authorization code generated by user during access grant process
	 * @return	string $token Dropbox Access Token which we can use to create backup files
	 * @since	4.9.4
	 */
	function generate_dropbox_token($auth_code){

		$post = array('code' => $auth_code,
			'action' => 'get_refresh_token'
		);

		$resp = $this->__curl($this->token_url, '', '', 0, $post);
		
		return $resp;
	}
	
	/**
	 * Generates access token using refresh token
	 * Dropbox access token has TTL of 4 hours(14400s)
	**/
	function refresh_access_token(){
		
		$post = array(
			'refresh_token' => $this->refresh_token,
			'action' => 'get_access_token'
		);

		$resp = $this->__curl($this->token_url, '', '', 0, $post);

		return $resp['access_token'];
	}
	
	function __curl($url, $headers = '', $filepointer = '', $upload_size = 0, $post = '', $download_file = '', $ignore_errors = 0){
		global $error;
		
		// Set the curl parameters.
		$ch = curl_init($url);
		
		if(!empty($headers)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		
		if(!empty($filepointer)){
			curl_setopt($ch, CURLOPT_PUT, true);
			curl_setopt($ch, CURLOPT_INFILE, $filepointer);
			curl_setopt($ch, CURLOPT_INFILESIZE, $upload_size);
		}
		
		if(!empty($post)){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		//curl_setopt($ch, CURLOPT_VERBOSE, TRUE);

		// Turn off the server and peer verification (TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		if(!empty($download_file)){
			curl_setopt($ch, CURLOPT_FILE, $download_file);
		}
		
		// Get response from the server.
		$resp = curl_exec($ch);
		
		curl_close($ch);
		
		$result = json_decode($resp, true);
		
		if(!empty($result['error']) && empty($ignore_errors)){
			if($result['error']['.tag'] == 'invalid_access_token'){
				$error[$result['error']['.tag']] = 'Invalid Access Token. Please Re-Authorize Dropbox APP from the Backup Location Tab';
				
			}elseif($result['error'][$result['error']['.tag']]['.tag'] == 'insufficient_space'){
				$error[$result['error'][$result['error']['.tag']]['.tag']] = 'Your Dropbox account is full. Please free some space and attempt the backup after sometime';
				
			}elseif(!empty($result['error'][$result['error']['.tag']]['.tag'])){
				$error[] = $result['error'][$result['error']['.tag']]['.tag'];
				
			}else{
				$error[$result['error']['.tag']] = $result['error']['.tag'];
			}
			
			return false;
		}
		
		return $result;
	}
	
	function get_quota($path){
		$stream = parse_url($path);
		$this->refresh_token = $stream['host'];
		
		if(empty($this->access_token)) {
			$this->access_token = $this->refresh_access_token();
		}

		$url = 'https://api.dropboxapi.com/2/users/get_space_usage';
		$headers = array('Authorization: Bearer '.$this->access_token);
		
		$resp = $this->__curl($url, $headers);

		if(empty($resp)){
			return false;
		}

		return ['total' => $resp['allocation']['allocated'], 'used' => $resp['used']];
	}
}
