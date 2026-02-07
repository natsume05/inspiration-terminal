<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

function loginizer_pro_activation(){
	update_option('loginizer_pro_version', LOGINIZER_PRO_VERSION);
}

function loginizer_pro_is_network_active($plugin){
	$is_network_wide = false;
	
	// Handling network site
	if(!is_multisite()){
		return $is_network_wide;
	}
	
	$_tmp_plugins = get_site_option('active_sitewide_plugins');

	if(!empty($_tmp_plugins) && preg_grep('/.*\/'.$plugin.'\.php$/', array_keys($_tmp_plugins))){
		$is_network_wide = true;
	}
	
	return $is_network_wide;
}

// Prevent update of loginizer free
function loginizer_pro_get_free_version_num(){
	if(defined('LOGINIZER_VERSION')){
		return LOGINIZER_VERSION;
	}
	
	// In case of loginizer deactive
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	$free_plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/loginizer/loginizer.php');
	
	if(empty($free_plugin_data)){
		return false;
	}
	
	return $free_plugin_data['Version'];
	
}

// Prevent update of loginizer free
function loginizer_pro_disable_manual_update_for_plugin($transient){
	$plugin = 'loginizer/loginizer.php';

	// Is update available?
	if(!isset($transient->response) || !isset($transient->response[$plugin])){
		return $transient;
	}

	$free_version = loginizer_pro_get_free_version_num();

	// Update the Loginizer version to the equivalent of Pro version
	if(version_compare($free_version, LOGINIZER_PRO_VERSION, '<')){
		$transient->response[$plugin]->package = 'https://downloads.wordpress.org/plugin/loginizer.'.LOGINIZER_PRO_VERSION.'.zip';
	}else{
		unset($transient->response[$plugin]);
	}

	return $transient;
}

// Auto update free version after update pro version
function loginizer_pro_update_free_after_pro($upgrader_object, $options) {

	// Check if the action is an update for the plugins
	if($options['action'] != 'update' || $options['type'] != 'plugin'){
		return;
	}

	// Define the slugs for the free and pro plugins
	$free_slug = 'loginizer/loginizer.php'; 
	$pro_slug = 'loginizer-security/loginizer-security.php';

	// Check if the pro plugin is in the list of updated plugins
	if( 
		(isset($options['plugins']) && in_array($pro_slug, $options['plugins'])) ||
		(isset($options['plugin']) && $pro_slug == $options['plugin'])
	){

		// Trigger the update for the free plugin
		$current_version = loginizer_pro_get_free_version_num();

		if(empty($current_version)){
			return;
		}

		// Check for updates for the free plugin
		include_once(ABSPATH . 'wp-admin/includes/plugin.php');
		$update_plugins = get_site_transient('update_plugins');
		
		if(isset($update_plugins->response[$free_slug])){
			$free_plugin_update = $update_plugins->response[$free_slug];

			// If there's an update available, proceed to update the free plugin
			if(version_compare($free_plugin_update->new_version, $current_version, '>')){
				require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
				$upgrader = new Plugin_Upgrader();
				$upgrader->upgrade($free_slug);
			}
		}
	}
}

function loginizer_pro_update_checker(){
	$current_version = get_option('loginizer_pro_version', '0.0');
	$version = (int) str_replace('.', '', $current_version);

	// No update required
	if($current_version == LOGINIZER_PRO_VERSION){
		return true;
	}
	
	$is_network_wide = loginizer_pro_is_network_active('loginizer-security');
	
	if($is_network_wide){
		$free_ins = get_site_option('loginizer_free_installed');
	}else{
		$free_ins = get_option('loginizer_free_installed');
	}
	
	// If plugin reached here it means Loginizer free installed
	if(empty($free_ins)){
		if($is_network_wide){
			update_site_option('loginizer_free_installed', time());
		}else{
			update_option('loginizer_free_installed', time());
		}
	}
	
	// To show the notice of Loginizer Social Auth
	if($current_version < 201){
		update_option('loginizer_pro_less_than_201', true);
	}

	update_option('loginizer_version_pro_nag', time());
	update_option('loginizer_version_free_nag', time());
	update_option('loginizer_pro_version', LOGINIZER_PRO_VERSION);
}

// Add our license key if ANY
function loginizer_updater_filter_args($queryArgs) {

	global $loginizer;
	
	if ( !empty($loginizer['license']['license']) ) {
		$queryArgs['license'] = $loginizer['license']['license'];
	}
	
	$queryArgs['url'] = rawurlencode(site_url());
	
	return $queryArgs;
}

// Handle the Check for update link and ask to install license key
function loginizer_updater_check_link($final_link){
	
	global $loginizer;
	
	if(empty($loginizer['license']['license'])){
		return '<a href="'.admin_url('admin.php?page=loginizer').'">Install License Key to Update</a>';
	}
	
	return $final_link;
}

function loginizer_pro_load_license($parent = 0){
	global $loginizer, $lic_resp, $sitepad;
	
	$license_field = 'loginizer_license';
	$license_api_url = LOGINIZER_API;
	
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
				return loginizer_pro_load_license(1);
			}
		}
	}
	
	if(!empty($lic['license'])){
		$loginizer['license'] = $lic;
	}
	
	// For sitepad users
	if(defined('SITEPAD') && empty($loginizer['license']) && !empty($sitepad)){
		
		$license = !empty($sitepad['license']) ? $sitepad['license'] : (!empty($sitepad['server_license']) ? $sitepad['server_license'] : array());
		
		if (!isset($license['active']) && isset($license['status'])) {
			$license['active'] = $license['status'];
		}

		$loginizer['license'] = $license;
	}
			
}

add_filter('softaculous_pro_products', 'loginizer_softaculous_pro_products', 10, 1);
function loginizer_softaculous_pro_products($r = []){
	$r['loginizer'] = 'loginizer';
	return $r;
}

function loginizer_pro_api_url($main_server = 0, $suffix = 'loginizer'){
	
	global $loginizer;
	
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
	if(!empty($main_server) || empty($loginizer['license']['last_edit']) || 
		(!empty($loginizer['license']['last_edit']) && (time() - 3600) < $loginizer['license']['last_edit'])
	){
		$mirror = LOGINIZER_API;
	}
	
	if(!empty($suffix)){
		$mirror = str_replace('/softwp', '/'.$suffix, $mirror);
	}

	return $mirror;
	
}

// List of Social Providers which suppports Loginizers keyless Social Auth.
function loginizer_pro_social_auth_providers(){
	return ['Google', 'GitHub', 'LinkedInOpenID', 'Twitter'];
}
