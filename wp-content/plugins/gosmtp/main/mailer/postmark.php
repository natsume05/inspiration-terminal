<?php
/**
 * Class GOSMTP_Mailer_Postmark.
 *
 * @since 1.0.0
 */
 
namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class Postmark extends Loader{

	var $title = 'Postmark';
	var $mailer = 'postmark';
	var $url = 'https://api.postmarkapp.com/email';

	public function send(){
		global $phpmailer;

		$phpmailer->isMail();

		if ($phpmailer->preSend()) {
			$return_response = $this->postSend();
			return $this->handle_response( $return_response );
		}

		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}

	public function postSend(){
		global $phpmailer;

		$body = array(
			'From' => $phpmailer->From,
			'To' => $this->getRecipients($phpmailer->getToAddresses()),
			'Subject' => $phpmailer->Subject,
		);
		
		$message_id = $this->getOption('message_stream_id', $this->mailer);
		
		if(!empty($message_id)){
			$body['MessageStream'] = $message_id;
		}
		
		if($replyTo = $this->getRecipients($phpmailer->getReplyToAddresses())){
			$body['ReplyTo'] = $replyTo;
		}

		if($bcc = $this->getRecipients($phpmailer->getBccAddresses())){
			$body['Bcc'] = $bcc;
		}

		if($cc = $this->getRecipients($phpmailer->getCcAddresses())){
			$body['Cc'] = $cc;
		}

		if($phpmailer->ContentType == 'text/plain'){
			$body['TextBody'] = $phpmailer->Body;
		} else {
			$body['HtmlBody'] = $phpmailer->Body;

			// TODO: create stting and if is true then set this to true
			$body['TrackOpens'] = true;

			// TODO: create stting and if is true then set this to HtmlOnly
			$body['TrackLinks'] = 'HtmlOnly';
		}

		if(!empty($phpmailer->AltBody)){
			$body['TextBody'] = $phpmailer->AltBody;
		}

		if(!empty($phpmailer->getAttachments())){
			$body['Attachments'] = $this->getAttachments();
		}

		// Handle apostrophes in email address From names by escaping them for the Postmark API.
		$from_regex = "/(\"From\": \"[a-zA-Z\\d]+)*[\\\\]{2,}'/";

		$args = array(
			'headers' => $this->getRequestHeaders(),
			'body' => preg_replace($from_regex, "'", wp_json_encode($body), 1),
		);

		$response = wp_remote_post($this->url, $args);

		if(is_wp_error($response)){
			$return_response = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
		}else{
			$responseBody = wp_remote_retrieve_body($response);
			$responseCode = wp_remote_retrieve_response_code($response);

			$responseBody = \json_decode($responseBody, true);

			if($responseCode == 200){
				$return_response = [
					'status' => true,
					'code' => $responseCode,
					'id' => $responseBody['MessageID'],
					'message' => $responseBody['Message'],
				];
			}else{
				$error_text = [''];
				if(!empty( $responseBody['Message'])){
					$message = $responseBody['Message'];
					$code = ! empty( $responseBody['ErrorCode'] ) ? $responseBody['ErrorCode'] : '';
					
					$error_text[] = $this->message_formatting( $message, $code );
				}else{
					$error_text[] = $this->get_response_error_message($response);
				}
				
				$error_msg = implode( '\r\n', array_map( 'esc_textarea', array_filter( $error_text ) ) );
				$return_response = new \WP_Error($responseCode, $error_msg, $responseBody);
			}
		}

		return $return_response ;
	}

	public function getRecipients($recipient){
		$recipients = $this->filterRecipientsArray($recipient);

		$array = array_map(function($recipient){
			return isset($recipient['name'])
			? $recipient['name'] . ' <' . $recipient['address'] . '>'
			: $recipient['address'];
			},
			$recipients
		);

		return implode(', ', $array);
	}

	function getRequestHeaders(){
		return array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
			'X-Postmark-Server-Token' => $this->getOption('server_api_token', $this->mailer),
		);
	}

	protected function getAttachments(){
		global $phpmailer;

		$data = [];
		$attachments = $phpmailer->getAttachments();

		foreach($attachments as $attachment){
			$file = false;

			try{
				if(is_file($attachment[0]) && is_readable($attachment[0])){
					$fileName = basename($attachment[0]);
					$file = file_get_contents($attachment[0]);
				}
			}catch(\Exception $e){
				$file = false;
			}

			if($file === false){
				continue;
			}

			$data[] = array(
				'Name'        => $fileName,
				'Content'     => base64_encode($file),
				'ContentType' => $this->determineMimeContentRype($attachment[0])
			);
		}

		return $data;
	}

	protected function determineMimeContentRype($filename){

		if(function_exists('mime_content_type')){
			return mime_content_type($filename);
		}elseif(function_exists('finfo_open')){
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime_type = finfo_file($finfo, $filename);
			finfo_close($finfo);
			return $mime_type;
		}

		return 'application/octet-stream';
	}

	public function load_field(){

		$fields = array(
			'server_api_token' => array(
				'title' => __('Server API Token', 'gosmtp'),
				'type' => 'password',
				'desc' => __( 'Follow this link to get a Server API Token from Postmark: <a href="https://account.postmarkapp.com/login" target="_blank">Get Server API Token.</a>', 'gosmtp' ),
			),
			'message_stream_id' => array(
				'title' => __('Message Stream ID', 'gosmtp'),
				'type' => 'text',
				'desc' => __( 'Follow this link to get a Server API Token from Postmark: <a href="https://account.postmarkapp.com/login" target="_blank">Get Server API Token.</a>', 'gosmtp' ),
			),
		);
		
		return $fields;
	}
}