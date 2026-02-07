<?php
/*
* gosmtp
* https://gosmtp.net
* (c) Softaculous Team
*/

// Are we being accessed directly ?
if(!defined('GOSMTP_PRO_VERSION')) {
	exit('Hacking Attempt !');
}

function gosmtp_pro_activation(){
	update_option('gosmtp_pro_version', GOSMTP_PRO_VERSION);
}

function gosmtp_pro_is_network_active($plugin_name){
	$is_network_wide = false;
	
	// Handling network site
	if(!is_multisite()){
		return $is_network_wide;
	}
	
	$_tmp_plugins = get_site_option('active_sitewide_plugins');

	if(!empty($_tmp_plugins) && preg_grep('/.*\/'.$plugin_name.'\.php$/', array_keys($_tmp_plugins))){
		$is_network_wide = true;
	}
	
	return $is_network_wide;
}

function gosmtp_pro_update_checker(){
	$current_version = get_option('gosmtp_pro_version', '0.0');
	$version = (int) str_replace('.', '', $current_version);

	// No update required
	if($current_version == GOSMTP_PRO_VERSION){
		return true;
	}
	
	$is_network_wide = gosmtp_pro_is_network_active('gosmtp-pro');
	
	if($is_network_wide){
		$free_ins = get_site_option('gosmtp_free_installed');
	}else{
		$free_ins = get_option('gosmtp_free_installed');
	}
	
	// If plugin runing reached here it means GoSMTP free installed 
	if(empty($free_ins)){
		if($is_network_wide){
			update_site_option('gosmtp_free_installed', time());
		}else{
			update_option('gosmtp_free_installed', time());
		}
	}
	
	update_option('gosmtp_version_pro_nag', time());
	update_option('gosmtp_version_free_nag', time());
	update_option('gosmtp_pro_version', GOSMTP_PRO_VERSION);
}


// Load license data
function gosmtp_pro_load_license($parent = 0){
	
	global $gosmtp, $lic_resp;
	
	$license_field = 'gosmtp_license';
	$license_api_url = GOSMTP_API;
	
	// Save license
	if(!empty($parent) && is_string($parent) && strlen($parent) > 5){		
		$lic['license'] = $parent;
	
	// Load license of Soft Pro
	}elseif(!empty($parent)){
		$license_field = 'softaculous_pro_license';
		$lic = get_option('softaculous_pro_license', []);
	
	// My license
	}else{
		$lic = get_option($license_field, []);
	}
	
	// Loaded license is a Soft Pro
	if(!empty($lic['license']) && preg_match('/^softwp/is', $lic['license'])){
		$license_field = 'softaculous_pro_license';
		$license_api_url = 'https://a.softaculous.com/softwp/';
		$prods = apply_filters('softaculous_pro_products', []);
	}else{
		$prods = [];
	}

	if(empty($lic['last_update'])){
		$lic['last_update'] = time() - 86600;
	}
	
	// Update license details as well
	if(!empty($lic) && !empty($lic['license']) && (time() - @$lic['last_update']) >= 86400){
		
		$url = $license_api_url.'/license.php?license='.$lic['license'].'&prods='.implode(',', $prods).'&url='.rawurlencode(site_url());
		$resp = wp_remote_get($url);
		$lic_resp = $resp;

		//Did we get a response ?
		if(is_array($resp)){
			
			$tosave = json_decode($resp['body'], true);
			
			//Is it the license ?
			if(!empty($tosave['license'])){
				$tosave['last_update'] = time();
				update_option($license_field, $tosave);
				$lic = $tosave;
			}
		}
	}
	
	// If the license is Free or Expired check for Softaculous Pro license
	if(empty($lic) || empty($lic['active'])){
		
		if(function_exists('softaculous_pro_load_license')){
			$softaculous_license = softaculous_pro_load_license();
			if(!empty($softaculous_license['license']) && 
				(!empty($softaculous_license['active']) || empty($lic['license']))
			){
				$lic = $softaculous_license;
			}
		}elseif(empty($parent)){
			$soft_lic = get_option('softaculous_pro_license', []);
			
			if(!empty($soft_lic)){
				return gosmtp_pro_load_license(1);
			}
		}
	}
	
	if(!empty($lic['license'])){
		$gosmtp->license = $lic;
	}
	
}

