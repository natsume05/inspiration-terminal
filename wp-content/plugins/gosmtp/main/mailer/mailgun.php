<?php
/**
 * Class GOSMTP_Mailer_Mailgun.
 *
 * @since 1.0.0
 */

namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class Mailgun extends Loader{

	var $title = 'Mailgun';
	
	const API_URL_US = 'https://api.mailgun.net/v3/';
	
	const API_URL_EU = 'https://api.eu.mailgun.net/v3/';
	
	var $url = '';
	
	var $mailer = 'mailgun';
	
	public function send(){
		global $phpmailer;
		
		$phpmailer->isMail();
		
		if ($phpmailer->preSend()) {
			$this->set_API_Url();
			$response = $this->postSend();
			return $this->handle_response( $response );
		}

		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}
	
	public function postSend(){
		global $phpmailer;
		
		$content_type = $phpmailer->ContentType;
		$reply_to = $phpmailer->getReplyToAddresses();
		
		$body = [
			'from'           => $phpmailer->From,
			'subject'        => $phpmailer->Subject,
			'h:X-Mailer'     => 'GOSMTPMailer - Mailgun',
			'h:Content-Type' => $content_type
		];
		
		if(stripos($content_type, 'html') === false){
			$body['text'] = $phpmailer->Body;
		}else{
			$body['html'] = $phpmailer->Body;
		}

		if(!empty($reply_to)){
			$body['h:Reply-To'] = $reply_to;
		}
			
		$recipients = [
			'to'  => $this->getRecipients($phpmailer->getToAddresses()),
			'cc'  => $this->getRecipients($phpmailer->getCcAddresses()),
			'bcc' => $this->getRecipients($phpmailer->getBccAddresses())
		];

		if ($recipients = array_filter($recipients)) {
			$body = array_merge($body, $recipients);
		}
		
		$timeout = (int) ini_get( 'max_execution_time' );

		$params = array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode('api:' . $this->getOption('api_key', $this->mailer))
				),
				'body' => $body,
				'timeout' => $timeout ? $timeout : 30,
				'httpversion' => '1.1',
				'blocking'    => true
		);

		$attachments = $phpmailer->getAttachments();
		
		if(!empty($attachments)){
			$params = $this->getAttachments($params);
		}

		$response = wp_safe_remote_post($this->url, $params);

		if (is_wp_error($response)) {
			$returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
		} else {
			$responseBody = wp_remote_retrieve_body($response);
			$responseCode = wp_remote_retrieve_response_code($response);

			$isOKCode = $responseCode == 200;

			if($isOKCode) {
				$responseBody = \json_decode($responseBody, true);
			}

			if($isOKCode && isset($responseBody['id'])) {
				$returnResponse = [
					'status' => true,
					'code' => $responseCode,
					'messageId' => $responseBody['id'],
					'message' => $responseBody['message'],
				];
			}else{
				if(!empty( $responseBody['message'])){
					$error_text[] = $this->message_formatting( $responseBody['message'] );
				} else {
					$error_text[] = $this->get_response_error_message($response);
				}
				
				$error_msg = implode( '\r\n', array_map( 'esc_textarea', array_filter( $error_text ) ) );
				$returnResponse = new \WP_Error($responseCode, $error_msg, $responseBody);
			}
		}

		return $returnResponse;
	}

	public function getRecipients($recipient){
		$recipients = $this->filterRecipientsArray($recipient);
		
		$array = array_map(function($recipient){
			return isset($recipient['name'])
			? $recipient['name'] . ' <' . $recipient['address'] . '>'
			: $recipient['address'];
		}, $recipients);

		return implode(', ', $array);
	}
	
	public function getAttachments($params){
		global $phpmailer;
		
		$data = [];
		$payload = '';
		$attachments = $phpmailer->getAttachments();

		foreach($attachments as $attachment){
			$file = false;

			try{
				if (is_file($attachment[0]) && is_readable($attachment[0])) {
					$fileName = basename($attachment[0]);
					$file = file_get_contents($attachment[0]);
				}
			}catch(\Exception $e){
				$file = false;
			}

			if($file === false){
				continue;
			}

			$data[] = [
				'content' => $file,
				'name'    => $fileName,
			];
		}

		if(!empty($data)){
			$boundary = hash('sha256', uniqid('', true));

			foreach($params['body'] as $key => $value){
				if(is_array($value)){
					foreach($value as $child_key => $child_value){
						$payload .= '--' . $boundary;
						$payload .= "\r\n";
						$payload .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
						$payload .= $child_value;
						$payload .= "\r\n";
					}
				}else{
					$payload .= '--' . $boundary;
					$payload .= "\r\n";
					$payload .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
					$payload .= $value;
					$payload .= "\r\n";
				}
			}

			foreach($data as $key => $attachment){
				$payload .= '--' . $boundary;
				$payload .= "\r\n";
				$payload .= 'Content-Disposition: form-data; name="attachment[' . $key . ']"; filename="' . $attachment['name'] . '"' . "\r\n\r\n";
				$payload .= $attachment['content'];
				$payload .= "\r\n";
			}

			$payload .= '--' . $boundary . '--';

			$params['body'] = $payload;
			$params['headers']['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

		}

		return $params;
	}
	
	public function set_API_Url(){
		
		$url = self::API_URL_US;
		
		if($this->getOption('region', $this->mailer) == 'eu'){
			$url = self::API_URL_EU;
		}

		$url .= sanitize_text_field($this->getOption('domain_name', $this->mailer) . '/messages');

		return $this->url = $url;
	}

	public function load_field(){
		
		$fields = array(
			'api_key' => array(
				'title' => __('Private API Key', 'gosmtp'),
				'type' => 'password',
				'desc' => __( 'Follow this link to get a Private API Key from Mailgun: <a href="https://app.mailgun.com/app/account/security/api_keys" target="_blank">Get a Private API Key.</a>', 'gosmtp' ),
			),
			'domain_name' => array(
				'title' => __('Domain Name', 'gosmtp'),
				'type' => 'text',
				'desc' => __( 'Follow this link to get a Domain Name from Mailgun: <a href="https://app.mailgun.com/app/domains" target="_blank">Get a Domain Name.</a>', 'gosmtp' ),
			),
			'region' => array(
				'title' => __('Region', 'gosmtp'),
				'type' => 'radio',
				'class' => 'regular-text',
				'list' => array(
					'us' => 'US',
					'eu' => 'EU',
				),
				'desc' => __( 'Define which endpoint you want to use for sending messages.<br>If you are operating under EU laws, you may be required to use EU region. <a href="https://www.mailgun.com/about/regions/" target="_blank">More information</a> on Mailgun.com.', 'gosmtp' ),
			),
		);
		
		return $fields;
	}
}


