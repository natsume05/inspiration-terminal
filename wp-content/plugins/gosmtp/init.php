<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

// We need the ABSPATH
if (!defined('ABSPATH')) exit;

define('GOSMTP_BASE', plugin_basename(GOSMTP_FILE));
define('GOSMTP_VERSION', '1.1.8');
define('GOSMTP_DIR', dirname(GOSMTP_FILE));
define('GOSMTP_SLUG', 'gosmtp');
define('GOSMTP_URL', plugins_url('', GOSMTP_FILE));
define('GOSMTP_CSS', GOSMTP_URL.'/css');
define('GOSMTP_JS', GOSMTP_URL.'/js');
define('GOSMTP_PRO_URL', 'https://gosmtp.net/pricing?from=plugin');
define('GOSMTP_WWW_URL', 'https://gosmtp.net/');
define('GOSMTP_DOCS', 'https://gosmtp.net/docs/');
define('GOSMTP_DB_PREFIX', 'gosmtp_');

include_once(GOSMTP_DIR.'/main/functions.php');

spl_autoload_register('gosmtp_autoload_register');
function gosmtp_autoload_register($class){

	if(!preg_match('/GOSMTP\\\\/', $class)){
		return;
	}
	
	$file = strtolower(str_replace( array('GOSMTP', '\\'), array('', DIRECTORY_SEPARATOR), $class)); 
	$file = trim(strtolower($file), '/').'.php';

	// For Free
	if(file_exists(GOSMTP_DIR.'/main/'.$file)){
		include_once(GOSMTP_DIR.'/main/'.$file);
	}

	// For Pro
	if(defined('GOSMTP_PRO_DIR') && file_exists(GOSMTP_PRO_DIR.'/main/'.$file)){
		include_once(GOSMTP_PRO_DIR.'/main/'.$file);
	}
	
}

function gosmtp_died(){
	print_r(error_get_last());
}
//register_shutdown_function('gosmtp_died');

// Ok so we are now ready to go
register_activation_hook(GOSMTP_FILE, 'gosmtp_activation');

// Is called when the ADMIN enables the plugin
function gosmtp_activation(){
	global $wpdb;

	add_option('gosmtp_version', GOSMTP_VERSION);

}

// Checks if we are to update ?
function gosmtp_update_check(){
	global $wpdb;

	$current_version = get_option('gosmtp_version');
	$version = (int) str_replace('.', '', $current_version);

	// No update required
	if($current_version == GOSMTP_VERSION){
		return true;
	}

	// Is it first run ?
	if(empty($current_version)){

		// Reinstall
		gosmtp_activation();

		// Trick the following if conditions to not run
		$version = (int) str_replace('.', '', GOSMTP_VERSION);

	}

	// Save the new Version
	update_option('gosmtp_version', GOSMTP_VERSION);
	
}

// Add action to load GoSMTP
add_action('plugins_loaded', 'gosmtp_load_plugin');
function gosmtp_load_plugin(){
	global $gosmtp;
	
	if(empty($gosmtp)){
		$gosmtp = new stdClass();
	}
	
	// Check if the installed version is outdated
	gosmtp_update_check();
	
	$options = get_option('gosmtp_options', array());
	$gosmtp->options = empty($options) ? array() : $options;
}

// The function that will be called when the plugin is loaded
add_action('wp_mail', 'gosmtp_load_phpmailer');
function gosmtp_load_phpmailer($atts){
	global $gosmtp, $phpmailer;
	
	if(!class_exists('GOSMTP_PHPMailer')){
		// Load PHPMailer class, so we can subclass it.
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		
		class GOSMTP_PHPMailer extends \PHPMailer\PHPMailer\PHPMailer {

			// Modify the default send() behaviour of PHPMailer.
			public function send(){
				
				global $gosmtp, $phpmailer;
				
				// Define a custom header, that will be used to identify the plugin and the mailer.
				$this->XMailer = 'GOSMTP/Mailer/' . $gosmtp->_mailer . ' ' . GOSMTP_VERSION;
				
				do_action( 'gosmtp_mailer_mail_pre_send' );
				
				// If mailer not exists or send function not exists
				if(!method_exists($gosmtp->mailer, 'send')){
					return parent::send();
				}
				
				do_action( 'gosmtp_mailer_mail_send_before' );
			
				// Are we to enforce from ?
				$gosmtp->mailer->set_from();
				
				$exception = false;
				
				/*
				 * Send the actual email.
				 * We reuse everything, that was preprocessed for usage in \PHPMailer.
				 */
				try{
					$is_sent = $gosmtp->mailer->send();
				}catch(PHPMailer\PHPMailer\Exception $e){
					$is_sent = false;
					$exception = $e;
				}
				
				// Get backup connection
				$backup_connection = $gosmtp->mailer->get_backup_connection();
				$backup_sent = false;
				
				// Store current values
				$current_data = array(
					'_mailer' => $gosmtp->_mailer,
					'mailer' => $gosmtp->mailer,
					'conn_id' => $gosmtp->mailer->conn_id
				);
				
				// Try to send email with secondary email
				if(empty($is_sent) && !empty($backup_connection) ){
					
					$mailer = sanitize_key( $gosmtp->options['mailer'][$backup_connection]['mail_type'] );			
					$class = $gosmtp->mailer_list[$mailer]['class'];
					$parent_log = $gosmtp->mailer->last_log;
					
					if(class_exists($class)){
						$gosmtp->_mailer = $mailer;
						$gosmtp->mailer = new $class();
						$gosmtp->mailer->conn_id = $backup_connection;
						$gosmtp->mailer->parent_log = $parent_log;
						
						try{
							$backup_sent = $phpmailer->send();
						}catch(Exception $e){}
					}
					
				}
				
				// Reset connection
				$gosmtp->_mailer = $current_data['_mailer'];
				$gosmtp->mailer = $current_data['mailer'];
				$gosmtp->mailer->conn_id = $current_data['conn_id'];
				
				do_action( 'gosmtp_mailer_mail_send_after', $is_sent, $exception, $backup_sent );
				
				if($exception && !$backup_sent){
					throw $exception;
				}
				
				return $is_sent || $backup_sent;
			}

		}
	}
	
	if($phpmailer instanceof GOSMTP_PHPMailer){
		return $atts;
	}
	
	// Load all mailer
	$gosmtp->mailer_list = gosmtp_get_mailer_list();

	$connection_key = apply_filters('gosmtp_connection_key', 0);
	
	// Making sure the filter returns expected type
	if(!is_string($connection_key) && !is_int($connection_key)){
		$connection_key = 0;
	}

	// For PHP Email dont do anything
	if(empty($gosmtp->options['mailer'][$connection_key]['mail_type'])){
		return $atts;
	}

	$mailer = sanitize_key( $gosmtp->options['mailer'][$connection_key]['mail_type'] );
	$class = $gosmtp->mailer_list[$mailer]['class'];
	
	if(!class_exists($class)){
		return $atts;
	}
	
	$gosmtp->_mailer = $mailer;
	$gosmtp->mailer = new $class();
	$gosmtp->mailer->conn_id = $connection_key;

	// Handle the from email name
	add_filter('wp_mail_from', [$gosmtp->mailer, 'get_from'], 100, 1);

	$phpmailer = new GOSMTP_PHPMailer(true);
	
	return $atts;
}

