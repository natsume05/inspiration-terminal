<?php
/*
* BACKUPLY
* https://backuply.com
* (c) Backuply Team
*/


if(!defined('BACKUPLY_PRO')) {
	die('HACKING ATTEMPT!');
}

// Returns Backups Locations for PRO Version
function backuply_get_pro_backups() {
	return array(
		'softftpes' => 'FTPS',
		'softsftp' => 'SFTP',
		'dropbox' => 'Dropbox',
		'aws' => 'Amazon S3', 
		'onedrive' => 'Microsoft OneDrive', 
		'webdav' => 'WebDAV',
		'caws' => 'S3 Compatible'
	);
}

// Handles the http request from Custom cron
// TODO: This function is not being used
function backuply_custom_cron_action() {
	global $backuply;
	
	if(!backuply_verify_self(backuply_optreq('backuply_key'))) {
		backuply_status_log('Security Check Failed', 'error');
		die();
	}
	
	$backuply['auto_backup'] = true;
	backuply_backup_rotation();
	
	if($cron_backuply_backup_information = get_option('backuply_cron_settings')){
		update_option('backuply_status', $cron_backuply_backup_information);
		backuply_backup_execute();
	}
}

// Adds a Wp-Cron for autobackup
if(!function_exists('backuply_add_auto_backup_schedule')){
function backuply_add_auto_backup_schedule($schedule = '') {

	if(empty($schedule)){
		$schedule = backuply_optpost('backuply_cron_schedule');
	}
	
	if (!wp_next_scheduled( 'backuply_auto_backup_cron' )){
		wp_schedule_event(time(), $schedule, 'backuply_auto_backup_cron');
	}
}
}

// Initiates auto backup
if(!function_exists('backuply_auto_backup_execute')){
function backuply_auto_backup_execute(){
	global $backuply;
	
	//$backuply['auto_backup'] = true;

	// We don't want the Backup to run in case a Auto Backup starts while restoring.
	if(file_exists(BACKUPLY_BACKUP_DIR.'/restoration/restoration.php')){
		error_log(__('Backuply: Can not start backup as restoration is going on', 'backuply-pro'));
		return;
	}

	backuply_create_log_file();

	if($auto_backup_settings = get_option('backuply_cron_settings')){
		$auto_backup_settings['auto_backup'] = true;
		update_option('backuply_status', $auto_backup_settings);
		backuply_backup_execute();
	}
}
}

// Rotate the backups
if(!function_exists('backuply_backup_rotation')){
function backuply_backup_rotation() {
	global $backuply;

	if(empty($backuply['status']['backup_rotation'])) {
		return;
	}

	$backup_info = backuply_get_backups_info();
	
	if(empty($backup_info)) {
		return;
	}
	
	$backup_info = array_filter($backup_info, 'backuply_filter_backups_on_loc');
	usort($backup_info, 'backuply_oldest_backup');
	
	if(count($backup_info) > $backuply['status']['backup_rotation']) {
		if(empty($backup_info[0])) {
			return;
		}
		
		backuply_log('Deleting Files because of Backup rotation');
		backuply_status_log('Deleting backup because of Backup rotation', 39);
		
		$extra_backups = count($backup_info) - $backuply['status']['backup_rotation'];
		
		if($extra_backups > 0) {
			for($i = 0; $i < $extra_backups; $i++) {
				backuply_delete_backup($backup_info[$i]->name .'.'. $backup_info[$i]->ext);
			}
		}
	}
}
}

// Returns backups based on location
function backuply_filter_backups_on_loc($backup) {
	global $backuply;
	
	if(!isset($backup->backup_location)){
		return ($backup->auto_backup);
	}
	
	return ($backuply['status']['backup_location'] == $backup->backup_location && $backup->auto_backup);
}

// Returns oldest backup
function backuply_oldest_backup($a, $b) {
	return (int) $a->btime - (int) $b->btime;
}

// Add our license key if ANY
function backuply_updater_filter_args($queryArgs){
	
	global $backuply;
	
	if (!empty($backuply['license']['license'])){
		$queryArgs['license'] = $backuply['license']['license'];
	}
	
	$queryArgs['url'] = rawurlencode(site_url());
	
	return $queryArgs;
}

// Handle the Check for update link and ask to install license key
function backuply_updater_check_link($final_link){
	
	global $backuply;
	
	if(empty($backuply['license']['license'])){
		return '<a href="'.admin_url('admin.php?page=backuply-license').'">Install Backuply Pro License Key</a>';
	}
	
	return $final_link;
}

