<?php
/**
 * Class GOSMTP_Mailer_Sparkpost.
 *
 * @since 1.0.0
 */

namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class Sparkpost extends Loader{
	
	var $title = 'Sparkpost';
	
	var $mailer = 'sparkpost';
	
	const API_URL_US = 'https://api.sparkpost.com/api/v1';
	
	const API_URL_EU = 'https://api.eu.sparkpost.com/api/v1';
	
	var $url = '';

	public function send(){
		global $phpmailer;
		
		$phpmailer->isMail();
		
		if($phpmailer->preSend()){
			$this->set_API_Url();
			$response = $this->postSend();
		 	return $this->handle_response( $response );
		}
		
		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}

	public function postSend(){
		global $phpmailer;
		
		$body = [
			'options' => [
				'open_tracking'  => false,
				'click_tracking' => false,
				'transactional'  => true,
			],
			'content' => [
				'from' => [
					'name' => $phpmailer->FromName,
					'email' => $phpmailer->From
				],
				'subject' => $phpmailer->Subject,
				'headers' => [],
			],
			'recipients' => $this->get_recipients()
		];
		
		$body['content']['headers']['CC'] = implode( ',', array_map( [$phpmailer, 'addrFormat'], $phpmailer->getCcAddresses() ) );
		
		if( $phpmailer->ContentType === 'text/plain' ){
			$body['content']['text'] = $phpmailer->AltBody;
		}else{
			$body['content']['html'] = $phpmailer->Body;
		}
		
		$replyTo = $phpmailer->getReplyToAddresses();	
		
		if(!empty($replyTo)){
			$body['content']['reply_to'] = implode( ',', array_map( [ $phpmailer, 'addrFormat' ], $replyTo ) );
		}
		
		$attachments = $phpmailer->getAttachments();
		
		if(!empty($attachments)){ 
			$body['Content']['Attachments'] = $this->getAttachments($attachments);
		}

		$params = [
			'body' => json_encode($body),
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => $this->getOption('api_key', $this->mailer)
			]
		];

		$params = array_merge($params, $this->getDefaultParams());

		$response = wp_safe_remote_post($this->url, $params);

		if(is_wp_error($response)){
			$returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
		}else{
			$responseBody = wp_remote_retrieve_body($response);
			$responseCode = wp_remote_retrieve_response_code($response);

			$isOKCode = $responseCode < 300;

			$responseBody = \json_decode($responseBody, true);

			if($isOKCode){
				$returnResponse = [
					'status' => true,
					'code' => $responseCode,
					'messageId' => $responseBody['id'],
					'message' => $responseBody['message'],
				];
								
			}else{
				$error_text = [''];
				
				if(!empty($responseBody['errors'] ) && is_array( $responseBody['errors'])){

					foreach($responseBody['errors'] as $error){

						if(empty($error['message'])){
							continue;
						}
						
						$code = !empty($error['code']) ? $error['code'] : '';
						$desc = !empty($error['description']) ? $error['description'] : '';
						
						$error_text[] = $this->message_formatting($error['message'], $code, $desc);
					}
				}else{
					$error_text[] = $this->get_response_error_message($response);
				}
			
				$error_message = implode( '\r\n', array_map( 'esc_textarea', array_filter( $error_text ) ) );
				$returnResponse = new \WP_Error($responseCode, $error_message, $responseBody);
			}
		}

		return $returnResponse;

	}

	public function set_API_Url(){

		$url = self::API_URL_US;

		if($this->getOption('region', $this->mailer) == 'eu'){
			$url = self::API_URL_EU;
		}
		
		$url .='/transmissions';
		
		return $this->url = $url;
	}
	
	public function get_recipients(){
		global $phpmailer;
		
		$data = [];
				
		$recipients = [
			'to' => $phpmailer->getToAddresses(),
			'cc' => $phpmailer->getCcAddresses(),
			'bcc' => $phpmailer->getBccAddresses(),
		];
		
		$recipients_to = isset( $recipients['to'] ) && is_array( $recipients['to'] ) ? $recipients['to'] : [];
		$header_to = implode( ',', array_map( [$phpmailer, 'addrFormat'], $recipients_to ) );
				
		foreach( $recipients as $key => $emails ){
			
			if(empty($emails)){
				continue;
			}
			
			foreach( $emails as $email ){
				$holder = [];
				
				$holder['email'] = $email[0];

				if( ! empty( $email[1] ) ){
					$holder['name'] = $email[1];
				}

				if(!empty($header_to) && $key != 'to'){
					$holder['header_to'] = $header_to;
				}

				$data[] = [ 'address' => $holder ];
			}
		}
		
		return $data;
	}
	
	protected function getAttachments($attachments){
		
		$data = [];

		foreach($attachments as $attachment){
			$file = false;

			try{
				if (is_file($attachment[0]) && is_readable($attachment[0])) {
					$fileName = basename($attachment[0]);
					$file = file_get_contents($attachment[0]);
					$mimeType = mime_content_type($attachment[0]);
					$filetype = str_replace(';', '', trim($mimeType));
				}
			} catch (\Exception $e) {
				$file = false;
			}

			if ($file === false) {
				continue;
			}

			$data[] = [
				'name' => $fileName,
				'type' => $filetype,
				'content' => base64_encode($file)
			];
		}

		return $data;
	}

	public function load_field(){
		$fields = array(
			'api_key' => array(
				'title' => __('API Key', 'gosmtp'),
				'type' => 'password',
				'desc' => __( 'Follow this link to get an API Key from SparkPost: <a href="https://app.sparkpost.com/account/api-keys" target="_blank">Get API Key.</a>', 'gosmtp' ),
			),
			'region' => array(
				'title' => __('Region', 'gosmtp'),
				'type' => 'radio',
				'class'=>'regular-text',
				'list'=>array(
					'Us'=>'US',
					'EU'=>'EU',
				),
				'desc' => __( 'Select your SparkPost account region. <a href="https://support.sparkpost.com/docs/getting-started/getting-started-sparkpost" target="_blank">More information </a>on SparkPost.', 'gosmtp' ),
			),
		);
		
		return $fields;
	}
}

