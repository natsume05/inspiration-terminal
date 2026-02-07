<?php

// Are we being accessed directly ?
if(!defined('SPEEDYCACHE_PRO_VERSION')) {
	exit('Hacking Attempt !');
}

// Ok so we are now ready to go
register_activation_hook(SPEEDYCACHE_PRO_FILE, 'speedycache_pro_activate');

// Prevent update of speedycache free
// This also work for auto update
add_filter('site_transient_update_plugins', 'speedycache_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'speedycache_pro_disable_manual_update_for_plugin');

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'speedycache_pro_update_free_after_pro', 10, 2);

if(!class_exists('SpeedyCache')){
#[\AllowDynamicProperties]
class SpeedyCache{}
}

add_action('plugins_loaded', 'speedycache_pro_load_plugin');
function speedycache_pro_load_plugin(){
	global $speedycache;
	
	if(empty($speedycache)){
		$speedycache = new \SpeedyCache();
	}
	
	speedycache_pro_load_license();
	
	// Check if the installed version is outdated
	speedycache_pro_update_check();
	
	// Check for updates
	include_once(SPEEDYCACHE_PRO_DIR.'/main/plugin-update-checker.php');
	$speedycache_updater = SpeedyCache_PucFactory::buildUpdateChecker(speedycache_pro_api_url().'/updates.php?version='.SPEEDYCACHE_PRO_VERSION, SPEEDYCACHE_PRO_FILE);
	
	// Add the license key to query arguments
	$speedycache_updater->addQueryArgFilter('speedycache_pro_updater_filter_args');
	
	// Show the text to install the license key
	add_filter('puc_manual_final_check_link-speedycache-pro', 'speedycache_pro_updater_check_link', 10, 1);
	
	// Nag informing the user to install the free version.
	if(current_user_can('activate_plugins')){
		add_action('admin_notices', 'speedycache_pro_free_version_nag', 9);
		add_action('admin_menu', 'speedycache_pro_add_menu', 9);
	}
	
	$is_network_wide = speedycache_pro_is_network_active('speedycache-pro');
	$_sc_version = get_option('speedycache_version');
	$req_free_update = !empty($_sc_version) && version_compare($_sc_version, '1.2.0', '<');
	
	if($is_network_wide){
		$sc_free_installed = get_site_option('speedycache_free_installed');
	}else{
		$sc_free_installed = get_option('speedycache_free_installed');
	}
	
	if(!empty($sc_free_installed)){
		return;
	}
	
	// Include the necessary stuff
	include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	include_once(ABSPATH . 'wp-admin/includes/file.php');
	
	if( file_exists( WP_PLUGIN_DIR . '/speedycache/speedycache.php' ) && is_plugin_inactive( '/speedycache/speedycache.php' ) && empty($req_free_update) ) {
		
		if($is_network_wide){
			update_site_option('speedycache_free_installed', time());
		}else{
			update_option('speedcache_free_installed', time());
		}
		
		activate_plugin('/speedycache/speedycache.php', '', $is_network_wide);
		remove_action('admin_notices', 'speedycache_pro_free_version_nag', 9);
		remove_action('admin_menu', 'speedycache_pro_add_menu', 9);
		return;
	}

	// Includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
	include_once(ABSPATH . 'wp-admin/includes/misc.php');
	include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

	// Filter to prevent the activate text
	add_filter('install_plugin_complete_actions', 'speedycache_pro_prevent_activation_text', 10, 3);

	$upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());

	// Upgrade the plugin to the latest version of free already installed.
	if(!empty($req_free_update) && file_exists( WP_PLUGIN_DIR . '/speedycache/speedycache.php' )){
		$installed = $upgrader->upgrade('speedycache/speedycache.php');
	}else{
		$installed = $upgrader->install('https://downloads.wordpress.org/plugin/speedycache.zip');
	}
	
	if(!is_wp_error($installed) && $installed){
		
		if($is_network_wide){
			update_site_option('speedycache_free_installed', time());
		}else{
			update_option('speedycache_free_installed', time());
		}
		
		activate_plugin('speedycache/speedycache.php', '', $is_network_wide);
		remove_action('admin_notices', 'speedycache_pro_free_version_nag', 9);
		remove_action('admin_menu', 'speedycache_pro_add_menu', 9);
	}
}

// Do not shows the activation text if 
function speedycache_pro_prevent_activation_text($install_actions, $api, $plugin_file){
	if($plugin_file == 'speedycache/speedycache.php'){
		return array();
	}

	return $install_actions;
}

function speedycache_pro_free_version_nag(){
	
	$sc_version = get_option('speedycache_version');
	
	$lower_version = __('You have not installed/activated the free version of SpeedCache. SpeedyCache Pro depends on the free version, so you must install/activate it first in order to use SpeedyCache Pro.');
	$btn_text = __('Install / Activate Now');
	
	if(!empty($sc_version) && version_compare($sc_version, '1.2.0', '<')){
		$lower_version = __('You are using an older version of the free version of SpeedyCache, please update SpeedyCache to work without any issues');
		$btn_text = __('Update Now');
	}

	echo '<div class="notice notice-error">
		<p style="font-size:16px;">'.esc_html($lower_version).' <a href="'.admin_url('plugin-install.php?s=speedycache&tab=search').'" class="button button-primary">'.esc_html($btn_text).'</a></p>
	</div>';
}

function speedycache_pro_add_menu(){
	add_menu_page('SpeedyCache', 'SpeedyCache Pro', 'activate_plugins', 'speedycache-pro', 'speedycache_pro_menu_page');
}

function speedycache_pro_menu_page(){
	echo '<div style="color: #333;padding: 50px;text-align: center;">
		<h1 style="font-size: 2em;margin-bottom: 10px;">SpeedyCache Free version is not installed / outdated!</h>
		<p style=" font-size: 16px;margin-bottom: 20px; font-weight:400;">SpeedyCache Pro depends on the free version of SpeedyCache, so you need to install / update the free version first.</p>
		<a href="'.admin_url('plugin-install.php?s=speedycache&tab=search').'" style="text-decoration: none;font-size:16px;">Install/Update Now</a>
	</div>';
}