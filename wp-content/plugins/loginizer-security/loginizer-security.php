<?php
/*
Plugin Name: Loginizer Pro
Plugin URI: https://loginizer.com
Description: Loginizer is a WordPress plugin which helps you fight against bruteforce attack by blocking login for the IP after it reaches maximum retries allowed. You can blacklist or whitelist IPs for login using Loginizer.
Version: 2.0.4
Text Domain: loginizer
Author: Softaculous
Author URI: https://www.loginizer.com
License: LGPLv2.1
*/

/*
Copyright (C) 2013 Loginizer (email : support@loginizer.com)
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if(!function_exists('add_action')){
	echo 'You are not allowed to access this page directly.';
	exit;
}

// Plugin already loaded
if(defined('LOGINIZER_PREMIUM')){
	return;
}

define('LOGINIZER_PRO_VERSION', '2.0.4');
define('LOGINIZER_PRO_FILE', __FILE__);
define('LOGINIZER_API', 'https://api.loginizer.com/');
define('LOGINIZER_PRO_DIR', plugin_dir_path(__FILE__));
define('LOGINIZER_PRO_DIR_URL', plugin_dir_url(__FILE__));

include_once LOGINIZER_PRO_DIR . 'functions.php';

// TODO:: Add Require Plugins in the WordPress plugin comment, to make loginizer-security dependent on the free version, do it when 1.9.0+ reaches 90% adoption.
$lz_tmp_plugins = get_option('active_plugins', []);
$_lz_version = get_option('loginizer_version');

if(
	!defined('SITEPAD') && (
	!(in_array('loginizer/loginizer.php', $lz_tmp_plugins) || 
	loginizer_pro_is_network_active('loginizer')) || 
	!file_exists(WP_PLUGIN_DIR . '/loginizer/loginizer.php') || 
	(!empty($_lz_version) && version_compare($_lz_version, '1.8.9', '<')))
){
	include_once LOGINIZER_PRO_DIR . 'main/upgrader.php';
	return;
}

function loginizer_security_load_plugin_textdomain(){
    load_plugin_textdomain( 'loginizer', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}

add_action('init', 'loginizer_security_load_plugin_textdomain', 0);

define('LOGINIZER_PREMIUM', __FILE__);

include_once(dirname(__FILE__).'/init.php');