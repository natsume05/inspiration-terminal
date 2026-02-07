<?php

// Are we being accessed directly ?
if(!defined('ABSPATH')) {
	exit('Hacking Attempt !');
}

// Is called when the ADMIN enables the plugin
function speedycache_pro_activate(){
	global $speedycache;
	
	if(empty($speedycache)){
		$speedycache = new \SpeedyCache();
	}

	$speedycache->options = get_option('speedycache_options', []);
	$speedycache->options['minify_html'] = true;
	$speedycache->options['minify_js'] = true;
	$speedycache->options['render_blocking'] = true;

	update_option('speedycache_options', $speedycache->options);
	update_option('speedycache_pro_version', SPEEDYCACHE_PRO_VERSION);
}

// Load license data
function speedycache_pro_load_license($parent = 0){
	global $speedycache, $lic_resp, $sitepad;
	
	$license_field = 'speedycache_license';
	$license_api_url = SPEEDYCACHE_API;
	
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
				return speedycache_pro_load_license(1);
			}
		}
	}
	
	if(!empty($lic['license'])){
		$speedycache->license = $lic;
	}

	// For sitepad users
	if(defined('SITEPAD') && empty($speedycache->license)){
		$license = !empty($sitepad['license']) ? $sitepad['license']: (isset($sitepad['server_license']) ? $sitepad['server_license'] : []);
		$license['active'] = isset($license['active']) ? $license['active'] : (isset($license['status']) ? $license['status'] : '');
		$speedycache->license = $license;
	}
}

add_filter('softaculous_pro_products', 'speedycache_softaculous_pro_products', 10, 1);
function speedycache_softaculous_pro_products($r = []){
	$r['speedycache'] = 'speedycache';
	return $r;
}

// Add our license key if ANY
function speedycache_pro_updater_filter_args($queryArgs) {
	
	global $speedycache;

	if ( !empty($speedycache->license['license']) ) {
		$queryArgs['license'] = $speedycache->license['license'];
	}

	$queryArgs['url'] = rawurlencode(site_url());

	return $queryArgs;
}

// Handle the Check for update link and ask to install license key
function speedycache_pro_updater_check_link($final_link){
	
	global $speedycache;
	
	if(empty($speedycache->license['license'])){
		return '<a href="'.admin_url('admin.php?page=speedycache_license').'">Install SpeedyCache Pro License Key</a>';
	}
	
	return $final_link;
}

function speedycache_pro_is_network_active($pluign){
	$is_network_wide = false;
	
	// Handling network site
	if(!is_multisite()){
		return $is_network_wide;
	}
	
	$_tmp_plugins = get_site_option('active_sitewide_plugins');

	if(!empty($_tmp_plugins) && preg_grep('/.*\/'.$pluign.'\.php$/', array_keys($_tmp_plugins))){
		$is_network_wide = true;
	}
	
	return $is_network_wide;
}

// Prevent update of SpeedyCache free
function speedycache_pro_get_free_version_num(){
		
	if(defined('SPEEDYCACHE_VERSION')){
		return SPEEDYCACHE_VERSION;
	}
	
	// In case of SpeedyCache deactive
	return speedycache_pro_file_get_version_num('speedycache/speedycache.php');
}

// Prevent update of SpeedyCache free
function speedycache_pro_file_get_version_num($plugin){
	
	// In case of SpeedyCache deactive
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/'.$plugin);

	if(empty($plugin_data)){
		return false;
	}

	return $plugin_data['Version'];

}

// Prevent update of SpeedyCache free
function speedycache_pro_disable_manual_update_for_plugin($transient){
	$plugin = 'speedycache/speedycache.php';
	
	// Is update available?
	if(!isset($transient->response) || !isset($transient->response[$plugin])){
		return $transient;
	}
	
	$free_version = speedycache_pro_get_free_version_num();
	$pro_version = SPEEDYCACHE_PRO_VERSION;
	
	if(!empty($GLOBALS['speedycache_pro_is_upgraded'])){
		$pro_version = speedycache_pro_file_get_version_num('speedycache-pro/speedycache-pro.php');
	}

	// Update the SpeedyCache version to the equivalent of Pro version
	if(!empty($pro_version) && version_compare($free_version, $pro_version, '<')){
		$transient->response[$plugin]->new_version = $pro_version;
		$transient->response[$plugin]->package = 'https://downloads.wordpress.org/plugin/speedycache.'.$pro_version.'.zip';
	}else{
		unset($transient->response[$plugin]);
	}

	return $transient;
}

