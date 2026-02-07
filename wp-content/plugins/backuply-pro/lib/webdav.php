<?php

class webdav{

	var $path;
	var $filename;
	var $filesize = 0;
	var $session_id = '';
	var $offset = 0;
	var $tmpsize = 0;
	var $tpfile = 'php://temp'; //php://memory not working on localhost
	var $mode = '';
	var $wp = NULL; // Memory Write Pointer
	var $credentials = array(); // The credentials
	var $url = ''; // The URL to make curl call
	var $chunk = 2097152;	//2MB
	var $range_lower_limit = 0;
	var $range_upper_limit = 0;
	var $chunk_url = ''; // The chunk URL to upload chunks
	var $chunk_counter = 0; // The chunk counter which will be used as file names
	var $filelist = [];
	var $orig_path = '';
	var $readsize = 0;
	
	function init($path){
		
		$stream = parse_url($path);
		
		$this->credentials = array(rawurldecode($stream['user']), rawurldecode($stream['pass']));
		
		$this->path = $stream['path'];
		
		$pathinfo = pathinfo($this->path);
		$this->filename = $pathinfo['basename'];
		//$dir = trim($pathinfo['dirname'], '/\\');
		//$this->backup_loc = (empty($dir) ? "" : $pathinfo['dirname']);
		//php://memory not working on localhost
		//$this->tpfile = 'php://temp';
		
		$_stream = $stream;
		
		unset($_stream['user']);
		unset($_stream['pass']);
		
		if($_stream['port'] == 80){
			$_stream['scheme'] = 'http';
		}else{
			$_stream['scheme'] = 'https';
		}
		
		$this->url = $this->soft_unparse_url($_stream);
		
		// Is this OwnCloud or Nextcloud ? 
		if(strpos($this->url, 'remote.php/dav/files')){
			$temp = explode('remote.php/dav/files', $this->url);
			$temp2 = explode('/', $temp[1]);
		
			// Can we form the chunk url ?
			if(!empty($temp[0]) && !empty($temp2[1])){
				// This chunk upload folder (when created) will have a .file file which will help to merge the chunk files
				$this->chunk_url = $temp[0].'remote.php/dav/uploads/'.$temp2[1].'/'.md5($this->url);
			}
		}
		
		return $this->url;
		
	}
	
	function stream_open($path, $mode, $options, &$opened_path){
		global $error;
		
		$this->init($path);
		
		$this->mode = $mode;
		$this->orig_path = $path;
		
		$ret = true;
		
		if(strpos($this->mode, 'r') !== FALSE){
			$this->filesize = filesize($this->orig_path);
			
			if(empty($this->filesize)){
				return false;
			}
		}

		if(preg_match('/w|a/is', $this->mode)){
			$this->offset = 0;
			
			if(!empty($GLOBALS['start_pos'])){
				$this->offset = $GLOBALS['start_pos'];
			}
			
			$tfp = fopen($this->tpfile, $this->mode);
			$ret = $this->upload_start($tfp);
			fclose($tfp);
		}
		
		return $ret;
		
	}
	
	function stream_read($count) {
		
		// Get the readsize
		if(empty($this->readsize)){
			$this->readsize = filesize($this->orig_path);
		}
		
		$block = $this->__read($this->offset, ($this->offset + $count - 1));
		$ret = substr($block, 0, $count);

		if(empty($ret)){
			return false;
		}
		
		$this->offset = $this->offset + $count;
		
		return $ret;
	}
	
	
	function dir_opendir($path, $options){
		$this->init($path);
		
		$headers = array('Depth: 1');
		
		$resp = $this->request($this->url, $headers, 'PROPFIND');
		
		if(!empty($resp['error'])){
			$error[] = 'WebDav : '.$resp['error'];
			return false;
		}
		
		// Getting File paths from the XML response from <D:href> tag
		preg_match_all('/<(?:.*?):href>(.*?)<\/(?:.*?):href>/im', $resp['response'], $this->filelist);
		
		if(empty($this->filelist)){
			$error[] = 'WebDav : No File Found';
			return false;
		}
		
		$this->filelist = $this->filelist[1];

		foreach($this->filelist as $i => $file) {
			$f_name = explode('/', $file);
			$f_name = end($f_name);
			
			$this->filelist[$i] = $f_name;
			
			if(empty($f_name)){
				unset($this->filelist[$i]);
			}
		}

		return $this->filelist;
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
	
	
	function upload_start($fp){
		global $error, $backuply;

		$headers = array('Content-type: application/octet-stream');

		/* Initiating the Upload again and again was causing the tar file to get damaged
		* as it was setting the file size to 0 everytime we initiate
		*/
		if(isset($GLOBALS['start_pos']) && $GLOBALS['start_pos'] != 0) {
			return true;
		}

		$resp = $this->request($this->url, $headers, 'PUT', $fp, 0);
		
		if(!in_array($resp['code'], array('201', '204'))){
			return false;
		}
		
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
			
			//Call upload append function to write the data from PHP Memory stream
			$this->upload_append($this->wp, $this->tmpsize);
			
			// Close the temp file and reset the variables
			fclose($this->wp);
			$this->wp = NULL;
			$this->tmpsize = 0;
		}
		
		return $data_size;
	}
	
