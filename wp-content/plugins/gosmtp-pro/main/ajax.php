<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

add_action('wp_ajax_gosmtp_get_log', 'gosmtp_get_log');
function gosmtp_get_log(){
	check_admin_referer( 'gosmtp_ajax' , 'gosmtp_nonce' );
	
	$logger = new GOSMTP\Logger();
	$id = gosmtp_optpost('id');
	
	if(empty($id)){
		$resp['error'] = __('Log ID Invalid!');
		gosmtp_json_output($resp);
	}
	
	$mail_data = $logger->get_logs('records', $id);
	
	if(empty($mail_data)){
		$resp['error'] = __('Records not found!');
		gosmtp_json_output($resp);
	}
	
	$mail = $mail_data[0];
	$tos = maybe_unserialize($mail->to);
	$attachments = maybe_unserialize($mail->attachments);
	$_attachments = array();
	$to_list = array();
	
	foreach($attachments as $key => $attachment){
		$_attachments[] = array(
			'Filename' => $attachment[1],
			'Content-Transfer-Encoding' => $attachment[3],
			'Content-Disposition' => $attachment[6],
			'Content-Type' => $attachment[4]
		);
	}

	$headers = maybe_unserialize($mail->headers);
	
	if(is_array($tos)){
		foreach($tos as $key => $to){
			$to_list[] = $to[0];
		}
	}else{
		$to_list[] = $tos;
	}
	
	$created_time = strtotime($mail->created_at);
	$created_at = date("M d, Y", $created_time).' at '. date('h:i A', $created_time);
	$backup_text = !empty($mail->parent_id) ? __('(Backup)') : '';

	$tmp = array(
		'id' => $mail->id,
		'to' => implode(',', $to_list),
		'from' => $mail->from,
		'subject' => $mail->subject,
		'source' => $mail->source,
		'status' => $mail->status == 'sent' ? __('Sent') : __('Failed'),
		'created' => $created_at,
		'headers' => gosmtp_header_format($headers, 'array', true),
		'attachments' => $_attachments,
		'body' => $mail->body,
		'provider' => !empty($mail->provider) ? ucfirst($mail->provider).' '.$backup_text : '',
		'response' => maybe_unserialize($mail->response)
	);
	
	$resp['response']['data'] = $tmp;

	gosmtp_json_output($resp);
}

add_action('wp_ajax_gosmtp_resend_mail','gosmtp_resend_mail');
function gosmtp_resend_mail(){
	check_admin_referer( 'gosmtp_ajax' , 'gosmtp_nonce' );
	
	$resp = array();
	$id = gosmtp_optpost('id');
	
	if(empty($id)){
		$resp['error'] = __('Log ID Invalid!');
		gosmtp_json_output($resp);
	}

	$mail_headers = array();
	$id = (int)gosmtp_optpost('id');
    
	$logger = new GOSMTP\Logger();
	$response = $logger->get_logs('records', $id);
 
	if(!isset($response[0])){
		$resp['error'] = __('Something Wents To Wrong!');
		gosmtp_json_output($resp);
	}
	
	$response = $response[0];
	$tos = maybe_unserialize($response->to);
	$subject = $response->subject;
	$attachments = maybe_unserialize($response->attachments);
	$_attachments = array();
	$tos_list = array();
	$body = $response->body;
	
	if(count($tos) > 0){
		foreach($tos as $key => $to){
			$tos_list[] = $to[0];
		}
	}

	if(isset($_POST['recipient_email'])){
		$tos_list = gosmtp_optpost('recipient_email');
	}

	if(count($attachments) > 0){
		foreach($attachments as $key => $attachment){
			$_attachments[] = $attachment[0];
		}
	}

	$headers = maybe_unserialize($response->headers);
	$headers = gosmtp_header_format($headers, 'text');

	$result = wp_mail($tos_list, $subject, $body, $headers, $_attachments);

	if(!$result){
		$resp['error'] = 'Unable to send mail!';
	}else{
		$resp['response'] = 'Message sent successfully!';
	}

	gosmtp_json_output($resp);
}

