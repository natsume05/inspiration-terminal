<?php

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

define('BACKUPLY_PRO_VERSION', '1.5.1');
define('BACKUPLY_PRO_DIR', dirname(BACKUPLY_PRO_FILE));
define('BACKUPLY_PRO_BASE', 'backuply-pro/backuply-pro.php');

// Some other constants
if(!defined('BACKUPLY_API')){
	define('BACKUPLY_API', 'https://api.backuply.com');
}

include_once BACKUPLY_PRO_DIR . '/functions.php';

add_action('plugins_loaded', 'backuply_pro_load_plugin');
register_activation_hook( __FILE__, 'backuply_pro_activate');
register_deactivation_hook( __FILE__, 'backuply_pro_deactivate');

add_filter('site_transient_update_plugins', 'backuply_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'backuply_pro_disable_manual_update_for_plugin');

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'backuply_pro_update_free_after_pro', 10, 2);


if(defined('WP_CLI') && !empty(WP_CLI) && defined('BACKUPLY_PRO')){
	include_once BACKUPLY_PRO_DIR . '/lib/cli.php';
}

function backuply_pro_load_plugin(){
	global $backuply;
	
	backuply_load_license();
	
	backuply_pro_check_updates();

	// Cron for Calling Auto Backup
	add_action('backuply_auto_backup_cron', 'backuply_auto_backup_execute');

	// Auto Backup using custom cron
	if(isset($_GET['action'])  && $_GET['action'] == 'backuply_custom_cron'){
		
		if(!backuply_verify_self(sanitize_text_field(wp_unslash($_REQUEST['backuply_key'])))){
			backuply_status_log('Security Check Failed', 'error');
			die();
		}
		
		backuply_auto_backup_execute();
	}
	
	// Check for updates
	include_once(BACKUPLY_PRO_DIR.'/lib/plugin-update-checker.php');
	$backuply_updater = Backuply_PucFactory::buildUpdateChecker(backuply_pro_api_url().'/updates.php?version='.BACKUPLY_PRO_VERSION, BACKUPLY_PRO_FILE);

	// Add the license key to query arguments
	$backuply_updater->addQueryArgFilter('backuply_updater_filter_args');

	// Show the text to install the license key
	add_filter('puc_manual_final_check_link-backuply-pro', 'backuply_updater_check_link', 10, 1);

	if(is_admin()){
		
		if(!empty($backuply['license']) && empty($backuply['license']['active']) && strpos($backuply['license']['license'], 'SOFTWP') !== FALSE){
			add_action('admin_notices', 'backuply_pro_expiry_notice');
			add_filter('softaculous_expired_licenses', 'backuply_pro_plugins_expired');
		}

		if(current_user_can('activate_plugins')){
			add_action('admin_notices', 'backuply_pro_free_version_nag');
			
			// === Plugin Update Notice === //
			$plugin_update_notice = get_option('softaculous_plugin_update_notice');
			$available_update_list = get_site_transient('update_plugins'); 
			$plugin_name_slug = 'backuply-pro/backuply-pro.php';

			if(
				!empty($available_update_list) &&
				!empty($available_update_list->response) &&
				!empty($available_update_list->response[$plugin_name_slug]) && 
				(empty($plugin_update_notice) || empty($plugin_update_notice[$plugin_name_slug]) || (!empty($plugin_update_notice[$plugin_name_slug]) &&
				version_compare($plugin_update_notice[$plugin_name_slug], $available_update_list->response[$plugin_name_slug]->new_version, '<'))) &&
				(defined('BACKUPLY_VERSION') && version_compare(BACKUPLY_VERSION, '1.4.8', '>='))
			){
				add_action('admin_notices', 'backuply_update_notice_handler');
				add_filter('softaculous_plugin_update_notice', 'backuply_pro_update_notice_filter');
			}
			// === Plugin Update Notice === //
		}
		
		if(!defined('BACKUPLY_VERSION')){
			add_action('admin_menu', 'backuply_pro_add_menu');
		}
	}
	
}

