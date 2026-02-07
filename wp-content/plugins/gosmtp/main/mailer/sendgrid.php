<?php
/**
 * Class GOSMTP_Mailer_Sendgrid.
 *
 * @since 1.0.0
 */
 
namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class Sendgrid extends Loader{

	var $title = 'Sendgrid';
	var $mailer = 'sendgrid';
	var $url = 'https://api.sendgrid.com/v3/mail/send';

	public function send(){
		global $phpmailer;
		
		$phpmailer->isMail();
		
		if ($phpmailer->preSend() ) {
			$response = $this->postSend();
		 	return $this->handle_response( $response );
		}
		
		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}

	public function postSend(){
		global $phpmailer;
						
		$body = [
			'from' => $this->getFrom(),
			'personalizations' => $this->getRecipients(),
			'subject' => $phpmailer->Subject,
			'content' => $this->getBody() 
		];
		
		if($replyTo = $this->filterRecipientsArray($phpmailer->getReplyToAddresses())){
			$body['ReplyTo'] = $replyTo;
		}

		if(!empty($this->getAttachments())){ 
			$body['Attachments'] = $this->getAttachments();
		}
		
		$params = [
			'body' => json_encode($body),
			'headers' => $this->getRequestHeaders()
		];

		$params = array_merge($params, $this->getDefaultParams());

		$response = wp_safe_remote_post($this->url, $params);

		if(is_wp_error($response)){
			$returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
		}else{
			$responseBody = wp_remote_retrieve_body($response);
			$responseCode = wp_remote_retrieve_response_code($response);
			$responseBody = !empty($responseBody) ? \json_decode($responseBody, true) : '';
			$responseHeaders = wp_remote_retrieve_headers($response);
			$messageId = isset($responseHeaders['x-message-id']) ?  $responseHeaders['x-message-id'] : '';
			
			if($responseCode == 202) {

				$returnResponse = [
					'status' => true,
					'code' => 202,
					'messageId' => $messageId,
					'message' => __('Mail Sent successfully', 'gosmtp'),
				];
				
			}else{
				$error_text = [''];
				if(!empty( $responseBody['errors'] ) && is_array( $responseBody['errors'] )){
					foreach ( $responseBody['errors'] as $error ) {
						
						if(empty( $error['message'] )){
							continue;
						}
						
						$message = $error['message'];
						$code = ! empty( $error['field'] ) ? $error['field'] : '';
						$description = ! empty( $error['help'] ) ? $error['help'] : '';
						
						$error_text[] = $this->message_formatting( $message, $code, $description );
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

	protected function getRequestHeaders(){
		return array(
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $this->getOption('api_key', $this->mailer)
		);
	}

	protected function getFrom(){
		global $phpmailer;
		
		$from = [
			'email' => $phpmailer->From,
			'name' => $phpmailer->FromName
		];

		return $from;
	}

	protected function getAttachments(){
		global $phpmailer;
		
		$data = [];
		
		foreach ($phpmailer->getAttachments() as $attachment){
			$file = false;
			
			try{
				if (is_file($attachment[0]) && is_readable($attachment[0])) {
					$fileName = basename($attachment[0]);
					$contentId = wp_hash($attachment[0]);
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
				'type' => $filetype,
				'filename' => $fileName,
				'disposition' => 'attachment',
				'content_id'  => $contentId,
				'content' => base64_encode($file)
			];
		}

		return $data;
	}

	public function getRecipients(){
		global $phpmailer;
		
		$recipients = [
			'to' => $this->filterRecipientsArray($phpmailer->getToAddresses()),
			'cc' => $this->filterRecipientsArray($phpmailer->getCcAddresses()),
			'bcc' => $this->filterRecipientsArray($phpmailer->getBccAddresses()),
		];

		return array(array_filter($recipients));
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
	
	protected function getBody(){
		global $phpmailer;
		
		$content = array(
			'value' => $phpmailer->Body,
			'type' => $phpmailer->ContentType
		);
		
		if($phpmailer->ContentType !== 'text/plain'){
			$content['type'] = 'text/html';
		}
		
		if(!empty($phpmailer->AltBody)){
			$content[] = [
				'value' => $phpmailer->AltBody,
				'type' => 'text/plain',
			];
		}

		return [$content];
	}

	public function load_field(){

		$fields = array(
			'api_key' => array(
				'title' => __('API Key', 'gosmtp'),
				'type' => 'password',
			),
		);
		
		return $fields;	
	}
}
