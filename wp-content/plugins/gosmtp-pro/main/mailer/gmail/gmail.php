<?php

/**
 * Class GOSMTP_Mailer_Gmail.
 *
 * @since 1.0.0
 */

namespace GOSMTP\Mailer\Gmail;
 
use GOSMTP\Mailer\Loader;

class Gmail extends Loader{

	var $title = 'Gmail';
	var $mailer = 'gmail';
	var $isOk = 200;
	var $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';
	
	public function getRedirectUrl($query = ''){

		// TODO check and change this
		return admin_url().'admin.php?page=gosmtp'.$query;
	}

	public function send(){
		global $phpmailer;
		
		if($phpmailer->preSend()){
			$response = $this->postSend();
			return $this->handle_response( $response );
		}
		
		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}

	protected function postSend(){
		global $phpmailer;

		try{
			$access_token = $this->getAccessToken($this->getMailerOption());
			
			if(is_wp_error($access_token)){ 
				return $access_token->get_error_message();
			}
			
			$mime = base64_encode($phpmailer->getSentMIMEMessage());
	
			$body = array(
				"raw" =>$mime,
			);

			$params = array(
				'method' => 'POST',
				'headers' => [
					'Authorization' => 'Bearer '. $access_token,
					'Content-Type' =>  'application/json',
					'Accept' => 'application/json',
				],
				'body' => wp_json_encode($body)
			);

			$response = wp_remote_request($this->url, $params);

			if(is_wp_error($response)){
				$return_response = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
			}else{
				$resp_body = wp_remote_retrieve_body($response);
				$resp_code = wp_remote_retrieve_response_code($response);
				$resp_body = \json_decode($resp_body, true);
				
				if($this->isOk == $resp_code){
					$msgId = isset( $resp_body['id'] ) ? $resp_body['id'] : '';
					$status = 'Email sent successfully';
					$return_response = [
						'status' => true,
						'code' => $resp_code,
						'messageId' => $msgId,
						'message' => $status
					];	
				}else{
					$err_code = $resp_code;
					$error_text = [''];
					if( ! empty( $resp_body['error'] ) && is_array( $resp_body['error'] ) ){
						$message = $resp_body['error']['message'];
						$code = !empty( $resp_body['error']['code'] ) ? $resp_body['error']['code'] : '';
						
						$error_text[] = $this->message_formatting( $message, $code );
					}else{
						$error_text[] = $this->get_response_error_message($response);
					}
        			
					$error_msg = implode( '\r\n', array_map( 'esc_textarea', array_filter( $error_text ) ) );
					$return_response = new \WP_Error($err_code, $error_msg, $resp_body);
				}
			}
			
		}catch(\Exception $e){
			$return_Response = new \WP_Error(423, $e->getMessage(), []);
		}

		return $return_response;
	}

	// get access token
	private function getAccessToken($options){
		
		$accessToken = $options['access_token'];
		
		// check if expired or will be expired in 300 seconds
		if( ($options['expire_stamp'] - 300) < time()){

			$state = ($this->conn_id === 0 ? '' : '-'.$this->conn_id);
			$client_id = $this->getOption('client_id', $this->mailer);
			$client_secret = $this->getOption('client_secret', $this->mailer);
			$redirect_url = $this->getRedirectUrl('&auth=gmail');
			$google_client = new \GOSMTP\mailer\gmail\Auth($client_id, $client_secret, $redirect_url, $state);

			$tokens = $google_client->sendTokenRequest('refresh_token', [
				'refresh_token' => $options['refresh_token']
			]);

			if(is_wp_error($tokens)) {
				return $tokens->get_error_message();
			}

			$this->saveNewTokens($tokens);

			$accessToken = $tokens['access_token'];
		}

		return $accessToken;
	}

