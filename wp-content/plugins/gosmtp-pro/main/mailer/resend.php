<?php
/**
 * Class GOSMTP_Mailer_Resend.
 *
 * @since 1.1.7
 */

namespace GOSMTP\Mailer;

use Exception;
use GOSMTP\Mailer\Loader;

class Resend extends Loader{

	var $title = 'Resend';
	var $mailer = 'resend';
	var $url = 'https://api.resend.com/emails';

	public function send(){
		global $phpmailer;

		if($phpmailer->preSend()){
			$response = $this->postSend();
			return $this->handle_response($response);
		}

		return $this->handle_response(new \WP_Error(400, __('Unable to send mail for some reason!', 'gosmtp-pro'), []));
	}

	protected function postSend(){
		global $phpmailer;

		try{
			$api_key = $this->getOption('api_key', $this->mailer);

			if(empty($api_key)){
				return new \WP_Error(401, __('Resend API Key is missing.', 'gosmtp-pro'));
			}

			//Resend requires subject to be passed
			$subject = $phpmailer->Subject;
			if(empty($subject)){
				$subject = 'Mail sent from '. site_url();
			}

			$from = $phpmailer->From;
			$from_name = $phpmailer->FromName;

			// Prepare Resend API JSON Payload
			$payload = [
				'from' => $from_name . '<' .$from. '>',
				'to' => $this->filterRecipientsArray($phpmailer->getToAddresses()),
				'cc' => $this->filterRecipientsArray($phpmailer->getCcAddresses()),
				'bcc' => $this->filterRecipientsArray($phpmailer->getBccAddresses()),
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

			if(200 === $resp_code){
				$msg_id = isset($resp_body['id']) ? $resp_body['id'] : '';
				$status = __('Email sent successfully', 'gosmtp-pro');
				$return_response = [
					'status' => true,
					'code' => $resp_code,
					'messageId' => $msg_id,
					'message' => $status 
				];
			} else {
				$err_code = $resp_code;
				$error_text = [''];

				if(!empty($resp_body) && is_array($resp_body)){
					$message = $resp_body['message'];
					$code = isset($resp_body['statusCode']) ? $resp_body['statusCode'] : '';

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
		return [
			'Authorization' => 'Bearer ' . $this->getOption('api_key', $this->mailer),
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
						'content_type' => $this->determineMimeContentType($file_path)
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
				'desc' => __('Follow this link to get an API Key from Resend: <a href="https://resend.com/api-keys/" target="_blank">Get API Key.</a>', 'gosmtp-pro'),
			]
		];

		return $fields;
	}
}