// Auto update free version after update pro version
function speedycache_pro_update_free_after_pro($upgrader_object, $options){
	
	// Check if the action is an update for the plugins
	if($options['action'] != 'update' || $options['type'] != 'plugin'){
		return;
	}
		
	// Define the slugs for the free and pro plugins
	$free_slug = 'speedycache/speedycache.php'; 
	$pro_slug = 'speedycache-pro/speedycache-pro.php';

	// Check if the pro plugin is in the list of updated plugins
	if( 
		(isset($options['plugins']) && in_array($pro_slug, $options['plugins']) && !in_array($free_slug, $options['plugins'])) ||
		(isset($options['plugin']) && $pro_slug == $options['plugin'])
	){
	
		// Trigger the update for the free plugin
		$current_version = speedycache_pro_get_free_version_num();
		
		if(empty($current_version)){
			return;
		}
		
		$GLOBALS['speedycache_pro_is_upgraded'] = true;
		
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

// Looks if SpeedyCache just got updated
function speedycache_pro_update_check(){

	$sql = array();
	$current_version = get_option('speedycache_pro_version');
	$version = (int) str_replace('.', '', $current_version);

	// No update required
	if($current_version == SPEEDYCACHE_PRO_VERSION){
		return true;
	}

	// If the user was using SpeedyCache Pro before seperation
	// then we need to clear the cache as we have updated the location of assets of the Pro version.
	if(empty($current_version) || (!empty($current_version) && version_compare($current_version, '1.2.0', '<'))){
		$free_version = get_option('speedycache_version');

		if(!empty($free_version)){
			$host = !empty($_SERVER['HTTP_HOST']) ? sanitize_text_field($_SERVER['HTTP_HOST']) : '';
			$desk_cache = glob(WP_CONTENT_DIR . '/cache/speedycache/'. $host .'/all/*', GLOB_ONLYDIR);
			$mobile_cache = glob(WP_CONTENT_DIR . '/cache/speedycache/'. $host .'/mobile-cache/*', GLOB_ONLYDIR);
			$deletable_dir = [];
			
			if(!empty($desk_cache)){
				$deletable_dir = $desk_cache;
			}

			if(!empty($mobile_cache)){
				$deletable_dir = array_merge($mobile_cache, $deletable_dir);
			}

			if(!empty($deletable_dir)){
				global $wp_filesystem;
				
				include_once(ABSPATH . 'wp-admin/includes/file.php');
				WP_Filesystem();
				
				foreach($deletable_dir as $dir){
					if(method_exists($wp_filesystem, 'delete')){
						$wp_filesystem->delete($dir, true);
					}
				}
			}
		}

		speedycache_pro_activate();
	}
	
	// Handling moving of object cache settings
	if(!empty($current_version) && version_compare($current_version, '1.3.6', '<')){
		$config_file = WP_CONTENT_DIR .'/.speedycache-object.dat';
		
		if(file_exists($config_file)){
			$object_content = file_get_contents($config_file);
			$config_dir = WP_CONTENT_DIR . '/speedycache-config';

			$config_dir_exists = true;
			if(!file_exists($config_dir)){
				if(!mkdir($config_dir)){
					$config_dir_exists = false;
				}
			}

			if(!empty($config_dir_exists)){
				file_put_contents($config_dir.'/.speedycache-object.php', "<?php exit(); \n". $object_content);
				file_put_contents($config_dir.'/.htaccess', 'deny from all'); // Making sure the folder is not accessible
				unlink($config_file); // We only delete the older file if we are sure we shifted the file.
				
			}
		}

		// We will only update the object.php if we were succesfull in shifting the config file.
		if(defined('SPEEDYCACHE_OBJECT_CACHE') && !empty($config_dir_exists) && file_exists(WP_CONTENT_DIR .'/object-cache.php')){
			if(class_exists('\SpeedyCache\ObjectCache') && method_exists('\SpeedyCache\ObjectCache', 'update_file')){
				\SpeedyCache\ObjectCache::update_file();
			}
		}
	}
	
	$is_network_wide = speedycache_pro_is_network_active('speedycache-pro');
	
	if($is_network_wide){
		$free_ins = get_site_option('speedycache_free_installed');
	}else{
		$free_ins = get_option('speedycache_free_installed');
	}
		
	// If plugin runing reached here it means SpeedyCache free installed 
	if(empty($free_ins)){
		if($is_network_wide){
			update_site_option('speedycache_free_installed', time());
		}else{
			update_option('speedycache_free_installed', time());
		}
	}
	
	// Re-init NAG dismiss expire time
	update_option('speedycache_pro_older_pro_version_nag', time());
	update_option('speedycache_pro_older_free_version_nag', time());

	// Save the new Version
	update_option('speedycache_pro_version', SPEEDYCACHE_PRO_VERSION);
}
	
function speedycache_pro_api_url($main_server = 0, $suffix = 'speedycache'){
	
	global $speedycache;
	
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
	// This is to give time to the mirror server to replicate the data.
	if(!empty($main_server) || empty($speedycache->license['last_edit']) || 
		(!empty($speedycache->license['last_edit']) && (time() - 3600) < $speedycache->license['last_edit'])
	){
		$mirror = SPEEDYCACHE_API;
	}
	
	if(!empty($suffix)){
		$mirror = str_replace('/softwp', '/'.$suffix, $mirror);
	}
	
	return $mirror;
	
}

function speedycache_pro_update_notice_filter($plugins = []){
	$plugins['speedycache-pro/speedycache-pro.php'] = 'SpeedyCache Pro';
	return $plugins;
}