	//save new token when expire time exeed
	private function saveNewTokens($tokens){

		$tokens['refresh_token'] = empty($tokens['refresh_token']) ? $this->getOption('refresh_token', $this->mailer) : $tokens['refresh_token'];

		if(empty($tokens['access_token']) || empty($tokens['refresh_token'])){
			return false;
		}

		$this->update_option('access_token', $tokens['access_token'], $this->mailer);
		$this->update_option('refresh_token', $tokens['refresh_token'], $this->mailer);
		$this->update_option('expire_stamp', $tokens['expires_in'] + time(), $this->mailer);
		$this->update_option('expires_in', $tokens['expires_in'], $this->mailer);

		return true;
	}

	//generate access token and refresh token and update in data base.
	public function set_token(){
		$errors = [];
		
		$clientId = $this->getOption('client_id', $this->mailer);
		$clientSecret = $this->getOption('client_secret', $this->mailer);

		$accessToken = $this->getOption('access_token', $this->mailer);
		$authToken = $this->getOption('auth_token', $this->mailer);
	   
		if(!$accessToken && $authToken ){
			
			$body = [
				'code'		  => $authToken,
				'grant_type'	=> 'authorization_code',
				'redirect_uri'  => $this->getRedirectUrl('&auth=gmail'),
				'client_id'	 => $clientId,
				'client_secret' => $clientSecret
			];
			
			$tokens = $this->makeRequest('https://accounts.google.com/o/oauth2/token', $body, 'POST');

			if(is_wp_error($tokens)){
				return new \WP_Error(423, $tokens->get_error_message());
			}else{
				$this->update_option('access_token', $tokens['access_token'], $this->mailer);
				$this->update_option('refresh_token', $tokens['refresh_token'], $this->mailer);
				$this->update_option('auth_token', '', $this->mailer);
				$this->update_option('expire_stamp', time() + $tokens['expires_in'], $this->mailer);
				$this->update_option('expires_in', $tokens['expires_in'], $this->mailer);
				$this->update_option('version', 2, $this->mailer);
			}
		}elseif(!$authToken && !$accessToken){
				return new \WP_Error(423,  __('Please Provide Auth Token.', 'GOSMTP'));
		}
		return true;
	}

	private function makeRequest($url, $bodyArgs, $type = 'GET', $headers = false){
		
		if(!$headers){
			$headers = array(
				'Content-Type' => 'application/http',
				'Content-Transfer-Encoding' => 'binary',
				'MIME-Version' => '1.0',
			);
		}

		$args = array(
			'headers' => $headers
		);
		
		if($bodyArgs){
			$args['body'] = json_encode($bodyArgs);
		}

		$args['method'] = $type;
		$request = wp_remote_request($url, $args);

		if(is_wp_error($request)){
			$message = $request->get_error_message();
			return new \WP_Error(423, $message);
		}

		$body = json_decode(wp_remote_retrieve_body($request), true);
		
		if(!empty($body['error'])){
			$error = 'Unknown Error';
			
			if(isset($body['error_description'])){
				$error = $body['error_description'];
			}elseif(!empty($body['error']['message'])){
				$error = $body['error']['message'];
			}
			
			return new \WP_Error(423, $error);
		}

		return $body;
	}


	public function get_auth_url() {
		
		$state = ($this->conn_id === 0 ? '' : '-'.$this->conn_id);
		$client_id = $this->getOption('client_id', $this->mailer);
		$client_secret = $this->getOption('client_secret', $this->mailer);
		$redirect_url = $this->getRedirectUrl('&auth=gmail');
		
		$google_client = new \GOSMTP\mailer\gmail\Auth($client_id, $client_secret, $redirect_url, $state);

		if($google_client->getAuthUrl()){
			return  $google_client->getAuthUrl();
		}
		
	}

