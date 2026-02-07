<?php
/**
 * TODO: need to cleen code and properer arrange
 * Class GOSMTP_Mailer_Zoho.
 *
 * @since 1.0.0
 */

namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class Zoho extends Loader{
	
	var $title = 'Zoho';
	
	var $mailer = 'zoho';

	var $scope = 'VirtualOffice.messages.CREATE,VirtualOffice.accounts.READ';

	var $send_code = 200;
	var $api_url = '';
	var $oauth_url = '';

	var $body = [];
	var $lang = [];

	private $allowed_exts = array('xlsx', 'xls', 'ods', 'docx', 'docm', 'doc', 'csv', 'pdf', 'txt', 'gif', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'rtf', 'bmp', 'cgm', 'css', 'shtml', 'html', 'htm', 'zip', 'xml', 'ppt', 'pptx', 'tar', 'ez', 'ics', 'mobi', 'msg', 'pub', 'eps', 'odt', 'mp3', 'm4a', 'm4v', 'wma', 'ogg', 'flac', 'wav', 'aif', 'aifc', 'aiff', 'mp4', 'mov', 'avi', 'mkv', 'mpeg', 'mpg', 'wmv');

	public function getLang($str = ''){
		
		$this->lang = array(
			'OK' => __('Mailer successfully configured!'),
			'unauthorized_client' => __('OAuth Client is Invalid'),
			'invalid_client' => __('Invalid Client ID (or) client Credentials did not match.'),
			'invalid_code' => __('Code Expired (or) Invalid Refresh Token.'),
			'invalid_redirect_uri' => __('Invalid Redirect Url configured'),
			'invalid_client_secret' => __('Client Secret did not match.'),
			'INVALID_TICKET' => __('Invalid Client Secret'),
			'INVALID_OAUTHTOKEN' => __('Authtoken invalidated'),
			'access_denied' => __('Multiple requests failed with same Refresh Token.'),
			'general_error' => __('Something went wrong'),
			'remote_token_error' => __('Error when getting the remote token.'),
			'no_user' => __('No User present.'),
			'token_limit_reached' => __('Refresh token limit reached.'),
			'refresh_token_limit_reached' => __('The limit for refresh token reached.'),
			'access_token_limit_reached' => __('The limit for access token reached.'),
			'invalid_client_type' => __('Invalid client type'),
			'invalid_authtoken' => __('Authtoken invalidated'),
			'invalid_operation_type' => __('The scope has an invalid operation.'),
			'URL_RULE_NOT_CONFIGURED' => __('Please configure zoho api.'),
			'invalid_from' => __('Your zoho account does not match with the from mail.'),
		);
		
		if(!isset($this->lang[$str])){
			return $str;
		}
		
		return $this->lang[$str];
	}

	public function getRedirectUrl($query = ''){

		// TODO check and change this
		return admin_url().'admin.php?page=gosmtp'.$query;
	}

	public function send(){

		global $phpmailer;

		$phpmailer->isMail();
		
		$this->setConfig();

		if($phpmailer->preSend()){
			return $this->handle_response( $this->postSend() );
		}
		
		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}

	public function setConfig(){
		
		$domain_name = $this->getOption('domain_name', $this->mailer, 'com');
	
		$this->api_url = 'https://mail.zoho.'.$domain_name.'/api/accounts';
		$this->oauth_url = 'https://accounts.zoho.'.$domain_name.'/oauth/v2/';
		
	}
	
	public function postSend(){

		global $phpmailer;
		

		$options = $this->getMailerOption();

		$options['access_token'] = $this->getAccessToken($options);

		$this->body['fromAddress'] = $phpmailer->FromName.'<'.$phpmailer->From.'>';

		$this->body['subject'] = $phpmailer->Subject;

		$this->set_content($phpmailer->Body);

		$this->set_recipients(
			array(
				'toAddress'  => $phpmailer->getToAddresses(),
				'ccAddress'  => $phpmailer->getCcAddresses(),
				'bccAddress' => $phpmailer->getBccAddresses(),
				'replyTo'    => $phpmailer->getReplyToAddresses()
			)
		);

		$attachments = $phpmailer->getAttachments();
		
		if(!empty($attachments)){
			$this->set_attachments($attachments);
		}

		$headers = [
			'Authorization' => 'Zoho-oauthtoken '.$options['access_token'],
			'Content-Type' => 'application/json'
		];

		$params = array(
			'headers' => $headers,
			'body' => wp_json_encode($this->body)
		);

		$accId = !empty($options['account_id']) ? $options['account_id'] : '';
		$url = $this->api_url.'/'.$accId.'/messages';

		// print_r(json_encode($this->body, JSON_PRETTY_PRINT));

		$response = wp_safe_remote_post($url, $params);

		if(is_wp_error($response)){
			$return_response = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
		}else{
			$resp_body = wp_remote_retrieve_body($response);
			$resp_code = wp_remote_retrieve_response_code($response);

			$isOk = $resp_code == $this->send_code;

			$resp_body = \json_decode($resp_body, true);
            
			if($isOk) {
				$msgId = isset( $resp_body['data']['messageId'] ) ? $resp_body['data']['messageId'] : '';
				$status = isset($resp_body['status']['description']) ? $resp_body['status']['description'] : '';
				$return_response = [
					'status' => true,
					'code' => $resp_code,
					'messageId' => $msgId,
					'message' => $status
				];    
			}else{
				$msg = ($resp_code == 500 ) ? $resp_body['data']['moreInfo'] : $this->getLang($resp_body['data']['errorCode']);
				$return_response = new \WP_Error($resp_code, $msg , $resp_body);  
			}

		}

		return $return_response;
	}
    
	public function set_content( $content ){
		global $phpmailer;

		if( empty( $content ) ){
			return;
		}

		if( is_array( $content ) ){

			if( ! empty( $content['text'] ) ){
				$this->body['mailFormat'] = 'plaintext';
			}

			if( ! empty( $content['html'] ) ){
				$this->body['mailFormat'] = 'html';
			}

			$this->body['content'] = $content['text'];

		}else{
			if( $phpmailer->ContentType === 'text/plain' ){
				$this->body['mailFormat'] = 'plaintext';
			}else{
				$this->body['mailFormat'] = 'html';
			}
		}

		$this->body['content'] = $content;
	}

	public function set_recipients( $recipients ) { 

		global $phpmailer;

		if( empty( $recipients ) ){
			return;
		}

		foreach( $recipients as $type => $emails ){

			$tmp = '';
			foreach( $emails as $key => $email ){

				$tmp .= $type == 'replyTo' ? '<'.$email[0].'>' : ( empty($email[1]) ? $email[0] : $email[1].'<'.$email[0].'>' );

				if( ( count($emails) - 1 ) != $key ){
					$tmp .= ',';
				}
			}
			
			if(empty($tmp)){
				continue;
			}

			$this->body[$type] = $tmp;
		}
	}

	public function set_attachments( $attachments = []){

		$attachment_data = [];
		$count = 0;
		$ext = '';
		foreach($attachments as $attachment){

			if(!is_file($attachment[0]) || !is_readable($attachment[0])){
				continue;
			}

			if(!empty($attachment[4])){
				$ext = explode("/",$attachment[4])[1];
			}

			$header = array(
				'Authorization' => 'Zoho-oauthtoken '. $this->getOption('access_token', $this->mailer),
				'Content-Type' => 'application/octet-stream'
			);

			if(in_array($ext, $this->allowed_exts, true)){
				$file_name = $attachment[2];
				$content = file_get_contents($attachment[0]);
				$options = $this->getMailerOption();
				
				$url = $this->api_url.'/'.$options['account_id'].'/messages/attachments'.'?fileName='.$file_name;

				$args = array(
					'body' => $content,
					'headers' => $header,
					'method' => 'POST'
				);

				$response = wp_remote_post($url, $args);
				$response_body = wp_remote_retrieve_body($response);
				$http_code = wp_remote_retrieve_response_code($response);
				$response_ = json_decode($response_body, true); 

				if( isset($response_['data']['errorCode']) ){
					$error = $response_['data']['errorCode'];
				}

				$attachments_ = array();

				if($http_code == '200') {
					$attachments_['storeName'] = $response_['data']['storeName'];
					$attachments_['attachmentPath'] = $response_['data']['attachmentPath'];
					$attachments_['attachmentName'] = $response_['data']['attachmentName'];

					$attachment_data[$count] = $attachments_;

					$count = $count + 1;
				}
			}
		}

		if( count($attachment_data) > 0 ){
			$this->body['attachments'] = $attachment_data;
		}
	}

	private function is_authorized(){
		$options = $this->getMailerOption();
		
		if(empty($options['refresh_token']) || empty($options['access_token']) || empty($options['account_id'])){
			return false;
		}

		return true;
	}

	public function authentication_process(){
		global $phpmailer;

		$options = $this->getMailerOption();
		
		$response = $this->generate_tokens($options);    
		
		if( !isset($response['access_token']) || !isset($response['refresh_token']) ){
			return $response;
		}
		
		$access_token = $response['access_token'];
		$refresh_token = $response['refresh_token'];
	

		if( empty($options['account_id']) ){

			$response = $this->zoho_account_id($access_token);    

			if(!$response != true){
				return $response;
			}
		}

		$this->delete_option('auth_code', $this->mailer);

		return 'OK';
	}

	private function get_authcode_link($options){

		$state = wp_create_nonce('redirect_url').($this->conn_id === 0 ? '' : '-'.$this->conn_id);
				
		$auth_url = $this->oauth_url ."auth?response_type=code&client_id=". $options['client_id'] ."&scope=". $this->scope ."&redirect_uri=".urlencode($this->getRedirectUrl('&auth=zoho'))."&prompt=consent&access_type=offline&state=". $state; 

		return $auth_url;
	}

	public function generate_tokens($options){

		$state = wp_create_nonce('redirect_url').($this->conn_id === 0 ? '' : '-'.$this->conn_id);

		$url = $this->oauth_url ."token?code=". $options['auth_code'] ."&client_id=". $options['client_id'] ."&client_secret=". $options['client_secret'] ."&redirect_uri=".urlencode($this->getRedirectUrl('&auth=zoho'))."&scope=".$this->scope."&grant_type=authorization_code&state=".$state;

		$response = wp_remote_retrieve_body(wp_remote_post( $url));
		$response = json_decode($response, true);

		if(isset($response['error'])){
			
			if($response['error'] == 'invalid_code'){
				$this->delete_option('auth_code', $this->mailer);
			}
			
			return $response['error'];
		}

		$access_token = $response['access_token'];
		$refresh_token = $response['refresh_token'];   

		$this->update_option('access_token', $access_token , $this->mailer);
		$this->update_option('refresh_token', $refresh_token, $this->mailer);

		return array(
			'access_token' => $access_token,
			'refresh_token' => $refresh_token
		);
	}

	public function zoho_account_id($access_token = ''){
        
		$from_email = $this->conn_id === 0 ? $this->getOption('from_email') : $this->getOption('from_email', $this->mailer,'');
		$accId = $this->getOption('account_id', $this->mailer, '');
		
		if(!empty($accId)){ 
			return;
		}

		$args = [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken '.$access_token
			]
		];

		$response = wp_remote_retrieve_body(wp_remote_get( $this->api_url, $args));
		$response = json_decode($response, true);

		if(empty($response)){
			return 'general_error';
		}

		if( isset($response['data']['errorCode']) ){
			return $response['data']['errorCode'];
		}

		for($i=0; $i<count($response['data']); $i++){
			for($j=0; $j<count($response['data'][$i]['sendMailDetails']); $j++) {
				if(strcmp($response['data'][$i]['sendMailDetails'][$j]['fromAddress'], $from_email) == 0){
					$this->update_option('account_id', $response['data'][0]['accountId'], $this->mailer);
				}else{
					return 'invalid_from';
				}
			}
		}
	}

	public function getAccessToken(	$options ){
		
		$setup_time = $options['setup_timestamp'];
		$access_token = $options['access_token'];

		if(empty($setup_time) || time() - $setup_time > 3000){

			$this->update_option('setup_timestamp', time(), $this->mailer);

			$url = $this->oauth_url.'token?refresh_token='.$options['refresh_token'].'&grant_type=refresh_token&client_id='.$options['client_id'].'&client_secret='.$options['client_secret'].'&redirect_uri='.urlencode($this->getRedirectUrl('&auth=zoho')).'&scope='.$this->scope;

			$response = wp_remote_retrieve_body( wp_remote_post( $url ) );
			$response = json_decode($response);
			
			$access_token = $response->access_token;
			
			$this->update_option('access_token', $access_token, $this->mailer);
		}
		
		return $access_token;
	}

	public function load_field(){
		
		$fields = array();
		$options = $this->getMailerOption();
		$client_id = $this->getOption('client_id', $this->mailer);
		$client_secret = $this->getOption('client_secret', $this->mailer);
		$opt_text = __('You need to save settings with Client ID and Client Secret before you can proceed.');
		$opt_type = 'notice';
		$opt_url = '';
		$readonly = '';

		$this->zoho_init();
		$_button = '';
		if(!empty($client_id) && !empty($client_secret) ){

			$opt_type = $_button = 'button';

			if(!$this->is_authorized()){
				$opt_url = $this->get_authcode_link($options);
				$opt_text = __('Authorize Zoho Account');
			}else{
				$query = '&zoho-deactivate=1';
				if(!is_numeric($this->conn_id)){
					$query = '&type=edit&conn_id='.$this->conn_id.$query.'#gosmtp-connections-settings';
				}
				$readonly = 'readonly=""';
				$opt_url = $this->getRedirectUrl($query);
				$opt_text = 'Deactivate Access Token';		        
			}
		}

		$fields = array(
			'domain_name' => array(
				'title' => __('Select domain name'),
				'type' => 'select',
				'list' => array(
					'com' => '.com',
					'eu' => '.eu',
					'in' => '.in',
					'com.cn' => '.com.cn',
					'com.au' => '.com.au',
					'jp' => '.jp',
				),
				'desc' => __( 'The name of the region the account is configured' ),
			),
			'client_id' => array(
				'title' => __('Client Id'),
				'type' => 'text',
				'class'=>'zoho_client_id',
				'desc' => __( 'Created in the developer console' ),
				'attr' => $readonly
			),
			'client_secret' => array(
				'title' => __('Client Secret'),
				'type' => 'password',
				'class'=>'zoho_client_secret',
				'desc' => __( 'Created in the developer console' ),
				'attr' => $readonly
			),
			'redirect_uri' => array(
				'title' => __('Authorization Redirect URI'),
				'type' => 'copy',
				'id' => 'zoho_redirect_uri',
				'attr' =>'readonly=""',
				'default' => $this->getRedirectUrl('&auth=zoho'),
				'desc' => __( 'Copy this URL into Redirect URI field of your Client Id creation' ),
			),
			'authorize' => array(
				'title' => __('Authorize'),
				'type' => $opt_type,
				'default' => $opt_text,
				'href' => $opt_url,
				'class' => 'auth_class gosmtp-auto-width '.$_button,
				'attr' => 'data-field=auth'
			)
		);

		return $fields;
	}
	
	public function zoho_deregister(){

		if(!empty(gosmtp_optget('conn_id')) && $this->conn_id === 0){
			return;
		}
		
		$this->delete_option('access_token', $this->mailer);
		$this->delete_option('refresh_token', $this->mailer);
		$this->delete_option('auth_code', $this->mailer);
		$this->delete_option('account_id', $this->mailer);
		$this->delete_option('setup_timestamp', $this->mailer);

		$query = '';
		if(!is_numeric($this->conn_id)){
			$query = '&type=edit&conn_id='.$this->conn_id.$query.'#gosmtp-connections-settings';
		}

		if(isset($_GET['conn_id'])){
			echo '<script>
				var url = "'.$this->getRedirectUrl($query).'";
				history.pushState({urlPath:url},"",url);
			</script>';
		}
		
	}

	public function zoho_init(){

		$this->setConfig();
		if(isset( $_GET['zoho-deactivate'] )){
			$this->zoho_deregister();
			return;
		}

		if( !$this->is_authorized() && isset( $_GET['auth_code'] ) && isset( $_GET['auth'] ) && $this->mailer == $_GET['auth'] && strlen($this->conn_id) > 0){
			
			if( !empty(gosmtp_optget('conn_id')) && $this->conn_id === 0 ){
				return;
			}
			
			// TODO sanitize  $_GET['code']
			$this->update_option('auth_code', gosmtp_optget('auth_code'), $this->mailer);

			$response = $this->authentication_process();
			// var_dump($response);
			
			if( $response ){
				$msg = is_array($this->getLang($response)) ? $this->getLang('general_error') : $this->getLang($response);
			}
			
			$query = '';
			if(!is_numeric($this->conn_id)){
				$query = '&type=edit&conn_id='.$this->conn_id.$query.'#gosmtp-connections-settings';
			}
			
			echo '<script>
				alert("'.$msg.'")					
				var url = "'.$this->getRedirectUrl($query).'";
				history.pushState({urlPath:url},"",url);
			</script>';
		}
	}
    
}