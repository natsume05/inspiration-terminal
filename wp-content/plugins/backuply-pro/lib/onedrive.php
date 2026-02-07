<?php

#[\AllowDynamicProperties]
class onedrive{
	
	var $access_token;
	var $refresh_token;
	var $path;
	var $filename;
	var $filesize = 0;
	var $complete = 0;
	var $offset = 0;
	var $tmpsize = 0;
	var $chunk = 1310720;
	var $buffer = '';
	var $range_lower_limit = 0;
	var $range_upper_limit = 0;
	var $bytes_remaining = 0;
	var $num_bytes = 0;
	var $num_fragments = 0;
	var $chunk_size = 0;
	var $onedrive_file_id = '';
	var $mode = '';
	var $wp = NULL; // Memory Write Pointer
	var $upload_url = '';
	var $local_dest = '';
	var $root_folder_path = 'root:';
	var $graph_api_url = 'https://graph.microsoft.com/v1.0/me/drive/';
	// APP name is Softaculous Auto Installer and is assigned to developers@softaculous.com Microsoft account
	var $app_key = '';
	var $app_secret = '';
	var $token_url = 'https://api.backuply.com/onedrive/token.php';
	var $app_dir = 'Backuply';
	var $redirect_uri = 'https://api.backuply.com/onedrive/callback.php';
	var $scopes = 'files.Read Files.ReadWrite offline_access';
	var $filelist = [];
	var $orig_path = '';
	var $retry = 0;

	function stream_open($path, $mode, $options, &$opened_path){
		global $error, $backuply;

		$stream = parse_url($path);
		$this->refresh_token = $stream['host'];
		$this->path = $stream['path'];
		$this->mode = $mode;
		$this->orig_path = $path;

		//One Drive access token expires in an hour so we need to refresh
		$this->access_token = $this->refresh_token_func($this->refresh_token);
		
		$pathinfo = pathinfo($this->path);
		$dirlist = explode('/', $pathinfo['dirname']);
		$this->filename = $pathinfo['basename'];
		$this->filesize = (!empty($backuply['status']) && !empty($backuply['status']['proto_file_size'])) ? $backuply['status']['proto_file_size'] : 0;

		// if its a read mode the check if the file exists
		if(strpos($this->mode, 'r') !== FALSE){
			$this->filesize = filesize($this->orig_path);
			
			if(empty($this->filesize)){
				return false;
			}
		}
		
		if(empty($backuply['status']['init_data'])){
			$this->create_upload_session();
		}else{
			$this->upload_url = $backuply['status']['init_data'];
		}
		
		return true;
	}
	
	function stream_read($count) {
		
		if(!$this->access_token){
			return false;
		}

		// Get the readsize
		if(empty($this->readsize)){
			$this->readsize = filesize($this->orig_path);
		}
		
		$url = parse_url($this->orig_path);
		
		$url = $this->graph_api_url.$this->root_folder_path.rawurlencode($url['path']).':';
		$headers = array('Content-Type: application/json',
						"Cache-Control: no-cache",
						"Pragma: no-cache",
						"Authorization: bearer ".$this->access_token);
		
		$resp = $this->__curl($url, $headers, '', 'GET');

		//Fetch download URL
		$object = json_decode($resp['result'], true);
		$download_url = $object['@microsoft.graph.downloadUrl'];

		$block = $this->__read($download_url, $this->offset, ($this->offset + $count - 1));
		//backuply_log($block);
		$ret = substr($block, 0, $count);
		
		if(empty($ret)){
			return false;
		}
		
		$this->offset = $this->offset + $count;
		return $ret;
		
	}

	function stream_write($data){
		global $error, $backuply;
		
		$this->buffer .= $data;
		
		$data_size = strlen($data);
		
		if(strlen($this->buffer) >= $this->chunk){
			
			$D = $this->buffer;
			$this->buffer = '';
			
			//Call upload append function to write the data from Local tar file to One Drive
			$retcode = $this->upload_append($this->upload_url, $D, $this->filesize);
			$GLOBALS['start_pos'] += strlen($D);
			$percentage = ($GLOBALS['start_pos'] / $this->filesize) * 100;

			backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'uploading', 78);
		
		}
		
