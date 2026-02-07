<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT');
}

function gosmtp_pro_check_failure_and_notify($is_sent, $exception, $backup_sent){
	global $gosmtp;

	if($is_sent){
		return;
	}

	$active_service_key = isset($gosmtp->options['notifications']['notification_service']) ? $gosmtp->options['notifications']['notification_service'] : '';
	$enabled = !empty($gosmtp->options['notifications']['notifications_enabled']);

	if(empty($active_service_key) || empty($enabled)){
		return;
	}

	$all_services = gosmtp_pro_load_notifications_service_list();

	if(isset($all_services[$active_service_key])){
		$service = $all_services[$active_service_key];

		// Load the specific saved options for this service (Slack URL, Email, etc.)
		if(method_exists($service, 'load_options')){
			$service->load_options();
		}

		$error_message = $exception ? $exception->getMessage() : 'Unknown Error';

		$message = "GoSMTP Alert: Email Delivery Failed.\n\n";
		$message .= "Your website failed to send an email.\n\n";
		$message .= "Domain: ".site_url()."\n";
		$message .= "Error Details: " . $error_message;

		if(method_exists($service, 'send')){
			$service->send($message);
		}
	}
}