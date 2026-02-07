<?php

// Are we being accessed directly ?
if(!defined('LOGINIZER_PRO_VERSION')) {
	exit('Hacking Attempt !');
}

// Ok so we are now ready to go
register_activation_hook(LOGINIZER_PRO_FILE, 'loginizer_pro_activation');

// Prevent update of loginizer free
// This also work for auto update
add_filter('site_transient_update_plugins', 'loginizer_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'loginizer_pro_disable_manual_update_for_plugin');

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'loginizer_pro_update_free_after_pro', 10, 2);

add_action('plugins_loaded', 'loginizer_pro_load_plugin');
function loginizer_pro_load_plugin(){
	global $loginizer;
	
	if(empty($loginizer)){
		$loginizer = [];
	}
	
	loginizer_pro_load_license();
	
	// Check if the installed version is outdated
	loginizer_pro_update_checker();
	
	// Check for updates
	include_once(LOGINIZER_PRO_DIR.'/updater/plugin-update-checker.php');
	$loginizer_updater = Loginizer_PucFactory::buildUpdateChecker(loginizer_pro_api_url().'/updates.php?version='.LOGINIZER_PRO_VERSION, LOGINIZER_PRO_FILE);
	
	// Add the license key to query arguments
	$loginizer_updater->addQueryArgFilter('loginizer_updater_filter_args');
	
	// Show the text to install the license key
	add_filter('puc_manual_final_check_link-loginizer-security', 'loginizer_updater_check_link', 10, 1);
	
	// Nag informing the user to install the free version.
	if(current_user_can('activate_plugins')){
		add_action('admin_notices', 'loginizer_pro_free_version_nag', 9);
		add_action('admin_menu', 'loginizer_pro_add_menu', 9);
	}
	
	$is_network_wide = loginizer_pro_is_network_active('loginizer-security');
	$_ls_version = get_option('loginizer_version');
	$req_free_update = !empty($_ls_version) && version_compare($_ls_version, '1.8.9', '<');
	
	if($is_network_wide){
		$ls_free_installed = get_site_option('loginizer_free_installed');
	}else{
		$ls_free_installed = get_option('loginizer_free_installed');
	}
	
	if(!empty($ls_free_installed)){
		return;
	}
	
	// Include the necessary stuff
	include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	include_once(ABSPATH . 'wp-admin/includes/file.php');
	
	if( file_exists( WP_PLUGIN_DIR . '/loginizer/loginizer.php' ) && is_plugin_inactive( '/loginizer/loginizer.php' ) && empty($req_free_update) ) {
		
		if($is_network_wide){
			update_site_option('loginizer_free_installed', time());
		}else{
			update_option('loginizer_free_installed', time());
		}
		
		activate_plugin('/loginizer/loginizer.php', '', $is_network_wide);
		remove_action('admin_notices', 'loginizer_pro_free_version_nag', 9);
		remove_action('admin_menu', 'loginizer_pro_add_menu', 9);
		return;
	}
	
	// Includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
	include_once(ABSPATH . 'wp-admin/includes/misc.php');
	include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

	// Filter to prevent the activate text
	add_filter('install_plugin_complete_actions', 'loginizer_pro_prevent_activation_text', 10, 3);

	$upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
	
	// Upgrade the plugin to the latest version of free already installed.
	if(!empty($req_free_update) && file_exists( WP_PLUGIN_DIR . '/loginizer/loginizer.php' )){
		$installed = $upgrader->upgrade('loginizer/loginizer.php');
	}else{
		$installed = $upgrader->install('https://downloads.wordpress.org/plugin/loginizer.zip');
	}
	
	if(!is_wp_error($installed) && $installed){
		
		if($is_network_wide){
			update_site_option('loginizer_free_installed', time());
		}else{
			update_option('loginizer_free_installed', time());
		}
		
		activate_plugin('loginizer/loginizer.php', '', $is_network_wide);
		remove_action('admin_notices', 'loginizer_pro_free_version_nag', 9);
		remove_action('admin_menu', 'loginizer_pro_add_menu', 9);
	}
}

// Do not shows the activation text if 
function loginizer_pro_prevent_activation_text($install_actions, $api, $plugin_file){
	if($plugin_file == 'loginizer/loginizer.php'){
		return array();
	}

	return $install_actions;
}

function loginizer_pro_free_version_nag(){
	
	$ls_version = get_option('loginizer_version');
	
	$lower_version = __('You have not installed the free version of Loginizer. Loginizer Pro depends on the free version, so you must install it first in order to use Loginizer Pro.');
	
	if(!empty($ls_version) && version_compare($ls_version, '1.8.9', '<')){
		$lower_version = __('You are using an older version of the free version of Loginizer, please update Loginizer to work without any issues');
	}

	echo '<div class="notice notice-error">
		<p style="font-size:16px;">'.esc_html($lower_version).' <a href="'.admin_url('plugin-install.php?s=loginizer&tab=search').'" class="button button-primary">Install/Update Now</a></p>
	</div>';
}

function loginizer_pro_add_menu(){
	add_menu_page('Loginizer', 'Loginizer Security', 'activate_plugins', 'loginizer-security', 'loginizer_pro_menu_page');
}

function loginizer_pro_menu_page(){
	echo '<div style="color: #333;padding: 50px;text-align: center;">
		<h1 style="font-size: 2em;margin-bottom: 10px;">Loginizer Free version is not installed / outdated!</h>
		<p style=" font-size: 16px;margin-bottom: 20px; font-weight:400;">Loginizer Pro depends on the free version of Loginizer, so you need to install / update the free version first.</p>
		<a href="'.admin_url('plugin-install.php?s=loginizer&tab=search').'" style="text-decoration: none;font-size:16px;">Install/Update Now</a>
	</div>';
}