		return $data_size;
	}

	function stream_close(){
		global $error;
		
		if(!empty($this->buffer)){
			
			$D = $this->buffer;
			$this->buffer = '';
			
			//Call upload append function to write the data from Local tar file to One Drive
			$retcode = $this->upload_append($this->upload_url, $D, $this->filesize);
			$GLOBALS['start_pos'] += strlen($D);
			$percentage = ($GLOBALS['start_pos'] / $this->filesize) * 100;

			backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'uploading', 78);
		
		}
		
		
		return true;
	}

	//One Drive API to create an Upload Session
	function create_upload_session(){
		global $error, $l, $backuply;
		
		$url = $this->graph_api_url.$this->root_folder_path.rawurlencode($this->path).':/createUploadSession';
		$headers = array('Content-Type: application/json',
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"Authorization: bearer ".$this->access_token
		);
		
		$data= '{}';
		$response = $this->__curl($url, $headers, $data);

		if(!empty($response['error'])){
			$error[] = $response['error'];
			return false;
		}

		$resp_data = json_decode($response['result'], true);
		$this->upload_url = $resp_data['uploadUrl'];
		if(empty($this->upload_url)){
			$error[] = $l['onedrive_err_upload_url'];
			return false;
		}
		
		// Initializing the status index
		if(empty($backuply['status'])){
			$backuply['status'] = [];
		}
		
		$backuply['status']['init_data'] = $this->upload_url;
		
		//backuply_log(' upload url : '.$this->upload_url);
		
		return true;
	}

	//One Drive API to append
	function upload_append($upload_url, $data, $final_size){
		global $error, $l, $backuply;
		
		$this->chunk_size = $this->num_bytes = strlen($data);
		$this->range_lower_limit = $GLOBALS['start_pos'];
		$this->range_upper_limit = $GLOBALS['start_pos'] + $this->chunk_size - 1;
		
		$headers = array(
			'Content-Length: '.$this->num_bytes, 
			'Content-Range: bytes '.$this->range_lower_limit.'-'.$this->range_upper_limit.'/'.$final_size
		);
		
		$response = $this->__curl($upload_url, $headers, $data, 'PUT');
		//backuply_log('upload append response : '. var_export($headers, 1). var_export($response, 1));
		
		if(!empty($response['error'])){
			$this->retry += 1; 
			if($this->retry <= 3){
				sleep(1);
				//backuply_log('Attemting retry bcoz of error'. $this->retry);
				return $this->upload_append($upload_url, $data, $final_size);
			}

			$error[] = $response['error'];
			return false;
		}

		//Check for response code
		$resp_obj = json_decode($response['result'], true);

		if(empty($resp_obj)){
			$this->retry += 1; 
			if($this->retry <= 3){
				sleep(1);
				//backuply_log('Attemting Attemting retry bcoz malfromed body'. $this->retry);
				return $this->upload_append($upload_url, $data, $final_size);
			}
		    
			$error[] = 'OneDrive retured an unexpected response';
			return false;
		}

		$retcode = '404';

		if (array_key_exists("nextExpectedRanges",$resp_obj)){
			$retcode = '308 Resume Incomplete';
		}else if(array_key_exists('id', $resp_obj)){
			$retcode = '201 Created';
		}else{
			$retcode = '416 Requested Range Not Satisfiable';
		}

		if($retcode != '308 Resume Incomplete' && $retcode != '201 Created'){
			$error[] = $retcode;
			return false;
		}
		
		if($retcode == '308 Resume Incomplete'){
			$this->range_lower_limit = $this->range_upper_limit + 1;
			$this->offset = $this->range_upper_limit + 1;

		}elseif($retcode == '201 Created'){
			$this->onedrive_file_id = $resp_obj['id'];
		}
		
		$this->retry = 0;

		return $retcode;
	}

	//In response to file_exists(), is_file(), is_dir()
	function url_stat($path){
		global $error;

		$stream = parse_url($path);
		$this->refresh_token = $stream['host'];
		$file_path = $stream['path'];
		$pathinfo = pathinfo($stream['path']);
		$filename = $pathinfo['basename'];

		//One Drive access token expires in an hour so we need to refresh
		if(empty($this->access_token)){
			$this->access_token = $this->refresh_token_func($this->refresh_token);
		}

		//Metadata for the root folder is unsupported
		if(!empty($filename)){
			
			$url=$this->graph_api_url.$this->root_folder_path.rawurlencode($file_path).':';
			$headers = array('Content-Type: application/json',
							"Cache-Control: no-cache",
							"Pragma: no-cache",
							"Authorization: bearer ".$this->access_token); 
			$resp = $this->__curl($url, $headers, '', 'GET');
			
			$data = json_decode($resp['result'], true);
			if(array_key_exists("folder",$data)){
				$mode = 0040000;	//For DIR
			}else{
				$mode = 0100000;	//For File
			}
			
			if(!empty($data['id'])){
				$stat = array('dev' => 0,
							'ino' => 0,
							'mode' => $mode,
							'nlink' => 0,
							'uid' => 0,
							'gid' => 0,
							'rdev' => 0,
							'size' => $data['size'],
							'atime' => strtotime($data['createdDateTime']),
							'mtime' => strtotime($data['fileSystemInfo']['lastModifiedDateTime']),
							'ctime' => strtotime($data['fileSystemInfo']['createdDateTime']),
							'blksize' => 0,
							'blocks' => 0);
					
				$this->filesize = $stat['size'];
				return $stat;
			}
		}
	
		return false;
	}

	//Get onedrive file/folder id if exist
	function get_onedrive_file_id($filename, $refresh_token = ''){
		global $error, $l;

		if(!empty($refresh_token)){
			$this->refresh_token = $refresh_token;
		}

		//One Drive access token expires in an hour so we need to refresh
		if(empty($this->access_token)){
			$this->access_token = $this->refresh_token_func($this->refresh_token);
		}
		
		$url = $this->graph_api_url.$this->root_folder_path.'/'.rawurlencode($filename).':';
		$headers = array('Content-Type: application/json',
						"Cache-Control: no-cache",
						"Pragma: no-cache",
						"Authorization: bearer ".$this->access_token); 
		$response = $this->__curl($url, $headers, '', 'GET');

		if(!empty($response['error'])){
			//$error[] = $response['error'];
			return false;
		}

		$data = json_decode($response['result'], true);

		if(!empty($data['error'])){
			
			return false;
		}

		$this->onedrive_file_id = $data['id'];
		return $this->onedrive_file_id;
	}

	//Download Backup File from One Drive to local server
	function download_file_loop($source, $dest, $startpos = 0){
		global $error;

		$stream = parse_url($source);
		$this->refresh_token = $stream['host'];
		$path = $stream['path'];

		//One Drive access token expires in an hour so we need to refresh
		if(empty($this->access_token)){
			$this->access_token = $this->refresh_token_func($this->refresh_token);
		}

		$this->get_onedrive_file_id($path);
		$file_stats = $this->url_stat($source);
		$this->filesize = $file_stats['size'];

		$url = $this->graph_api_url.$this->root_folder_path.rawurlencode($path).':';
		$headers = array('Content-Type: application/json',
						"Cache-Control: no-cache",
						"Pragma: no-cache",
						"Authorization: bearer ".$this->access_token);
		
		$resp = $this->__curl($url, $headers, '', 'GET');

		//Fetch download URL
		$object = json_decode($resp['result'], true);
		$download_url = $object['@microsoft.graph.downloadUrl'];

		$this->range_lower_limit = $startpos;
		$this->range_upper_limit = ($this->range_lower_limit + $this->chunk) - 1;

		$fp = @fopen($dest, "ab");
		while(!$this->__eof()){

			if(time() >= $GLOBALS['end']){
				break;
			}

			if($this->range_upper_limit >= $this->filesize){
				$this->range_upper_limit = $this->filesize - 1;
			}
	
			$block = $this->__read($download_url, $this->range_lower_limit, $this->range_upper_limit);
			fwrite($fp, $block);
			  
			$this->offset = $this->range_upper_limit + 1;
			$this->range_lower_limit = $this->range_upper_limit + 1;
			$this->range_upper_limit = ($this->range_lower_limit + $this->chunk) - 1;
			
			$percentage = (filesize($dest) / $this->filesize) * 100;
			
			backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'downloading', 22);
		}
		
		$GLOBALS['l_readbytes'] = filesize($dest);
		fclose($fp);
	}

	function __read($download_url, $lower_limit, $upper_limit){
		global $error;
		
		$headers = array('Range: bytes='.$lower_limit.'-'.$upper_limit);
		
		$resp = $this->__curl($download_url, $headers, '', 'GET');
		
		if(!empty($resp['error'])){
			$error[] = $resp['error'];
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
		$this->path = $stream['path'];

		//One Drive access token expires in an hour so we need to refresh
		$this->access_token = $this->refresh_token_func($this->refresh_token);
		
		$url = $this->graph_api_url.$this->root_folder_path.$stream['path'].':/children';
		
		$headers = array('Content-Type: application/json',
						"Cache-Control: no-cache",
						"Pragma: no-cache",
						"Authorization: bearer ".$this->access_token); 
		
		$resp = $this->__curl($url, $headers, '', 'GET');
		
		if(!empty($resp['error'])){
			$error[] = 'OneDrive : '.$resp['error'];
			return false;
		}
		
		$this->filelist = json_decode($resp['result'], true);
		
		if(empty($this->filelist['value'])){
			$error[] = 'OneDrive : No File Found';
			return false;
		}
		
		$this->filelist = $this->filelist['value'];
		
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

	//Delete the backup from One Drive
	function unlink($path){
		global $error, $l;
		
		$stream = parse_url($path);
		$this->refresh_token = $stream['host'];
		$pathinfo = pathinfo($stream['path']);
		$filename = $pathinfo['basename'];
		$file_path = $stream['path'];

		//One Drive access token expires in an hour so we need to refresh
		if(empty($this->access_token)){
			$this->access_token = $this->refresh_token_func($this->refresh_token);
		}

		if(empty($this->onedrive_file_id)){
			$this->get_onedrive_file_id($file_path);
		}
		
		$url=$this->graph_api_url.'items/'.$this->onedrive_file_id.':';
		$headers = array('Content-Type: application/json',
						"Cache-Control: no-cache",
						"Pragma: no-cache",
						"Authorization: bearer ".$this->access_token);
		
		$resp = $this->__curl($url, $headers, '', 'DELETE');

		if(!empty($resp['error'])){
			$error[] = $resp['error'];
			return false;
		}

		return true;
	}

	//Rename the backup file
	function rename($from, $to){
		global $error;
				
		$stream_from = parse_url($from);
		$this->refresh_token = $stream_from['host'];
		$from_path = $stream_from['path'];
		$from_pathinfo = pathinfo($stream_from['path']);
		$from_file = $from_pathinfo['basename'];
		
		$stream_to = parse_url($to);
		$to_path = trim($stream_to['path'], '/\\');
		$to_pathinfo = pathinfo($stream_to['path']);
		$to_file = $to_pathinfo['basename'];

		//One Drive access token expires in an hour so we need to refresh
		if(empty($this->access_token)){
			$this->access_token = $this->refresh_token_func($this->refresh_token);
		}

		$this->get_onedrive_file_id($from_path);

		$url = $url=$this->graph_api_url.'items/'.$this->onedrive_file_id.':';
		$data= '{
				  "name": "'.$to_file.'"
				}';
		$headers = array('Content-Type: application/json',
						"Cache-Control: no-cache",
						"Pragma: no-cache",
						"Authorization: bearer ".$this->access_token);
		
		$resp = $this->__curl($url, $headers, $data, 'PATCH');

		if(!empty($resp['error'])){
			$error[] = $resp['error'];
			return false;
		}	
		return $resp['result'];	
	}

	/**
	 * Generate One Drive Refresh and Access Token from the Authorization Code provided
	 *
	 * @package	softaculous 
	 * @author	Pratik Jaiswal
	 * @param	string $auth_code The authorization code generated by user during access grant process
	 * @return	string $data One Drive Refresh and Access Token which we can use to create backup files
	 * @since	5.7.1
	 */

	function generate_onedrive_token($auth_code){
		global $error, $l, $onedrive;

		$headers = array("Content-Type: application/x-www-form-urlencoded");
		$post = http_build_query(array(
			'code' => $auth_code,
			'grant_type' => 'authorization_code',
			'action' => 'get_refresh_token'
		));
				
		$resp = $this->__curl($this->token_url, $headers, $post);
		
		if(!empty($resp['error'])){
			$error[] = $resp['error'];
			return false;
		}
		
		$data = json_decode($resp['result'], true);
		
		if(!empty($data['error'])){
			if(is_array($data['error'])){
				$error[] = $data['error']['code'].' : '.$data['error']['message'];
			}else{
				$error[] = $data['error'].' : '.$data['error_description'];
			}
			return false;
		}

		return $data;
	}

	/**
	 * Generate a new Access Token or Refresh Token from the previous Refresh Token.
	 *
	 * @package	softaculous 
	 * @author	Pratik Jaiswal
	 * @param	string $refresh_token The refresh token generated by user during access grant process
	 * @return	string $token One Drive Access Token which we can use for authentication in behalf of user
	 * @since	5.7.1
	 */

	function refresh_token_func($refresh_token){
		global $error;
		
		$refresh_token = rawurldecode($refresh_token);	

		$url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
		
		$headers = array("Content-Type: application/x-www-form-urlencoded");
		$post = http_build_query(array(
			'refresh_token' => $refresh_token,
			'action' => 'get_access_token'
		));

		$resp = $this->__curl($this->token_url, $headers, $post);
		
		if(!empty($resp['error'])){
			$error[] = $resp['error'];
			return false;
		}
		
		$data = json_decode($resp['result'], true);
		
		if(!empty($data['error'])){
			if(is_array($data['error'])){
				$error[] = $data['error']['code'].' : '.$data['error']['message'];
			}else{
				$error[] = $data['error'].' : '.$data['error_description'];
			}
			return false;
		}

		return $data['access_token'];
	}

	
	/**
	 * Create Softaculous App Directory in user's One Drive account
	 *
	 * @package	softaculous 
	 * @author	Pratik jaiswal
	 * @param	string $refresh_token Refresh Token of user's One Drive account to generate the access token
	 * @since	5.7.1
	 */
	function create_onedrive_app_dir($refresh_token){
		global $error;
		
		$file_id = $this->get_onedrive_file_id($this->app_dir, $refresh_token);

		if(empty($file_id)){
			$this->create_dir($this->app_dir);
		}
	}

	function create_dir($dirname){
		global $error;
		
		$url = $this->graph_api_url.'root/children';
		$data= '{
		  "name": "'.$dirname.'",
		  "folder": { }
		}';
		$headers = array('Content-Type: application/json',
						"Cache-Control: no-cache",
						"Pragma: no-cache",
						"Authorization: bearer ".$this->access_token);
		$resp = $this->__curl($url, $headers, $data, 'POST');

		if(!empty($resp['error'])){
			$error[] = $resp['error'];
			return false;
		}
		
		$data = json_decode($resp['result'], true);
		
		if(!empty($data['error'])){
			if(is_array($data['error'])){
				$error[] = $data['error']['code'].' : '.$data['error']['message'];
			}else{
				$error[] = $data['error'].' : '.$data['error_description'];
			}
			return false;
		}
		return $data['id'];
	}

	function __curl($url, $headers = '', $post = '', $request_type = 'POST'){
		global $error;
		
		// Set the curl parameters.
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_URL, $url);
		
		if(!empty($headers)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
		//We are setting this as on some servers, the default HTTP version was taken as 2.0 by curl, causing issue
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		
		if(!empty($post)){
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		// Turn off the server and peer verification (TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		//Get response from the server.
		$resp = array();
		$resp['result'] = curl_exec($ch);
		$resp['error'] = curl_error($ch);
		
		curl_close($ch);
		return $resp;
	}
	
	function get_quota($path){
		$stream = parse_url($path);
		$this->refresh_token = $stream['host'];
		
		if(empty($this->access_token)) {
			$this->access_token = $this->refresh_token_func($this->refresh_token);
		}

		// Just make a GET request to the graph API and it will return basic details and Quota
		$url = $this->graph_api_url;
		$headers = array('Authorization: Bearer '.$this->access_token);
		
		$resp = $this->__curl($url, $headers, '', 'GET');

		if(empty($resp) || empty($resp['result'])){
			return false;
		}
		
		$decoded_resp = json_decode($resp['result'], true);
		
		if(empty($decoded_resp) || !is_array($decoded_resp) || empty($decoded_resp['quota'])){
			return false;
		}

		return ['total' => $decoded_resp['quota']['total'], 'used' => $decoded_resp['quota']['used']];
	}
}
