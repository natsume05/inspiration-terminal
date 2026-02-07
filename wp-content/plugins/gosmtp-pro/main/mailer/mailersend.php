<?php
/**
	* Class GOSMTP_Mailer_MailerSend.
	*
	* @since 1.1.7
 */

namespace GOSMTP\Mailer;

use GOSMTP\Mailer\Loader;

class MailerSend extends Loader{

	var $title = 'MailerSend';
	var $mailer = 'mailersend';
	var $url = 'https://api.mailersend.com/v1/email';

	public function send(){
		global $phpmailer;

		if($phpmailer->preSend()){
			$response = $this->postSend();
			return $this->handle_response($response);
		}

		return $this->handle_response(new \WP_Error(400, __('Unable to send for some reason!', 'gosmtp-pro'), []));
	}

	protected function postSend(){
		global $phpmailer;

		try{
			$api_token = $this->getOption('api_token', $this->mailer);

			if(empty($api_token)){
				return new \WP_Error(401, __('MailerSend API Token is missing', 'gosmtp-pro'));
			}

			// MailerSend requires subject to be passed
			$subject = $phpmailer->Subject;
			if(empty($subject)){
				$subject = 'Email sent from '. site_url();
			}

			$from = [
				'email' => $phpmailer->From,
				'name' => $phpmailer->FromName
			];

			$to = $this->filterRecipientsArray($phpmailer->getToAddresses());

			$cc = $this->filterRecipientsArray($phpmailer->getCcAddresses());

			$bcc = $this->filterRecipientsArray($phpmailer->getBccAddresses());

			// Prepare MailerSend API JSON Payload
			$payload = [
				'from' => $from,
				'to' => $to,
				'cc' => $cc,
				'bcc' => $bcc,
				'subject' => $subject,
				'text' => '',
				'html' => '',
				'attachments' => $this->getAttachments()
			];

			if(!empty($phpmailer->AltBody)){
				$payload['text'] = $phpmailer->AltBody;
			}

			if(!empty($phpmailer->Body)){
				$payload['html'] = $phpmailer->Body;
			}

			$params = [
				'method' => 'POST',
				'headers' => $this->getRequestHeaders(),
				'body' => wp_json_encode($payload)
			];

			$response = wp_safe_remote_request($this->url, $params);

			if(is_wp_error($response)){
				return new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
			}

			$resp_body = wp_remote_retrieve_body($response);
			$resp_code = wp_remote_retrieve_response_code($response);
			$resp_body = \json_decode($resp_body, true);

			if(202 === $resp_code){
				$msg_id = wp_remote_retrieve_header($response, 'x-message-id');
				$status = 'Email sent successfully';
				$return_response = [
					'status' => true,
					'code' => $resp_code,
					'messageId' => $msg_id,
					'message' => $status
				];
			} else{
				$err_code = $resp_code;
				$error_text = [''];

				if(!empty($resp_body) && is_array($resp_body)){
					$message = $resp_body['message'];
					$code = isset($response['status_code']) ? $resp_body['status_code'] : '';

					$error_text[] = $this->message_formatting($message, $code);
				} else{
					$error_text[] = $this->get_response_error_message($response);
				}

				$error_msg = implode('\r\n', array_map('esc_textarea', array_filter($error_text)));
				$return_response = new \WP_Error($err_code, $error_msg, $resp_body);
			}
		} catch(Exception $e){
			return new \WP_Error(423, $e->getMessage(), []);
		}

		return $return_response;
	}

	protected function getRequestHeaders(){
		return[
			'Authorization' => 'Bearer ' . $this->getOption('api_token', $this->mailer),
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
			foreach($attachments_raw as $attachment){
				$file_path = $attachment[0];

				if(file_exists($file_path) && is_file($file_path) && is_readable($file_path)){
					$file_content = file_get_contents($file_path);

					if(empty($file_content)){
						continue;
					}

					$attachments[] = [
						'content' => base64_encode($file_content),
						'filename' => basename($file_path),
						'type' => $this->determineMimeContentType($file_path),
						'diposition' => 'attachment'
					];
				}
			}
		}

		return $attachments;
	}

	// Filter Recipients Array as suitable for MailerSend (Array of Objects)
	protected function filterRecipientsArray($args){
		$recipients = [];
		foreach($args as $recip){
			
			$recip = array_filter($recip);

			if(empty($recip) || ! filter_var($recip[0], FILTER_VALIDATE_EMAIL)){
				continue;
			}

			$entry = ['email' => $recip[0]];

			if(!empty($recip[1])){
				$entry['name'] = $recip[1];
			}
			
			$recipients[] = $entry;
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
			'api_token' => [
				'title' => __('API Token', 'gosmtp-pro'),
				'type' => 'password',
				'desc' => __('Follow this link to get an API Token from MailerSend: <a href="https://app.mailersend.com/api-tokens/" target="_blank">Get API Token.</a>', 'gosmtp-pro'),
			]
		];

		return $fields;
	}
}