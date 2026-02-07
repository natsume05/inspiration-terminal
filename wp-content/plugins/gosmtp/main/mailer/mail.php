<?php
/**
 * Class GOSMTP_Mailer_Mail.
 *
 * @since 1.0.0
 */
 
namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class Mail extends Loader{
	
	var $title = 'Default';
	var $mailer = 'mail';
	
	/**
	 * Override default mail send function.
	 * @since 1.0.0
	 */
	public function send() {
		global $phpmailer;
		
		$phpmailer->isMail();

		if($phpmailer->preSend()){
		    
			try{
				if($phpmailer->postSend()){
					$response = [
						'status' => true,
						'code' => 200,
						'messageId' => '',
						'message' => 'Mail sent successfully',
					];
					
					return $this->handle_response($response);
				}
			}catch( \Exception $e ){
				return $this->handle_response(new \WP_Error(400,  $e->getMessage(), []));
			}

		}
		
		return $this->handle_response(new \WP_Error(400, 'Unable to send mail for some reason!', []));
	}
}
