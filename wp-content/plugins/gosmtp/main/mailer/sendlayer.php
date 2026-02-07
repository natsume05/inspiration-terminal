<?php
/**
 * Class GOSMTP_Mailer_Sendlayer
 *
 * @since 1.0.0
 */
 
namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class Sendlayer extends Loader{
	
	var $title = 'Sendlayer';
	var $mailer = 'sendlayer';
	var $url = 'https://console.sendlayer.com/api/v1/email';

	public function send(){
		global $phpmailer;
		
		$phpmailer->isMail();
		
		if ($phpmailer->preSend()) {
			$response = $this->postSend();
		 	return $this->handle_response( $response );
		}
		
		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}

	public function postSend(){		
		global $phpmailer;
		
		$sender = array(
			'name' => $phpmailer->FromName,
			'email' => $phpmailer->From
		);

		$body = array(
			'From' => $sender,
			'subject' => $phpmailer->Subject,
			'to' => $this->filterRecipientsArray($phpmailer->getToAddresses()),
			'cc' => $this->filterRecipientsArray($phpmailer->getCcAddresses()),
			'bcc' => $this->filterRecipientsArray($phpmailer->getBccAddresses())
		);
		
		$body['ReplyTo'] = $this->filterRecipientsArray($phpmailer->getReplyToAddresses());
		
		// Remove empty array values
		$body = array_filter($body);
		
		$content = $phpmailer->Body;
		
		if(!empty($content)){
			if( is_array( $content ) ){
				if( ! empty( $content['text'] ) ){
					$body['ContentType'] = 'plain';
					$body['PlainContent'] = $content['text'];
				}

				if( ! empty( $content['html'] ) ){
					$body['ContentType'] = 'html';
					$body['HTMLContent'] = $content['html'];
				}
			}else{
				if( $phpmailer->ContentType === 'text/plain' ){
					$body['ContentType'] = 'plain';
					$body['PlainContent'] = $content;
				}else{
					$body['ContentType'] = 'html';
					$body['HTMLContent'] = $content;
				}
			}
		}

		$attachments = $phpmailer->getAttachments();
		
		if(!empty($attachments)){
			$body['attachment'] = $this->getAttachments($attachments);
		}

		$custom_headers = $phpmailer->getCustomHeaders();
		
		$body['Headers'] = array_merge($custom_headers, ['X-Mailer' => 'GOSMTPMailer - Sendlayer']);

		$timeout = (int) ini_get( 'max_execution_time' );

		$api_key = $this->getOption('api_key', $this->mailer);

		$headers = array(
			'Authorization' => 'Bearer ' .$api_key,
			'Content-Type' => 'application/json',
			'Accept' => 'application/json'
		);

		$params = array(
			'headers' => $headers,
			'body' => wp_json_encode($body),
			'timeout' => $timeout ? $timeout : 30
		);

		// print_r(json_encode($body, JSON_PRETTY_PRINT));

		$response = wp_safe_remote_post($this->url, $params);

		if(is_wp_error($response)){
			$returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
		}else{
			$responseBody = wp_remote_retrieve_body($response);
			$responseCode = wp_remote_retrieve_response_code($response);
			$responseBody = \json_decode($responseBody, true);
			
			if($responseCode == 200){
				$returnResponse = [
					'status' => true,
					'code' => 200,
					'messageId' => $responseBody['id'],
					'message' => $responseBody['message'], 
				];
				
			}else{
				
				$error_text = [''];
				if(!empty($responseBody['Errors']) ){
					foreach ( $responseBody['Errors'] as $error ) {
    					
						if(empty( $error['Message'] )) {
							continue;
						}
						
						$message = $error['Message'];
						$code = !empty($error['Code']) ? $error['Code'] : '';
						
						$error_text[] = $this->message_formatting( $message, $code );
					}
				}else{
					$error_text[] = $this->get_response_error_message($response);
				}
				
				$error_msg = implode( '\r\n', array_map( 'esc_textarea', array_filter( $error_text ) ) );
				$returnResponse = new \WP_Error($responseCode, $error_msg, $responseBody);
			}
		}
		
		return $returnResponse;
	}

	protected function filterRecipientsArray($args){
		$recipients = [];
		foreach($args as $key => $recip){
			
			$recip = array_filter($recip);

			if(empty($recip) || ! filter_var( $recip[0], FILTER_VALIDATE_EMAIL ) ){
				continue;
			}

			$_recip = array(
				'email' => $recip[0]
			);

			if(!empty($recip[1])){
				$_recip['name'] = $recip[1];
			}
			
			$recipients[] = $_recip;
		}

		return $recipients;
	}
	
	private function getAttachments( $attachments ) {

		$data = [];

		foreach( $attachments as $attachment ){
			
			$file = false;

			try{
				if( $attachment[5] === true ){
					$file = $attachment[0];
				}elseif( is_file( $attachment[0] ) && is_readable( $attachment[0] ) ){
					$file = file_get_contents( $attachment[0] );
				}
			}catch( \Exception $e ){ 
				$file = false;
			}
			
			if(false === $file){
				continue;
			}

			$filetype = str_replace( ';', '', trim( $attachment[4] ) );

			$data[] = array(
				'Filename' => empty( $attachment[2] ) ? 'file-' . wp_hash( microtime() ) . '.' . $filetype : trim( $attachment[2] ),
				'Content' => base64_encode( $content ),
				'Type' => $attachment[4],
				'Disposition' => in_array( $attachment[6], [ 'inline', 'attachment' ], true ) ? $attachment[6] : 'attachment',
				'ContentId' => empty( $attachment[7] ) ? '' : trim( (string) $attachment[7] ),
			);
		}

		return $data;
	}
	
	public function load_field(){
		
		$fields = array(
			'api_key' => array(
				'title' => __('API Key', 'gosmtp'),
				'type' => 'password',
				'desc' => __( 'Follow this link to get an API Key from SendLayer: <a href="https://app.sendlayer.com/settings/api/" target="_blank">Get API Key.</a>', 'gosmtp' ),
			),

		);
		
		return $fields;
	}
}