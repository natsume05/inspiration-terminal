<?php
/*
Plugin Name: GoSMTP Pro
Plugin URI: https://gosmtp.net
Description: Send emails from your WordPress site using your preferred SMTP provider like Gmail, Outlook, AWS, Zoho, SMTP.com, Brevo (formerly Sendinblue), Mailgun, Postmark, Sendgrid, Sparkpost, Sendlayer or any custom SMTP provider.
Version: 1.1.8
Author: Softaculous Team
Author URI: https://softaculous.com
Text Domain: gosmtp
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/*
* GoSMTP
* https://gosmtp.net
* (c) GoSMTP Team
*/

/*
GoSMTP's Mailer API connecters are derived from Fluent SMTP:
https://wordpress.org/plugins/fluent-smtp/
(C) FluentSMTP & WPManageNinja Team

FluentSMTP is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.	
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

// We need the ABSPATH
if (!defined('ABSPATH')) exit;

if(!function_exists('add_action')){
	echo 'You are not allowed to access this page directly.';
	exit;
}

// If GOSMTP_VERSION exists then the plugin is loaded already !
if(defined('GOSMTP_PREMIUM')) {
	return;
}

define('GOSMTP_PRO_VERSION', '1.1.8');
define('GOSMTP_PRO_DIR', plugin_dir_path(__FILE__));
define('GOSMTP_API', 'https://api.gosmtp.net/');
define('GOSMTP_PRO_FILE', __FILE__);
define('GOSMTP_PRO_PLUGIN_URL', plugins_url('', GOSMTP_PRO_FILE));

include_once(GOSMTP_PRO_DIR.'functions.php');

$gosmtp_tmp_plugins = get_option('active_plugins', []);
$_go_version = get_option('gosmtp_version');

$go_req_free_update = !empty($_go_version) && version_compare($_go_version, '1.0.7', '<');

if(
	!defined('SITEPAD') && (
	!(in_array('gosmtp/gosmtp.php', $gosmtp_tmp_plugins) || 
	gosmtp_pro_is_network_active('gosmtp')) || 
	!file_exists(WP_PLUGIN_DIR . '/gosmtp/gosmtp.php') || 
	!empty($go_req_free_update) )
){
	include_once(GOSMTP_PRO_DIR .'/main/gosmtp-init.php');
	return;
}

define('GOSMTP_PREMIUM', plugin_basename(__FILE__));
include_once(dirname(__FILE__).'/init.php');