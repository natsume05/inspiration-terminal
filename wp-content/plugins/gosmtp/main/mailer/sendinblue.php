<?php
/**
 * Class GOSMTP_Mailer_Sendinblue.
 *
 * @since 1.0.0
 */
 
namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class Sendinblue extends Loader{

	var $title = 'Brevo';
	
	var $mailer = 'sendinblue';

	var $url = 'https://api.brevo.com/v3/smtp/email';
	
	
	private $allowed_exts = [ 'xlsx', 'xls', 'ods', 'docx', 'docm', 'doc', 'csv', 'pdf', 'txt', 'gif', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'rtf', 'bmp', 'cgm', 'css', 'shtml', 'html', 'htm', 'zip', 'xml', 'ppt', 'pptx', 'tar', 'ez', 'ics', 'mobi', 'msg', 'pub', 'eps', 'odt', 'mp3', 'm4a', 'm4v', 'wma', 'ogg', 'flac', 'wav', 'aif', 'aifc', 'aiff', 'mp4', 'mov', 'avi', 'mkv', 'mpeg', 'mpg', 'wmv'];

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

		$sender = [
			'name' => $phpmailer->FromName,
			'email' => $phpmailer->From
		];

		$body = [
			'sender' => $sender,
			'subject' => $phpmailer->Subject,
			'to' => $this->filterRecipientsArray($phpmailer->getToAddresses()),
			'cc' => $this->filterRecipientsArray($phpmailer->getCcAddresses()),
			'bcc' => $this->filterRecipientsArray($phpmailer->getBccAddresses())
		];
		
		$body = array_filter($body);
			
		$content = $phpmailer->Body;
		
		if(!empty($content)){
			if( is_array( $content ) ){

				if(!empty( $content['text'])){
					$body['textContent'] = $content['text'];
				}

				if(!empty( $content['html'])){
					$body['htmlContent'] = $content['html'];
				}
			}else{
				if($phpmailer->ContentType === 'text/plain' ){
					$body['textContent'] = $content;
				}else{
					$body['htmlContent'] = $content;
				}
			}
		}

		$body = $this->set_replyto( $phpmailer->getReplyToAddresses(), $body );

		$attachments = $phpmailer->getAttachments();
		
		if(!empty($attachments)){
			$body['attachment'] = $this->getAttachments($attachments);
		}

		$timeout = (int) ini_get( 'max_execution_time' );

		$api_key = $this->getOption('api_key', $this->mailer);

		$headers = [ 'Api-Key' => $api_key,
			'Content-Type' => 'application/json',
			'Accept' => 'application/json'
		];

		$params = array(
			'headers' => $headers,
			'body' => json_encode($body),
			'timeout' => $timeout ? $timeout : 30
		);

		$response = wp_safe_remote_post($this->url, $params);

		if(is_wp_error($response)){
			$returnResponse = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
		}else{
			$responseBody = wp_remote_retrieve_body($response);
			$responseCode = wp_remote_retrieve_response_code($response);
			$responseBody = \json_decode($responseBody, true);
			
			// TODO: check the responseCode is 200 is correct
			if($responseCode == 201){
				$returnResponse = [
					'status' => true,
					'code' => $responseCode,
					'messageId' => $responseBody['messageId'],
					'message' => __('Mail Sent successfully', 'gosmtp'),

				];
				
			}else{
				$error_text = [''];
				if(!empty( $responseBody['message'])){
					$error_text[] = $this->message_formatting( $responseBody['message'] );
				}else{
					$error_text[] = $this->get_response_error_message($response);
				}
				
				$error_msg = implode( '\r\n', array_map( 'esc_textarea', array_filter( $error_text ) ) );
				$returnResponse = new \WP_Error($responseCode, $error_msg, $responseBody);
			}
		}
		
		return $returnResponse;
	}

	public function set_replyto( $emails,  $body) {
		
		$data = $this->filterRecipientsArray( $emails );
		
		if(!empty( $data )){
			$body['replyTo'] = $data[0];
		}
		
		return $body;
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

	public function getAttachments($attachments = []){
		$files = [];

		foreach($attachments as $attachment){
			if(is_file($attachment[0]) && is_readable($attachment[0])){
				$ext = pathinfo($attachment[0], PATHINFO_EXTENSION);

				if(in_array($ext, $this->allowed_exts, true)){
					$files[] = [
						'name' => basename($attachment[0]),
						'content' => base64_encode(file_get_contents($attachment[0]))
					];
				}
			}
		}

		return $files;
	}
	
	public function load_field(){

		$fields = array(
			'api_key' => array(
				'title' => __('API Key', 'gosmtp'),
				'type' => 'password',
				'desc' => __( 'Follow this link to get an API Key: <a href="https://app.brevo.com/settings/keys/api" target="_blank">Get v3 API Key.</a>', 'gosmtp' ),
			)
		);
		
		return $fields;
	}
}

