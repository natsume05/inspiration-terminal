<?php
/*
Plugin Name: Backuply Pro
Plugin URI: https://backuply.com/
Description: Backuply is a Wordpress Backup plugin. Backups are the best form of security and safety a website can have.
Version: 1.5.1
Author: Softaculous
Author URI: https://backuply.com
Requires Plugins: backuply
Text Domain: backuply-pro
*/

// We need the ABSPATH
if(!defined('ABSPATH')) exit;

if(!function_exists('add_action')){
	echo 'You are not allowed to access this page directly.';
	exit;
}

// If BACKUPLY_PRO_VERSION exists then the plugin is loaded already !
if(defined('BACKUPLY_PRO_VERSION')){
	return;
}

define('BACKUPLY_PRO', plugin_basename(__FILE__));
define('BACKUPLY_PRO_FILE', __FILE__);

$bk_tmp_plugins = get_option('active_plugins');

if(!in_array('backuply/backuply.php', $bk_tmp_plugins)){
	add_action('plugins_loaded', 'backuply_pro_load_plugin');

	function backuply_pro_load_plugin(){

		// Nag informing the user to install the free version.
		if(current_user_can('activate_plugins')){
			add_action('admin_notices', 'backuply_pro_free_version_nag');
			add_action('admin_menu', 'backuply_pro_add_menu');

			if(!empty(get_option('backuply_free_installed'))){
				return;
			}

			update_option('backuply_free_installed', time());

			// Include the necessary stuff
			include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
			include_once(ABSPATH . 'wp-admin/includes/plugin.php');
			include_once(ABSPATH . 'wp-admin/includes/file.php');
			// Includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
			include_once(ABSPATH . 'wp-admin/includes/misc.php');
			include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

			// Filter to prevent the activate text
			add_filter('install_plugin_complete_actions', 'backuply_pro_prevent_activation_text', 10, 3);

			$upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
			$installed = $upgrader->install('https://downloads.wordpress.org/plugin/backuply.zip');

			if(!is_wp_error($installed) && $installed){
				$activate = activate_plugin('backuply/backuply.php');
				//wp_safe_redirect(admin_url('/'));
			}
		}
	}
	
	// Do not shows the activation text if 
	function backuply_pro_prevent_activation_text($install_actions, $api, $plugin_file){
		if($plugin_file == 'backuply/backuply.php'){
			return array();
		}

		return $install_actions;
	}

	function backuply_pro_free_version_nag(){
		echo '<div class="notice notice-error">
			<p style="font-size:16px;">You have not installed the free version of Backuply. Backuply Pro depends on the free version, so you must install it first in order to use Backuply. <a href="'.admin_url('plugin-install.php?s=backuply&tab=search').'" class="button button-primary">Install Now</a></p>
		</div>';
	}

	function backuply_pro_add_menu(){
		add_menu_page('Backuply Dahsboard', 'Backuply', 'activate_plugins', 'backuply', 'backuply_pro_menu_page');
	}

	function backuply_pro_menu_page(){
		echo '<div style="color: #333;padding: 50px;text-align: center;">
			<h1 style="font-size: 2em;margin-bottom: 10px;">Backuply Free version is not installed!</h>
			<p style=" font-size: 16px;margin-bottom: 20px; font-weight:400;">Backuply Pro depends on the free version of Backuply, so you need to install the free version first.</p>
			<a href="'.admin_url('plugin-install.php?s=backuply&tab=search').'" style="text-decoration: none;font-size:16px;">Install Now</a>
		</div>';
	}
	
	return;
}

include_once(__DIR__ . '/init.php');