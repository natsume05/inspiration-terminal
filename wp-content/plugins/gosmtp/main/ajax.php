<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

if(!defined('GOSMTP_VERSION')){
	die('Hacking Attempt!');
}

add_action('wp_ajax_gosmtp_test_mail', 'gosmtp_test_mail');
function gosmtp_test_mail(){
	
	global $phpmailer;

	// Check nonce
	check_admin_referer( 'gosmtp_ajax' , 'gosmtp_nonce' );

	$to = gosmtp_optpost('reciever_test_email');
	$subject = gosmtp_optpost('smtp_test_subject');
	$body = gosmtp_optpost('smtp_test_message');
	$use_template = gosmtp_optpost('use_html_template');
	
	// TODO: send debug param
	if(isset($_GET['debug'])){
		// show wp_mail() errors
		add_action( 'wp_mail_failed', function( $wp_error ){
			echo "<pre>";
			print_r($wp_error);
			echo "</pre>";
		}, 10, 1 );
	}
	
	$msg = array();
	$headers = [];
	
	// TODO check for mailer
	if(!get_option('gosmtp_options')){
		$msg['error'] = __('You have not configured SMTP settings yet !', 'gosmtp');
	}else{
		
		if(!empty($use_template) && function_exists('gosmtp_pro_test_html_template')){
			$body = gosmtp_pro_test_html_template();
			$headers = ['Content-Type: text/html; charset=UTF-8'];
		}

		$result = wp_mail($to, $subject, $body, $headers);

		if(!$result){
			$msg['error'] = __('Unable to send mail !', 'gosmtp').(empty($phpmailer->ErrorInfo) ? '' : ' '.__('Error : ', 'gosmtp').$phpmailer->ErrorInfo);
		}else{
			$msg['response'] = __('Message sent successfully !', 'gosmtp');
		}
	}
	
	gosmtp_json_output($msg);
}

function gosmtp_close_update_notice(){

	if(!wp_verify_nonce($_GET['security'], 'gosmtp_promo_nonce')){
		wp_send_json_error('Security Check failed!');
	}

	if(!current_user_can('manage_options')){
		wp_send_json_error('You don\'t have privilege to close this notice!');
	}

	$plugin_update_notice = get_option('softaculous_plugin_update_notice', []);
	$available_update_list = get_site_transient('update_plugins');
	$to_update_plugins = apply_filters('softaculous_plugin_update_notice', []);

	if(empty($available_update_list) || empty($available_update_list->response)){
		return;
	}

	foreach($to_update_plugins as $plugin_path => $plugin_name){
		if(isset($available_update_list->response[$plugin_path])){
			$plugin_update_notice[$plugin_path] = $available_update_list->response[$plugin_path]->new_version;
		}
	}

	update_option('softaculous_plugin_update_notice', $plugin_update_notice);
}
add_action('wp_ajax_gosmtp_close_update_notice', 'gosmtp_close_update_notice');