add_filter('softaculous_pro_products', 'gosmtp_softaculous_pro_products', 10, 1);
function gosmtp_softaculous_pro_products($r = []){
	$r['gosmtp'] = 'gosmtp';
	return $r;
}

// Add our license key if ANY
function gosmtp_pro_updater_filter_args($queryArgs){
	
	global $gosmtp;
	
	if (!empty($gosmtp->license['license'])){
		$queryArgs['license'] = $gosmtp->license['license'];
	}
	
	$queryArgs['url'] = rawurlencode(site_url());
	
	return $queryArgs;
}

// Handle the Check for update link and ask to install license key
function gosmtp_pro_updater_check_link($final_link){
	
	global $gosmtp;
	
	if(empty($gosmtp->license['license'])){
		return '<a href="'.admin_url('admin.php?page=gosmtp-license').'">Install GoSMTP Pro License Key</a>';
	}
	
	return $final_link;
}

// Prevent update of gosmtp free
function gosmtp_pro_get_free_version_num(){
		
	if(defined('GOSMTP_VERSION')){
		return GOSMTP_VERSION;
	}
	
	// In case of gosmtp deactive
	return gosmtp_pro_file_get_version_num('gosmtp/gosmtp.php');
}

// Prevent update of gosmtp free
function gosmtp_pro_file_get_version_num($plugin){
	
	// In case of gosmtp deactive
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/'.$plugin);
	
	if(empty($plugin_data)){
		return false;
	}
	
	return $plugin_data['Version'];
	
}

// Prevent update of gosmtp free
function gosmtp_pro_disable_manual_update_for_plugin($transient){
	$plugin = 'gosmtp/gosmtp.php';
	
	// Is update available?
	if(!isset($transient->response) || !isset($transient->response[$plugin])){
		return $transient;
	}
	
	$free_version = gosmtp_pro_get_free_version_num();
	$pro_version = GOSMTP_PRO_VERSION;
	
	if(!empty($GLOBALS['gosmtp_pro_is_upgraded'])){
		$pro_version = gosmtp_pro_file_get_version_num('gosmtp-pro/gosmtp-pro.php');
	}
	
	// Update the gosmtp version to the equivalent of Pro version
	if(!empty($pro_version) && version_compare($free_version, $pro_version, '<')){
		$transient->response[$plugin]->new_version = $pro_version;
		$transient->response[$plugin]->package = 'https://downloads.wordpress.org/plugin/gosmtp.'.$pro_version.'.zip';
	}else{
		unset($transient->response[$plugin]);
	}

	return $transient;
}

// Override test connection key
function gosmtp_pro_override_connection($default_key){
	// We need to override only if it is ajax request for test mail
	if(empty($_REQUEST['gosmtp_nonce']) || empty($_REQUEST['action']) || sanitize_text_field(wp_unslash($_REQUEST['action'])) !== 'gosmtp_test_mail'){
		return 0;
	}

	// We need to check ajax nonce here as well becuase this filter should not be called
	check_admin_referer('gosmtp_ajax' , 'gosmtp_nonce');
	$connection_key = gosmtp_optpost('smtp_test_connection');
	return !empty($connection_key) ? $connection_key : $default_key;
}

