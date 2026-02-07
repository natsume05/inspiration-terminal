<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

// Are we being accessed directly ?
if(!defined('GOSMTP_PRO_VERSION')) {
	exit('Hacking Attempt !');
}

// Prevent update of gosmtp free
// This also work for auto update
add_filter('site_transient_update_plugins', 'gosmtp_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'gosmtp_pro_disable_manual_update_for_plugin');

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'gosmtp_pro_update_free_after_pro', 10, 2);

add_action('plugins_loaded', 'gosmtp_pro_load_plugin');
function gosmtp_pro_load_plugin(){
	global $gosmtp;
	
	if(empty($gosmtp)){
		$gosmtp = new stdClass();
	}

	// Load license
	gosmtp_pro_load_license();
	
	gosmtp_pro_update_checker();
	
	// Check for updates
	include_once(GOSMTP_PRO_DIR.'/main/plugin-update-checker.php');
	$gosmtp_updater = Gosmtp_PucFactory::buildUpdateChecker(gosmtp_pro_api_url().'updates.php?version='.GOSMTP_PRO_VERSION, GOSMTP_PRO_FILE);
	
	// Add the license key to query arguments
	$gosmtp_updater->addQueryArgFilter('gosmtp_pro_updater_filter_args');
	
	// Show the text to install the license key
	add_filter('puc_manual_final_check_link-gosmtp-pro', 'gosmtp_pro_updater_check_link', 10, 1);
	
	// Nag informing the user to install the free version.
	if(current_user_can('activate_plugins')){
		add_action('admin_notices', 'gosmtp_pro_free_version_nag', 9);
		add_action('admin_menu', 'gosmtp_pro_add_menu', 9);
	}

	$is_network_wide = gosmtp_pro_is_network_active('gosmtp-pro');
	$_do_version = get_option('gosmtp_version');
	$req_free_update = !empty($_do_version) && version_compare($_do_version, '1.0.7', '<');
	
	if($is_network_wide){
		$free_installed = get_site_option('gosmtp_free_installed');
	}else{
		$free_installed = get_option('gosmtp_free_installed');
	}
	
	if(!empty($free_installed)){
		return;
	}
	
	// Include the necessary stuff
	include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	include_once(ABSPATH . 'wp-admin/includes/file.php');
	
	if(file_exists(WP_PLUGIN_DIR . '/gosmtp/gosmtp.php') && is_plugin_inactive('/gosmtp/gosmtp.php') && empty($req_free_update)) {
		
		if($is_network_wide){
			update_site_option('gosmtp_free_installed', time());
		}else{
			update_option('gosmtp_free_installed', time());
		}
		
		activate_plugin('/gosmtp/gosmtp.php', '', $is_network_wide);
		remove_action('admin_notices', 'gosmtp_pro_free_version_nag', 9);
		remove_action('admin_menu', 'gosmtp_pro_add_menu', 9);
		return;
	}
	
	// Includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
	include_once(ABSPATH . 'wp-admin/includes/misc.php');
	include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

	// Filter to prevent the activate text
	add_filter('install_plugin_complete_actions', 'gosmtp_pro_prevent_activation_text', 10, 3);
	 
	$upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
	
	// Upgrade the plugin to the latest version of free already installed.
	if(!empty($req_free_update)){
		$installed = $upgrader->upgrade('gosmtp/gosmtp.php');
	}else{
		$installed = $upgrader->install('https://downloads.wordpress.org/plugin/gosmtp.zip');
	}
	
	if(!is_wp_error($installed) && $installed){
		
		if($is_network_wide){
			update_site_option('gosmtp_free_installed', time());
		}else{
			update_option('gosmtp_free_installed', time());
		}
		
		activate_plugin('gosmtp/gosmtp.php', '', $is_network_wide);
		remove_action('admin_notices', 'gosmtp_pro_free_version_nag', 9);
		remove_action('admin_menu', 'gosmtp_pro_add_menu', 9);
	}
}

// Do not shows the activation text if 
function gosmtp_pro_prevent_activation_text($install_actions, $api, $plugin_file){
	if($plugin_file == 'gosmtp/gosmtp.php'){
		return array();
	}

	return $install_actions;
}

function gosmtp_pro_free_version_nag(){
	
	$go_version = get_option('gosmtp_version');
	
	$lower_version = __('You have not installed the free version of GoSMTP. GoSMTP Pro depends on the free version, so you must install it first in order to use GoSMTP Pro.');
	
	if(!empty($go_version) && version_compare($go_version, '1.0.7', '<')){
		$lower_version = __('You are using an older version of the free version of GoSMTP, please update GoSMTP to work without any issues');
	}

	echo '<div class="notice notice-error">
		<p style="font-size:16px;">'.esc_html($lower_version).' <a href="'.admin_url('plugin-install.php?s=gosmtp&tab=search').'" class="button button-primary">Install/Update Now</a></p>
	</div>';
}

function gosmtp_pro_add_menu(){
	add_menu_page('GoSMTP Settings', 'GoSMTP', 'activate_plugins', 'gosmtp', 'gosmtp_pro_menu_page');
}

function gosmtp_pro_menu_page(){
	echo '<div style="color: #333;padding: 50px;text-align: center;">
		<h1 style="font-size: 2em;margin-bottom: 10px;">GoSMTP Free version is not installed / outdated!</h>
		<p style=" font-size: 16px;margin-bottom: 20px; font-weight:400;">GoSMTP Pro depends on the free version of GoSMTP, so you need to install / update the free version first.</p>
		<a href="'.admin_url('plugin-install.php?s=gosmtp&tab=search').'" style="text-decoration: none;font-size:16px;">Install/Update Now</a>
	</div>';
}