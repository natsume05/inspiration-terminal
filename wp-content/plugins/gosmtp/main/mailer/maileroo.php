<?php
/**
 * Class GOSMTP_Mailer_Maileroo.
 *
 * @since 1.1.5
 */

namespace GOSMTP\Mailer;

use GOSMTP\Mailer\Loader;

class Maileroo extends Loader{

	var $title = 'Maileroo';
	var $mailer = 'maileroo';
	var $url = 'https://smtp.maileroo.com/api/v2/emails';

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
				return new \WP_Error(401, 'Maileroo Sending Key is missing');
			}

			// Maileroo requires subject to be passed
			$subject = $phpmailer->Subject;
			if(empty($subject)){
				$subject = 'Mail sent from: '. site_url();
			}

			//Prepare Maileroo v2 API JSON Payload
			$payload = [
				'from' => [
					'address' => $phpmailer->From,
					'display_name' => $phpmailer->FromName,
				],
				'to' => [],
				'subject' => $subject,
				'text' => '',
				'html' => '',
				'attachments' => $this->getAttachments(),
			];

			foreach($this->filterRecipientsArray($phpmailer->getToAddresses()) as $to){
				$payload['to'][] = ['address' => $to];
			}

			foreach($this->filterRecipientsArray($phpmailer->getCcAddresses()) as $cc){
				$payload['cc'][] = ['address' => $cc];
			}

			foreach($this->filterRecipientsArray($phpmailer->getBccAddresses()) as $bcc){
				$payload['bcc'][] = ['address' => $bcc];
			}

			foreach($this->filterRecipientsArray($phpmailer->getReplyToAddresses()) as $replyTo){
				$payload['reply_to'][] = ['address' => $replyTo];
			}
    
			if(!empty($phpmailer->AltBody)){
				$payload['text'] = $phpmailer->AltBody;
			}

			if(!empty($phpmailer->Body)){
				$payload['html'] = $phpmailer->Body;
			}

			$params = [
				'method'  => 'POST',
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
				$msgId = isset($resp_body['data']['reference_id']) ? $resp_body['data']['reference_id'] : '';
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
		} catch(\Exception $e){
			return new \WP_Error(423, $e->getMessage(), []);
		}

		return $return_response;
	}

	protected function getRequestHeaders(){
		return [
			'X-Api-Key' => $this->getOption('api_key', $this->mailer),
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
						'file_name' => basename($file_path),
						'content_type' => $this->determineMimeContentType($file_path),
						'content' => base64_encode($file_content)
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
				'title' => __('Sending Key', 'gosmtp'),
				'type' => 'password',
				'desc' => sprintf(__('Follow this link to get a Sending Key from Maileroo: %1$sGet Sending Key%2$s%3$s
						Special Offer: Get 7,000 emails/month for first 4 months! %4$sClaim Offer%5$s', 'gosmtp'),
						'<a href="https://app.maileroo.com/domains/" target="_blank">', '</a>', '<br>', '<a href="https://maileroo.com/?r=gosmtp" target="_blank">', '</a>'
					),
			]
		];

		return $fields;
	}
}