<?php
/**
 * Class GOSMTP_Mailer_SMTP2Go.
 *
 * @since 1.0.0
 */

namespace GOSMTP\Mailer;

use GOSMTP\Mailer\Loader;

class SMTP2Go extends Loader{

	var $title = 'SMTP2Go';
	var $mailer = 'smtp2go';
	var $url = 'https://api.smtp2go.com/v3/email/send';

	public function send(){
		global $phpmailer;

		if($phpmailer->preSend()){
			$response = $this->postSend();
			return $this->handle_response($response);
		}

		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}

	protected function postSend(){
		global $phpmailer;
        
		try{
			$api_key = $this->getOption('api_key', $this->mailer);
    
			if(empty($api_key)){
				return new \WP_Error(401, 'SMTP2Go API Key is missing.');
			}

			// SMTP2Go requires subject to be passed
			$subject = $phpmailer->Subject;
			if(empty($subject)){
				$subject = 'Mail sent from: '. site_url();
			}

			$from = $phpmailer->From;
			$fromName = $phpmailer->FromName;
    
			// Prepare SMTP2Go v4 API JSON Payload
			$payload = [
				'sender' => $fromName. '<' .$from. '>',
				'to' => $this->filterRecipientsArray($phpmailer->getToAddresses()),
				'cc' => $this->filterRecipientsArray($phpmailer->getCcAddresses()),
				'bcc' => $this->filterRecipientsArray($phpmailer->getBccAddresses()),
				'subject' => $subject,
				'text_body' => '',
				'html_body' => '',
				'attachments' => $this->getAttachments()
			];
			
			$body_text = $phpmailer->AltBody;
			$body_html = $phpmailer->Body;
    
			if(!empty($body_text)){
				$payload['text_body'] = $body_text;
			}

			if(!empty($body_html)){
				$payload['html_body'] = $body_html;
			}
            
			$params = [
				'method'  => 'POST',
				'headers' => $this->getRequestHeaders(),
				'body' => wp_json_encode($payload)
			];

			$response = wp_safe_remote_request($this->url, $params);

			if(is_wp_error($response)){
				return new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
			} else{
				$resp_body = wp_remote_retrieve_body($response);
				$resp_code = wp_remote_retrieve_response_code($response);
				$resp_body = \json_decode($resp_body, true);

				if(200 === $resp_code){
					$msgId = isset($resp_body['data']['email_id']) ? $resp_body['data']['email_id'] : '';
					$status = 'Email sent successfully';
					$return_response = [
						'status' => true,
						'code' => $resp_code,
						'messageId' => $msgId,
						'message' => $status
					];
				} else{
					$err_code = $resp_code;
					$error_text = [''];

					if(!empty($resp_body['Error']) && is_array($resp_body['Error'])){
						$message = $resp_body['Error'];
						$code = isset($resp_body['Code']) ? $resp_body['Code'] : '';

						$error_text[] = $this->message_formatting($message, $code);
					} else{
						$error_text[] = $this->get_response_error_message($response);
					}

					$error_msg = implode('\r\n', array_map('esc_textarea', array_filter($error_text)));
					$return_response = new \WP_Error($err_code, $error_msg, $resp_body);
				}
			}
		} catch(\Exception $e){
			return new \WP_Error(423, $e->getMessage(), []);
		}

		return $return_response;
	}

	protected function getRequestHeaders(){
		return [
			'X-Smtp2go-Api-Key' => $this->getOption('api_key', $this->mailer),
			'Content-Type'  => 'application/json',
			'Accept' => 'application/json'
		];
	}

	protected function getAttachments(){
		global $phpmailer;

		$attachments_raw = $phpmailer->getAttachments();
		$attachments = [];

		if(!empty($attachments_raw)){
			// Handles multiple filenames
			/*if(!is_array($attachments_raw)){
				$attArray = explode(PHP_EOL, $attachments_raw);
			} else{
				$attArray = $attachments_raw;
			}*/

			foreach($attachments_raw as $attachment){
				$file_path = $attachment[0];
				if(file_exists($file_path) && is_file($file_path) && is_readable($file_path)){
					$file_content = file_get_contents($file_path);
					if(empty($file_content)){
						continue;
					}

					$attachments[] = [
						'filename' => basename($file_path),
						'mimetype' => $this->determineMimeContentType($file_path),
						'fileblob' => base64_encode($file_content)
					];
				}
			}
		}

		return $attachments;
	}
	
	protected function filterRecipientsArray($args){
		$recipients = [];
		foreach($args as $key => $recip){
			
			$recip = array_filter($recip);

			if(empty($recip) || ! filter_var($recip[0], FILTER_VALIDATE_EMAIL)){
				continue;
			}
			
			$recipients[] = $recip[0];
		}

		return $recipients;
	}

	protected function determineMimeContentType($filename){

		if(function_exists('mime_content_type')){
			return mime_content_type($filename);
		} elseif(function_exists('finfo_open')){
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime_type = finfo_file($finfo, $filename);
			finfo_close($finfo);
			return $mime_type;
		}

		return 'application/octet-stream';
	}

	public function load_field(){
		$options = $this->getMailerOption();

		$fields = [
			'api_key' => [
				'title' => __('API Key', 'gosmtp-pro'),
				'type' => 'password',
				'desc' => __('Follow this link to get an API Key from SMTP2Go: <a href="https://app-us.smtp2go.com/sending/apikeys/" target="_blank">Get API Key.</a>', 'gosmtp-pro'),
			]
		];

		return $fields;
	}
}