// This adds the left menu in WordPress Admin page
add_action('admin_menu', 'gosmtp_admin_menu', 5);
function gosmtp_admin_menu() {

	global $wp_version;

	$capability = 'activate_plugins';// TODO : Capability for accessing this page

	// Add the menu page
	add_menu_page(__('GoSMTP', 'gosmtp'), __('GoSMTP', 'gosmtp'), $capability, 'gosmtp', 'gosmtp_page_handler', 'dashicons-email-alt');
	
	// Settings Page
	add_submenu_page( 'gosmtp', __('Settings', 'gosmtp'), __('Settings', 'gosmtp'), $capability, 'gosmtp', 'gosmtp_page_handler');
	
	// Test Mail Page
	add_submenu_page( 'gosmtp', 'Test Mail', 'Test Mail', $capability, 'gosmtp#test-mail', 'gosmtp_page_handler');
	
	if(defined('GOSMTP_PREMIUM')){
		
		// Logs Page
		add_submenu_page( 'gosmtp', __('Email Logs', 'gosmtp'), __('Email Logs', 'gosmtp'), $capability, 'gosmtp-logs', 'gosmtp_logs_handler');

		// Email reports
		add_submenu_page( 'gosmtp', __('Email Reports', 'gosmtp'), __('Email Reports', 'gosmtp'), $capability, 'email_reports', 'gosmtp_email_reports_handler');

		// Export Page
		add_submenu_page( 'gosmtp', __('Export', 'gosmtp'), __('Export', 'gosmtp'), $capability, 'export', 'gosmtp_export_handler');
		
		// Email reports
		add_submenu_page( '', __('Email Reports', 'gosmtp'), __('Weekly Email', 'gosmtp'), $capability, 'weekly_email_reports', 'gosmtp_weekly_email_handler');

		// License Page
		if(!defined('SITEPAD')){
			add_submenu_page( 'gosmtp', __('License', 'gosmtp'), __('License', 'gosmtp'), $capability, 'gosmtp-license', 'gosmtp_license_handler');
		}
	}
	
	// Support
	if(!defined('SITEPAD')){
		add_submenu_page( 'gosmtp', __('Support', 'gosmtp'), __('Support', 'gosmtp'), $capability, 'gosmtp#support', 'gosmtp_page_handler');
	}
}

// SMTP page Handler
function gosmtp_page_handler(){
	include_once GOSMTP_DIR .'/main/settings.php';
	gosmtp_settings_page();
}

function gosmtp_logs_handler(){
	include_once GOSMTP_PRO_DIR .'/main/smtp-logs.php';
}

function gosmtp_email_reports_handler(){
	include_once GOSMTP_PRO_DIR .'/main/email-reports.php';
	gosmtp_reports_table();
}

function gosmtp_export_handler(){
	include_once GOSMTP_PRO_DIR .'/main/export.php';
	gosmtp_export_page();
}

function gosmtp_weekly_email_handler(){
	include_once GOSMTP_PRO_DIR .'/main/weekly_email_reports.php';
	gosmtp_send_email_reports();
}

function gosmtp_license_handler(){
	global $gosmtp;
	
	include_once GOSMTP_PRO_DIR .'/main/license.php';
}

if(wp_doing_ajax()){	
	include_once GOSMTP_DIR.'/main/ajax.php';
}

add_action( 'admin_init', 'gosmtp_admin_init');
function gosmtp_admin_init(){
	wp_register_style( 'gosmtp-admin', GOSMTP_URL .'/css/admin.css', array(), GOSMTP_VERSION);
	wp_register_script( 'gosmtp-admin', GOSMTP_URL .'/js/admin.js', array('jquery'), GOSMTP_VERSION);
	
	include_once GOSMTP_DIR .'/main/admin.php';
	
	gosmtp_admin_hooks();
	
}