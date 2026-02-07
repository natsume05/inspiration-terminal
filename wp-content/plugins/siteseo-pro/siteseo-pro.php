<?php
/*
Plugin Name: SiteSEO Pro
Plugin URI: https://siteseo.io/
Description: This plugin handles On Page SEO, Content Analysis, Social Previews, Google Preview, Hyperlink Analysis, Image Analysis, Home Page Monitor, Schemas for various type of posts.
Author: Softaculous
Version: 1.3.5
Author URI: https://siteseo.io/
License: GPLv2
Text Domain: siteseo-pro
Domain Path: /languages
Requires Plugins: siteseo
*/

// We need the ABSPATH
if (!defined('ABSPATH')) exit;

if(!function_exists('add_action')){
	echo 'You are not allowed to access this page directly.';
	exit;
}

// If SITESEO_PRO_VERSION exists then the plugin is loaded already !
if(defined('SITESEO_PRO_VERSION')){
	return;
}

define('SITESEO_PRO_FILE', __FILE__);
define('SITESEO_PRO_VERSION', '1.3.5');
define('SITESEO_PRO_DIR', plugin_dir_path(SITESEO_PRO_FILE));
define('SITESEO_PRO_URL', plugin_dir_url(SITESEO_PRO_FILE));
define('SITESEO_PRO_AI_API', 'https://s2.softaculous.com/a/softai/ai.php');
define('SITESEO_PRO_AI_BUY', 'https://www.softaculous.com/clients?ca=softai_buy');
define('SITESEO_PRO_ASSETS_PATH', SITESEO_PRO_DIR . 'assets');
define('SITESEO_PRO_ASSETS_URL', SITESEO_PRO_URL . 'assets');
define('SITESEO_PREMIUM', plugin_basename(__FILE__));

if(!defined('SITESEO_API')){
	define('SITESEO_API', 'https://api.siteseo.io/');
}

include_once SITESEO_PRO_DIR . 'functions.php';

function siteseopro_autoloader($class){

	if(!preg_match('/^SiteSEOPro\\\(.*)/is', $class, $m)){
		return;
	}
	
	$m[1] = str_replace('\\', '/', $m[1]);

	// Include file
	if(file_exists(SITESEO_PRO_DIR . 'main/'.strtolower($m[1]).'.php')){
		include_once(SITESEO_PRO_DIR.'main/'.strtolower($m[1]).'.php');
	}
}

spl_autoload_register('siteseopro_autoloader');

register_activation_hook( __FILE__, '\SiteSEOPro\Install::activate');
register_deactivation_hook( __FILE__, '\SiteSEOPro\Install::deactivate');
register_uninstall_hook(__FILE__, '\SiteSEOPro\Install::uninstall');

add_action('plugins_loaded', 'sitseopro_load_plugin');

// Prevent update of Siteseo free
// This also work for auto update
add_filter('site_transient_update_plugins', 'siteseo_pro_disable_manual_update_for_plugin', 20);
add_filter('pre_site_transient_update_plugins', 'siteseo_pro_disable_manual_update_for_plugin', 20);

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'siteseo_pro_update_free_after_pro', 20, 2);

// Check on update
function sitseopro_check_updates(){

	$current_version = get_option('siteseo_pro_version');
	$version = (int) str_replace('.', '', $current_version);
	
	// No update required
	if($current_version == SITESEO_PRO_VERSION){
		return true;
	}

	// Is it first run ?
	if(empty($current_version)){
		\SiteSEOPro\Install::activate();
		return;
	}

	// Check version less
	if($version < 118){
		\SiteSEOPro\Install::activate();
	}
	
	// Till 1.1.9 we used to update free using the Pro version so we need to remove the scheduler
	if(wp_next_scheduled('check_plugin_updates-siteseo')){
		wp_clear_scheduled_hook('check_plugin_updates-siteseo');
	}

	update_option('siteseo_pro_version', SITESEO_PRO_VERSION);
}