	public function load_field(){
				
		$this->gmail_init();

		$access_token = $this->getOption('access_token', $this->mailer);
		$client_id = $this->getOption('client_id', $this->mailer);
		$client_secret = $this->getOption('client_secret', $this->mailer);
		$refresh_token = $this->getOption('refresh_token', $this->mailer);
		$mail_type = $this->getOption('mail_type', $this->mailer);

		$deactivate = !empty($refresh_token) && !empty($access_token) && $this->mailer == $mail_type;
		
		$activate = !empty($client_id) && !empty($client_secret)  && empty($access_token) && empty($refresh_token);

		$readonly = $deactivate ? 'readonly' : '';

		$fields = array(
			'client_id' => array(
				'title' => __('Client ID'),
				'type' => 'text',
				'desc' => '',
				'attr'=> $readonly,
			),
			'client_secret' => array(
				'title' => __('Client Secret'),
				'type' => 'password',
				'desc' => '',
				'attr'=> $readonly,
			),
			'authorized_redirect_uri' => array(
				'title' => __('Authorized redirect URI'),
				'type' => 'copy', 
				'id' => 'gmail_redirect_uri',
                		'attr'=>'readonly',
				'default' => $this->getRedirectUrl('&auth=gmail'),
				'desc' => __('Please copy this URL into the "Authorized redirect URIs" field of your Google web application.')
			),
		);
		
		if($activate){
			$fields['get_acces_token'] = array(
					'title' => __('Get Access Token'),
					'type' => 'button',
					'class'=>'access_token',
					'default' => 'Get Access Token',
					'href' => $this->get_auth_url(),
					'attr' => 'data-field=auth',
			);
		}elseif($deactivate){
			$fields['get_acces_token'] = array(
					'title' => __('Deactivate Access Token'),
					'type' => 'button',
					'class'=>'deactivate_token',
					'default' => 'Deactivate Access Token',
					'href' => $this->getRedirectUrl().($this->conn_id !== 0 ? '&type=edit&conn_id='.$this->conn_id.'&act=deactivate_token#gosmtp-connections-settings' : '&act=deactivate_token'),
					'attr' => 'data-field=auth',
				);
		}else{
			$fields['get_acces_token'] = array(
				'title' => __('Get Access Token'),
				'type' => 'notice',
				'default' => __('You need to save settings with Client ID and Client Secret before you can proceed.'),
			);
		}
		
		return $fields;
	}

	public function gmail_init(){
		$options = $this->getMailerOption();
		
		// Update auth URl when user succesfull regirect our page
		if( empty($options['access_token']) && empty($options['refresh_token']) && isset( $_GET['auth_code'] ) && isset( $_GET['auth'] ) && $this->mailer == $_GET['auth'] && strlen($this->conn_id) > 0 ){

			if( !empty(gosmtp_optget('conn_id')) && $this->conn_id === 0 ){
				return;
			}

			$auth_code = gosmtp_optget('auth_code');
			$this->update_option('auth_token', $auth_code, $this->mailer);
			$resp = '';
			
			if($this->set_token()){                
				$resp = __('Token Updated Sucessfully');
			}elseif(is_wp_error($this->set_token())){
				$resp = $this->set_token()->get_error_message();
			};

			$query = '';
			if( !is_numeric($this->conn_id) ){
				$query = '&type=edit&conn_id='.$this->conn_id.$query.'#gosmtp-connections-settings';
			}

			echo '<script>
				alert("'.$resp.'");
				var url = "'.$this->getRedirectUrl($query).'";
				history.pushState({urlPath:url},"",url);
			</script>';
		}

		// Delete all the tokens or expire stamp when user click deactivate access token
		if(isset($_GET['act']) && $_GET['act'] == 'deactivate_token'){
			
			if(!empty(gosmtp_optget('conn_id')) && $this->conn_id === 0){
				return;
			}
	
			$this->delete_option('refresh_token', $this->mailer);
			$this->delete_option('expire_stamp', $this->mailer);
			$this->delete_option('expires_in', $this->mailer);
			$this->delete_option('version', $this->mailer);
			$this->delete_option('access_token', $this->mailer);
	
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
	}
}

