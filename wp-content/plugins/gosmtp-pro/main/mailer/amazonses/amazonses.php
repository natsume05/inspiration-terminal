<?php
/**
 * Class GOSMTP_Mailer_AmazonSES.
 *
 * @since 1.0.0
 */

namespace GOSMTP\Mailer\AmazonSES;
 
use GOSMTP\Mailer\Loader;
use GOSMTP\mailer\amazonses\EmailService;
use GOSMTP\mailer\amazonses\EmailServiceMessage;

class AmazonSES extends Loader{

	var $title = 'AmazonSES';
	var $mailer = 'amazonses';
	var $client = null;
	
	public function send(){
		global $phpmailer;

		if ($phpmailer->preSend()) {
	
		 	$response = $this->postSend();
		 	return $this->handle_response( $response );
		}	
		
		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}

	public function postSend(){
		global $phpmailer;
			
		$mime = chunk_split(base64_encode($phpmailer->getSentMIMEMessage()), 76, "\n");
		
		$options = $this->getMailerOption();
		
		$region = 'email.' . $options['region'] . '.amazonaws.com';

		$ses = new EmailService($options['access_key'], $options['secret_key'], $region, false);
		
		$response = $ses->sendRawEmail($mime); 
		
		if(is_wp_error($response)){
			$return_response = new \WP_Error($response->get_error_code(), $response->get_error_message(), $response->get_error_messages());
		}else{
			$resp_body = wp_remote_retrieve_body($response);
			$resp_code = wp_remote_retrieve_response_code($response);
			$resp_body = \json_decode($resp_body, true);
				
			if(!empty($response['MessageId'])){
				$msgId = $response['MessageId'];
				$status = __('Email sent successfully');
				$return_response = [
					'status' => true,
					'code' => 200,
					'messageId' => $msgId,
					'message' => $status
				];	
			}else{
				$err_code = $resp_code;
				$error_text = [''];
				if( ! empty( $resp_body['error'] ) && is_array( $resp_body['error'] ) ){
					
					$message = $resp_body['error']['message'];
						
					$error_text[] = $this->message_formatting( $message, $code );
				}else{
					$error_text[] = $this->get_response_error_message($response);
				}
    			
				$error_msg = implode( '\r\n', array_map( 'esc_textarea', array_filter( $error_text ) ) );
				$return_response = new \WP_Error($err_code, $error_msg, $resp_body);
			}
		}
			
		return $return_response;
		
	}

	public function email_checker($data){
		
		$region= 'email.' . $data['amazonses']['region'] . '.amazonaws.com';

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
		});
			
		$ses = new EmailService($data['amazonses']['access_key'], $data['amazonses']['secret_key'], $region, false);

		try{
			$ses->listVerifiedEmailAddresses();
		}catch(\Exception $e) {
			return new \WP_Error(423, $e->getMessage());
		}

		return false;
	}
	
	public function load_field(){
		
		$fields = array(
			'access_key' => array(
				'title' => __('Access Key'),
				'type' => 'password',
			),
			'secret_key' => array(
				'title' => __('Secret Key'),
				'type' => 'password',
				'desc' => __( 'Follow this link to get a Secret Key from AmazonSES: <a href="https://aws.amazon.com/blogs/security/wheres-my-secret-access-key/" target="_blank">Secret Key.</a>' ),
			),
			'region' => array(
				'title' => __('Region'),
				'type' => 'select',
				'list' => array(
					'us-east-1'	  => __('US East (N. Virginia)', 'gosmtp'),
					'us-east-2'	  => __('US East (Ohio)', 'gosmtp'),
					'us-west-1'	  => __('US West (N. California)', 'gosmtp'),
					'us-west-2'	  => __('US West (Oregon)', 'gosmtp'),
					'ca-central-1'   => __('Canada (Central)', 'gosmtp'),
					'eu-west-1'	  => __('EU (Ireland)', 'gosmtp'),
					'eu-west-2'	  => __('EU (London)', 'gosmtp'),
					'eu-west-3'	  => __('Europe (Paris)', 'gosmtp'),
					'eu-central-1'   => __('EU (Frankfurt)', 'gosmtp'),
					'eu-south-1'	 => __('Europe (Milan)', 'gosmtp'),
					'eu-north-1'	 => __('Europe (Stockholm)', 'gosmtp'),
					'ap-south-1'	 => __('Asia Pacific (Mumbai)', 'gosmtp'),
					'ap-northeast-2' => __('Asia Pacific (Seoul)', 'gosmtp'),
					'ap-southeast-1' => __('Asia Pacific (Singapore)', 'gosmtp'),
					'ap-southeast-2' => __('Asia Pacific (Sydney)', 'gosmtp'),
					'ap-northeast-1' => __('Asia Pacific (Tokyo)', 'gosmtp'),
					'sa-east-1'	  => __('South America (Sao Paulo)', 'gosmtp'),
					'me-south-1'	 => __('Middle East (Bahrain)', 'gosmtp'),
					'us-gov-west-1'  => __('AWS GovCloud (US)', 'gosmtp'),
					'af-south-1'	 => __('Africa (Cape Town)', 'gosmtp'),
					'cn-northwest-1' => __('China (Ningxia)', 'gosmtp')
				),
				'desc' => __( 'Define which endpoint you want to use for sending messages.<br>If you are operating under EU laws, you may be required to use EU region. <a href="https://aws.amazon.com/about-aws/global-infrastructure/regions_az/" target="_blank">More information</a> on aws.amazon.com.' ),
			)
		);
		
		return $fields;
	}
}