function sitseopro_load_plugin(){
	global $siteseo;

	if(empty($siteseo)){
		$siteseo = new StdClass();
	}

	$siteseo->pro = get_option('siteseo_pro_options', []);

	siteseo_pro_load_license();

	//check updates
	sitseopro_check_updates();

	// Check for updates
	if(!defined('SITEPAD')){
		include_once(SITESEO_PRO_DIR . 'main/plugin-update-checker.php');
		$siteseo_updater = SiteSEO_PucFactory::buildUpdateChecker(siteseo_pro_api_url().'/updates.php?version='.SITESEO_PRO_VERSION, SITESEO_PRO_FILE);
		
		// Add the license key to query arguments
		$siteseo_updater->addQueryArgFilter('siteseo_pro_updater_filter_args');
		
		// Show the text to install the license key
		add_filter('puc_manual_final_check_link-siteseo-pro', 'siteseo_pro_updater_check_link', 10, 1);
	}

	// Cron Action
	add_action('siteseo_404_cleanup', 'siteseo_404_cleanup');
	add_action('siteseo_send_404_report_email', '\SiteSEOPro\RedirectManager::send_weekly_report');

	if(wp_doing_ajax()){
		\SiteSEOPro\Ajax::hooks();
		return;
	}
	
	//breadcrumbs
	add_action('init', '\SiteSEOPro\RegisterBlocks::init', 999);	
	add_action('init', '\SiteSEOPro\Breadcrumbs::enable_breadcrumbs');
	
	add_filter('robots_txt', '\SiteSEOPro\Settings\Util::get_virtual_robots', 10, 2);
	
	add_action('init', '\SiteSEOPro\Admin::local_business_block');
	
	if(!empty($siteseo->pro['enable_rss_sitemap']) && !empty($siteseo->pro['toogle_state_rss_sitemap']) && !empty($siteseo->pro['rss_sitemap_posts'])){
		add_action('init', '\SiteSEOPro\RssSitemap::add_rewrite_rules', 20);
		add_action('template_redirect', '\SiteSEOPro\RssSitemap::handle_sitemap_requests', 0);
	}

	if(!empty($siteseo->pro['toggle_state_llm_txt'])){
		add_action('init', '\SiteSEOPro\LLMTxtFile::add_rewrite_rules', 20);
		add_action('template_redirect', '\SiteSEOPro\LLMTxtFile::handle_llm_requests', 0);
		add_action('init', '\SiteSEOPro\LLMTxtFile::init');
	}

	if(is_admin()){
		\SiteSEOPro\Admin::init();
		return;
	}

	// Actions
	// TODO: Will need to shift these actions to a seperate file as the code grows.
	add_action('init', '\SiteSEOPro\RssSitemap::settings');
	add_action('wp_head', '\SiteSEOPro\Tags::dublin_core', 2);
	add_filter('wp_robots', '\SiteSEOPro\Tags::woocommerce_index_tags', 9999);
	add_filter('wp_robots', '\SiteSEOPro\Tags::kkart_index_tags', 9999);
	add_filter('wp_head', '\SiteSEOPro\Tags::woocommerce');
	add_filter('wp_head', '\SiteSEOPro\Tags::kkart');
	add_action('wp_head', '\SiteSEOPro\Tags::easy_digital_downloads', 2);
	add_action('wp_head', '\SiteSEOPro\Tags::structured_data');
	add_action('template_redirect', '\SiteSEOPro\RedirectManager::handle_404_request');
	add_action('wp_head', '\SiteSEOPro\StructuredData::render');
	
}

// Deleting 404 older than 30 days
function siteseo_404_cleanup(){
	global $wpdb, $siteseo;

	// Clear Logs
	if(!empty($siteseo->pro['clean_404_logs'])){
		$wpdb->query("DELETE FROM `".$wpdb->prefix."siteseo_redirect_logs` WHERE `timestamp` < DATE_SUB(NOW(), INTERVAL 30 DAY)");
	}
}