// Prevent update of Backuply free
function backuply_pro_disable_manual_update_for_plugin($transient){
	$plugin = 'backuply/backuply.php';

	// Is update available?
	if(!isset($transient->response) || !isset($transient->response[$plugin])){
		return $transient;
	}

	$free_version = backuply_pro_get_free_version_num();
	$pro_version = BACKUPLY_PRO_VERSION;

	if(!empty($GLOBALS['backuply_pro_is_upgraded'])){
		$pro_version = backuply_pro_file_get_version_num('backuply-pro/backuply-pro.php');
	}

	// Update the Backuply version to the equivalent of Pro version
	if(!empty($pro_version) && version_compare($free_version, $pro_version, '<')){
		$transient->response[$plugin]->new_version = $pro_version;
		$transient->response[$plugin]->package = 'https://downloads.wordpress.org/plugin/backuply.'.$pro_version.'.zip';
	}else{
		unset($transient->response[$plugin]);
	}

	return $transient;
}

// Prevent update of Backuply free
function backuply_pro_get_free_version_num(){
		
	if(defined('BACKUPLY_VERSION')){
		return BACKUPLY_VERSION;
	}
	
	// In case of Backuply deactive
	return backuply_pro_file_get_version_num('backuply/backuply.php');
}

// Prevent update of Backuply free
function backuply_pro_file_get_version_num($plugin){
	
	// In case of Backuply deactive
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/'.$plugin);

	if(empty($plugin_data)){
		return false;
	}

	return $plugin_data['Version'];

}

// Auto update free version after update pro version
function backuply_pro_update_free_after_pro($upgrader_object, $options){
	
	// Check if the action is an update for the plugins
	if($options['action'] != 'update' || $options['type'] != 'plugin'){
		return;
	}
		
	// Define the slugs for the free and pro plugins
	$free_slug = 'backuply/backuply.php'; 
	$pro_slug = 'backuply-pro/backuply-pro.php';

	// Check if the pro plugin is in the list of updated plugins
	if( 
		(isset($options['plugins']) && in_array($pro_slug, $options['plugins']) && !in_array($free_slug, $options['plugins'])) ||
		(isset($options['plugin']) && $pro_slug == $options['plugin'])
	){
	
		// Trigger the update for the free plugin
		$current_version = backuply_pro_get_free_version_num();
		
		if(empty($current_version)){
			return;
		}
		
		$GLOBALS['backuply_pro_is_upgraded'] = true;
		
		// This will set the 'update_plugins' transient again
		wp_update_plugins();

		// Check for updates for the free plugin
		$update_plugins = get_site_transient('update_plugins');
		
		if(empty($update_plugins) || !isset($update_plugins->response[$free_slug]) || version_compare($update_plugins->response[$free_slug]->new_version, $current_version, '<=')){
			return;
		}
		
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
		
		$skin = wp_doing_ajax() ? new WP_Ajax_Upgrader_Skin() : null;
		
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

// Load license data
if(!function_exists('backuply_load_license')){
function backuply_load_license($parent = 0){
	global $backuply, $lic_resp;
	
	$license_field = 'backuply_license';
	$license_api_url = BACKUPLY_API;
	
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
				return backuply_load_license(1);
			}
		}
	}
	
	if(!empty($lic['license'])){
		$backuply['license'] = $lic;
	}
	
}
}

add_filter('softaculous_pro_products', 'backuply_softaculous_pro_products', 10, 1);
function backuply_softaculous_pro_products($r = []){
	$r['backuply'] = 'backuply';
	return $r;
}
	
function backuply_pro_api_url($main_server = 0, $suffix = 'backuply'){
	
	global $backuply;
	
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
	if(!empty($main_server) || empty($backuply['license']['last_edit']) || 
		(!empty($backuply['license']['last_edit']) && (time() - 3600) < $backuply['license']['last_edit'])
	){
		$mirror = BACKUPLY_API;
	}
	
	if(!empty($suffix)){
		$mirror = str_replace('/softwp', '/'.$suffix, $mirror);
	}
	
	return $mirror;
	
}

function backuply_pro_update_notice_filter($plugins = []){
	$plugins['backuply-pro/backuply-pro.php'] = 'Backuply Pro';

	return $plugins;
}
