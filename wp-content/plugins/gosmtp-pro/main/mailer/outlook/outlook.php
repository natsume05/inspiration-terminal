<?php
/**
 * Class GOSMTP_Mailer_Outlook.
 *
 * @since 1.0.0
 */

namespace GOSMTP\Mailer\Outlook;
 
use GOSMTP\Mailer\Loader;

class Outlook extends Loader{

	var $title = 'Outlook';
	var $mailer = 'outlook';
	var $url = 'https://graph.microsoft.com/v1.0/me/sendMail';
	
	public function send(){
		global $phpmailer;
		
		$phpmailer->isMail();
		$phpmailer->Encoding = 'base64';
		
		if ($phpmailer->preSend()) {
			$response = $this->postSend();
			return $this->handle_response( $response );
		}

		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}
	
	public function postSend(){
		global $phpmailer;
		
		try{
			$access_token = $this->getAccessToken($this->getMailerOption());
			
			if(is_wp_error($access_token)){ 
				return $access_token->get_error_message();
			}
			
			$mime = chunk_split(base64_encode($phpmailer->getSentMIMEMessage()), 76, "\n");
			
			$params = array(
				'method' => 'POST',
				'headers' => [
					'Authorization' => 'Bearer '. $access_token,
					'Content-Type' => 'text/plain'
				],
				'body' => $mime
			);
			
			$response = wp_remote_request($this->url, $params);
			
			if(is_wp_error($response)){
				$return_response = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
			}else{
				$resp_body = wp_remote_retrieve_body($response);
				$resp_code = wp_remote_retrieve_response_code($response);
				$resp_body = \json_decode($resp_body, true);
				
				if(202 == $resp_code){
					$msgId = isset( $response['headers']['request-id'] ) ? $response['headers']['request-id'] : '';
					$status = __('Email sent successfully');
					$return_response = [
						'status' => true,
						'code' => $resp_code,
						'messageId' => $msgId,
						'message' => $status
					];	
				}else{
					$err_code = $resp_code;
					$error_text = [''];
					if(!empty( $resp_body['error']) && is_array($resp_body['error'])){
						$message = $resp_body['error']['message'];
						$code = !empty( $resp_body['error']['code'] ) ? $resp_body['error']['code'] : '';
						$desc = '';

						if($code === 'ErrorAccessDenied'){
							$desc = esc_html__( 'Note: This issue can also be caused by exceeding the total message size limit. If you are using large attachments, please remove the existing Outlook Mailer OAuth connection in WP Mail SMTP settings and reconnect it. We recently added support for large attachments, but oAuth re-connection is required.');
						}
						
						$error_text[] = $this->message_formatting( $message, $code, $desc );
						
					}else{
						$error_text[] = $this->get_response_error_message($response);
					}
        			
					$error_msg = implode( '\r\n', array_map( 'esc_textarea', array_filter( $error_text ) ) );
					$return_response = new \WP_Error($err_code, $error_msg, $resp_body);
				}
			}
			
		}catch(\Exception $e){
			$return_response = new \WP_Error(423, $e->getMessage(), []);
		}

		return $return_response;
	}

	public function getRedirectUrl($query = ''){

		// TODO check and change this
		return admin_url().'admin.php?page=gosmtp'.$query;
	}

	private function getAccessToken($options){
		$accessToken = $options['access_token'];
		
		// check if expired or will be expired in 300 seconds
		if( ($options['expire_stamp'] - 300) < time()){
			
			$api = new \GOSMTP\mailer\outlook\Auth($options['client_id'], $options['client_secret']);

			$tokens = $api->sendTokenRequest('refresh_token', [
				'refresh_token' => $options['refresh_token']
			]);

			if(is_wp_error($tokens)) {
				return false;
			}

			$this->saveNewTokens($options, $tokens);

			$accessToken = $tokens['access_token'];
		}

		return $accessToken;
	}
	
	private function saveNewTokens($data, $tokens){
		
		if (empty($tokens['access_token']) || empty($tokens['refresh_token'])) {
			return false;
		}

		$this->update_option('access_token', $tokens['access_token'], $this->mailer);
		$this->update_option('refresh_token', $tokens['refresh_token'], $this->mailer);
		$this->update_option('expire_stamp', $tokens['expires_in'] + time(), $this->mailer);
	}
	
