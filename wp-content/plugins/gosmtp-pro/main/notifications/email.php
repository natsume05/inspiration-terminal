<?php

namespace GOSMTP\Notifications;

use GOSMTP\Notifications\Manager;

class Email extends Manager{
	
	var $title = 'Email';
	var $service = 'email';

	public function send($message){
		
		global $phpmailer;

		$to_email = $this->get_option('email', $this->service);

		if(empty($to_email)) return false;

		$from_email = !empty($phpmailer->From) ? $phpmailer->From : get_option('admin_email');
		$subject = site_url().': GoSMTP Email Error';
		$headers = "From: GoSMTP Alerts <$from_email>\r\n";
  		$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

		$message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

		return mail($to_email, $subject, $message, $headers);
	}

	public function load_services_field(){

		$fields = [
			'email' => [
				'title' => __('Email Address', 'gosmtp-pro'),
				'type' => 'email',
				'desc' => sprintf(__('Enter your Email Address.%1$s%2$sFollow this link for guide.%3$s', 'gosmtp-pro'),'<br>',
				'<a href="https://gosmtp.net/docs/notifications/email" target="_blank">','</a>')
			]
		];

		return $fields;
	}
} 