<?php
/**
 * Class GOSMTP_Mailer_SMTP.
 *
 * @since 1.0.0
 */
 
namespace GOSMTP\Mailer;
 
use GOSMTP\Mailer\Loader;

class SMTP extends Loader{
	
	var $title = 'Other SMTP';
	var $mailer = 'smtp';
	
	/**
	 * Override default mail send function.
	 * @since 1.0.0
	 */
	public function send(){
		global $phpmailer;
		
		$phpmailer->isSMTP();
		
		$encryption = $this->getOption('encryption', $this->mailer);
		
		if ( !empty($encryption) && 'none' !== $encryption) {
			$phpmailer->SMTPSecure = $encryption;
		}

		// Set the other options
		$phpmailer->Host = $this->getOption('smtp_host', $this->mailer);
		$phpmailer->Port = $this->getOption('smtp_port', $this->mailer);

		// If we're using smtp auth, set the username & password
		$smtp_auth = $this->getOption('smtp_auth', $this->mailer);
		if(!empty($smtp_auth) && $smtp_auth == 'Yes'){
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $this->getOption('smtp_username', $this->mailer);
			$phpmailer->Password = $this->getOption('smtp_password', $this->mailer);
		}
		
		//PHPMailer 5.2.10 introduced this option. However, this might cause issues if the server is advertising TLS with an invalid certificate.
		$phpmailer->SMTPAutoTLS = false;
		
		$ssl_verification = $this->getOption('disable_ssl_verification', $this->mailer);
		
		if(!empty( $ssl_verification )) {
			// Insecure SSL option enabled
			$phpmailer->SMTPOptions = array(
				'ssl' => array(
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'allow_self_signed' => true,
				),
			);
		}

		//set reasonable timeout
		$phpmailer->Timeout = 10;
		
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
	
	public function load_field(){
		
		$fields = array(
			'smtp_host' => array(
				'title' => __('SMTP Host', 'gosmtp'),
				'type' => 'text',
				'place_holder' => 'smtp.example.com',
				'desc' => __( 'Your mail server', 'gosmtp' ),
			),
			'encryption' => array(
				'title' => __('Type of Encryption', 'gosmtp'),
				'type' => 'radio',
				'desc' => __( 'For most servers TLS is the recommended option. If your SMTP provider offers both SSL and TLS options, we recommend using TLS.', 'gosmtp' ),
				'list' => array(
					'none' => 'None',
					'ssl' => 'SSL',
					'tls' => 'TLS',	
				),
			),
			'smtp_port' => array(
				'title' => __('SMTP Port', 'gosmtp'),
				'type' => 'text',
				'place_holder' => '465',
				'desc' => __( 'The port to your mail server', 'gosmtp' ),
			),
			'smtp_auth' => array(
				'title' => __('SMTP Authentication', 'gosmtp'),
				'type' => 'radio',
				'desc' => __( 'This options should always be checked Yes', 'gosmtp' ),
				'list' => array(
					'No' => 'No',
					'Yes' => 'Yes',
				),
			),
			'smtp_username' => array(
				'title' => __('SMTP Username', 'gosmtp'),
				'type' => 'text',
				'place_holder' => 'admin',
				'tr_class' => 'smtp-authentication',
				'desc' => __( 'The username to login to your mail server', 'gosmtp'),
			),
			'smtp_password' => array(
				'title' => __('SMTP Password', 'gosmtp'),
				'type' => 'password',
				'place_holder' => 'Password',
				'tr_class' => 'smtp-authentication',
				'desc' => __( 'The SMTP Password to login to your mail server. The saved password is not shown for security reasons. You need enter it every time you update the settings.', 'gosmtp'),
			),
			'disable_ssl_verification' => array(
				'title' => __('Disable SSL Certificate Verification', 'gosmtp'),
				'type' => 'checkbox',
				'desc' => __( 'As of PHP 5.6 you will get a warning/error if the SSL certificate on the server is not properly configured. You can check this option to disable that default behaviour. Please note that PHP 5.6 made this change for a good reason. So you should get your host to fix the SSL configurations instead of bypassing it', 'gosmtp'),
			),
		);
		
		return $fields;
	}
}