	function upload_append($filep, $data_size){
		global $error, $backuply;
		
		$total_size = $this->offset + $data_size;
		$range_start = $this->offset;
		$range_end = $total_size - 1;
		
		//backuply_log('Upload Pos : '.$this->offset);
		
		// Do we need to create chunk files ? 
		if(!empty($this->chunk_url)){
			
			// cannot call $this->mkdir because it will overwrite $this->url by calling init
			$resp = $this->request($this->chunk_url, array(''), 'MKCOL');
			
			$headers = array('Content-type: application/octet-stream');
			$resp = $this->request($this->chunk_url.'/'.$this->chunk_counter, $headers, 'PUT', $filep, $data_size);
			
		}else{
		
			$headers = array('Content-type: application/octet-stream',
							'Content-Length: '.$data_size,
							'Content-Range: bytes '.$range_start.'-'.$range_end.'/'.$total_size);
			
			$resp = $this->request($this->url, $headers, 'PUT', $filep, $data_size);
		}
		
		if(in_array($resp['code'], array('201', '204'))){
			$this->offset += $data_size;
			$this->chunk_counter++;
			return $data_size;
		}
		
		return false;
		
	}
	
	function stream_close(){
		global $error;
		
		if(preg_match('/w|a/is', $this->mode)){
			// Is there still some data left to be written ?
			if($this->tmpsize > 0){
				backuply_log('WEBDAV: Writing last part to the file');
				rewind($this->wp);
				
				//Call upload append function to write the data from PHP Memory stream
				$data_size = $this->upload_append($this->wp, $this->tmpsize);
				
				// Close the temp file and reset the variables
				fclose($this->wp);
				$this->wp = NULL;
				$this->tmpsize = 0;
			}
			
			// If we have written chunk files merge the files
			if(!empty($this->chunk_url)){
				// cannot call $this->rename because it will overwrite $this->url by calling init
				$headers = array('Destination: '.$this->url);
				$resp = $this->request($this->chunk_url.'/.file', $headers, 'MOVE');
				
				// Maybe timeout. But we can wait
				if($resp['code'] == 504){
					
					// Sometimes merge file can take time. Wait for the process to complete
					$max_time = time() + 60;
					do{
						sleep(1);
						$headers = array('Depth: 0');
						$resp = $this->request($this->chunk_url.'/.file', $headers, 'PROPFIND');
					}while($resp['code'] == 207 && time() < $max_time);
					
				}
			}
		}
		
		return true;
	}
	
	function mkdir($path){
		global $error;
		
		$this->init($path);
		
		$headers = array('');
		
		$resp = $this->request($this->url, $headers, 'MKCOL');
		
		if($resp['code'] == 201 || $resp['code'] == 405){
			return true;
		}
		
		return false;
	}
	
	//In response to file_exists(), is_file(), is_dir()
	function url_stat($path){
		global $error;
		
		$this->init($path);
		
		$headers = array('Depth: 0');
		
		$resp = $this->request($this->url, $headers, 'PROPFIND');
		
		if($resp['code'] != 207){
			return false;
		}
		
		backuply_preg_replace('/<(.*?):creationdate>(.*?)<\/(.*?):creationdate>/is', $resp['response'], $creation_date, 2);
		backuply_preg_replace('/<(.*?):getlastmodified>(.*?)<\/(.*?):getlastmodified>/is', $resp['response'], $last_modified, 2);
		backuply_preg_replace('/<(.*?):getcontentlength>(.*?)<\/(.*?):getcontentlength>/is', $resp['response'], $size, 2);

		if(preg_match('/<D:getcontenttype>(.*?)directory(.*?)<\/D:getcontenttype>/is', $resp['response'])){
			$mode = 0040000;	//For DIR
		}else{
			$mode = 0100000;	//For File
		}
		
		if(!empty($resp['response'])){
			$stat = array('dev' => 0,
						'ino' => 0,
						'mode' => $mode,
						'nlink' => 0,
						'uid' => 0,
						'gid' => 0,
						'rdev' => 0,
						'size' => $size,
						'atime' => strtotime($last_modified),
						'mtime' => strtotime($last_modified),
						'ctime' => strtotime($creation_date),
						'blksize' => 0,
						'blocks' => 0);
				
			$this->filesize = $stat['size'];
			return $stat;
		}
		
		return false;
	}