// HTML Template for Test Mail
function gosmtp_pro_test_html_template(){
	return'
	<!DOCTYPE html>
	<html lang="en">
	<head>
	<meta charset="UTF-8">
	<title>GoSMTP Test Email</title>
	</head>
	<body style="margin:0;padding:0;background-color:#f6f9fc;font-family:Arial,sans-serif;">
		<div style="max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 3px 10px rgba(0,0,0,0.1);">
			<div style="background-color:#0073aa;color:#fff;text-align:center;padding:25px 20px;font-size:22px;font-weight:600;">
				GoSMTP Test Email Sent Successfully!
			</div>
			<div style="padding:30px;text-align:center;color:#333;line-height:1.6;">
				<h2 style="color:#0073aa;margin-bottom:10px;">CONGRATS!</h2>
				<p>Your test email was sent successfully using <strong>GoSMTP</strong>.</p>
				<p>Thank you for choosing GoSMTP — a simple and reliable way to ensure your WordPress emails are delivered smoothly and securely.</p>
				<p>GoSMTP helps you connect with popular email providers and monitor your outgoing emails easily.</p>
				<a href="https://gosmtp.net" style="display:inline-block;margin-top:20px;padding:10px 22px;background-color:#0073aa;color:#fff;text-decoration:none;font-weight:500;border-radius:5px;" target="_blank">Visit GoSMTP</a>
			</div>
			<div style="background-color:#f3f4f6;color:#666;text-align:center;padding:15px;font-size:13px;">
				<p>Need help? You can contact the GoSMTP Team via email.</p>
				<p>Our email address is <a href="mailto:support@gosmtp.net" style="color:#0073aa;text-decoration:none;">support@gosmtp.net</a></p>
				<p>or through Our Premium Support Ticket System <a href="https://softaculous.deskuss.com/open.php?topicId=20" target="_blank" style="color:#0073aa;text-decoration:none;">here</a></p>
			</div>
		</div>
	</body>
	</html>
	';
}

// Auto update free version after update pro version
function gosmtp_pro_update_free_after_pro($upgrader_object, $options){
	
	// Check if the action is an update for the plugins
	if($options['action'] != 'update' || $options['type'] != 'plugin'){
		return;
	}
		
	// Define the slugs for the free and pro plugins
	$free_slug = 'gosmtp/gosmtp.php'; 
	$pro_slug = 'gosmtp-pro/gosmtp-pro.php';

	// Check if the pro plugin is in the list of updated plugins
	if( 
		(isset($options['plugins']) && in_array($pro_slug, $options['plugins']) && !in_array($free_slug, $options['plugins'])) ||
		(isset($options['plugin']) && $pro_slug == $options['plugin'])
	){
	
		// Trigger the update for the free plugin
		$current_version = gosmtp_pro_get_free_version_num();
		
		if(empty($current_version)){
			return;
		}
		
		$GLOBALS['gosmtp_pro_is_upgraded'] = true;
		
		// This will set the 'update_plugins' transient again
		wp_update_plugins();

		// Check for updates for the free plugin
		$update_plugins = get_site_transient('update_plugins');
		
		if(empty($update_plugins) || !isset($update_plugins->response[$free_slug]) || version_compare($update_plugins->response[$free_slug]->new_version, $current_version, '<=')){
			return;
		}
		
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
		
		$skin = wp_doing_ajax()? new WP_Ajax_Upgrader_Skin() : null;
		
		$upgrader = new Plugin_Upgrader($skin);
		$upgraded = $upgrader->upgrade($free_slug);
		
		if(!is_wp_error($upgraded) && $upgraded){
			// Re-active free plugins
			if( file_exists( WP_PLUGIN_DIR . '/'.  $free_slug ) && is_plugin_inactive($free_slug) ){
				activate_plugin($free_slug); // TODO for network
			}
			
			// Re-active pro plugins
			if( file_exists( WP_PLUGIN_DIR . '/'.  $pro_slug ) && is_plugin_inactive($pro_slug) ){
				activate_plugin($pro_slug); // TODO for network
			}
		}
	}
}