// Nag when plugins dont have same version.
function backuply_pro_free_version_nag(){
	
	if(!defined('BACKUPLY_VERSION')){
		return;
	}

	$dismissed_free = (int) get_option('backuply_version_free_nag');
	$dismissed_pro = (int) get_option('backuply_version_pro_nag');

	// Checking if time has passed since the dismiss.
	if(!empty($dismissed_free) && time() < $dismissed_pro && !empty($dismissed_pro) && time() < $dismissed_pro){
		return;
	}

	$showing_error = false;
	if(version_compare(BACKUPLY_VERSION, BACKUPLY_PRO_VERSION) > 0 && (empty($dismissed_pro) || time() > $dismissed_pro)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="backuply-pro-version-notice" onclick="backuply_pro_dismiss_notice(event)" data-type="pro">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of Backuply Pro. We recommend updating to the latest version to ensure seamless and uninterrupted use of the application.', 'backuply-pro').'</p>
	</div>';
	}elseif(version_compare(BACKUPLY_VERSION, BACKUPLY_PRO_VERSION) < 0 && (empty($dismissed_free) || time() > $dismissed_free)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="backuply-pro-version-notice" onclick="backuply_pro_dismiss_notice(event)" data-type="free">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of Backuply. We recommend updating to the latest free version to ensure smooth and uninterrupted use of the application.', 'backuply-pro').'</p>
	</div>';
	}
	
	if(!empty($showing_error)){
		wp_register_script('backuply-pro-version-notice', '', array('jquery'), BACKUPLY_PRO_VERSION, true );
		wp_enqueue_script('backuply-pro-version-notice');
		wp_add_inline_script('backuply-pro-version-notice', '
	function backuply_pro_dismiss_notice(e){
		e.preventDefault();
		let target = jQuery(e.target);

		if(!target.hasClass("notice-dismiss")){
			return;
		}

		let jEle = target.closest("#backuply-pro-version-notice"),
		type = jEle.data("type");

		jEle.slideUp();
		
		jQuery.post("'.admin_url('admin-ajax.php').'", {
			security : "'.wp_create_nonce('backuply_version_notice').'",
			action: "backuply_pro_version_notice",
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

// Version nag ajax
function backuply_pro_version_notice(){
	check_admin_referer('backuply_version_notice', 'security');

	if(!current_user_can('activate_plugins')){
		wp_send_json_error(__('You do not have required access to do this action', 'backuply-pro'));
	}
	
	$type = '';
	if(!empty($_REQUEST['type'])){
		$type = sanitize_text_field(wp_unslash($_REQUEST['type']));
	}

	if(empty($type)){
		wp_send_json_error(__('Unknown version difference type', 'backuply-pro'));
	}
	
	update_option('backuply_version_'. $type .'_nag', time() + WEEK_IN_SECONDS);
	wp_send_json_success();
}
add_action('wp_ajax_backuply_pro_version_notice', 'backuply_pro_version_notice');

function backuply_pro_dismiss_expired_licenses(){
	check_admin_referer('backuply_expiry_notice', 'security');

	if(!current_user_can('activate_plugins')){
		wp_send_json_error(__('You do not have required access to do this action', 'backuply-pro'));
	}

	update_option('softaculous_expired_licenses', time());
	wp_send_json_success();
}
add_action('wp_ajax_backuply_pro_dismiss_expired_licenses', 'backuply_pro_dismiss_expired_licenses');

function backuply_pro_activate(){
	update_option('backuply_pro_version', BACKUPLY_PRO_VERSION);
}

// Check on update
function backuply_pro_check_updates(){

	$current_version = get_option('backuply_pro_version');
	$version = (int) str_replace('.', '', $current_version);

	// No update required
	if($current_version == BACKUPLY_PRO_VERSION){
		return true;
	}

	update_option('backuply_pro_version', BACKUPLY_PRO_VERSION);
}

function backuply_pro_deactivate(){
	delete_option('backuply_pro_version');
}

function backuply_pro_add_menu(){
	add_menu_page('Backuply Dahsboard', 'Backuply', 'activate_plugins', 'backuply', 'backuply_pro_menu_page');
}

function backuply_pro_menu_page(){

	echo '<div style="color: #333;padding: 50px;text-align: center;">
		<h1 style="font-size: 2em;margin-bottom: 10px;">Backuply Free version is not installed or need to be updated!</h>
		<p style=" font-size: 16px;margin-bottom: 20px; font-weight:400;">Backuply Pro after version 1.2.0 depends on the free version of Backuply, so you need to install the free version first and if you already have the free version please update it.</p>
		<a href="'.admin_url('plugin-install.php?s=backuply&tab=search').'" style="text-decoration: none;font-size:16px;">Install Now</a>
	</div>';
}

function backuply_pro_plugins_expired($plugins){
	$plugins[] = 'Backuply';
	return $plugins;
}

function backuply_pro_expiry_notice(){
	global $backuply;

	if(!current_user_can('activate_plugins')){
		return;
	}
	
	// The combined notice for all Softaculous plugin to show that the license has expired
	$dismissed_at = get_option('softaculous_expired_licenses', 0);
	$expired_plugins = apply_filters('softaculous_expired_licenses', []);
	$soft_wp_buy = 'https://www.softaculous.com/clients?ca=softwp_buy';
	if(
		!empty($expired_plugins) && 
		is_array($expired_plugins) && 
		!defined('SOFTACULOUS_EXPIRY_LICENSES') && 
		(empty($dismissed_at) || ($dismissed_at + WEEK_IN_SECONDS) < time())
	){

		define('SOFTACULOUS_EXPIRY_LICENSES', true); // To make sure other plugins don't return a Notice
		$soft_rebranding = get_option('softaculous_pro_rebranding', []);

		if(!empty($backuply['license']['has_plid'])){
			if(!empty($soft_rebranding['sn']) && $soft_rebranding['sn'] != 'Softaculous'){
				
				$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact %3$s to continue receiving uninterrupted updates and support for %4$s.', 'backuply-pro'),
					'<font style="color:red;"><b>',
					'</b></font>',
					esc_html($soft_rebranding['sn']),
					esc_html(implode(', ', $expired_plugins))
				);
				
			}else{
				$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact your hosting provider to continue receiving uninterrupted updates and support for %3$s.', 'backuply-pro'),
					'<font style="color:red;"><b>',
					'</b></font>',
					esc_html(implode(', ', $expired_plugins))
				);
			}
		}else{
			$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please %3$srenew%4$s it to continue receiving uninterrupted updates and support for %5$s.', 'backuply-pro'),
				'<font style="color:red;"><b>',
				'</b></font>',
				'<a href="'.esc_url($soft_wp_buy.'&license='.$backuply['license']['license'].'&plan='.$backuply['license']['plan']).'" target="_blank">',
				'</a>',
				esc_html(implode(', ', $expired_plugins))
			);
		}
		
		
		echo '<div class="notice notice-error is-dismissible" id="backuply-pro-expiry-notice">
				<p>'.$msg. '</p>
			</div>';

		wp_register_script('backuply-pro-expiry-notice', '', ['jquery'], BACKUPLY_PRO_VERSION, true);
		wp_enqueue_script('backuply-pro-expiry-notice');
		wp_add_inline_script('backuply-pro-expiry-notice', '
		jQuery(document).ready(function(){
			jQuery("#backuply-pro-expiry-notice").on("click", ".notice-dismiss", function(e){
				e.preventDefault();
				let target = jQuery(e.target);

				let jEle = target.closest("#backuply-pro-expiry-notice");
				jEle.slideUp();
				
				jQuery.post("'.admin_url('admin-ajax.php').'", {
					security : "'.wp_create_nonce('backuply_expiry_notice').'",
					action: "backuply_pro_dismiss_expired_licenses",
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