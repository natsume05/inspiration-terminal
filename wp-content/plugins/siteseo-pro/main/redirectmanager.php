<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class RedirectManager{
	
	static function handle_404_request(){
		global $siteseo, $wpdb;
		
		// Toggle
		if(empty($siteseo->pro['toggle_state_redirect_monitoring'])){
			return;
		}
		
		// Disable
		if(!empty($siteseo->pro['guess_redirect'])){
			remove_filter('template_redirect', 'redirect_guess_404_permalink');
		}
		
		if(!is_404() || empty($siteseo->pro['enable_404_log'])){
			return;
		}
		
		$log_limit = !empty($siteseo->pro['log_limits']) ? $siteseo->pro['log_limits'] : '';
		$redirect_to = !empty($siteseo->pro['redirect_type']) ? $siteseo->pro['redirect_type'] : '';
		$custom_url = !empty($siteseo->pro['custom_redirect_url']) ? $siteseo->pro['custom_redirect_url'] : '';
		$status_code = !empty($siteseo->pro['status_code']) ? $siteseo->pro['status_code'] : '';
		$disable_ip_logging = !empty($siteseo->pro['disable_ip_logging']) ? $siteseo->pro['disable_ip_logging'] : '';
		$redirect_url = $redirect_to === 'homepage' ? home_url() : $custom_url;
		
		$current_url = sanitize_url($_SERVER['REQUEST_URI']);

		// TODO:: Need to make it more robust to make sure we always get an IP.
		$current_request_ip = $_SERVER['REMOTE_ADDR'];
		$current_request_ip = filter_var($current_request_ip, FILTER_VALIDATE_IP);

		if($current_request_ip){
			switch($disable_ip_logging){
				case 'no_ip_logging':
					$current_request_ip = null;
					break;
				case 'anonymize_the_last_part':
					// TODO:: Handle IPv6
					$ip_parts = explode('.', $current_request_ip);
					$ip_parts[count($ip_parts) - 1] = 'xxx';
					$current_request_ip = implode('.', $ip_parts);
					break;
			}
		} else {
			$current_request_ip = null;
		}
		
		if(!empty($siteseo->pro['enable_404_log'])){
			
			\SiteSEOPro\Settings\Util::maybe_create_404_table();

			$existing_record = $wpdb->get_row($wpdb->prepare("SELECT id, hit_count FROM `".$wpdb->prefix."siteseo_redirect_logs` WHERE url = %s", $current_url));
			
			if($existing_record){
				// hit count increase		
				$wpdb->update($wpdb->prefix.'siteseo_redirect_logs', ['hit_count' => intval($existing_record->hit_count + 1)], ['id' => $existing_record->id], ['%d'], ['%d']);

			} else{
					
					$current_records = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."siteseo_redirect_logs`");
					
					if($current_records >= $log_limit){	
						$wpdb->query("DELETE FROM `".$wpdb->prefix."siteseo_redirect_logs` ORDER BY `timestamp` ASC LIMIT 1");
					}
					
					$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
					$referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_url($_SERVER['HTTP_REFERER']) : '';

					$wpdb->insert($wpdb->prefix .'siteseo_redirect_logs', ['url' => $current_url, 'ip_address' => $current_request_ip, 'user_agent' => $user_agent, 'referer' => $referer, 'hit_count' => 1,'timestamp' => current_time('mysql')], ['%s', '%s', '%s', '%s', '%d', '%s']);
					
			}
		}
		
		// redirect here
		if(!empty($redirect_url)){
			wp_redirect($redirect_url, intval($status_code));
			exit;
		}
	   	
	}
	
	static function setup_log_scheduled(){
		global $siteseo;
		
		if(empty($siteseo->pro['email_notify'])){
			return;
		}
		
		if(!wp_next_scheduled('siteseo_send_404_report_email')){
			wp_schedule_event(time(), 'weekly', 'siteseo_send_404_report_email');
		}
	}
	
	static function send_weekly_report(){
		global $siteseo, $wpdb;

		if(empty($siteseo->pro['email_notify'])){
			return;
		}

		$site_name = get_bloginfo('name');
		$site_url = get_bloginfo('url');
		$admin_email = get_option('admin_email');
		$user_email = !empty($siteseo->pro['send_email_to']) ? $siteseo->pro['send_email_to'] : $admin_email;
		$admin_user = get_user_by('email', $admin_email);
		$admin_name = $admin_user ? $admin_user->display_name : 'Administrator';

		if(empty($user_email)){
			return;
		}

		$top_errors = $wpdb->get_results("SELECT url, hit_count, COUNT(*) as count FROM `{$wpdb->prefix}siteseo_redirect_logs` GROUP BY url ORDER BY hit_count DESC LIMIT 10");
		
		$latest_errors = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}siteseo_redirect_logs` ORDER BY `timestamp` DESC LIMIT 10");
		
		$subject = sprintf('404 Error Report for %s - Week of %s', $site_name, gmdate('F j, Y'));
		
		$content = sprintf(
			'<!DOCTYPE html>
			<html>
			<body>
			<div style="font-family: Arial, sans-serif; line-height: 1.6;">
				<p>Hello %s,</p>
				
				<p>Here\'s your weekly 404 error report for %s. We\'ve identified several broken links that may need your attention.</p>
				
				<div style="margin: 25px 0;">
					<h2>Weekly 404 Error Report for %s</h2>
					<p>Below are the most frequent and recent 404 errors detected on your site:</p>
				</div>
				
				<h3>Top 404 Errors</h3>
				<table style="border-collapse: collapse; width: 100%%;">
					<tr style="background-color: #f2f2f2;">
						<th style="border:1px solid #ddd; padding:8px; text-align:left;">URL</th>
						<th style="border:1px solid #ddd; padding:8px; text-align:center; width:200px;">Hit Count</th>
					</tr>',
			esc_html($admin_name),
			esc_html($site_name),
			esc_html($site_name)
		);
	
		foreach($top_errors as $error){
			$content .= sprintf(
				'<tr>
					<td style="border:1px solid #ddd; padding:8px; text-align:left"><a href="%s">%s</a></td>
					<td style="border:1px solid #ddd; padding:8px; text-align:center;">%d</td>
				</tr>',
				esc_url($site_url . $error->url),
				esc_html($error->url),
				intval($error->hit_count)
			);
		}
    
		$content .= '</table>    
			<h3>Latest 404 Errors</h3>
			<table style="border-collapse: collapse; width: 100%;">
			<tr style="background-color: #f2f2f2;">
				<th style="border:1px solid #ddd; padding:8px; text-align:left;">URL</th>
				<th style="border:1px solid #ddd; padding:8px; text-align:center; width:200px;">Timestamp</th>
			</tr>';
    
		foreach($latest_errors as $error){
			$content .= sprintf(
				'<tr>
					<td style="border:1px solid #ddd; padding:8px; text-align:left;"><a href="%s">%s</a></td>
					<td style="border:1px solid #ddd; padding:8px; text-align:center;">%s</td>
				</tr>',
				esc_url($site_url . $error->url),
				esc_html($error->url),
				esc_html(gmdate('F j, Y g:i a', strtotime($error->timestamp)))
			);
		}
		
		$content .= '</table>
				<div style="margin-top:30px; padding-top:20px; border-top: 1px solid #ddd;">
					<p style="margin-top:20px; font-size:12px; color:#666;">
						This report was automatically generated on '.gmdate('F j, Y').'
					</p>
					<p style="font-size:14px; color:#444; margin-top:15px;">
						Powered by <a href="https://siteseo.io" target="_blank" style="color:#163d89;text-decoration:none;"><strong>SiteSEO</strong></a>
					</p>
				</div>
			</body>
		</html>';
    
		// Html
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: SiteSEO <'.get_option('admin_email').'>'
		);
		
		// Send email
		return wp_mail($user_email, $subject, $content, $headers);
        
	}

}