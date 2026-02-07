<?php
/**
 * Class GOSMTP_Mailer_SMTPcom.
 *
 * @since 1.0.0
 */

namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class SMTPcom extends Loader{
	
	var $title = 'SMTP.com';

	var $mailer = 'smtpcom';

	var $url = 'https://api.smtp.com/v4/messages';
	
	public function send(){
		global $phpmailer;

		$phpmailer->isMail();

		if($phpmailer->preSend()){
			$response = $this->postSend();
		 	return $this->handle_response( $response );
		}
		
		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}

	public function postSend(){		
		global $phpmailer;

		$sender = array(
			'address' => $phpmailer->From,
		);
		
		if(!empty($phpmailer->FromName)){
			$sender['name'] = $phpmailer->FromName;
		}

		$body = array(
			'originator' => [
				'from' => $sender,
			],
			'subject' => $phpmailer->Subject,
			'channel' => $this->getOption('channel', $this->mailer),
			'body' => array(
				'parts' => $this->set_content($phpmailer->Body)
			)
		);
		
		$reply_to = $this->filterRecipientsArray($phpmailer->getReplyToAddresses());
		
		if(!empty($reply_to)){
			$body['originator']['reply_to'] = $reply_to;
		}
		
		$recipients = array(
			'to'  => $this->filterRecipientsArray($phpmailer->getToAddresses()),
			'cc'  => $this->filterRecipientsArray($phpmailer->getCcAddresses()),
			'bcc' => $this->filterRecipientsArray($phpmailer->getBccAddresses())
		);
		
		$body['recipients'] = array_filter($recipients);
		
		$attachments = $phpmailer->getAttachments();
		
		if(!empty($attachments)){
			$body['body']['attachments'] = $this->getAttachments($attachments);
		}
		
		$timeout = (int) ini_get( 'max_execution_time' );

		$api_key = $this->getOption('api_key', $this->mailer);		

		$headers = [
			'Authorization' => 'Bearer ' .$api_key,
			'content-type' => 'application/json',
			'Accept' => 'application/json'
		];

		$custom_headers = $phpmailer->getCustomHeaders();
		$body['custom_headers'] = array_merge($custom_headers,['X-Mailer' => 'GOSMTPMailer - SMTPCom']);

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
			
			// TODO: check aginf for error
			if($responseCode == 200) {
				$returnResponse = [
					'status' => true,
					'code' => 200,
					'messageId' => $responseBody['id'],
					'message' => $responseBody['message'], 
				];
			}else{
				$error_text = [''];
				if(!empty($responseBody['data']) ){
					foreach( (array) $responseBody['data'] as $error_key => $error_message ) {
						$error_text[] = $this->message_formatting( $error_message );
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

	public function set_content( $content ){
		global $phpmailer;

		if( empty( $content ) ){
			return;
		}

		$parts = [];

		if( is_array( $content ) ){
			$allowed = [ 'text', 'html' ];

			foreach( $content as $type => $body ){
				if( ! in_array( $type, $allowed, true ) || empty( $body ) ){
					continue;
				}

				$content_type  = 'text/plain';
				$content_value = $body;

				if( $type === 'html' ){
					$content_type = 'text/html';
				}

				$parts[] = [
					'type' => $content_type,
					'content' => $content_value,
					'charset' => $phpmailer->CharSet,
				];
			}
		}else{
			$content_type  = 'text/html';
			$content_value = $content;

			if( $this->phpmailer->ContentType === 'text/plain' ){
				$content_type = 'text/plain';
			}

			$parts[] = [
				'type'    => $content_type,
				'content' => $content_value,
				'charset' => $this->phpmailer->CharSet,
			];
		}
		
		return $parts;

	}

	protected function filterRecipientsArray($args){
		$recipients = [];

		foreach($args as $key => $recip){
			
			$recip = array_filter($recip);

			if(empty($recip) || ! filter_var( $recip[0], FILTER_VALIDATE_EMAIL ) ){
				continue;
			}

			$recipients[$key] = array(
				'address' => $recip[0]
			);

			if(!empty($recip[1])){
				$recipients[$key]['name'] = $recip[1];
			}
		}

		return $recipients;
	}

	public function getAttachments( $attachments ){

		if( empty( $attachments ) ){
			return;
		}

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

			if( $file === false ){
				continue;
			}

			$filetype = str_replace( ';', '', trim( $attachment[4] ) );

			$data[] = [
				'content' => chunk_split( base64_encode( $file ) ),
				'type' => $filetype,
				'encoding' => 'base64',
				'filename' => empty( $attachment[2] ) ? 'file-' . wp_hash( microtime() ) . '.' . $filetype : trim( $attachment[2] ),
				'disposition' => in_array( $attachment[6], [ 'inline', 'attachment' ], true ) ? $attachment[6] : 'attachment',
				'cid' => empty( $attachment[7] ) ? '' : trim( (string) $attachment[7] ),
			];
		}

		return $data;
	}
	
	public function load_field(){

		$fields = array(
			'api_key' => array(
				'title' => __('API Key', 'gosmtp'),
				'type' => 'password',
				'desc' => __( 'Follow this link to get an API Key from SMTP.com: <a href="https://my.smtp.com/settings/api" target="_blank">Get API Key.</a>', 'gosmtp' ),
			),
			'channel' => array(
				'title' => __('Sender Name', 'gosmtp'),
				'type' => 'text',
				'desc' => __( 'Follow this link to get a Sender Name from SMTP.com: <a href="https://my.smtp.com/senders/" target="_blank">Get Sender Name.</a>', 'gosmtp' ),
			),
		);
		
		return $fields;
	}
}