	function unlink($path){
		global $error;
		
		$this->init($path);
		
		$headers = array('');
		
		$resp = $this->request($this->url, $headers, 'DELETE');
		
		if($resp['code'] == 204 || $resp['code'] == 404){
			return true;
		}
		
		return false;
	}	
	
	function rename($from, $to){
		global $error;
		
		$dest_url = $this->init($to);
		$this->init($from);
		
		$headers = array('Destination: '.$dest_url);
		
		$resp = $this->request($this->url, $headers, 'MOVE');
		
		if($resp['code'] == 201){
			return true;
		}
		
		return false;
	}
	
	//Download Backup File from WebDAV to local server
	function download_file_loop($source, $dest, $startpos = 0){
		
		global $error;
		
		$this->init($source);
		
		//Set chunk size for download as 1 MB
		$this->chunk = 1048576;
		
		$file_stats = $this->url_stat($source);
		$this->filesize = $file_stats['size'];
		
		$this->range_lower_limit = $startpos;
		$this->range_upper_limit = ($this->range_lower_limit + $this->chunk) - 1;


		$fp = fopen($dest, 'ab');

		while(!$this->__eof()){
			if(time() + 5 >= $GLOBALS['end']){
				backuply_log('WebDAV Download: Short on time');
				//$GLOBALS['l_readbytes'] = filesize($dest);
				break;
			}

			$block = $this->__read($this->range_lower_limit, $this->range_upper_limit);

			if(empty($block)){
				$error[] = 'Unable to download file from WEBDAV';
				return false;
			}

			fwrite($fp, $block);
			$this->offset = $this->range_upper_limit + 1;
			$this->range_lower_limit = $this->range_upper_limit + 1;
			$this->range_upper_limit = ($this->range_lower_limit + $this->chunk) - 1;
			
			$percentage = (filesize($dest) / $this->filesize) * 100;
			
			backuply_status_log('<div class="backuply-upload-progress"><span class="backuply-upload-progress-bar" style="width:'.round($percentage).'%;"></span><span class="backuply-upload-size">'.round($percentage).'%</span></div>', 'downloading', 22);
		}
		
		$GLOBALS['l_readbytes'] = filesize($dest);
		
		fclose($sfp);
		return true;
	}
	
	function __read($l_limit, $u_limit) {
		global $error;

		$headers = array('Range: bytes='.$l_limit.'-'.$u_limit);
		$resp = $this->request($this->url, $headers, 'GET');
		
		if(!empty($resp['curl_error'])){
			$error[] = $resp['error'];
			backuply_log('WebDAV read error : '. $resp['error']);
		}
		
		if($resp['code'] != 200 && $resp['code'] != 206){
			$error[] = 'Something went Wrong while downloading the file from WebDAV!';
			return false;
		}
		
		return $resp['response'];
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
	
	function request($url, $headers = array(), $method = '', $filepointer = '', $upload_size = 0, $post = '', $download_file = '', $retry = 0){
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_USERPWD, implode(':', $this->credentials));

		if(!empty($this->authtype)){
			curl_setopt($ch, CURLOPT_HTTPAUTH, $this->authtype);
		}

		if(!empty($headers)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		
		if(!empty($method)){
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}
		
		if(!empty($filepointer)){
			curl_setopt($ch, CURLOPT_PUT, true);
			curl_setopt($ch, CURLOPT_INFILE, $filepointer);
			curl_setopt($ch, CURLOPT_INFILESIZE, $upload_size);
		}
		
		if(!empty($download_file)){
			curl_setopt($ch, CURLOPT_FILE, $download_file);
		}

		$response = curl_exec($ch);
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		$curl_info = curl_getinfo($ch);
		
		curl_close($ch);
		
		// Some servers do not follow redirect so we need to make another call to the redirect URL
		if($statusCode == 301 && !empty($curl_info['redirect_url']) && empty($retry)){
			return $this->request($curl_info['redirect_url'], $headers, $method, $filepointer, $upload_size, $post, $download_file, 1);
		}

		$result = array();
		$result['response'] = $response;
		$result['code'] = $statusCode;
		$result['curl_error'] = $curl_error;
		$result['curl_info'] = $curl_info;

		return $result;
	}
	
	function soft_unparse_url($parsed_url){
		$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
		$pass = ($user || $pass) ? $pass.'@' : '';
		$path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

		return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;
	}
}
