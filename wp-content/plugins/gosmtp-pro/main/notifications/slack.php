<?php

namespace GOSMTP\Notifications;

use GOSMTP\Notifications\Manager;

class Slack extends Manager{

	var $title = 'Slack';
	var $service = 'slack';

	public function send($message){

		$webhook_url = $this->get_option('webhook_url', $this->service);

		if(empty($webhook_url)) return false;

		$headers = [
			'content-type' => 'application/json'
		];

		$message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

		$body = [
			'text' => $message
		];

		$args = [
			'headers' =>  $headers,
			'body' => json_encode($body)
		];

		return wp_remote_post($webhook_url, $args);
	}
	
	public function load_services_field(){

		$fields = [
			'webhook_url' => [
				'title' => __('Slack Webhook URL', 'gosmtp-pro'),
				'type' => 'password',
				'desc' => sprintf(__('Enter your Slack Webhook URL. %1$s%2$sFollow this link for guide.%3$s', 'gosmtp-pro'),
				'<br>', '<a href="https://gosmtp.net/docs/notifications/slack" target="_blank">', '</a>')
			]
		];

		return $fields;
	}
}