add_action('wp_ajax_gosmtp_delete_log', 'gosmtp_delete_log');
function gosmtp_delete_log(){

	check_admin_referer( 'gosmtp_ajax' , 'gosmtp_nonce' );
	
	$resp = array();
	$ids = gosmtp_optpost('id');
	
	if(empty($ids)){
		$resp['error'] = __('Log ID Invalid!');
		gosmtp_json_output($resp);
	}
	
	$logger = new GOSMTP\Logger();
	
	if(is_array($ids)){
		foreach($ids as $k => $id){
			$response = (int)$logger->delete_log($id);
			
			if(!empty($response)){
				continue;
			}
			
			$resp['error'] = __('Some logs have not been removed for some reason!');
		}

	}else{
		$response = $logger->delete_log((int)$ids);
	}
	
	if(!empty($resp['error'])){
		$resp['error'] = $resp['error'];
		gosmtp_json_output($resp);
	}
	
	if($response){
		$resp['response'] = __('Log Removed Successfully!');
	}else{
		$resp['error'] = __('Unable to Remove logs for some reason!');
	}

	gosmtp_json_output($resp);
}

// Miscellaneous
function gosmtp_header_format($headers, $output = 'text', $replace_chars = false){
	
	$heads = array();

	if(empty($headers) || count($headers) < 1){
		return $heads;
	}

	foreach($headers as $type => $header){
		
		switch($output){
			case 'text':
				$tmp_qry = $type.': ';
				
				if(is_array($header)){
					foreach($header as $k => $vals){
						$format = ($type != 'Reply-To' ? $vals[1].' <'.$vals[0].'>' : '<'.$vals[0].'>');

						if($replace_chars){
							$format = htmlspecialchars($format);
						}

						$tmp_qry .= $format;
					}
					
					$heads[] = $tmp_qry;
				}else{
					$heads[] = $tmp_qry.' '.$header;
				}
				
				break;
				
			default:
				$tmp_qry = [];
				
				if(is_array($header)){
					foreach($header as $k => $vals){
						$format = ($type != 'Reply-To' ? $vals[1].' <'.$vals[0].'>' : '<'.$vals[0].'>');

						if($replace_chars){
							$format = htmlspecialchars($format);
						}

						$tmp_qry[] = $format;
					}
					
					$heads[$type] = $tmp_qry;
				}else{
					$heads[$type] = $header;
				}
		}
	}

	return $heads;
}

