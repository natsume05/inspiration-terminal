<?php

namespace GOSMTP\Notifications;

use GOSMTP\Notifications\Manager;

class Discord extends Manager{

	var $title = 'Discord';
	var $service = 'discord';

	public function send($message){

		$webhook_url = $this->get_option('webhook_url', $this->service);

		if(empty($webhook_url)) return;

	 	$message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

		$body = [
			'content' => $message
		];

		$headers = [
			'content-type' => 'application/json'
		];

		return wp_remote_post($webhook_url, [
			'body' => json_encode($body),
			'headers' => $headers
		]);
	}
	
	public function load_services_field(){

		$fields = [
			'webhook_url' => [
				'title' => __('Discord Webhook URL', 'gosmtp-pro'),
				'type' => 'password',
				'desc' => sprintf(__('Enter your Discord Webhook URL.%1$s%2$sFollow this link for guide.%3$s', 'gosmtp-pro'),'<br>',
				'<a href="https://gosmtp.net/docs/notifications/discord/" target="_blank">','</a>')
			]
		];

		return $fields;
	}
}
?>