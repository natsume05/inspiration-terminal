<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

if(wp_doing_ajax()){	
	include_once GOSMTP_PRO_DIR.'/main/ajax.php';
}

register_activation_hook(__FILE__, 'gosmtp_pro_activation');

// Prevent update of gosmtp free
// This also work for auto update
add_filter('site_transient_update_plugins', 'gosmtp_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'gosmtp_pro_disable_manual_update_for_plugin');

// Filter to pass the gosmtp test connection key
add_filter('gosmtp_connection_key', 'gosmtp_pro_override_connection');

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'gosmtp_pro_update_free_after_pro', 10, 2);

// Add action to load GoSMTP
add_action('plugins_loaded', 'gosmtp_pro_load_plugin');
function gosmtp_pro_load_plugin(){
	global $gosmtp;
	
	if(empty($gosmtp)){
		$gosmtp = new stdClass();

		$gosmtp->options = get_option('gosmtp_options', []);
	}
	
	// Load license
	gosmtp_pro_load_license();
	
	gosmtp_pro_update_checker();

	if(current_user_can('activate_plugins')){
		add_action('admin_notices', 'gosmtp_pro_free_version_nag');
		
		// Softaculous Common notice to show that the license has expired.
		if(!empty($gosmtp->license) && empty($gosmtp->license['active']) && strpos($gosmtp->license['license'], 'SOFTWP') !== FALSE){
			add_action('admin_notices', 'gosmtp_pro_expiry_notice');
			add_filter('softaculous_expired_licenses', 'gosmtp_pro_plugins_expired');
		}
	}
	
	if(!defined('SITEPAD')){
		// Check for updates
		include_once(GOSMTP_PRO_DIR.'/main/plugin-update-checker.php');
		$gosmtp_updater = Gosmtp_PucFactory::buildUpdateChecker(gosmtp_pro_api_url().'updates.php?version='.GOSMTP_PRO_VERSION, GOSMTP_PRO_FILE);
		
		// Add the license key to query arguments
		$gosmtp_updater->addQueryArgFilter('gosmtp_pro_updater_filter_args');
		
		// Show the text to install the license key
		add_filter('puc_manual_final_check_link-gosmtp-pro', 'gosmtp_pro_updater_check_link', 10, 1);
	}
	
	// Is log enabled and retention period set?
	if(!empty($gosmtp->options['logs']['enable_logs']) && !empty($gosmtp->options['logs']['retention_period'])){
		add_action( 'gosmtp_log_retention_cron', 'GOSMTP\Logger::retention_logs');
	}
	
	// Is log enabled and retention period set?
	if(!empty($gosmtp->options['weekly_reports']['enable_weekly_reports']) && !function_exists('gosmtp_send_email_reports')){
		
		include_once GOSMTP_PRO_DIR .'/main/weekly_email_reports.php';

		add_action( 'gosmtp_weekly_email_reports_cron', 'gosmtp_send_email_reports', 10, 1 );
	}

	// Sending failed email notification
	if(!empty($gosmtp->options['notifications']) && !empty($gosmtp->options['notifications']['notifications_enabled'])){
		add_action('gosmtp_mailer_mail_send_after', 'gosmtp_pro_check_failure_and_notify_handler', 10, 3);
	}
}

add_action('rest_api_init', 'outlook_api_init');
function outlook_api_init(){

	register_rest_route('gosmtp-smtp', '/outlook_callback/', array(
		'methods' => 'GET',
		'callback' => function (\WP_REST_Request $request) {
			
			$url = parse_url($_SERVER['REQUEST_URI']);
			$redirect_uri = admin_url().'admin.php?page=gosmtp&'.$url['query'].'&auth=outlook';
			wp_redirect($redirect_uri);
			die();
		},
		'permission_callback' => function() {
			$state = $_REQUEST['state'];
		
			if($state != get_option('_gosmtp_last_generated_state')) {
				return false;
			}
			
			return true;
		}
	));
}

function gosmtp_pro_free_version_nag(){
	
	if(!defined('GOSMTP_VERSION')){
		return;
	}

	$dismissed_free = (int) get_option('gosmtp_version_free_nag');
	$dismissed_pro = (int) get_option('gosmtp_version_pro_nag');

	// Checking if time has passed since the dismiss.
	if(!empty($dismissed_free) && time() < $dismissed_pro && !empty($dismissed_pro) && time() < $dismissed_pro){
		return;
	}

	$showing_error = false;
	if(version_compare(GOSMTP_VERSION, GOSMTP_PRO_VERSION) > 0 && (empty($dismissed_pro) || time() > $dismissed_pro)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="gosmtp-pro-version-notice" onclick="gosmtp_pro_dismiss_notice(event)" data-type="pro">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of GoSMTP Pro. We recommend updating to the latest version to ensure seamless and uninterrupted use of the application.', 'gosmtp').'</p>
	</div>';
	}elseif(version_compare(GOSMTP_VERSION, GOSMTP_PRO_VERSION) < 0 && (empty($dismissed_free) || time() > $dismissed_free)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="gosmtp-pro-version-notice" onclick="gosmtp_pro_dismiss_notice(event)" data-type="free">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of GoSMTP. We recommend updating to the latest free version to ensure smooth and uninterrupted use of the application.', 'gosmtp').'</p>
	</div>';
	}
	
	if(!empty($showing_error)){
		wp_register_script('gosmtp-pro-version-notice', '', array('jquery'), GOSMTP_PRO_VERSION, true );
		wp_enqueue_script('gosmtp-pro-version-notice');
		wp_add_inline_script('gosmtp-pro-version-notice', '
	function gosmtp_pro_dismiss_notice(e){
		e.preventDefault();
		let target = jQuery(e.target);

		if(!target.hasClass("notice-dismiss")){
			return;
		}

		let jEle = target.closest("#gosmtp-pro-version-notice"),
		type = jEle.data("type");

		jEle.slideUp();
		
		jQuery.post("'.admin_url('admin-ajax.php').'", {
			security : "'.wp_create_nonce('gosmtp_version_notice').'",
			action: "gosmtp_pro_version_notice",
			type: type
		}, function(res){
			if(!res["success"]){
				alert(res["data"]);
			}
		}).fail(function(data){
			alert("There seems to be some issue dismissing this alert");
		});
	}');
	}
}

add_action('admin_init', 'gosmtp_pro_admin_init');
function gosmtp_pro_admin_init(){
	wp_register_style('gosmtp-pro-admin', GOSMTP_PRO_PLUGIN_URL .'/assets/css/admin.css', array(), GOSMTP_PRO_VERSION);
	wp_register_script('gosmtp-pro-admin', GOSMTP_PRO_PLUGIN_URL .'/assets/js/admin.js', array('jquery'), GOSMTP_PRO_VERSION);
	wp_register_script('gosmtp-pro-chartjs', GOSMTP_PRO_PLUGIN_URL .'/assets/js/chart.umd.min.js', array(), GOSMTP_PRO_VERSION);

}