add_action('wp_ajax_gosmtp_export_data', 'gosmtp_export_data');
function gosmtp_export_data(){

	check_admin_referer( 'gosmtp_ajax' , 'gosmtp_nonce' );

	$error = array();
	
	if(!class_exists('GOSMTP\Logger')){
		$error['error'] = __('logger class not found');
	}
	
	if(!current_user_can('activate_plugins')){
		$error['error'] = __('Permission Denied');
	}
	
	if(!empty($error)){
		$json_error = json_encode($error);
		header("x-error: $json_error");
		wp_die();
	}
	
	$common_info = gosmtp_optreq('common_information', array());
	$addtional_info = gosmtp_optreq('addtional_information', array());
	$export_key = explode(',', gosmtp_optreq('all_field'));

	if(!empty($_REQUEST['custom-field'])){
		$export_key = array_merge($common_info, $addtional_info);
	}

	// Assign all data in option array
	$options = array(
		'interval' => array(
			'start' => gosmtp_optreq('start-date'),
			'end' => gosmtp_optreq('end-date', date("Y-m-d"))
		), 
		'search' => gosmtp_optreq('search'),
		'multiselect' => gosmtp_optreq('search_type'),
		'pagination' => false,
	);
	
	$logger = new GOSMTP\Logger();
	$email_logs = $logger->get_logs('records', 0, $options);
	$custom_data = array($export_key);
	
 	if(empty($email_logs)){
		$error['error'] = __('No Data Found');
		$json_error = json_encode($error);
		header("x-error: $json_error");
		wp_die();
	}
	
	$export_data = [];

	foreach($email_logs as $val){
		
		$temp_array = [];
		
		foreach($val as $inner_key => $inner_val){
			
			$_data = maybe_unserialize($inner_val);
			
			if($inner_key == 'headers' && is_array($_data) ){
				
				foreach($_data as $header_key => $header_val){
	
					if(!in_array(strtolower($header_key), $export_key)){
						continue;
					}
					
					if(is_array($header_val)){
						$header_list = [];
						foreach($header_val as $header_inner_val){
							$header_list[] = $header_inner_val[0];
						}
						$header_val = implode(', ', $header_list);
					}
					
					$temp_array[array_search(strtolower($header_key), $export_key, true)] = $header_val;
				}
				
				continue;
			}
			
			if(!in_array($inner_key, $export_key)){
				continue;
			}
			
			// Is array?
			if(is_array($_data)){
				
				$unerialize_list = [];
				foreach($_data as $unserialize_keys => $unserialize_val){
					switch ($inner_key) {
						case 'to':
							$unerialize_list[] = $unserialize_val[0];
						  break;

						case 'response':
							if($unserialize_keys == 'message'){
								if(is_array($unserialize_val)){
									$unserialize_val = implode(', ', $unserialize_val);
								}
								$unerialize_list[] = $unserialize_val;
							}
							break;

						case 'attachments':

							if(is_array($unserialize_val)){
								$unserialize_val = implode('*', $unserialize_val);
							}

							$unerialize_list[] = $unserialize_val;
							break;

						default:
							$unerialize_list[] = $unserialize_val;
					  }
				}

				$inner_val = implode(', ', $unerialize_list);
			}
			
			$temp_array[array_search($inner_key, $export_key, true)] = $inner_val;
	
		}
		
		ksort($temp_array);
		array_push($custom_data, $temp_array);
	}

	// Export format
	$func = 'gosmtp_export_'. gosmtp_optreq('format', 'csv');
	
	include_once GOSMTP_PRO_DIR .'/main/export.php';
	
	if(!function_exists($func) || !count($custom_data)>1){
		$error['error'] = __('No Data Found Or '.$func.' function is not defined');
		$json_error = json_encode($error);
		header("x-error: $json_error");
		wp_die();
	}
	
	$func($custom_data);
	wp_die();
}

add_action('wp_ajax_gosmtp_pro_version_notice', 'gosmtp_pro_version_notice');
function gosmtp_pro_version_notice(){
	check_admin_referer('gosmtp_version_notice', 'security');

	if(!current_user_can('activate_plugins')){
		wp_send_json_error(__('You do not have required access to do this action', 'gosmtp-pro'));
	}
	
	$type = '';
	if(!empty($_REQUEST['type'])){
		$type = sanitize_text_field(wp_unslash($_REQUEST['type']));
	}

	if(empty($type)){
		wp_send_json_error(__('Unknow version difference type', 'gosmtp-pro'));
	}
	
	update_option('gosmtp_version_'. $type .'_nag', time() + WEEK_IN_SECONDS);
	wp_send_json_success();
}

add_action('wp_ajax_gosmtp_pro_dismiss_expired_licenses', 'gosmtp_pro_dismiss_expired_licenses');
function gosmtp_pro_dismiss_expired_licenses(){
	check_admin_referer('gosmtp_expiry_notice', 'security');

	if(!current_user_can('activate_plugins')){
		wp_send_json_error(__('You do not have required access to do this action', 'gosmtp-pro'));
	}

	update_option('softaculous_expired_licenses', time());
	wp_send_json_success();
}