function gosmtp_pro_api_url($main_server = 0, $suffix = 'gosmtp'){
	
	global $gosmtp;
	
	$r = array(
		'https://s0.softaculous.com/a/softwp/',
		'https://s1.softaculous.com/a/softwp/',
		'https://s2.softaculous.com/a/softwp/',
		'https://s3.softaculous.com/a/softwp/',
		'https://s4.softaculous.com/a/softwp/',
		'https://s5.softaculous.com/a/softwp/',
		'https://s7.softaculous.com/a/softwp/',
		'https://s8.softaculous.com/a/softwp/'
	);
	
	$mirror = $r[array_rand($r)];
	
	// If the license is newly issued, we need to fetch from API only
	if(!empty($main_server) || empty($gosmtp->license['last_edit']) || 
		(!empty($gosmtp->license['last_edit']) && (time() - 3600) < $gosmtp->license['last_edit'])
	){
		$mirror = GOSMTP_API;
	}
	
	if(!empty($suffix)){
		$mirror = str_replace('/softwp', '/'.$suffix, $mirror);
	}
	
	return $mirror;
	
}

function gosmtp_pro_plugins_expired($plugins){
	$plugins[] = 'GoSMTP';
	return $plugins;
}

function gosmtp_pro_expiry_notice(){
	global $gosmtp;

	// The combined notice for all Softaculous plugin to show that the license has expired
	$dismissed_at = get_option('softaculous_expired_licenses', 0);
	$expired_plugins = apply_filters('softaculous_expired_licenses', []);
	if(
		!empty($expired_plugins) && 
		is_array($expired_plugins) && 
		!defined('SOFTACULOUS_EXPIRY_LICENSES') && 
		(empty($dismissed_at) || ($dismissed_at + WEEK_IN_SECONDS) < time())
	){

		define('SOFTACULOUS_EXPIRY_LICENSES', true); // To make sure other plugins don't return a Notice
		echo '<div class="notice notice-error is-dismissible" id="gosmtp-pro-expiry-notice">
				<p>'.sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please renew it to continue receiving uninterrupted updates and support for %3$s.', 'gosmtp-pro'),
				'<font style="color:red;"><b>',
				'</b></font>',
				esc_html(implode(', ', $expired_plugins))
				). '</p>
			</div>';

		wp_register_script('gosmtp-pro-expiry-notice', '', ['jquery'], GOSMTP_PRO_VERSION, true);
		wp_enqueue_script('gosmtp-pro-expiry-notice');
		wp_add_inline_script('gosmtp-pro-expiry-notice', '
		jQuery(document).ready(function(){
			jQuery("#gosmtp-pro-expiry-notice").on("click", ".notice-dismiss", function(e){
				e.preventDefault();
				let target = jQuery(e.target);

				let jEle = target.closest("#gosmtp-pro-expiry-notice");
				jEle.slideUp();

				jQuery.post("'.admin_url('admin-ajax.php').'", {
					security : "'.wp_create_nonce('gosmtp_expiry_notice').'",
					action: "gosmtp_pro_dismiss_expired_licenses",
				}, function(res){
					if(!res["success"]){
						alert(res["data"]);
					}
				}).fail(function(data){
					alert("There seems to be some issue dismissing this alert");
				});
			});
		})');
	}
}

function gosmtp_pro_check_failure_and_notify_handler($is_sent, $exception, $backup_sent){
	include_once GOSMTP_PRO_DIR .'/main/notifications/sender.php';
	gosmtp_pro_check_failure_and_notify($is_sent, $exception, $backup_sent);
}

function gosmtp_pro_get_notifications_service_list(){

	$list = [
		'email' => ['title' => __('Email', 'gosmtp-pro'), 'class' => 'GOSMTP\Notifications\Email'],
		'slack' => ['title' => __('Slack', 'gosmtp-pro'), 'class' => 'GOSMTP\Notifications\Slack'],
		'discord' => ['title' => __('Discord', 'gosmtp-pro'), 'class' => 'GOSMTP\Notifications\Discord'],
	];

	return apply_filters('gosmtp_pro_get_notifications_service_list', $list);
}

function gosmtp_pro_load_notifications_service_list(){
	
	$list = gosmtp_pro_get_notifications_service_list();
	
	$smtp_service = [];
	
	foreach($list as $key => $service){
		
		$class = $service['class'];
		
		if(!class_exists($class)){
			continue;
		}
		
		$smtp_service[$key] = new $class();
	}
		
	return apply_filters('gosmtp_pro_load_notifications_service_list', $smtp_service);
}