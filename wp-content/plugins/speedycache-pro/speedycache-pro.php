<?php
/*
Plugin Name: SpeedyCache Pro
Plugin URI: https://speedycache.com
Description: SpeedyCache is a plugin that helps you reduce the load time of your website by means of caching, minification, and compression of your website.
Version: 1.3.7
Author: Softaculous Team
Author URI: https://speedycache.com/
Text Domain: speedycache
Require Plugins: speedycache
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/*
* SPEEDYCACHE
* https://speedycache.com/
* (c 2024) SpeedyCache Team
*/

/*
SpeedyCache is fork of WP Fastest Cache :
https://wordpress.org/plugins/wp-fastest-cache/
Copyright (C) 2013 Emre Vona

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.	
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

// We need the ABSPATH
if(!defined('ABSPATH')) exit;

if(!function_exists('add_action')){
	echo 'You are not allowed to access this page directly.';
	exit;
}

// If SPEEDYCACHE_PRO_VERSION exists then the plugin is loaded already !
if(defined('SPEEDYCACHE_PRO_VERSION')){
	return;
}

// Constants
define('SPEEDYCACHE_PRO', plugin_basename(__FILE__));
define('SPEEDYCACHE_PRO_FILE', __FILE__);
define('SPEEDYCACHE_PRO_VERSION', '1.3.7');
define('SPEEDYCACHE_PRO_DIR', dirname(__FILE__));
define('SPEEDYCACHE_PRO_BASE', 'speedycache-pro/speedycache-pro.php');
define('SPEEDYCACHE_PRO_BASE_NAME', basename(SPEEDYCACHE_PRO_DIR));
define('SPEEDYCACHE_PRO_URL', plugins_url('', __FILE__));

if(!defined('SPEEDYCACHE_API')){
	define('SPEEDYCACHE_API', 'https://api.speedycache.com/');
}

$_tmp_plugins = get_option('active_plugins', []);
$_sc_version = get_option('speedycache_version');

include_once SPEEDYCACHE_PRO_DIR . '/functions.php';

if(
	!defined('SITEPAD') && (
	!(in_array('speedycache/speedycache.php', $_tmp_plugins) || 
	speedycache_pro_is_network_active('speedycache')) || 
	!file_exists(WP_PLUGIN_DIR . '/speedycache/speedycache.php') || 
	(!empty($_sc_version) && version_compare($_sc_version, '1.2.0', '<')))
){
	include_once(SPEEDYCACHE_PRO_DIR .'/upgrader.php');
	return;
}

include_once(__DIR__ . '/init.php');