	public function load_field(){
		
		$this->outlook_init();
		
		$client_id = $this->getOption('client_id', $this->mailer);
		$client_secret = $this->getOption('client_secret', $this->mailer);
		$access_token = $this->getOption('access_token', $this->mailer);
		$refresh_token = $this->getOption('refresh_token', $this->mailer);
		$mail_type = $this->getOption('mail_type', $this->mailer);
		
		$activate = !empty($client_id) && !empty($client_secret)  && empty($access_token) && empty($refresh_token);
		$deactivate = !empty($refresh_token) && !empty($access_token) && $this->mailer == $mail_type;
		
		$readonly = $deactivate ? 'readonly' : '';

		$state = ($this->conn_id === 0 ? '' : '-'.$this->conn_id);

		$api = new \GOSMTP\mailer\outlook\Auth($client_id, $client_secret, $state);

		$fields = array(
			'client_id' => array(
				'title' => __('Application Client ID'),
				'type' => 'text',
				'attr' => $readonly,
			),
			'client_secret' => array(
				'title' => __('Application Client Secret'),
				'type' => 'password',
				'attr' => $readonly,
				'desc' => __( 'Follow this link to get a Application Client Secret from Outlook: <a href="https://learn.microsoft.com/en-us/azure/industry/training-services/microsoft-community-training/frequently-asked-questions/generate-new-clientsecret-link-to-key-vault" target="_blank">Application Client Secret.</a>' ),
			),
			'redirect_uri' => array(
				'title' => __('Outlook Callback URL'),
				'type' => 'copy', 
				'id' => 'outlook_redirect_uri',
				'attr'=>'readonly',
				'default' => $api->getRedirectUrl(),
				'desc' => __('Use this URL to your APP as Redirect URI.')
			)
		);

		if($activate){
			$fields['get_access_token'] = array(
				'title' => __('Get Access Token'),
				'type' => 'button',
				'class'=>'access_token',
				'default' => __('Authenticate with outlook/Office365 & Get Access Token'),
				'href' => $api->getAuthUrl(),
				'attr' => 'data-field=auth',
			);

		}elseif($deactivate){
			$fields['get_access_token'] = array(
					'title' => __('Deactivate Access Token'),
					'type' => 'button',
					'class'=>'deactivate_token',
					'default' => 'Deactivate Access Token',
					'href' => $this->getRedirectUrl().($this->conn_id !== 0 ? '&type=edit&conn_id='.$this->conn_id.'&act=deactivate_token#gosmtp-connections-settings' : '&act=deactivate_token'),
					'attr' => 'data-field=auth',
				);

		}else{
			$fields['get_access_token'] = array(
				'title' => __('Get Access Token'),
				'type' => 'notice',
				'default' => __('You need to save settings with Client ID and Client Secret before you can proceed.'),
			);
		}
		
		return $fields;
	}

	// Generate access token and refresh token and update in data base.
	public function set_token(){
		$errors = [];
		
		$options = $this->getMailerOption();

		if(empty($options['access_token']) && !empty($options['auth_token'])){
			
			$api = new \GOSMTP\mailer\outlook\Auth($options['client_id'], $options['client_secret']);

			$tokens = $api->generateToken($options['auth_token']);

			if(is_wp_error($tokens) || (is_array($tokens) && isset($tokens['error']))) {
				$err = is_wp_error($tokens) ? $tokens->get_error_message() : __('Mailer Authentication failed!');
				return new \WP_Error(423,  $err);
			}

			$this->saveNewTokens($options, $tokens);

		}elseif(!$authToken && !$accessToken){
				return new \WP_Error(423,  __('Please Provide Auth Token.', 'GOSMTP'));
		}

		return true;
	}

	public function outlook_init(){
		$options = $this->getMailerOption();
		
		// Update auth URl when user succesfull regirect our page
		if( empty($options['access_token']) && empty($options['refresh_token']) && isset( $_GET['auth_code'] ) && isset( $_GET['auth'] ) && $this->mailer == $_GET['auth'] && strlen($this->conn_id) > 0 ){

			if( !empty(gosmtp_optget('conn_id')) && $this->conn_id === 0 ){
				return;
			}

			$auth_code = gosmtp_optget('auth_code');
			$this->update_option('auth_token', $auth_code, $this->mailer);
			$resp = '';
			
			$set_token = $this->set_token();
		
			if(is_wp_error($set_token)){
				$resp = $set_token->get_error_message();
			}else{
				$resp = __('Mailer successfully configured!');
			}

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