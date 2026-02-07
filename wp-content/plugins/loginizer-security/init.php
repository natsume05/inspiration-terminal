<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

// Prevent update of loginizer free
// This also work for auto update
add_filter('site_transient_update_plugins', 'loginizer_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'loginizer_pro_disable_manual_update_for_plugin');

// Auto update free version after update pro version
add_action('upgrader_process_complete', 'loginizer_pro_update_free_after_pro', 10, 2);

register_activation_hook(__FILE__, 'loginizer_pro_activation');
register_deactivation_hook(__FILE__, 'loginizer_pro_deactivate');
function loginizer_pro_deactivate(){
	delete_option('loginizer_pro_version');
	delete_option('loginizer_free_installed');
	delete_option('loginizer_version_free_nag');
	delete_option('loginizer_version_pro_nag');
}

add_action('plugins_loaded', 'loginizer_security_init');
function loginizer_security_init(){

	global $loginizer;

	if(empty($loginizer)){
		$loginizer = [];
	}

	loginizer_pro_update_checker();

	$loginizer['social_settings'] = get_option('loginizer_social_settings', []);
	add_action('init', 'loginizer_security_load_translation_vars', 0);

	// Email to Login
	$options = get_option('loginizer_epl', []);
	$loginizer['email_pass_less'] = empty($options['email_pass_less']) ? 0 : $options['email_pass_less'];
	$loginizer['passwordless_sub'] = empty($options['passwordless_sub']) ? '' : $options['passwordless_sub'];
	$loginizer['passwordless_msg'] = empty($options['passwordless_msg']) ? '' : $options['passwordless_msg'];
	$loginizer['passwordless_msg_is_custom'] = empty($options['passwordless_msg']) ? 0 : 1;
	$loginizer['passwordless_html'] = empty($options['passwordless_html']) ? 0 : $options['passwordless_html'];
	$loginizer['passwordless_redirect'] = empty($options['passwordless_redirect']) ? 0 : $options['passwordless_redirect'];
	$loginizer['passwordless_redirect_for'] = empty($options['passwordless_redirect_for']) ? 0 : $options['passwordless_redirect_for'];
	$loginizer['passwordless_disabled_for'] = empty($options['passwordless_disabled_for']) ? 0 : $options['passwordless_disabled_for'];

	// 2FA OTP Email to Login
	$options = get_option('loginizer_2fa_email_template');
	$loginizer['2fa_email_d_sub'] = 'OTP : Login at $site_name';
	$loginizer['2fa_email_d_msg'] = 'Hi,

A login request was submitted for your account $email at :
$site_name - $site_url

Please use the following One Time password (OTP) to login :
$otp

Note : The OTP expires after 10 minutes.

If you haven\'t requested for the OTP, please ignore this email.

Regards,
$site_name';

	$loginizer['2fa_email_sub'] = empty($options['2fa_email_sub']) ? $loginizer['2fa_email_d_sub'] : $options['2fa_email_sub'];
	$loginizer['2fa_email_msg'] = empty($options['2fa_email_msg']) ? $loginizer['2fa_email_d_msg'] : $options['2fa_email_msg'];
	$loginizer['2fa_email_html'] = !empty($options['2fa_email_html']);

	// For SitePad its always on
	if(defined('SITEPAD')){
		$loginizer['email_pass_less'] = 1;
	}

	// Captcha
	$options = get_option('loginizer_captcha');
	$loginizer['captcha_type'] = empty($options['captcha_type']) ? '' : $options['captcha_type'];
	$loginizer['captcha_key'] = empty($options['captcha_key']) ? '' : $options['captcha_key'];
	$loginizer['captcha_secret'] = empty($options['captcha_secret']) ? '' : $options['captcha_secret'];
	$loginizer['captcha_theme'] = empty($options['captcha_theme']) ? 'light' : $options['captcha_theme'];
	$loginizer['captcha_size'] = empty($options['captcha_size']) ? 'normal' : $options['captcha_size'];
	$loginizer['captcha_lang'] = empty($options['captcha_lang']) ? '' : $options['captcha_lang'];
	$loginizer['captcha_disable_btn'] = empty($options['captcha_disable_btn']) ? '' : $options['captcha_disable_btn'];
	$loginizer['turn_captcha_key'] = empty($options['turn_captcha_key']) ? '' : $options['turn_captcha_key'];
	$loginizer['turn_captcha_secret'] = empty($options['turn_captcha_secret']) ? '' : $options['turn_captcha_secret'];
	$loginizer['turn_captcha_theme'] = empty($options['turn_captcha_theme']) ? 'light' : $options['turn_captcha_theme'];
	$loginizer['turn_captcha_size'] = empty($options['turn_captcha_size']) ? 'normal' : $options['turn_captcha_size'];
	$loginizer['turn_captcha_lang'] = empty($options['turn_captcha_lang']) ? '' : $options['turn_captcha_lang'];
	$loginizer['captcha_user_hide'] = !isset($options['captcha_user_hide']) ? 0 : $options['captcha_user_hide'];
	$loginizer['captcha_no_js'] = 1;
	$loginizer['captcha_login'] = !isset($options['captcha_login']) ? 1 : $options['captcha_login'];
	$loginizer['captcha_lostpass'] = !isset($options['captcha_lostpass']) ? 1 : $options['captcha_lostpass'];
	$loginizer['captcha_resetpass'] = !isset($options['captcha_resetpass']) ? 1 : $options['captcha_resetpass'];
	$loginizer['captcha_register'] = !isset($options['captcha_register']) ? 1 : $options['captcha_register'];
	$loginizer['captcha_comment'] = !isset($options['captcha_comment']) ? 1 : $options['captcha_comment'];
	$loginizer['captcha_wc_checkout'] = !isset($options['captcha_wc_checkout']) ? 1 : $options['captcha_wc_checkout'];
	$loginizer['captcha_wc_block_checkout'] = !empty($options['captcha_wc_block_checkout']);
	$loginizer['captcha_wc_checkout_pos'] = isset($options['captcha_wc_checkout_pos']) ? $options['captcha_wc_checkout_pos'] : '';

	$loginizer['captcha_no_google'] =  !isset($options['captcha_no_google']) ? 0 : $options['captcha_no_google'];
	$loginizer['captcha_domain'] = empty($options['captcha_domain']) ? 'www.google.com' : $options['captcha_domain'];
	// We are setting default to low to prevent anything from breaking for users who already have v3 enabled
	$loginizer['captcha_score_threshold'] = isset($options['captcha_score_threshold']) ? $options['captcha_score_threshold'] : '';

	$loginizer['captcha_text'] =  empty($options['captcha_text']) ? '' : $options['captcha_text'];
	$loginizer['captcha_time'] =  empty($options['captcha_time']) ? 300 : $options['captcha_time'];
	$loginizer['captcha_words'] =  !isset($options['captcha_words']) ? 0 : $options['captcha_words'];
	$loginizer['captcha_add'] =  !isset($options['captcha_add']) ? 1 : $options['captcha_add'];
	$loginizer['captcha_subtract'] =  !isset($options['captcha_subtract']) ? 1 : $options['captcha_subtract'];
	$loginizer['captcha_multiply'] =  !isset($options['captcha_multiply']) ? 0 : $options['captcha_multiply'];
	$loginizer['captcha_divide'] =  !isset($options['captcha_divide']) ? 0 : $options['captcha_divide'];
	$loginizer['captcha_status'] =  !isset($options['captcha_status']) ? 0 : $options['captcha_status'];

	// hcaptcha
	$loginizer['hcaptcha_secretkey'] =  !isset($options['hcaptcha_secretkey']) ? '' : $options['hcaptcha_secretkey'];
	$loginizer['hcaptcha_sitekey'] =  !isset($options['hcaptcha_sitekey']) ? '' : $options['hcaptcha_sitekey'];
	$loginizer['hcaptcha_theme'] = empty($options['hcaptcha_theme']) ? 'light' : $options['hcaptcha_theme'];
	$loginizer['hcaptcha_lang'] = empty($options['hcaptcha_lang']) ? '' : $options['hcaptcha_lang'];
	$loginizer['hcaptcha_size'] = empty($options['hcaptcha_size']) ? 'normal' : $options['hcaptcha_size'];

	// 2fa/question
	$options = get_option('loginizer_2fa');
	$loginizer['2fa_app'] = !isset($options['2fa_app']) ? 0 : $options['2fa_app'];
	$loginizer['2fa_email'] = !isset($options['2fa_email']) ? 0 : $options['2fa_email'];
	$loginizer['2fa_email_force'] = !isset($options['2fa_email_force']) ? 0 : $options['2fa_email_force'];
	$loginizer['2fa_sms'] = !isset($options['2fa_sms']) ? 0 : $options['2fa_sms'];
	$loginizer['question'] = !isset($options['question']) ? 0 : $options['question'];
	$loginizer['2fa_default'] = empty($options['2fa_default']) ? 'question' : $options['2fa_default'];
	$loginizer['2fa_roles'] = empty($options['2fa_roles']) ? array() : $options['2fa_roles'];

	// Security Settings
	$options = get_option('loginizer_security');
	$loginizer['login_slug'] = empty($options['login_slug']) ? '' : $options['login_slug'];
	$loginizer['rename_login_secret'] = empty($options['rename_login_secret']) ? '' : $options['rename_login_secret'];
	$loginizer['hide_wp_admin'] = empty($options['hide_wp_admin']) ? '' : $options['hide_wp_admin'];
	$loginizer['login_redirect_url'] = empty($options['login_redirect_url']) ? '' : $options['login_redirect_url'];
	$loginizer['xmlrpc_slug'] = empty($options['xmlrpc_slug']) ? '' : $options['xmlrpc_slug'];
	$loginizer['xmlrpc_disable'] = empty($options['xmlrpc_disable']) ? '' : $options['xmlrpc_disable'];// Disable XML-RPC
	$loginizer['pingbacks_disable'] = empty($options['pingbacks_disable']) ? '' : $options['pingbacks_disable'];// Disable Pingbacks

	// Admin Slug Settings
	$options = get_option('loginizer_wp_admin');
	$loginizer['admin_slug'] = empty($options['admin_slug']) ? '' : $options['admin_slug'];
	$loginizer['restrict_wp_admin'] = empty($options['restrict_wp_admin']) ? '' : $options['restrict_wp_admin'];
	$loginizer['wp_admin_msg'] = empty($options['wp_admin_msg']) ? '' : $options['wp_admin_msg'];

	// Checksum Settings
	$options = get_option('loginizer_checksums');
	$loginizer['disable_checksum'] = empty($options['disable_checksum']) ? '' : $options['disable_checksum'];
	$loginizer['checksum_time'] = empty($options['checksum_time']) ? '' : $options['checksum_time'];
	$loginizer['checksum_frequency'] = empty($options['checksum_frequency']) ? 7 : $options['checksum_frequency'];
	$loginizer['no_checksum_email'] = empty($options['no_checksum_email']) ? '' : $options['no_checksum_email'];
	$loginizer['checksums_last_run'] = get_option('loginizer_checksums_last_run');

	// Auto Blacklist Usernames
	$loginizer['username_blacklist'] = get_option('loginizer_username_blacklist');

	$loginizer['domains_blacklist'] = get_option('loginizer_domains_blacklist');

	// CSRF Protection
	$loginizer['enable_csrf_protection'] = get_option('loginizer_csrf_protection');
	$loginizer['2fa_custom_login_redirect'] = get_option('loginizer_2fa_custom_redirect');
	$loginizer['limit_session'] = get_option('loginizer_limit_session');

	// Checking if Ultimate Member plugins is active
	if(!isset($loginizer['ultimate-member-active'])){
		$um_is_active = in_array('ultimate-member/ultimate-member.php', apply_filters('active_plugins', get_option('active_plugins', [])));

		$loginizer['ultimate-member-active'] = !empty($um_is_active) ? true : false;
	}

	// Blocking access to wp-admin if user is not logged in.
	if(!empty($loginizer['login_slug']) && !empty($loginizer['hide_wp_admin'])){
		add_action('wp_loaded', 'loginizer_hide_wp_admin');
	}

	// Check if there is a license file and update it in the database
	if(file_exists(__DIR__.'/license.key')){

		$license =	trim(file_get_contents(__DIR__.'/license.key'));

		if(!empty($license)){
			loginizer_pro_load_license($license);
		}

		unlink(__DIR__.'/license.key');
	}

	// Load license
	loginizer_pro_load_license();

	// Load license
	if(!defined('SITEPAD')){

		// Check for updates
		include_once('updater/plugin-update-checker.php');
		$loginizer_updater = Loginizer_PucFactory::buildUpdateChecker(loginizer_pro_api_url().'/updates.php?version='.LOGINIZER_PRO_VERSION, LOGINIZER_PRO_FILE);

		// Add the license key to query arguments
		$loginizer_updater->addQueryArgFilter('loginizer_updater_filter_args');

		// Show the text to install the license key
		add_filter('puc_manual_final_check_link-loginizer-security', 'loginizer_updater_check_link', 10, 1);

		add_filter('plugin_row_meta', 'loginizer_plugin_row_links', 10, 2);

	}

	// Checking For SSO
	if(!empty($_GET['ssotoken'])){
		add_filter('authenticate', 'loginizer_sso_authenticate', 10003, 3);
		add_action('wp_login_errors', 'loginizer_error_handler', 10001, 2);
		add_action('wp_login', 'loginizer_login_success', 10, 2);
	}

	// CSRF Session URL
	if(!empty($loginizer['enable_csrf_protection']) && loginizer_is_csrf_prot_mod_set()){
		add_action('init', 'loginizer_csrf_sess_init');
		add_filter('login_redirect', 'loginizer_login_csrf_redirect', 200, 3);
		add_action('admin_bar_menu', 'loginizer_csrf_admin_bar_shortcut', 70);
		add_filter('admin_url', 'loginizer_csrf_admin_redirects', 100005, 3);
		add_filter('wp_redirect', 'loginizer_csrf_wp_redirects');
		add_action('set_auth_cookie', 'loginizer_admin_url_cookie'); // Creates session key and handles cookies
		add_action('wp_logout', 'loginizer_destroy_csrf_session', 10, 1);
	}

	// Handles Concurrent Sessions
	if(!empty($loginizer['limit_session']) && !empty($loginizer['limit_session']['enable'])){
		add_filter('wp_authenticate_user', 'loginizer_limit_sessions');
		add_action('wp_login', 'loginizer_limit_sessions_wp_login');
		add_filter('check_password', 'loginizer_limit_destroy_sessions_handler', 10, 4);
		add_filter('loginizer_pro_limit_sessions', 'loginizer_limit_sessions', 10);
	}

	// MasterStudy Login filter
	add_filter('stm_lms_login', 'loginizer_handle_stm_lms_login');

	add_filter('loginizer_system_information', 'loginizer_premium_system_info', 10);
	add_filter('loginizer_pre_page_dashboard', 'loginizer_premium_page_dashboard', 10);

	// A way to remove the settings
	if(file_exists(LOGINIZER_PRO_DIR.'/reset_admin.txt')){
		update_option('loginizer_wp_admin', array());
		delete_option('loginizer_csrf_protection');
	}

	// Are we to ban user emails ?
	if(!empty($loginizer['domains_blacklist']) && count($loginizer['domains_blacklist']) > 0){
		add_filter('registration_errors', 'loginizer_domains_blacklist', 10, 3);
		add_filter('woocommerce_registration_errors', 'loginizer_domains_blacklist', 10, 3);
	}

	// Is email password less login enabled ?
	$sapi_type = defined('PHP_SAPI') ? PHP_SAPI : '';
	if(!empty($loginizer['email_pass_less']) && !defined('XMLRPC_REQUEST') && $sapi_type !== 'cli'){

		// Add a handler for the GUI Login
		add_filter('authenticate', 'loginizer_epl_wp_authenticate', 10002, 3);

		// Dont show password error
		add_filter('wp_login_errors', 'loginizer_epl_error_handler', 10000, 2);

		// Hide the password field
		add_action('login_enqueue_scripts', 'loginizer_epl_hide_pass');
		add_action('wp_enqueue_scripts', 'loginizer_epl_hide_woocommerce_pass');

	}

	// Are we to rename the login ?
	if(!empty($loginizer['login_slug'])){

		//$loginizer['login_slug'] = 'login';

		// Add the filters / actions
		add_filter('site_url', 'loginizer_rl_site_url', 10, 2);
		add_filter('network_site_url', 'loginizer_rl_site_url', 10, 2);
		add_filter('wp_redirect', 'loginizer_rl_wp_redirect', 10, 2);
		add_filter('register', 'loginizer_rl_register');
		add_action('wp_loaded', 'loginizer_rl_wp_loaded');

	}

	// Rename the WP-ADMIN folder
	if(!defined('SITEPAD') && !empty($loginizer['admin_slug'])){

		add_filter('admin_url', 'loginizer_admin_url', 10001, 3);
		add_action('set_auth_cookie', 'loginizer_admin_url_cookie');

		// For multisite
		if(lz_is_multisite()){
			add_filter('network_admin_url', 'loginizer_network_admin_url', 10001, 2);
		}

		if(!empty($loginizer['restrict_wp_admin']) && preg_match('/\/wp-admin/is', $_SERVER['REQUEST_URI'])){
			die(empty($loginizer['wp_admin_msg']) ? $loginizer['wp_admin_d_msg'] : $loginizer['wp_admin_msg']);
		}

	}

	// Are we to rename the xmlrpc ?
	if(!defined('SITEPAD') && !empty($loginizer['xmlrpc_slug']) && empty($loginizer['xmlrpc_disable'])){

		// Add the filters / actions
		add_action('wp_loaded', 'loginizer_xml_rename_wp_loaded');

	}

	// Are we to DISABLE the xmlrpc ?
	if(!empty($loginizer['xmlrpc_disable'])){

		// Add the filters / actions
		add_filter('xmlrpc_enabled', 'loginizer_xmlrpc_null');
		add_filter('bloginfo_url', 'loginizer_xmlrpc_remove_pingback_url', 10000, 2);
		add_action('wp_loaded', 'loginizer_xmlrpc_disable');

	}

	// Are we to disable pingbacks ?
	if(!empty($loginizer['pingbacks_disable'])){

		// Add the filters / actions
		add_filter('xmlrpc_methods', 'loginizer_pingbacks_disable');

	}

	if(!empty($loginizer['ultimate-member-active']) && class_exists('UM')){
		add_action('um_user_edit_profile', 'loginizer_user_page_post', 10, 1);
		remove_action('template_redirect', array(UM()->account(), 'account_submit'), 10002);
		remove_action( 'um_before_form', 'um_add_update_notice', 500 );
	}

	//-----------------------------------
	// Add the captcha filters / actions
	//-----------------------------------
	if(!empty($loginizer['social_settings']) && !loginizer_is_blacklisted()){

		// Shortcode has options shape|divide|container_alignment|button_alignment
		add_shortcode('loginizer_social', 'loginizer_social_shortcode');

		if(!empty($_COOKIE['lz_social_error'])){
			add_action('woocommerce_before_customer_login_form', 'loginizer_social_wc_error');
		}

		if(!empty($loginizer['social_settings']['general']['save_avatar'])){
			add_filter('get_avatar', 'loginizer_social_update_avatar', 1, 5);
		}

		if(!empty($loginizer['social_settings']['login']['registration_form'])){
			add_action('register_form', 'loginizer_social_btn_login', 100);
		}

		$lz_active_plugins = apply_filters('active_plugins', get_option('active_plugins'));

		if(in_array('woocommerce/woocommerce.php', $lz_active_plugins)){
			if(!empty($loginizer['social_settings']['woocommerce']['login_form'])){
				add_action('woocommerce_login_form', 'loginizer_social_btn_woocommerce', 100);
			}

			if(!empty($loginizer['social_settings']['woocommerce']['registration_form'])){
				add_action('woocommerce_register_form', 'loginizer_social_btn_woocommerce');
			}
		}

		// Social Login for Ultimate Member plugin
		if(in_array('ultimate-member/ultimate-member.php', $lz_active_plugins)){
			if(!empty($loginizer['social_settings']['ultimate_member']['enable_buttons'])){
				if(strpos($loginizer['social_settings']['ultimate_member']['button_position'], 'below') !== FALSE){
					add_action('um_after_form', 'loginizer_social_btn_um', 100);
				} else {
					add_action('um_before_form', 'loginizer_social_btn_um', 100);
				}
			}
		}

		if(!empty($loginizer['social_settings']['comment']['enable_buttons'])){
			add_action('comment_form_must_log_in_after', 'loginizer_social_btn_comment');
		}
	}

	if(!empty($loginizer['captcha_key']) || !empty($loginizer['captcha_no_google']) || !empty($loginizer['captcha_status'])){

		add_action('login_init', 'loginizer_cap_session_key');

		// Is reCaptcha on for login ?
		if(!empty($loginizer['captcha_login']) && !defined('XMLRPC_REQUEST')){

			add_filter('authenticate', 'loginizer_cap_login_verify', 10000);
			add_action('login_form', 'loginizer_cap_form_login', 100);
			add_action('woocommerce_login_form', 'loginizer_cap_form_login', 100);
			add_action('login_form_middle', 'loginizer_cap_wp_login_form', 100); // https://developer.wordpress.org/reference/functions/wp_login_form/

			if(!empty($loginizer['ultimate-member-active']) && class_exists('UM')){
				add_action('um_after_login_fields', 'loginizer_cap_form_um_login', 100);
			}

			// Need to make more room for login form
			if(empty($loginizer['captcha_remove_css'])){
				add_action('login_enqueue_scripts', 'loginizer_cap_login_form');
			}

		}

		// Is reCaptcha on for Lost Password utility ?
		if(!empty($loginizer['captcha_lostpass'])){
			add_action('allow_password_reset', 'loginizer_cap_lostpass_verify', 10, 2);
			add_action('lostpassword_form', 'loginizer_cap_form_login', 100);
			add_filter('woocommerce_lostpassword_form', 'loginizer_cap_form_login');
		}

		// Is reCaptcha on for Reset Password utility ?
		if(!empty($loginizer['captcha_resetpass'])){
			add_filter('validate_password_reset', 'loginizer_cap_resetpass_verify', 10, 2);
			add_action('resetpass_form', 'loginizer_cap_reset_form', 99);
			add_filter('woocommerce_resetpassword_form', 'loginizer_cap_form_login');
		}

		// Is reCaptcha on for registration ?
		if(!empty($loginizer['captcha_register'])){
			add_filter('registration_errors', 'loginizer_cap_register_verify', 10, 3);
			add_action('register_form', 'loginizer_cap_form_login', 100);

			// For BuddyPress
			add_filter('bp_signup_validate', 'loginizer_cap_register_verify_buddypress', 10, 3);
			add_action('bp_after_signup_profile_fields', 'loginizer_cap_form_login', 100);

			add_filter('woocommerce_before_checkout_process', 'loginizer_wc_before_checkout_process', 10);

			add_filter('woocommerce_register_form', 'loginizer_cap_form_login');
			add_filter('woocommerce_registration_errors', 'loginizer_cap_register_verify', 10, 3);

			if(!empty($loginizer['captcha_wc_checkout'])){
				// Checkout captcha position was added in v2.0.3 so the action in else was default before that.
				if(isset($loginizer['captcha_wc_checkout_pos']) && $loginizer['captcha_wc_checkout_pos'] == 'before_submit'){
					add_action('woocommerce_review_order_before_submit', 'loginizer_cap_form_ecommerce', 10);
				} else {
					// This is before payment position
					add_action('woocommerce_checkout_order_review', 'loginizer_cap_form_ecommerce');
				}
			}
			
			// For block based checkout
			// To add captcha to blocks of Checkout page refer:
			// https://developer.woocommerce.com/docs/block-development/tutorials/integrating-protection-checkout-block/
			if(!empty($loginizer['captcha_wc_block_checkout'])){
				include_once LOGINIZER_PRO_DIR .'/main/integrations/woocommerce.php';
				
				// Checkout captcha position was added in v2.0.3 so the action in else was default before that.
				if(isset($loginizer['captcha_wc_checkout_pos']) && $loginizer['captcha_wc_checkout_pos'] == 'before_submit'){
					add_filter('render_block_woocommerce/checkout-actions-block', 'loginizer_pro_cap_woo_block_render', 999, 1);
				} else {
					// This is before payment position
					add_filter('render_block_woocommerce/checkout-payment-block', 'loginizer_pro_cap_woo_block_render_before_payment', 999, 1);
				}
			}
		}
		
		// For veirification for block based checkout
		if(!empty($loginizer['captcha_wc_block_checkout'])){
			include_once LOGINIZER_PRO_DIR .'/main/integrations/woocommerce.php';
		}

		// Are we to show Captcha for guests only ?
		if((is_user_logged_in() && empty($loginizer['captcha_user_hide'])) || !is_user_logged_in()){

			// Is reCaptcha on for comment utility ?
			if(!empty($loginizer['captcha_comment'])){
				add_filter('preprocess_comment', 'loginizer_cap_comment_verify');
				add_action('comment_form', 'loginizer_cap_comment_form');
			}

			// Is reCaptcha on for WooCommerce Logout utility ?
			if(!empty($loginizer['captcha_wc_checkout'])){
				add_action('woocommerce_after_checkout_validation', 'loginizer_wc_checkout_verify');
				if(isset($loginizer['captcha_wc_checkout_pos']) && $loginizer['captcha_wc_checkout_pos'] == 'before_submit'){
					add_action('woocommerce_review_order_before_submit', 'loginizer_cap_form_ecommerce', 10);
				} else {
					// This is before payment position
					add_action('woocommerce_checkout_order_review', 'loginizer_cap_form_ecommerce');
				}
			}

		}

	}

	//-----------------
	// Two Factor Auth
	//-----------------

	if(!defined('SITEPAD') && loginizer_is_2fa_enabled() && !defined('XMLRPC_REQUEST')){

		// After username and password check has been verified, are we to redirect ?
		add_filter('authenticate', 'loginizer_user_redirect', 10003, 3);

		$user_id = get_current_user_id();
		$lz_2fa_state = get_transient('loginizer_2fa_'. $user_id);

		// To redirect after login
		if(!empty($_COOKIE['loginizer_2fa_' . $user_id]) && !empty($lz_2fa_state) && $lz_2fa_state != '2fa'){
			loginizer_2fa_ajax_redirect();
		}

		$login_slug = 'wp-login.php';
		if($loginizer['login_slug']){
			$login_slug = $loginizer['login_slug'];
		}

		if(loginizer_cur_page() !== $login_slug && !empty($lz_2fa_state) && $lz_2fa_state == '2fa'){
			wp_logout();
			wp_safe_redirect(admin_url());
			exit;
		}

		// Shows the Question / 2fa field
		add_action('login_form_loginizer_security', 'loginizer_user_security');

		$cur_user = wp_get_current_user();

		//Add the Loginizer Security Settings Page for WooCommerce
		if(class_exists('WooCommerce') && loginizer_is_2fa_applicable($cur_user)){
			add_action( 'init', 'loginizer_add_premium_security_endpoint' );
			add_filter( 'query_vars', 'loginizer_premium_security_query_vars', 0 );
			add_filter( 'woocommerce_account_menu_items', 'loginizer_add_premium_security_link_my_account' );
			add_action( 'woocommerce_account_loginizer-security_endpoint', 'loginizer_user_page' );
		}

		//Add the Loginizer Security Settings Page for ultimate member plugin
		if(!empty($loginizer['ultimate-member-active']) && class_exists('UM') && loginizer_is_2fa_applicable($cur_user)){
			add_filter('um_account_content_hook_loginizer-security', 'loginizer_ultimatemember_security_tab_content');
			add_filter('um_account_page_default_tabs_hook', 'loginizer_ultimatemember_security_tab', 100 );
		}

		// Is the user logged in ?
		if(is_user_logged_in()){

			// Load user settings
			loginizer_load_user_settings($tfa_uid, $tfa_user, $tfa_settings, $tfa_current_pref);

			// If 2FA applicable as per role
			if(loginizer_is_2fa_applicable($tfa_user)){

				// Add to Settings menu on sites
				add_action('admin_menu', 'loginizer_user_menu');

				// Show the user the notification to set a 2FA
				$loginizer['loginizer_2fa_notice'] = get_user_meta($tfa_uid, 'loginizer_2fa_notice');

				// Are we to show the loginizer notification to set a 2FA
				if(empty($loginizer['loginizer_2fa_notice']) && (empty($_COOKIE['loginizer_2fa_notice_'.$tfa_uid]) || $_COOKIE['loginizer_2fa_notice_'.$tfa_uid] != md5(wp_get_session_token())) &&
					(empty($tfa_current_pref) || $tfa_current_pref == 'none') &&
					lz_optget('page') != 'loginizer_user'
				){

					add_action('admin_notices', 'loginizer_2fa_notice');

				}

				// Are we to disable the notice forever ?
				if(isset($_GET['loginizer_2fa_notice']) && (int)$_GET['loginizer_2fa_notice'] == 0){
					update_user_meta($tfa_uid, 'loginizer_2fa_notice', time());
					die('DONE');
				}

				// Are we to disable the notice temporarily ?
				if(isset($_GET['loginizer_2fa_notice']) && (int)$_GET['loginizer_2fa_notice'] == 1){
					@setcookie('loginizer_2fa_notice_'.$tfa_uid, md5(wp_get_session_token()), time() + (3 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl());
				}

			}

			add_filter('manage_users_columns', 'loginizer_2fa_columns_users');
			add_filter('manage_users_custom_column', 'loginizer_2fa_column_data', 10, 3);

		}

	}

	// Checksum is enabled right i.e. its not disabled ?
	if(!defined('SITEPAD') && empty($loginizer['disable_checksum'])){

		// Create an action always
		add_action('loginizer_do_checksum', 'loginizer_checksums');

		// Difference in seconds since last time
		$diff = (time() - $loginizer['checksums_last_run']);

		// Has it crossed the time ?
		if(($diff / 86400) >= $loginizer['checksum_frequency']){
			//loginizer_checksums();
			wp_schedule_single_event(time(), 'loginizer_do_checksum');
		}

	}

	if(!empty($_GET['lz_api'])){
		add_action('init', 'loginizer_pro_social_auth_load');
		return;
	}

	if(wp_doing_ajax()){
		include_once LOGINIZER_PRO_DIR . 'main/ajax.php';
		return;
	}

	if(is_admin()){
		include_once LOGINIZER_PRO_DIR . 'main/admin.php';
	}

}

function loginizer_social_wc_error(){

	// Showing woocommerce error
	if(!function_exists('wc_add_wp_error_notices')){
		return;
	}

	$errors = loginizer_social_login_error_handler();

	if(empty($errors) || !is_wp_error($errors)){
		return;
	}

	wc_add_wp_error_notices($errors);
	loginizer_woocommerce_error_handler();
	woocommerce_output_all_notices();
}

function loginizer_premium_system_info(){

	global $loginizer;

	$license_key = '';

	if(!empty($loginizer['license'])){
		if(isset($loginizer['license']['plan']) && $loginizer['license']['plan'] == 'business'){
			$license_parts = explode('-', $loginizer['license']['license']);
			$license_parts[1] = $license_parts[2] = $license_parts[3] = 'XXXXX';
			$license_parts[4] = 'XX' . substr($license_parts[4], 2);

			$license_key = implode('-', $license_parts);
		} else {
			$license_key = $loginizer['license']['license'];
		}
	}

	echo '
	<tr>
		<th align="left" valign="top">'.__('Loginizer License', 'loginizer').'</th>
		<td align="left">
			'.(empty($loginizer['license']) ? '<span style="color:red">Unlicensed</span> &nbsp; &nbsp;' : '').'
			<input type="text" name="lz_license" value="'.esc_attr($license_key).'" size="30" placeholder="e.g. WXCSE-SFJJX-XXXXX-AAAAA-BBBBB" style="width:300px;" /> &nbsp;
			<input name="save_lz" class="button button-primary" value="Update License" type="submit" />';
			if(!empty($loginizer['license'])){
				echo '&nbsp;<input name="delete_lz" class="button" value="Delete License" type="submit"/>';
			}

			if(!empty($loginizer['license'])){

				$expires = $loginizer['license']['expires'];
				$expires = substr($expires, 0, 4).'/'.substr($expires, 4, 2).'/'.substr($expires, 6);

				echo '<div style="margin-top:10px;">License Active : '.(empty($loginizer['license']['active']) ? '<span style="color:red">No</span>' : '<span style="color:green">Yes</span>').' &nbsp; &nbsp; &nbsp;';

				if(empty($loginizer['license']['has_plid']) || $loginizer['license']['expires'] <= date('Ymd')){
					echo 'License Expires : '.($loginizer['license']['expires'] <= date('Ymd') ? '<span style="color:red">'.$expires.'</span>' : $expires);
				}
				echo '</div>';

				if(!empty($loginizer['license']['status_txt']) && !empty($loginizer['license']['status_msg'])){
					echo '<div>'.wp_kses_post($loginizer['license']['status_txt']).'</div>';
					echo '<div>'.wp_kses_post($loginizer['license']['status_msg']).'</div>';
				}
			}
		echo
		'</td>
	</tr>';

}

function loginizer_premium_page_dashboard(){

	global $loginizer, $lz_error, $lic_resp;

	// Is there a license key ?
	if(isset($_POST['save_lz'])){

		$license = lz_optpost('lz_license');

		// Check if its a valid license
		if(empty($license)){
			$lz_error['lic_invalid'] = __('The license key was not submitted', 'loginizer');
			return loginizer_page_dashboard_T();
		}

		loginizer_pro_load_license($license);

		if(is_array($lic_resp)){
			$json = json_decode($lic_resp['body'], true);
			//print_r($json);
		}else{

			$lz_error['resp_invalid'] = __('The response was malformed<br>'.var_export($lic_resp, true), 'loginizer');
			return loginizer_page_dashboard_T();

		}

		// Save the License
		if(empty($json['license'])){

			$lz_error['lic_invalid'] = __('The license key is invalid', 'loginizer');
			return loginizer_page_dashboard_T();

		}else{

			// Mark as saved
			$GLOBALS['lz_saved'] = true;

		}

	}

	// Deleting the License
	if(isset($_POST['delete_lz']) && isset($_POST['lz_license'])){
		$license = sanitize_text_field(wp_unslash($_POST['lz_license']));
		if(strpos($license, 'LOGIN') === 0){
			delete_option('loginizer_license');
		}
	}

}

// Change the Admin URL
function loginizer_admin_url($url, $path, $blog_id){

	global $loginizer;

	//echo $url."\n";echo $path."\n";
	$new = str_replace('wp-admin', $loginizer['admin_slug'], $url);

	//echo $new.'<br>';
	return $new;
}

function loginizer_network_admin_url($url, $path){

	global $loginizer;

	//echo $url.'<br>';echo $path.'<br>';
	$new = str_replace('wp-admin', $loginizer['admin_slug'], $url);

	//echo $new.'<br>';
	return $new;
}

// Required to be able to Login
function loginizer_admin_url_cookie($auth_cookie, $expire = 0, $expiration = '', $user_id = '', $scheme = ''){

	global $loginizer;

	if($scheme == 'secure_auth' || is_ssl()){
		$auth_cookie_name = SECURE_AUTH_COOKIE;
		$secure = true;
	}else {
		$auth_cookie_name = AUTH_COOKIE;
		$secure = false;
	}

	$admin_slug = $loginizer['admin_slug'];

	// Auth cookie has the user's username in it as the first word before pipe | so we get the user name through that
	if(!empty($loginizer['enable_csrf_protection']) && !empty($auth_cookie)){
		$u_login = explode('|', $auth_cookie);
		$u_login = $u_login[0];

		$user = get_user_by('login', $u_login);
		$session = get_user_meta($user->ID, 'loginizer_csrf_session', true);

		if(empty($session)){
			loginizer_csrf_create_session($user->ID);
		}

		$session = get_user_meta($user->ID, 'loginizer_csrf_session', true);
		if(!empty($session)){
			$admin_slug = 'wp-admin-' . $session;

			if(!empty($loginizer['admin_slug'])){
				$admin_slug = $loginizer['admin_slug'] . '-' . $session;
			}
		}
	}

	@setcookie($auth_cookie_name, $auth_cookie, $expire, SITECOOKIEPATH . $admin_slug, COOKIE_DOMAIN, $secure, true);

}

// Verifies if the token is valid and creates the user session
function loginizer_epl_verify(){

	global $loginizer;

	if(empty($_GET['uid']) || empty($_GET['lepltoken'])){
		return false;
	}

	$uid = (int) sanitize_key($_GET['uid']);
	$token = sanitize_key($_GET['lepltoken']);
	$action = 'loginizer_epl_'.$uid;

	$hash = get_user_meta($uid, $action, true);
	$expires = get_user_meta($uid, $action.'_expires', true);

	include_once(ABSPATH.'/'.$loginizer['wp-includes'].'/class-phpass.php');
	$wp_hasher = new PasswordHash(8, TRUE);
	$time = time();

	if(!$wp_hasher->CheckPassword($expires.$token, $hash) || $expires < $time){
		$token_error_msg = __('The token is invalid or has expired. Please request a new email', 'loginizer');
		// Throw an error
		return new WP_Error('token_invalid', $token_error_msg, 'loginizer_epl');

	}else{

		if(!empty($loginizer['limit_session']) && !empty($loginizer['limit_session']['enable'])){
			$limit_session = loginizer_limit_destroy_sessions($uid);

			if(empty($limit_session)){
				return new WP_Error('loginizer_session_limit', __('User ID not found so can not proceed', 'loginizer'), 'loginizer_epl');
			}
		}

		// Login the User
		wp_set_auth_cookie($uid);

		// Delete the meta
		delete_user_meta($uid, $action);
		delete_user_meta($uid, $action.'_expires');

		$user = get_user_by('id', $uid);
		$redirect = !empty($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : '';

		if(!empty($loginizer['passwordless_redirect']) && !empty($user) && !empty($user->roles)){
			$roles = $user->roles;

			if(!is_array($user->roles)) {
				$roles = [$user->roles];
			}

			// To check if we need a custom redirect for the role this user has
			foreach($roles as $r){
				if(!empty($loginizer['passwordless_redirect_for']) && in_array($r, $loginizer['passwordless_redirect_for'])){
					$redirect = $loginizer['passwordless_redirect'];
					break;
				}
			}
		}

		$redirect_to = !empty($redirect) ? $redirect : admin_url();
		$redirect_to = loginizer_csrf_change_url($redirect_to, $uid);

		loginizer_update_attempt_stats(1);

		// Redirect and exit
		wp_safe_redirect($redirect_to);
		exit;

	}

	return false;

}

// Hides the password field for the password less email login
function loginizer_epl_hide_pass() {
	global $loginizer;

	// We do not need to do it for the admin login page.
	if(!empty($loginizer['passwordless_disabled_for']) && is_array($loginizer['passwordless_disabled_for']) && in_array('admin', $loginizer['passwordless_disabled_for'])){
		return;
	}

	?>
	<style type="text/css">
	label[for="user_pass"], .user-pass-wrap {
	display:none;
	}
	</style>
	<?php
}

// Hides the password field for the password less email login (WooCommerce)
function loginizer_epl_hide_woocommerce_pass(){
	global $loginizer;

	// We do not need to do it for the WooCommerce Login page.
	if(!empty($loginizer['passwordless_disabled_for']) && is_array($loginizer['passwordless_disabled_for']) && in_array('woocommerce', $loginizer['passwordless_disabled_for'])){
		return;
	}

	?>
	<style type="text/css">
	label[for="password"], .password-input, .lost_password{
	display:none !important;
	}
	</style>
	<?php

	// In WooCommerce the password input is required which prevents the login from Working.
	wp_register_script('lz-disable-login-pass', '', ['jquery'], '', true);
	wp_enqueue_script('lz-disable-login-pass');
	wp_add_inline_script('lz-disable-login-pass', "jQuery(document).ready(function(){
		let pass_input = jQuery('form.login [name=\"password\"]');
		if(pass_input.length){
			pass_input.attr('required', false);
			pass_input.attr('value', '');
		}
	})");
}

// Handles the error of the password not being there
function loginizer_epl_error_handler($errors, $redirect_to){

	//echo 'loginizer_epl_error_handler :';print_r($errors->errors);echo '<br>';

	// Remove the empty password error
	if(is_wp_error($errors)){
		$errors->remove('empty_password');
	}

	return $errors;

}

// Handles the verification of the username or email
function loginizer_epl_wp_authenticate($user, $username, $password){

	global $loginizer;

	//echo 'loginizer_epl_wp_authenticate : '; print_r($user).'<br>';
	// Checking which page made the request to login.
	if(loginizer_is_epl_disabled()){
		return $user;
	}

	if(is_wp_error($user)){

		// Ignore certain codes
		$ignore_codes = array('empty_username', 'empty_password');

		if(is_wp_error($user) && !in_array($user->get_error_code(), $ignore_codes)) {
			return $user;
		}

	}

	// Is it a login attempt
	$verified = loginizer_epl_verify();
	if(is_wp_error($verified)){
		return $verified;
	}

	if(empty($username) && empty($_POST)){
		return $user;
	}

	$email = NULL;

	// Is it an email address ?
	if(is_email($username) && email_exists($username)){
		$email = $username;
	}

	// Maybe its a username
	if(!is_email($username) && username_exists($username)){
		$user = get_user_by('login', $username);
		if($user){
			$email = $user->data->user_email;
		}
	}

	// Did you get any valid email ?
	if(empty($email)){
		$account_error_msg = __('The username or email you provided does not exist !', 'loginizer');
		return new WP_Error('invalid_account', $account_error_msg, 'loginizer_epl');
	}

	if(!empty($loginizer['limit_session']) && !empty($loginizer['limit_session']['enable'])){
		$user = get_user_by('email', $email);
		$session_limit = loginizer_limit_sessions($user);

		if(is_wp_error($session_limit)){
		    return $session_limit;
		}
	}

	// Send the email
	$site_name = get_bloginfo('name');
	$login_url = loginizer_epl_login_url($email);

	$vars = array('email' => $email,
				'site_name' => $site_name,
				'site_url' => get_site_url(),
				'login_url' => $login_url);

	$subject = lz_lang_vars_name($loginizer['passwordless_sub'], $vars);
	$message = lz_lang_vars_name($loginizer['passwordless_msg'], $vars);

	//echo $subject.'<br><br>';echo $message;

	$headers = array();

	// Do we need to send the email as HTML ?
	if(!empty($loginizer['passwordless_html'])){
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		if(!empty($loginizer['passwordless_msg_is_custom'])){
			$message = html_entity_decode($message);
		}else{
			$message = preg_replace("/\<br\s*\/\>/i", "<br/>", $message);
			$message = preg_replace('/(?<!<br\/>)\n/i', "<br/>\n", $message);
		}
	}

	$sent = wp_mail($email, $subject, $message, $headers);

	//echo $login_url;

	if(empty($sent)){
		$email_not_sent = __('There was a problem sending your email. Please try again or contact an admin.', 'loginizer');
		return new WP_Error('email_not_sent', $email_not_sent, 'loginizer_epl');
	}else{
		$loginizer['no_loginizer_logs'] = 1;
		$email_sent_msg = __('An email has been sent with the Login URL', 'loginizer');
		return new WP_Error('email_sent', $email_sent_msg, 'message');
	}

}


// Generate the URL for the
function loginizer_epl_login_url($email){

	// Get the User ID
	$user = get_user_by('email', $email);
	$token = loginizer_epl_token($user->ID);

	// The current URL
	$redirect_url = '';
	if(!empty($_REQUEST['redirect_to'])){
		$redirect_url = $_REQUEST['redirect_to'];
	} elseif (!empty($_REQUEST['redirect'])){
		$redirect_url = $_REQUEST['redirect'];
	} elseif(!empty(wp_validate_redirect($_SERVER['HTTP_REFERER']))){
		$redirect_url = wp_validate_redirect($_SERVER['HTTP_REFERER']);
	}

	$redirect_param = (!empty($redirect_url) ? '&redirect_to='.urlencode($redirect_url) : '');

	$url = wp_login_url().'?uid='.$user->ID.'&lepltoken='.$token.$redirect_param;

	return $url;

}

// Creates a one time token
function loginizer_epl_token($uid = 0){

	global $loginizer;

	// Variables
	$time = time();
	$expires = ($time + 600);
	$action =  'loginizer_epl_'.$uid;

	include_once( ABSPATH . '/'.$loginizer['wp-includes'].'/class-phpass.php');
	$wp_hasher = new PasswordHash(8, TRUE);

	// Create the token with a random salt and the time
	$token  = wp_hash(wp_generate_password(20, false).$action.$time);

	// Create a hash of the token
	$stored_hash = $wp_hasher->HashPassword($expires.$token);

	// Store the hash and when it expires
	update_user_meta($uid, $action, $stored_hash);
	update_user_meta($uid, $action.'_expires', $expires);

	return $token;

}

// Send a 404
function loginizer_set_404(){

	global $wp_query;

	// To prevent WordPress from redirecting to login page.
	remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);

	status_header(404);
	$wp_query->set_404();

	// Some page builders have registered shortcodes on this hook.
	do_action('template_redirect');

	if( (($template = get_404_template()) || ($template = get_index_template()))
		&& ($template = apply_filters('template_include', $template))
	){
		include($template);
	}

	die();

}

// Find the page being accessed
function loginizer_cur_page(){

	$blog_url = trailingslashit(get_bloginfo('url'));
	$server_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
	$server_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

	// Build the Current URL
	$url = (is_ssl() ? 'https://' : 'http://') . $server_host . $server_uri;

	if(is_ssl() && preg_match('/^http\:/is', $blog_url)){
		$blog_url = substr_replace($blog_url, 's', 4, 0);
	}

	// The relative URL to the Blog URL
	$req = str_replace($blog_url, '', $url);
	$req = str_replace('index.php/', '', $req);

	// We dont need the args
	$parts = explode('?', $req, 2);
	$relative = basename($parts[0]);

	// Remove trailing slash
	$relative = rtrim($relative, '/');
	$tmp = explode('/', $relative, 2);
	$page = end($tmp);

	//echo 'Page : '.$page.'<br>';

	return $page;

}

// Converts the URL as per the one stored
function loginizer_rl_convert_url($link){

	global $loginizer;
	$dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 7);

	// If the login page is to be kept secret
	if(!empty($loginizer['rename_login_secret']) && loginizer_cur_page() !== $loginizer['login_slug'] && !is_user_logged_in() && (empty($dbt[6]) || $dbt[6]['function'] != 'get_the_password_form')){
		return $link;
	}

	$result = $link;

	if(!empty($loginizer['login_slug']) && strpos($link, $loginizer['login_basename']) !== false){
		$result = str_replace($loginizer['login_basename'], $loginizer['login_slug'], $link);
	}

	if(!empty($loginizer['xmlrpc_slug']) && strpos($link, 'xmlrpc.php') !== false){
		$result = str_replace($loginizer['login_basename'], $loginizer['login_slug'], $link);
	}

	return $result;
}

function loginizer_rl_site_url($link){
	$result = loginizer_rl_convert_url($link);
	return $result;
}

function loginizer_rl_wp_redirect($link){
	$result = loginizer_rl_convert_url($link);
	return $result;
}

function loginizer_rl_register($link){
	$result = loginizer_rl_convert_url($link);
	return $result;
}

// Shows the Login correctly
function loginizer_rl_wp_loaded(){

	global $loginizer, $interim_login;

	$page = loginizer_cur_page();

	// Is it wp-login.php ?
	if ($page === $loginizer['login_basename']) {
		loginizer_set_404();
	}

	// Is it our SLUG ? If not then return
	if($page !== rtrim($loginizer['login_slug'], '/')){
		return false;
	}

	// We dont want a WP plugin caching this page
	@define('NO_CACHE', true);
	@define('WTC_IN_MINIFY', true);
	@define('WP_CACHE', false);

	$user_login = '';
	$error = '';

	// Prevent errors from defining constants again
	error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);

	include ABSPATH.'/'.$loginizer['login_basename'];

	exit();

}

// Renames the XML-RPC functionality
function loginizer_xml_rename_wp_loaded(){

	global $loginizer;

	$page = loginizer_cur_page();

	// Is it xmlrpc.php ?
	if ($page === 'xmlrpc.php') {
		loginizer_set_404();
	}

	// Is it our SLUG ? If not then return
	if($page !== $loginizer['xmlrpc_slug']){
		return false;
	}

	// We dont want a WP plugin caching this page
	@define('NO_CACHE', true);
	@define('WTC_IN_MINIFY', true);
	@define('WP_CACHE', false);

	// Prevent errors from defining constants again
	error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);

	include ABSPATH.'/xmlrpc.php';

	exit();

}

// Disables the XML-RPC functionality
function loginizer_xmlrpc_null(){
	return null;
}

// Disables the XML-RPC functionality
function loginizer_xmlrpc_disable(){

	global $loginizer;

	$page = loginizer_cur_page();

	// Is it xmlrpc.php ?
	if ($page === 'xmlrpc.php'){
		echo 'XML-RPC is disabled';
		exit();
	}

}

// Disables the XML-RPC functionality
function loginizer_xmlrpc_remove_pingback_url($output, $show) {

	if($show == 'pingback_url'){
		$output = '';
	}

	return $output;

}

// Disable Pingbacks
function loginizer_pingbacks_disable($methods) {

	if(isset($methods['pingback.ping'])){
		unset($methods['pingback.ping']);
	}

	if(isset($methods['pingback.extensions.getPingbacks'])){
		unset($methods['pingback.extensions.getPingbacks']);
	}

	return $methods;

}

//========================
// Captcha Codes
//========================

// Adjusts the login form
function loginizer_cap_login_form(){
	?>
	<style type="text/css">
	#login {
	width: 350px !important;
	padding: 4% 0 0 !important;
	}
	</style>
	<?php
}

// Verify the login captcha is valid ?
function loginizer_cap_login_verify($user){

	if(defined('REST_REQUEST') && !empty(REST_REQUEST)){
		return $user;
	}

	if(!loginizer_cap_verify()){
		$captcha_fail_msg = __('The CAPTCHA verification failed. Please try again.', 'loginizer-security');

		if(!empty($loginizer['ultimate-member-active']) && class_exists('UM')){
			\UM()->form()->add_error('blocked_msg', $captcha_fail_msg);
		}

		return new WP_Error('loginizer_cap_login_error', $captcha_fail_msg, 'loginizer_cap');
	}

	return $user;

}


// Verify the lostpass captcha is valid ?
function loginizer_cap_lostpass_verify($res, $uid){

	if(!loginizer_cap_verify()){
		$captcha_fail_msg = __('The CAPTCHA verification failed. Please try again.', 'loginizer');
		return new WP_Error('loginizer_cap_lostpass_error', $captcha_fail_msg, 'loginizer_cap');
	}

	return $res;

}

// Verify the resetpass captcha is valid ?
function loginizer_cap_resetpass_verify($errors, $user){

	if(!loginizer_cap_verify()){
		$captcha_fail_msg = __('The CAPTCHA verification failed. Please try again.', 'loginizer');
		$errors->add('loginizer_resetpass_cap_error', $captcha_fail_msg, 'loginizer_cap');
	}

}

// Verify the register captcha is valid ?
function loginizer_cap_register_verify($errors, $username = '', $email = ''){

	if(!loginizer_cap_verify()){
		$captcha_fail_msg = __('The CAPTCHA verification failed. Please try again.', 'loginizer');
		$errors->add('loginizer_cap_register_error', $captcha_fail_msg, 'loginizer_cap');
	}

	return $errors;

}

// Verify the register captcha is valid ?
function loginizer_cap_register_verify_buddypress($errors, $username = '', $email = ''){

	global $bp;

	// $bp is for BuddyPress Registration it does not pass $errors
	if(!loginizer_cap_verify()){
		$captcha_fail_msg = __('The CAPTCHA verification failed. Please try again.', 'loginizer');

		// $bp is for BuddyPress
		$bp->signup->errors['signup_username'] = $captcha_fail_msg;
	}

	return $errors;

}

// Verify the register captcha is valid ?
function loginizer_cap_comment_verify($comment){

	if(!loginizer_cap_verify()){
		wp_die('The CAPTCHA verification failed. Please try again.', 200);
	}

	return $comment;

}

// Verify WooCommerce Checkout Orders
function loginizer_wc_checkout_verify(){

	global $loginizer;

	// Is the registration function verifying it ?
	if(!is_user_logged_in()
		&& get_option('woocommerce_enable_signup_and_login_from_checkout', 'yes') == 'yes'
		&& !empty($loginizer['captcha_register'])){

		// So, no need of any more verification

	// Lets verify
	}elseif(!loginizer_cap_verify()){
		$captcha_fail_msg = __('The CAPTCHA verification failed. Please try again.', 'loginizer');
		wc_add_notice($captcha_fail_msg, 'error');
	}
}

// Reset password form passes $user, hence we need to manually write echo
function loginizer_cap_reset_form($user = false){
	loginizer_cap_form_login(false);
}

// For comment form pass false to echo the form
function loginizer_cap_comment_form($post_id = 0){
	echo '<br />';loginizer_cap_form_social(false);
}

// Converts numbers to words
function loginizer_cap_num_to_words( $number ) {
	$words = array(
		1	 => __( 'one', 'loginizer' ),
		2	 => __( 'two', 'loginizer' ),
		3	 => __( 'three', 'loginizer' ),
		4	 => __( 'four', 'loginizer' ),
		5	 => __( 'five', 'loginizer' ),
		6	 => __( 'six', 'loginizer' ),
		7	 => __( 'seven', 'loginizer' ),
		8	 => __( 'eight', 'loginizer' ),
		9	 => __( 'nine', 'loginizer' ),
		10	 => __( 'ten', 'loginizer' ),
		11	 => __( 'eleven', 'loginizer' ),
		12	 => __( 'twelve', 'loginizer' ),
		13	 => __( 'thirteen', 'loginizer' ),
		14	 => __( 'fourteen', 'loginizer' ),
		15	 => __( 'fifteen', 'loginizer' ),
		16	 => __( 'sixteen', 'loginizer' ),
		17	 => __( 'seventeen', 'loginizer' ),
		18	 => __( 'eighteen', 'loginizer' ),
		19	 => __( 'nineteen', 'loginizer' ),
		20	 => __( 'twenty', 'loginizer' ),
		30	 => __( 'thirty', 'loginizer' ),
		40	 => __( 'forty', 'loginizer' ),
		50	 => __( 'fifty', 'loginizer' ),
		60	 => __( 'sixty', 'loginizer' ),
		70	 => __( 'seventy', 'loginizer' ),
		80	 => __( 'eighty', 'loginizer' ),
		90	 => __( 'ninety', 'loginizer' )
	);

	if ( isset( $words[$number] ) )
		return $words[$number];
	else {
		$reverse = false;

		switch ( get_bloginfo( 'language' ) ) {
			case 'de-DE':
				$spacer = 'und';
				$reverse = true;
				break;

			case 'nl-NL':
				$spacer = 'en';
				$reverse = true;
				break;

			case 'ru-RU':
			case 'pl-PL':
			case 'en-EN':
			default:
				$spacer = ' ';
		}

		$first = (int) (substr( $number, 0, 1 ) * 10);
		$second = (int) substr( $number, -1 );

		return ($reverse === false ? $words[$first] . $spacer . $words[$second] : $words[$second] . $spacer . $words[$first]);
	}
}

// Encode the operation
function loginizer_cap_encode_op($string){
	return $string;
}

// Get the session key. If not there create one
function loginizer_cap_session_key(){

	if(isset($_COOKIE['lz_math_sess']) && preg_match('/[a-z0-9]/is', $_COOKIE['lz_math_sess']) && strlen($_COOKIE['lz_math_sess']) == 40){
		return $_COOKIE['lz_math_sess'];
	}

	// Generate the key
	$new_session_key = lz_RandomString(40);

	// Set the cookie
	if(@setcookie('lz_math_sess', $new_session_key, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl())){
		// Set this to use first time
		$_COOKIE['lz_math_sess'] = $new_session_key;
	}

	return $new_session_key;

}

// Generate the Captcha field if its a Math Captcha
function loginizer_cap_phrase($form = 'default'){

	global $loginizer;

	$ops = array('add' => '+',
				'subtract' => '&#8722;',
				'multiply' => '&#215;',
				'divide' => '&#247;',
			);

	$input = '<input type="text" size="2" length="2" id="loginizer_cap_math" style="display: inline-block;	width: 60px!important; vertical-align: middle; margin-bottom: 0; border:1px solid #8c8f94;" name="loginizer_cap_math" value="" aria-required="true"/>';

	if(empty($loginizer['captcha_add'])){
		unset($ops['add']);
	}

	if(empty($loginizer['captcha_subtract'])){
		unset($ops['subtract']);
	}

	if(empty($loginizer['captcha_multiply'])){
		unset($ops['multiply']);
	}

	if(empty($loginizer['captcha_divide'])){
		unset($ops['divide']);
	}

	// Randomly select an operation
	$rnd_op = array_rand($ops, 1);
	$number[3] = $ops[$rnd_op];

	// Select where to place empty input
	$rnd_input = mt_rand(0, 2);

	// Generate the numbers
	switch ($rnd_op){

		case 'add':

			if($rnd_input === 0){
				$number[0] = mt_rand(1, 10);
				$number[1] = mt_rand(1, 89);
			}elseif($rnd_input === 1) {
				$number[0] = mt_rand(1, 89);
				$number[1] = mt_rand(1, 10);
			}elseif($rnd_input === 2){
				$number[0] = mt_rand(1, 9);
				$number[1] = mt_rand(1, 10 - $number[0]);
			}

			$number[2] = $number[0] + $number[1];
			break;

		case 'subtract':
			if($rnd_input === 0){
				$number[0] = mt_rand(2, 10);
				$number[1] = mt_rand(1, $number[0] - 1);
			}elseif($rnd_input === 1){
				$number[0] = mt_rand(11, 99);
				$number[1] = mt_rand(1, 10);
			}elseif($rnd_input === 2){
				$number[0] = mt_rand(11, 99);
				$number[1] = mt_rand($number[0] - 10, $number[0] - 1);
			}

			$number[2] = $number[0] - $number[1];
			break;

		case 'multiply':
			if($rnd_input === 0){
				$number[0] = mt_rand(1, 10);
				$number[1] = mt_rand(1, 9);
			}elseif($rnd_input === 1){
				$number[0] = mt_rand(1, 9);
				$number[1] = mt_rand(1, 10);
			}elseif($rnd_input === 2){
				$number[0] = mt_rand(1, 10);
				$number[1] = ($number[0] > 5 ? 1 : ($number[0] === 4 && $number[0] === 5 ? mt_rand(1, 2 ) : ($number[0] === 3 ? mt_rand(1, 3 ) : ($number[0] === 2 ? mt_rand(1, 5 ) : mt_rand(1, 10 )))));
			}

			$number[2] = $number[0] * $number[1];
			break;

		case 'divide':
			$divide = array( 1 => 99, 2 => 49, 3 => 33, 4 => 24, 5 => 19, 6 => 16, 7 => 14, 8 => 12, 9 => 11, 10 => 9 );

			if($rnd_input === 0){
				$divide = array( 2 => array( 1, 2 ), 3 => array( 1, 3 ), 4 => array( 1, 2, 4 ), 5 => array( 1, 5 ), 6 => array( 1, 2, 3, 6 ), 7 => array( 1, 7 ), 8 => array( 1, 2, 4, 8 ), 9 => array( 1, 3, 9 ), 10 => array( 1, 2, 5, 10 ) );
				$number[0] = mt_rand(2, 10);
				$number[1] = $divide[$number[0]][mt_rand(0, count( $divide[$number[0]] ) - 1 )];
			}elseif($rnd_input === 1){
				$number[1] = mt_rand(1, 10);
				$number[0] = $number[1] * mt_rand(1, $divide[$number[1]]);
			}elseif($rnd_input === 2){
				$number[2] = mt_rand(1, 10 );
				$number[0] = $number[2] * mt_rand(1, $divide[$number[2]]);
				$number[1] = (int) ($number[0] / $number[2]);
			}

			if(! isset( $number[2] ) )
				$number[2] = (int) ($number[0] / $number[1]);

			break;
	}

	// Are we to display in words ?
	if(!empty($loginizer['captcha_words'])){
		if($rnd_input === 0){
			$number[1] = loginizer_cap_num_to_words( $number[1] );
			$number[2] = loginizer_cap_num_to_words( $number[2] );
		}elseif($rnd_input === 1){
			$number[0] = loginizer_cap_num_to_words( $number[0] );
			$number[2] = loginizer_cap_num_to_words( $number[2] );
		}elseif($rnd_input === 2){
			$number[0] = loginizer_cap_num_to_words( $number[0] );
			$number[1] = loginizer_cap_num_to_words( $number[1] );
		}
	}

	// Finally make the input field
	if(in_array( $form, array( 'default' ) ) ){

		// As per the position of the empty input
		if($rnd_input === 0 ){
			$return = $input . ' ' . $number[3] . ' ' . loginizer_cap_encode_op( $number[1] ) . ' = ' . loginizer_cap_encode_op( $number[2] );
		}elseif($rnd_input === 1 ){
			$return = loginizer_cap_encode_op( $number[0] ) . ' ' . $number[3] . ' ' . $input . ' = ' . loginizer_cap_encode_op( $number[2] );
		}elseif($rnd_input === 2 ){
			$return = loginizer_cap_encode_op( $number[0] ) . ' ' . $number[3] . ' ' . loginizer_cap_encode_op( $number[1] ) . ' = ' . $input;
		}
	}

	// Get the session ID
	$session_id = loginizer_cap_session_key();

	// Save the time
	set_transient('lz_math_cap_'.$session_id, sha1(AUTH_KEY . $number[$rnd_input] . $session_id, false), $loginizer['captcha_time']);

	// Save the value in the users cookie
	//loginizer_cap_cookie_set(sha1(AUTH_KEY . $number[$rnd_input] . $session_id, false));

	// In some themes the input field does not look fine if it is not in a div it used to take full page height for the input text tag
	$return = '<div style="display:flex;align-items: center;gap: 3px;">'.$return.'</div>';

	return $return;
}

// Captcha form for ecommerce
function loginizer_cap_form_ecommerce($return = false, $id = ''){
	return loginizer_cap_form($return, $id, 'ecommerce');
}

// Captcha form for login
function loginizer_cap_form_login($return = false, $id = ''){
	return loginizer_cap_form($return, $id, 'login');
}

function loginizer_cap_wp_login_form($content = '', $id = ''){
	return loginizer_cap_form(true, $id, 'login');
}

// Captcha form for ultimate member
function loginizer_cap_form_um_login(){
    return loginizer_cap_form_login();
}


// Captcha form for comments/social
function loginizer_cap_form_social($return = false, $id = ''){
	return loginizer_cap_form($return, $id, 'social');
}

// Shows the captcha

function loginizer_cap_form($return = false, $id = '', $page_type = 'login'){

	global $loginizer;
	
	$is_checkout = function_exists('is_checkout') && is_checkout();

	// Math Captcha
	if(!empty($loginizer['captcha_no_google'])){

		// We generate it only once
		if(empty($GLOBALS['lz_captcha_no_google'])){
			$GLOBALS['lz_captcha_no_google'] = $loginizer['captcha_text'].'<br>'.loginizer_cap_phrase().'<br><br>';
		}

		// Store this value
		$field = $GLOBALS['lz_captcha_no_google'];

	// hcaptcha
	}else if(!empty($loginizer['captcha_status']) && $loginizer['captcha_status'] === 3){

		if(!wp_script_is('loginizer_hcaptcha_script', 'registered')){
			// For block based woo checkout, we have to render the captcha explictly
			$query_parameters = '';
			if(!empty($loginizer['captcha_wc_block_checkout']) && !empty($is_checkout)){
				$query_parameters = '?render=explicit';
			}
			
			wp_register_script('loginizer_hcaptcha_script', 'https://js.hcaptcha.com/1/api.js'.$query_parameters, ['jquery'], 1, ['strategy' => 'defer']);
		}

		wp_enqueue_script('loginizer_hcaptcha_script');

		$field = '<div class="h-captcha" data-sitekey="' . esc_attr($loginizer['hcaptcha_sitekey']) . '" data-hl="' . esc_attr($loginizer['hcaptcha_lang']) . '" data-theme="' . esc_attr($loginizer['hcaptcha_theme']) . '" data-size="' .esc_attr($loginizer['hcaptcha_size']) .'" ></div>';

	// Cloudflare Turnstile
	} elseif(!empty($loginizer['captcha_status']) && $loginizer['captcha_status'] === 4){

		$do_multiple = false;
		if(!wp_script_is('loginizer_turnstil_script', 'registered')){
			// For block based woo checkout, we have to render the captcha explictly
			$query_parameters = '';
			if(!empty($loginizer['captcha_wc_block_checkout']) && !empty($is_checkout)){
				$query_parameters = '?render=explicit';
			}
			
			wp_register_script('loginizer_turnstil_script', 'https://challenges.cloudflare.com/turnstile/v0/api.js'.$query_parameters, ['jquery'], 0, ['strategy' => 'defer', 'in_footer' => true]);
		}

		wp_enqueue_script('loginizer_turnstil_script');

		$field = '<div class="cf-turnstile" id="lz-turnstile-div" data-sitekey="'.esc_attr($loginizer['turn_captcha_key']).'" data-theme="'.esc_attr($loginizer['turn_captcha_theme']).'" data-language="'.esc_attr($loginizer['turn_captcha_lang']).'" data-size="'.esc_attr($loginizer['turn_captcha_size']).'" style="margin-bottom:10px;"></div>';

	// Google reCaptcha
	} else {

		$field = '';
		$query_string = array();
		
		// For block based woo checkout, we have to render the captcha explictly
		if(!empty($loginizer['captcha_wc_block_checkout']) && !empty($is_checkout)){
			$query_string['render'] = 'explicit';
		}

		$captcha_type = (!empty($loginizer['captcha_type']) ? $loginizer['captcha_type'] : '');
		$site_key = $loginizer['captcha_key'];
		$theme = $loginizer['captcha_theme'];
		$size = $loginizer['captcha_size'];
		$no_js = $loginizer['captcha_no_js'];
		$captcha_ver = 2;
		$captcha_js_ver = '2.0';
		$invisible = 0;

		if($captcha_type == 'v3'){
			$invisible = 1;
			$captcha_ver = 3;
			$captcha_js_ver = '3.0';
			$do_multiple = 1;
			$lz_cap_div_class = 'lz-recaptcha-invisible-v3';

			if(!empty($site_key)){
				$query_string['render'] = $site_key;
			}
		}

		// For v2 invisible
		if($captcha_type == 'v2_invisible'){
			$invisible = 1;
			$do_multiple = 1;
			$size = 'invisible';
			$lz_cap_div_class = 'lz-recaptcha-invisible-v2';
			$query_string['render'] = 'explicit';
		}

		// Is this a first call ?
		if(!wp_script_is('loginizer_cap_script', 'registered')){

			$language = $loginizer['captcha_lang'];
			if(!empty($language)){
				$query_string['hl'] = $language;
			}

			// We need these variables in JS
			if(!empty($invisible)){
				$field .= '<script>
				var lz_cap_ver = "'.$captcha_ver.'";
				var lz_cap_sitekey = "'.$site_key.'";
				var lz_cap_div_class = "'.$lz_cap_div_class.'";
				var lz_cap_page_type = "'.$page_type.'";
				var lz_cap_invisible = "1";
				</script>';
			}

			wp_register_script('loginizer_cap_script', "https://".$loginizer['captcha_domain']."/recaptcha/api.js?".http_build_query($query_string), array('jquery'), $captcha_js_ver, true);

		// We need to load multiple times
		}else{
			$do_multiple = 1;
		}

		if(!empty($do_multiple)){

			if(!wp_script_is('loginizer_multi_cap_script', 'registered')){
				wp_register_script('loginizer_multi_cap_script', LOGINIZER_PRO_DIR_URL.'/assets/js/multi-recaptcha.js', array('jquery'), $captcha_js_ver, true);
			}
			wp_enqueue_script('loginizer_multi_cap_script');

		}

		wp_enqueue_script('loginizer_cap_script');

		// For v3 everything is done in javascript
		if(empty($invisible)){

			if(!empty($loginizer['captcha_disable_btn'])){
				// This disables the Login button and undo that when the captcha succeed
				wp_add_inline_script('loginizer_cap_script', '
					jQuery(document).ready(function(){
						let form = jQuery(".lz-recaptcha").closest("form");
						button = form.find("[type=\"submit\"]");
						button.attr("disabled", true);
					});

					function lz_recaptcha_callback(){
						let form = jQuery(".lz-recaptcha").closest("form");

						button = form.find("[type=\"submit\"]");
						button.attr("disabled", false);
					}
				');
			}

			$field .= "<div ".(!empty($id) ? 'id="'.$id.'"' : '')." class='g-recaptcha lz-recaptcha' data-sitekey='$site_key' data-theme='$theme' data-size='$size' ".(!empty($loginizer['captcha_disable_btn']) ? 'data-callback="lz_recaptcha_callback"' : '')."></div>";

			if($no_js == 1){

				$field .= "
<noscript>
	<div style='width: 302px; height: 352px;'>
		<div style='width: 302px; height: 352px; position: relative;'>
			<div style='width: 302px; height: 352px; position: absolute;'>
				<iframe src='https://".$loginizer['captcha_domain']."/recaptcha/api/fallback?k=$site_key' frameborder='0' scrolling='no' style='width: 302px; height:352px; border-style: none;'>
				</iframe>
			</div>
			<div style='width: 250px; height: 80px; position: absolute; border-style: none; bottom: 21px; left: 25px; margin: 0px; padding: 0px; right: 25px;'>
				<textarea name='g-recaptcha-response' class='g-recaptcha-response' style='width: 250px; height: 80px; border: 1px solid #c1c1c1; margin: 0px; padding: 0px; resize: none;' value=''>
				</textarea>
			</div>
		</div>
	</div>
</noscript>";

			}

			$field .= '<br>';

		}else{

			$field .= '<div class="'.$lz_cap_div_class.'"></div>';

			if($captcha_ver == 3){
				$field .= '<input type="hidden" name="g-recaptcha-response" class="lz-v3-input" value="">';
			}
		}
	}
	
	if($is_checkout){
		wp_enqueue_script('loginizer-cap-woo-block', LOGINIZER_PRO_DIR_URL . '/assets/js/woocommerce.js', ['jquery', 'wp-data'], LOGINIZER_PRO_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
	}

	// Are we to return the code ?
	if($return){
		return $field;

	// Lets echo it
	}else{
		echo $field;
	}
}

// Verifies the Google Captcha	and is called by individual for verifiers
// @since 2.0.4 $token is used currently only to handle woo block based checkout.
function loginizer_cap_verify($token = ''){

	global $loginizer;

	// WooCommerce is calling this function as well. Hence Captcha fails
	if(isset($GLOBALS['called_loginizer_cap_verify'])){
		return $GLOBALS['called_loginizer_cap_verify'];
	}

	// Is the post set/ or the data is being passed through token.
	if(count($_POST) < 1 && empty($token)){
		return true;
	}

	// Some plugin allows to login via Google account does not post the captcha details but does add ONLY rememberme to POST causing the captcha validation to trigger
	if(count($_POST) == 1 && isset($_POST['rememberme'])){
		return true;
	}

	$GLOBALS['called_loginizer_cap_verify'] = true;

	// Math Captcha
	if(!empty($loginizer['captcha_no_google'])){

		if(empty($token)){
			$response = (int) (!empty($_POST['loginizer_cap_math']) ? $_POST['loginizer_cap_math'] : '');
		} else {
			$response = $token;
		}

		// Is the response valid ?
		if(!is_numeric($response) || empty($response)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		// Get the session ID
		$session_id = loginizer_cap_session_key();

		// Is the response valid ?
		if(empty($session_id)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		// Get the Value stored
		$captcha_value = get_transient('lz_math_cap_'.$session_id);

		// Do we have a stored value ?
		if(empty($captcha_value) || strlen($captcha_value) != 40){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		// Is the value matching
		if($captcha_value != sha1(AUTH_KEY . $response . $session_id, false)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		return true;

	// hcaptcha
	}else if(!empty($loginizer['hcaptcha_sitekey']) && !empty($loginizer['captcha_status']) && $loginizer['captcha_status'] == 3){

		if(empty($loginizer['hcaptcha_sitekey'])){
			return true;
		}

		if(empty($token)){
			$hresponse = (!empty($_POST['h-captcha-response']) ? $_POST['h-captcha-response'] : '');
		} else {
			$hresponse = $token;
		}
		$ip = lz_getip();

		// Is the IP or response not there ?
		if(empty($hresponse) || empty($ip)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		$url = 'https://hcaptcha.com/siteverify';

		// Verify the post
		$req = wp_remote_post($url, [
				'timeout' => 10,
				'body' => [
					'secret' => $loginizer['hcaptcha_secretkey'],
					'response' => $hresponse,
					'remoteip' => $ip,
				]
			]
		);

		// Was there an error posting ?
		if(is_wp_error($req)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		// Process the post response
		$resp = wp_remote_retrieve_body($req);

		// Is the body valid
		if(empty($resp)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		$json = json_decode($resp, true);

		if(!empty($json['success'])){
			return true;
		}

	// Turnstile Captcha
	} elseif(!empty($loginizer['turn_captcha_secret']) && !empty($loginizer['captcha_status']) && $loginizer['captcha_status'] == 4){

		if(empty($token)){
			$response = (!empty($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '');
		} else {
			$response = $token;
		}

		$ip = lz_getip();

		// Is the IP or response not there ?
		if(empty($response) || empty($ip)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		$url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

		$req = wp_remote_post($url, [
			'timeout' => 10,
			'body' => [
				'response' => $response,
				'secret' => $loginizer['turn_captcha_secret'],
				'remoteip' => $ip
			]
		]);

		if(is_wp_error($req)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		// Process the post response
		$resp = wp_remote_retrieve_body($req);

		// Is the body valid
		if(empty($resp)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		$json = json_decode($resp, true);

		if(!empty($json['success'])){
			return true;
		}

	}else{
		// If secret key is not there, return
		if(empty($loginizer['captcha_secret'])){
			return true;
		}

		if(empty($token)){
			$response = (!empty($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '');
		} else {
			$response = $token;
		}

		$ip = lz_getip();

		// Is the IP or response not there ?
		if(empty($response) || empty($ip)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		$url = 'https://'.$loginizer['captcha_domain'].'/recaptcha/api/siteverify';

		// Verify the post
		$req = wp_remote_post($url, [
				'timeout' => 10,
				'body' => [
					'secret' => $loginizer['captcha_secret'],
					'response' => $response,
					'remoteip' => $ip
				]
			]
		);

		// Was there an error posting ?
		if(is_wp_error($req)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		// Process the post response
		$resp = wp_remote_retrieve_body($req);

		// Is the body valid
		if(empty($resp)){
			$GLOBALS['called_loginizer_cap_verify'] = false;
			return false;
		}

		$json = json_decode($resp, true);

		if(!empty($json['success'])){
			if(!empty($json['score']) && !empty($loginizer['captcha_score_threshold']) && $json['score'] <= $loginizer['captcha_score_threshold']){
				// The user is likely a bot
				$GLOBALS['called_loginizer_cap_verify'] = false;
				return false;
			}

			return true;
		}

	}

	// Couldnt verify
	$GLOBALS['called_loginizer_cap_verify'] = false;
	return false;

}

function loginizer_wc_before_checkout_process(){

	global $loginizer;

	// This is checkout page. If admin has disabled captcha on checkout
	// and the user is registering during checkout we have not displayed the captcha form so we should not verify the same
	if(empty($loginizer['captcha_wc_checkout'])){
		remove_filter('woocommerce_registration_errors', 'loginizer_cap_register_verify');
	}
}

//=========================================
// Registration Domain Blacklist
//=========================================

function loginizer_domains_blacklist($errors, $username, $email){

	global $wpdb, $loginizer, $lz_error;

	$domains = $loginizer['domains_blacklist'];
	$domains = is_array($domains) ? $domains : array();

	// Are you blacklisted ?
	foreach($domains as $domain_to_match){

		$domain_to_match = str_replace('*', '(.*?)', $domain_to_match);

		if(preg_match('/'.$domain_to_match.'$/is', $email)){
			$match_found = 1;
		}

	}

	// Did we get a match ?
	if(!empty($match_found)){
		$errors->add('loginizer_domains_blacklist_error', 'The domain of your email is banned from registering on this website', 'loginizer_domains_blacklist');
	}

	return $errors;

}

//=========================================
// 2 Factor Auth / Question based security
//=========================================

// Handle the users secondary login i.e. 2fa / question, etc.
function loginizer_user_redirect($user, $username, $password){

	global $loginizer;

	// Is the post set ?
	if(count($_POST) < 1 && empty($_GET['lz_social_provider'])){
		return $user;
	}

	//print_r($user);die();

	// Is it a valid user ?
	if(!is_a($user, 'WP_User')){
		return $user;
	}

	// The user has given correct details
	// Now does the user have any of our features enabled ?
	$settings = get_user_meta($user->ID, 'loginizer_user_settings', true);
	//print_r($settings);die();

	// Is it applicable as per role
	if(!loginizer_is_2fa_applicable($user)){
		return $user;
	}

	if(loginizer_is_whitelisted_2fa()){
		return $user;
	}

	// Set the default return to the user only
	$ret = $user;

	// Is it a secondary question ?
	if(!empty($settings['pref']) && $settings['pref'] == 'question'){

		// Is there a question and answer
		if(!empty($settings['question']) && !empty($settings['answer'])){
			$save = 1;
		}

	}

	// Is it a 2fa via App ?
	if(!empty($settings['pref']) && $settings['pref'] == '2fa_app'){

		if(!empty($settings['app_enable'])){
			$save = 1;
		}

	}

	// Is it a 2fa via email ?
	if((!empty($settings['pref']) && $settings['pref'] == '2fa_email')
		|| ((empty($settings['pref']) || @$settings['pref'] == 'none') && !empty($loginizer['2fa_email_force']))
	){

		// Generate a 6 digit code
		$otp = wp_rand(100000, 999999);
		$r['code'] = base64_encode($otp);

		// Email them
		$site_name = get_bloginfo('name');

		$first_name = get_user_meta($user->ID, 'first_name', true);
		$last_name = get_user_meta($user->ID, 'last_name', true);

		if(empty($first_name)){
			$first_name = $user->data->display_name;
		}

		$vars = array('email' => $user->data->user_email,
					'otp' => $otp,
					'site_name' => $site_name,
					'site_url' => get_site_url(),
					'display_name' => $user->data->display_name,
					'user_login' => $user->data->user_login,
					'first_name' => $first_name,
					'last_name' => $last_name);

		$subject = lz_lang_vars_name($loginizer['2fa_email_sub'], $vars);
		$message = lz_lang_vars_name($loginizer['2fa_email_msg'], $vars);

		$headers = [];
		// Do we need to send the email as HTML ?
		if(!empty($loginizer['2fa_email_html'])){
			$headers[] = 'Content-Type: text/html; charset=UTF-8';

			if(!empty($loginizer['2fa_email_msg'])){
				$message = html_entity_decode($message);
			}else{
				$message = preg_replace("/\<br\s*\/\>/i", "<br/>", $message);
				$message = preg_replace('/(?<!<br\/>)\n/i', "<br/>\n", $message);
			}
		}

		//echo $user->data->user_email.'<br>'.$message;die();

		$sent = wp_mail($user->data->user_email, $subject, $message, $headers);

		if(empty($sent)){
			// For plugins that login using AJAX
			if(!empty(get_transient('loginizer_2fa_'. $user->ID))){
				return array('message' => esc_html__('There was a problem sending your email with the OTP. Please try again or contact an admin.', 'loginizer'));
			}

			return new WP_Error('email_not_sent', 'There was a problem sending your email with the OTP. Please try again or contact an admin.', 'loginizer_2fa_email');
		}else{
			$save = 1;
		}

	}

	// Are we to create and save a token ?
	if(!empty($save)){

		// Are we to be remembered ?
		$r['rememberme'] = lz_optreq('rememberme');

		// Create a token
		$token = loginizer_user_token($user->ID, $r);

		// For custom redirect on Login when 2FA is enabled
		$custom_redirects = get_option('loginizer_2fa_custom_redirect');

		if(!empty($custom_redirects) && !empty($user->roles)){
			foreach($user->roles as $role){
				if(!empty($custom_redirects[$role]) && wp_validate_redirect($custom_redirects[$role])){
					$_REQUEST['redirect_to'] = wp_validate_redirect($custom_redirects[$role]);
					break;
				}
			}
		}

		$redirect_to = '';
		if(!empty($_REQUEST['redirect_to'])){
			$redirect_to = '&redirect_to='.urlencode($_REQUEST['redirect_to']);
		} elseif(!empty($_REQUEST['redirect'])){
			$redirect_to = '&redirect_to='.urlencode($_REQUEST['redirect']);
		} elseif(empty($_REQUEST['lz_social_provider']) && !empty(wp_validate_redirect($_SERVER['HTTP_REFERER']))){
			$redirect_to = '&redirect_to='.urlencode(wp_validate_redirect($_SERVER['HTTP_REFERER']));
		}

		// Form the URL
		$url = wp_login_url().'?action=loginizer_security&uid='.$user->ID.'&lutoken='.$token.$redirect_to.(isset( $_REQUEST['interim-login'] ) ? '&interim-login=1' : '').(!empty($_SERVER['IS_WPE']) ? '&wpe-login=true' : '');

		// For plugins that login using AJAX
		if(!empty(get_transient('loginizer_2fa_'. $user->ID))){
			return $url;
		}

		loginizer_update_attempt_stats(1);

		// Lets redirect
		wp_safe_redirect($url);
		die();

	}

	return $ret;
}

function loginizer_is_epl_disabled(){
	global $loginizer;

	$page_from = '';
	$is_wordpress_login = isset($_POST['log']) && isset($_POST['pwd']) && isset($_POST['wp-submit']);
	$is_woocommerce_login = isset($_POST['username']) && isset($_POST['password']) && isset($_POST['woocommerce-login-nonce']);

	if(!empty($is_woocommerce_login)){
		$page_from = 'woocommerce';
	}elseif(!empty($is_wordpress_login)){
		$page_from = 'admin';
	}

	// We do not want to use Password less login auth if it is disabled.
	if(!empty($page_from) && !empty($loginizer['passwordless_disabled_for']) && is_array($loginizer['passwordless_disabled_for']) && in_array($page_from, $loginizer['passwordless_disabled_for'])){
		return true;
	}

	return false;
}

// Creates a one time token
function loginizer_user_token($uid = 0, $r = array()){

	global $loginizer;

	// Variables
	$time = time();
	$expires = ($time + 600);
	$action =  'loginizer_user_token';

	include_once( ABSPATH.'/'.$loginizer['wp-includes'].'/class-phpass.php');
	$wp_hasher = new PasswordHash(8, TRUE);

	// Create the token with a random salt and the time
	$token  = wp_hash(wp_generate_password(20, false).$action.$time);

	// Create a hash of the token
	$r['stored_hash'] = $wp_hasher->HashPassword($expires.$token);
	$r['expires'] = $expires;

	// Store the hash and when it expires
	update_user_meta($uid, $action, $r);

	return $token;

}

// Process the secondary form i.e. question / 2fa, etc.
function loginizer_user_security(){

	global $loginizer, $lz, $lz_error, $interim_login;

	if(empty($_GET['uid']) || empty($_GET['lutoken'])){
		return false;
	}

	$uid = (int) sanitize_key($_GET['uid']);
	$token = sanitize_key($_GET['lutoken']);
	$action = 'loginizer_user_token';

	$meta = get_user_meta($uid, $action, true);
	$hash = !empty($meta['stored_hash']) ? $meta['stored_hash'] : '';
	$expires = !empty($meta['expires']) ? $meta['expires'] : '';

	include_once(ABSPATH.'/'.$loginizer['wp-includes'].'/class-phpass.php');
	$wp_hasher = new PasswordHash(8, TRUE);
	$time = time();

	if(!$wp_hasher->CheckPassword($expires.$token, $hash) || $expires < $time){

		// Throw an error
		$lz['error'] = sprintf(__('The token is invalid or has expired. Please provide your user details by clicking <a href="%s">here</a>', 'loginizer'), wp_login_url());
		loginizer_user_security_form();

	}

	// Get the username
	$userdata = get_userdata($uid);
	$username = $userdata->data->user_login;

	// Load the settings
	$lz['settings'] = get_user_meta($uid, 'loginizer_user_settings', true);

	// If the user was just created and the settings is empty
	if(empty($lz['settings'])){
		$lz['settings'] = array();
	}

	if((empty($lz['settings']['pref']) || $lz['settings']['pref'] == 'none') && !empty($loginizer['2fa_email_force'])){
		$lz['settings']['pref'] = '2fa_email';
	}

	/* Make sure post was from this page */
	if(count($_POST) > 0 && !check_admin_referer('loginizer-enduser')){
		$lz['error'] = __('The form security was compromised !', 'loginizer');
		loginizer_user_security_form();
	}

	// Has the user reached max attempts ?
	if(!loginizer_can_login()){
		$lz['error'] = $lz_error;
		loginizer_user_security_form();
	}

	$interim_login = isset( $_REQUEST['interim-login'] );

	// Process the post
	if(!empty($_POST['lus_submit'])){

		if(@$lz['settings']['pref'] == 'question'){

			// Is there an answer ?
			$answer = lz_optpost('lus_value');

			// Is the answer correct ?
			if($answer != @base64_decode($lz['settings']['answer'])){

				loginizer_login_failed($username.' | 2FA-Answer', 1);
				$lz['error'][] = __('The answer is wrong !', 'loginizer');
				$lz['error'][] = loginizer_retries_left();
				loginizer_user_security_form();

			// Login the user
			}else{

				$do_login = 1;

			}

		}

		if(@$lz['settings']['pref'] == '2fa_email'){

			// Is there an OTP ?
			$otp = lz_optpost('lus_value');

			// Is the answer correct ?
			if($otp != @base64_decode($meta['code'])){

				loginizer_login_failed($username.' | 2FA-Email', 1);
				$lz['error'][] = __('The OTP is wrong !', 'loginizer');
				$lz['error'][] = loginizer_retries_left();
				loginizer_user_security_form();

			// Login the user
			}else{

				$do_login = 1;

			}

		}

		// App based login
		if(@$lz['settings']['pref'] == '2fa_app'){

			// Is there an OTP ?
			$otp = lz_optpost('lus_value');

			$app2fa = loginizer_2fa_app($uid);

			// Is the answer correct ?
			if($otp != $app2fa['2fa_otp']){

				// Maybe its an Emergency OTP
				if(empty($lz['settings']['2fa_emergency']) || !@in_array($otp, $lz['settings']['2fa_emergency'])){

					loginizer_login_failed($username.' | 2FA-APP', 1);
					$lz['error'][] = __('The OTP is wrong !', 'loginizer');
					$lz['error'][] = loginizer_retries_left();
					loginizer_user_security_form();

				}else{

					// Remove the Emergency used and save the rest
					unset($lz['settings']['2fa_emergency'][$otp]);

					// Save it
					update_user_meta($uid, 'loginizer_user_settings', $lz['settings']);

					$do_login = 1;

				}

			// Login the user
			}else{

				$do_login = 1;

			}

		}

		// Are we to login ?
		if(!empty($do_login)){

			$remember_me = !empty($meta['rememberme']) ? true : false;

			// Login the User
			wp_set_auth_cookie($uid, $remember_me);

			// Delete the meta
			delete_user_meta($uid, $action);
			delete_transient('loginizer_2fa_' . $uid); // it is generated when the user logins through some ajax form

			$redirect_to = !empty($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : admin_url();

			// Redirect and exit

			$user = get_user_by('ID', $uid);
			loginizer_login_success($user->user_login, $user);
			// Interim Login is used when session times out due to inactivity and login form is opened in a popup iframe
			if ( $interim_login ) {

				$message       = '<p class="message">' . __( 'You have logged in successfully.', 'loginizer' ) . '</p>';
				$interim_login = 'success';
				login_header( '', $message );

				?>
				</div>
				<?php

				/** This action is documented in wp-login.php */
				do_action( 'login_footer' );

				?>

				</body></html>

				<?php
			}else{
				$redirect_to = loginizer_csrf_change_url($redirect_to, $uid);

				if(isset($_REQUEST['lus_is_popup'])){
					echo '<script>
					if(window.opener && window.opener !== window){
						window.opener.location.href="'.wp_validate_redirect(wp_sanitize_redirect($redirect_to)).'";
						window.close();
					}
					</script>';
				}

				wp_safe_redirect($redirect_to);
			}
			exit;

		}

	}

	loginizer_user_security_form();

}

// Shows the secondary form i.e. question / 2fa, etc.
function loginizer_user_security_form(){

	global $loginizer, $lz;

	// Some plugins uses these actions
	do_action( 'login_init' );
	do_action( "login_form_login" );

	// Deletes cookie by setting past time, this cookie is created when you login from a login that works using AJAX
	@setCookie('loginizer_2fa_' . get_current_user_id(), '', time() - DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, true);

	login_header();

	if(!empty($lz['error'])){

		if(!is_array($lz['error'])){
			$lz['error'] = array($lz['error']);
		}

		echo '<div id="login_error" class="notice notice-error">';
		foreach($lz['error'] as $ek => $error_txt){
			echo wp_kses($error_txt, NULL).'<br />';
		}
		echo '</div>';
	}

	if(!empty($lz['settings'])){

		echo '<form action="" method="post" autocomplete="new-password">';
		wp_nonce_field('loginizer-enduser');

		// Are we to ask a question
		if(@$lz['settings']['pref'] == 'question'){

			echo '<p>
				'.$loginizer['2fa_msg']['otp_question'].' : <br /><br />
				<span title="" style="color:#444; font-size:16px">
					'.$lz['settings']['question'].'<br />
				</span>
			</p>
			<br />
			<p>
				<label title="">
					'.$loginizer['2fa_msg']['otp_answer'].'
					<div class="wp-pwd">
					<input type="text" name="lus_value" id="lus_value" class="input password-input" value="" size="20" autocomplete="off" />
					</div>
				</label>
			</p>';

		}

		// Its a 2fa email
		if(@$lz['settings']['pref'] == '2fa_email'){

			echo '<p>'.$loginizer['2fa_msg']['otp_email'].'</p>
			<br>
			<p>
				<label title="">
					'.$loginizer['2fa_msg']['otp_field'].'
					<div class="wp-pwd">
					<input type="text" name="lus_value" id="lus_value" class="input password-input" value="" size="20" autocomplete="off" />
					</div>
				</label>
			</p>';

		}

		// Its a 2fa app ?
		if(@$lz['settings']['pref'] == '2fa_app'){

			echo '<p>'.$loginizer['2fa_msg']['otp_app'].'</p>
			<br>
			<p>
				<label title="">
					'.$loginizer['2fa_msg']['otp_field'].'
					<div class="wp-pwd">
					<input type="text" name="lus_value" id="lus_value" class="input password-input" value="" size="20" autocomplete="off" />
					</div>
				</label>
			</p>';

		}

		echo '<p class="submit">
			<input type="submit" id="lus_submit" name="lus_submit" class="button button-primary button-large" value="'.__('Log In', 'loginizer').'" />
		</p>
		</form>
		<script>
		// To handle condition when 2fa is opened in a popup
		if(window.opener && window.opener !== window){
			// Create the hidden input element
			const hiddenInput = document.createElement("input");
			hiddenInput.type = "hidden";
			hiddenInput.name = "lus_is_popup";
			hiddenInput.value = "true";

			const submitParagraph = document.querySelector("p.submit");

			if(submitParagraph){
				// Insert input after submit input
				submitParagraph.insertAdjacentElement("afterend", hiddenInput);
			}
		}
		</script>';

	}

	// Focus on the field
	login_footer('lus_value');
	exit();

}

// Show the 2fa Notice
function loginizer_2fa_notice(){

	echo '
<style>
.lz_button {
background-color: #4CAF50; /* Green */
border: none;
color: white;
padding: 8px 16px;
text-align: center;
text-decoration: none;
display: inline-block;
font-size: 16px;
margin: 4px 2px;
-webkit-transition-duration: 0.4s; /* Safari */
transition-duration: 0.4s;
cursor: pointer;
}

.lz_button:focus{
border: none;
color: white;
}

.lz_button1 {
color: white;
background-color: #4CAF50;
border:3px solid #4CAF50;
}

.lz_button1:hover {
box-shadow: 0 6px 8px 0 rgba(0,0,0,0.24), 0 9px 25px 0 rgba(0,0,0,0.19);
color: white;
border:3px solid #4CAF50;
}

.lz_button2 {
color: white;
background-color: #0085ba;
}

.lz_button2:hover {
box-shadow: 0 6px 8px 0 rgba(0,0,0,0.24), 0 9px 25px 0 rgba(0,0,0,0.19);
color: white;
}

.lz_button3 {
color: white;
background-color: #365899;
}

.lz_button3:hover {
box-shadow: 0 6px 8px 0 rgba(0,0,0,0.24), 0 9px 25px 0 rgba(0,0,0,0.19);
color: white;
}

.lz_button4 {
color: white;
background-color: rgb(66, 184, 221);
}

.lz_button4:hover {
box-shadow: 0 6px 8px 0 rgba(0,0,0,0.24), 0 9px 25px 0 rgba(0,0,0,0.19);
color: white;
}

.loginizer_2fa_notice-close{
float:right;
text-decoration:none;
margin: 5px 10px 0px 0px;
}

.loginizer_2fa_notice-close:hover{
color: red;
}
</style>

<script>
jQuery(document).ready( function() {
	(function($) {
		$("#loginizer_2fa_notice .loginizer_2fa_notice-close").click(function(){
			var data;

			// Hide it
			$("#loginizer_2fa_notice").hide();

			// Save this preference
			$.post("'.admin_url('?loginizer_2fa_notice=0').'", data, function(response) {
				//alert(response);
			});
		});


		$("#loginizer_2fa_notice .lz_button2").click(function(){
			var data;

			// Hide it
			$("#loginizer_2fa_notice").hide();

			// Save this preference
			$.post("'.admin_url('?loginizer_2fa_notice=1').'", data, function(response) {
				//alert(response);
			});
		});
	})(jQuery);
});
</script>

<div class="notice notice-success" id="loginizer_2fa_notice" style="min-height:120px">
	<a class="loginizer_2fa_notice-close" href="javascript:" aria-label="Dismiss this Notice">
		<span class="dashicons dashicons-dismiss"></span> Dismiss Forever
	</a>
	<img src="'.LOGINIZER_URL.'/assets/images/loginizer-200.png" style="float:left; margin:10px 20px 10px 10px" width="100" />
	<p style="font-size:16px">'.__('The site admin has enabled Two Factor Authentication features to secure your account. <br>For your safety, you must setup your login security preferences.', 'loginizer').'</p>
	<p>
		<a class="lz_button lz_button1" href="'.admin_url('?page=loginizer_user').'">'.__('Setup My Security Settings', 'loginizer').'</a>
		<a id="" class="lz_button lz_button2" href="javascript:void(0)">'.__('Remind me later', 'loginizer').'</a>
	</p>
</div>';

}

// Shows the user menu to all users
function loginizer_user_menu(){
	add_menu_page(__('My Loginizer Security Settings', 'loginizer'), __('My Security', 'loginizer'), 'read', 'loginizer_user', 'loginizer_user_page', '', 72);
}

// Generates the 2FA as seen in the APP
function loginizer_2fa_app_key($settings, $length = 6, $counter = 0){

	$key = $settings['2fa_key'];
	$type = (empty($settings['2fa_type']) ? 'totp' : $settings['2fa_type']);

	if($type == 'hotp'){
		$stored_in_db = 1;
		$counter = !empty($counter) ? $counter : $stored_in_db;
		$res = \Loginizer\HOTP::generateByCounter($key, $counter);
	}else{
		$time = !empty($counter) ? $counter : time();
		$res = \Loginizer\HOTP::generateByTime($key, 30, $time);
	}

	return $res->toHotp($length);

}

// Returns the 2fa_app data. Is also used during ajax
function loginizer_2fa_app($uid = 0){

	// Include necessary stuff
	include_once(LOGINIZER_PRO_DIR.'/lib/hotp.php');
	include_once(LOGINIZER_PRO_DIR.'/lib/Base32.php');

	$uid = empty($uid) ? get_current_user_id() : $uid;
	$user = get_user_by('id', $uid);

	// For 2fa_app we must be prepared
	$tmpkey = get_user_meta($uid, 'loginizer_user_2fa_tmpkey', true);
	$settings['2fa_key'] = empty($tmpkey) ? '' : base64_decode($tmpkey);// Just decode it

	// We might need to create a 10 char secret KEY for 2fa App based
	if(empty($settings['2fa_key']) || isset($_REQUEST['reset_2fa_key'])){

		// Generate
		$settings['2fa_key'] = strtoupper(lz_RandomString(10));

		// Save the new one
		update_user_meta($uid, 'loginizer_user_2fa_tmpkey', base64_encode($settings['2fa_key']));

	}

	// Base32 Key
	$settings['2fa_key_32'] = \Loginizer\Base32::encode($settings['2fa_key']);

	// The QR Code text
	$url = preg_replace('/^https?:\/\//', '', site_url());
	$site_name = get_bloginfo('name');
	$settings['2fa_qr'] = 'otpauth://'.(empty($settings['2fa_type']) ? 'totp' : $settings['2fa_type']).'/'.rawurlencode($url).':'.@$user->user_login.'?secret='.\Loginizer\Base32::encode($settings['2fa_key']).'&issuer='.rawurlencode($site_name).'&counter=';

	// Time now
	$settings['2fa_server_time'] = get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'Y-m-d H:i:s');

	// Current OTP
	$settings['2fa_otp'] = loginizer_2fa_app_key($settings);

	return $settings;

}

// Handles the users choice page POST
function loginizer_user_page_post(&$error = array()){

	global $loginizer, $loginizer_allowed;


	/* Make sure post was from this page */
	if(count($_POST) > 0){
		check_admin_referer('loginizer-user');
	}

	$uid = get_current_user_id();

	if(!empty($_POST['submit'])){

		// What has the user selected ?
		$Nsettings['pref'] = lz_optpost('loginizer_user_choice');

		if(empty($loginizer_allowed[$Nsettings['pref']])){
			$error['lz_not_allowed'] = __('You have submitted an invalid preference', 'loginizer');
			return 0;
		}

		// Process security question
		if($Nsettings['pref'] == 'question'){

			$Nsettings['question'] = lz_optpost('lz_question');
			$Nsettings['answer'] = lz_optpost('lz_answer');

			// Was there a question ?
			if(empty($Nsettings['question'])){
				$error['lz_no_question'] = __('No question was submitted', 'loginizer');
			}

			// Question too long ?
			if(strlen($Nsettings['question']) > 255){
				$error['lz_question_long'] = __('The question is too long', 'loginizer');
			}

			// Was there an answer ?
			if(empty($Nsettings['answer'])){
				$error['lz_no_answer'] = __('No answer was submitted', 'loginizer');
			}

			// Question too long ?
			if(strlen($Nsettings['answer']) > 255){
				$error['lz_answer_long'] = __('The answer is too long', 'loginizer');
			}

			if(!empty($error)){
				return 0;
			}

			// Hash the answer
			$Nsettings['answer'] = base64_encode($Nsettings['answer']);

		}

		// Process 2fa via Email
		if($Nsettings['pref'] == '2fa_email'){
			// Actually nothing to store !
		}

		// Process 2fa via App
		if($Nsettings['pref'] == '2fa_app'){

			// Enable APP
			$Nsettings['app_enable'] = (int) lz_optpost('lz_app_enable');

			// Any one time passwords ?
			$emergency = lz_optpost('lz_2fa_emergency');

			// Is there any Emergency OTP
			if(!empty($emergency)){

				$emergency = explode(',', $emergency);

				// Loop through and correct
				foreach($emergency as $ek => $ev){

					$orig = $ev;

					$ev = (int) $ev;

					$_emergency[$ev] = $ev;

					if(strlen($ev) != 6){
						$incorrect[] = $orig;
					}

				}

				if(!empty($incorrect)){
					$error['lz_emergency'] = __('The emergency code(s) are incorrect', 'loginizer').' : '.implode(', ', $incorrect);
				}

				$Nsettings['2fa_emergency'] = $_emergency;

			}

			if(!empty($error)){
				return 0;
			}

		}

		// Lets save the settings
		update_user_meta($uid, 'loginizer_user_settings', $Nsettings);

		return 1;

	}

}

// Loginizer 2FA User settings loader
function loginizer_load_user_settings(&$uid, &$user, &$settings, &$current_pref){

	$uid = get_current_user_id();
	$user = wp_get_current_user();//print_r($user);
	$settings = get_user_meta($uid, 'loginizer_user_settings', true);
	$settings = empty($settings) ? array() : $settings;

	$current_pref = !empty($settings['pref']) ? $settings['pref'] : '';
	$current_pref = empty($current_pref) ? '' : $current_pref;

}

function loginizer_is_whitelisted_2fa(){

	global $wpdb, $loginizer, $lz_error;

	$whitelist = $loginizer['2fa_whitelist'];

	foreach($whitelist as $k => $v){

		// Is the IP in the blacklist ?
		if(inet_ptoi($v['start']) <= inet_ptoi($loginizer['current_ip']) && inet_ptoi($loginizer['current_ip']) <= inet_ptoi($v['end'])){
			$result = 1;
			break;
		}

		// Is it in a wider range ?
		if(inet_ptoi($v['start']) >= 0 && inet_ptoi($v['end']) < 0){

			// Since the end of the RANGE (i.e. current IP range) is beyond the +ve value of inet_ptoi,
			// if the current IP is <= than the start of the range, it is within the range
			// OR
			// if the current IP is <= than the end of the range, it is within the range
			if(inet_ptoi($v['start']) <= inet_ptoi($loginizer['current_ip'])
				|| inet_ptoi($loginizer['current_ip']) <= inet_ptoi($v['end'])){
				$result = 1;
				break;
			}

		}

	}

	// You are whitelisted
	if(!empty($result)){
		return true;
	}

	return false;

}

// If 2FA is ON and there are roles, then is 2FA applicable to the user
function loginizer_is_2fa_applicable($user = array()){

	global $loginizer;

	// If roles is empty then its applicable to all
	if(empty($loginizer['2fa_roles'])){
		return true;
	}

	// Are there any roles we need to check
	if(!empty($loginizer['2fa_roles'])){

		foreach($loginizer['2fa_roles'] as $role => $v){
			if(in_array($role, $user->roles)){
				return true;
			}
		}

	}

	return false;

}

// The settings page shown to users
function loginizer_user_page(){

	global $loginizer, $loginizer_allowed;

	$loginizer_allowed = array();
	$loginizer_allowed['none'] = 1;
	if(!empty($loginizer['2fa_app'])){ $loginizer_allowed['2fa_app'] = 1; }
	if(!empty($loginizer['2fa_email'])){ $loginizer_allowed['2fa_email'] = 1; }
	if(!empty($loginizer['2fa_sms'])){ $loginizer_allowed['2fa_sms'] = 1; }
	if(!empty($loginizer['question'])){ $loginizer_allowed['question'] = 1; }

	//------------------
	// Process the form
	//------------------
	$error = array();
	$saved = loginizer_user_page_post($error);

	//------------------
	// Load Settings
	//------------------
	loginizer_load_user_settings($uid, $user, $settings, $current_pref);

	$app2fa = loginizer_2fa_app();

	//------------------
	// Show the Page
	//------------------

	echo '<h2>'.__('Loginizer Security Settings', 'loginizer').'</h2>';

	if(!empty($saved)){
		echo '<div class="updated notice is-dismissible"><p><strong>'.__('Settings saved.', 'loginizer').'</strong></p></div>';
	}

	if(!empty($error)){
		lz_report_error($error);
	}

	if(is_admin()){

		echo '<p class="">'.__('These are your personal security and login settings and will not affect other users.', 'loginizer').'</p>';

	}else{

		if(class_exists('WooCommerce')){
			echo '<style>
			body.woocommerce-account ul li.woocommerce-MyAccount-navigation-link--loginizer-security a:before{
				content: "\f3ed"
			}
			</style>';
		}
	}

	if (current_user_can('manage_options')) {
		echo '<p><a href="https://wordpress.org/plugins/loginizer/faq/">'._e('You should also bookmark the FAQs, which explain how to de-activate the plugin even if you cannot log in.', 'loginizer').'</a></p>';
	}

	wp_enqueue_script('jquery-qrcode', LOGINIZER_PRO_DIR_URL.'/assets/js/jquery.qrcode.min.js', array('jquery'), '0.12.0');

	// Give the user the drop down to choose the settings
	echo 'Choose Preference : <form method="post" action="">
	'.wp_nonce_field('loginizer-user').'
	<select name="loginizer_user_choice" id="loginizer_user_choice" onchange="loginizer_pref_handle();">
		<option value="none" '.($current_pref == 'none' ? 'selected="selected"' : '').'>None (Not Recommended !)</option>
		'.(empty($loginizer['2fa_app']) ? '' : '<option value="2fa_app" '.($current_pref == '2fa_app' ? 'selected="selected"' : '').'>2fa : Google Authenticator, Authy, etc</option>').'
		'.(empty($loginizer['2fa_email']) ? '' : '<option value="2fa_email" '.($current_pref == '2fa_email' || ((empty($current_pref) || $current_pref == 'none') && !empty($loginizer['2fa_email_force'])) ? 'selected="selected"' : '').'>2fa : Email Auth Code</option>').'
		'.(empty($loginizer['2fa_sms']) ? '' : '<option value="2fa_sms" '.($current_pref == '2fa_sms' ? 'selected="selected"' : '').'>2fa : SMS Auth Code</option>').'
		'.(empty($loginizer['question']) ? '' : '<option value="question" '.($current_pref == 'question' ? 'selected="selected"' : '').'>Solve Security Question</option>').'
	</select>

<script>

var loginizer_nonce = "'.wp_create_nonce('loginizer_ajax').'";
var loginizer_req_ajax = "'.admin_url('admin-ajax.php').'";

// Handle on change
function loginizer_pref_handle(){
	(function($) {

		// Get the value
		var current = $("#loginizer_user_choice").val();
		$(".loginizer_upd").each(function(){
			if($(this).attr("id") == "loginizer_"+current){
				$(this).show();
			}else{
				$(this).hide();
			}
		});

		// Are we to show the QR Code ?
		if(current == "2fa_app"){
			loginizer_2fa_app_load();
		}

	})(jQuery);
};

// Show the QR Code and stuff
function loginizer_2fa_app_load(reset){

	reset = reset || 0;

	// Remove existing QRCode
	jQuery("#loginizer_2fa_app_qr").html("");

	// Refresh OTP
	if(reset == 2){


		var data = new Object();
		data["action"] = "loginizer_ajax";
		data["nonce"]	= loginizer_nonce;

		// AJAX and on success function
		jQuery.post(loginizer_req_ajax, data, function(response){
			jQuery("#loginizer_2fa_app_time").html(response["2fa_server_time"]);
			jQuery("#loginizer_2fa_app_key").val(response["2fa_key"]);
			jQuery("#loginizer_2fa_app_key_32").val(response["2fa_key_32"]);
			jQuery("#loginizer_2fa_app_otp").html(response["2fa_otp"]);
			jQuery("#loginizer_2fa_app_qr").attr("data-qrcode", response["2fa_qr"]);
		});

	}

	// Reset code
	if(reset == 1){

		var confirmed = confirm("'.__('Warning: If you reset the secret key you will have to update your apps with the new one. Are you sure you want to continue ?', 'loginizer').'");

		if(confirmed){

			// Data to Post
			var data = new Object();
			data["action"] = "loginizer_ajax";
			data["nonce"]	= loginizer_nonce;
			data["reset_2fa_key"]	= 1;

			// AJAX and on success function
			jQuery.post(loginizer_req_ajax, data, function(response){
				jQuery("#loginizer_2fa_app_time").html(response["2fa_server_time"]);
				jQuery("#loginizer_2fa_app_key").val(response["2fa_key"]);
				jQuery("#loginizer_2fa_app_key_32").val(response["2fa_key_32"]);
				jQuery("#loginizer_2fa_app_otp").html(response["2fa_otp"]);
				jQuery("#loginizer_2fa_app_qr").attr("data-qrcode", response["2fa_qr"]);
			});

		}else{
			return;
		}

	}

	var qrtext = jQuery("#loginizer_2fa_app_qr").attr("data-qrcode");
	jQuery("#loginizer_2fa_app_qr").qrcode({"text" : qrtext});

	return;

};

// Onload stuff
jQuery(document).ready(function(){
	loginizer_pref_handle();
});

</script>

<style>
.loginizer_upd{
	display: none;
}
</style>

	<br />

	<div id="loginizer_2fa_app" class="loginizer_upd">

		<h2>'.__('App based Two Factor Auth Code Settings', 'loginizer').'</h2>

		<p>
			'.__('<b>NOTE :</b> Generating two-factor codes depends upon your web-server and your device agreeing upon the time.', 'loginizer').' <br>
			'.__('The current UTC time according to this server when this page loaded', 'loginizer').': <b id="loginizer_2fa_app_time">'.$app2fa['2fa_server_time'].'</b>
		</p>

		<table border="0" cellpadding="8" cellspacing="1" width="500">
			<tr>
				<td width="50%"><b>Enable :</b></td>
				<td><input type="checkbox" value="1" name="lz_app_enable" '.lz_POSTchecked('lz_app_enable', (empty($settings['app_enable']) ? false : true)).' /></td>
			</tr>
			<tr>
			<tr>
				<td>
					<b>Secret Key :</b><br>
					<a href="javascript:loginizer_2fa_app_load(1)">Reset Secret Key</a>
				</td>
				<td><input type="text" name="lz_2fa_key" id="loginizer_2fa_app_key" value="'.$app2fa['2fa_key'].'" disabled="disabled" /></td>
			</tr>
			<tr>
				<td><b>Secret Key (Base32) :</b><br>
				Used by Google Authenticator, Authy, etc.</td>
				<td><input type="text" name="lz_2fa_key_32" id="loginizer_2fa_app_key_32" value="'.$app2fa['2fa_key_32'].'" disabled="disabled" /></td>
			</tr>
			<tr>
				<td>
					<b>'.__('One Time Emergency Codes', 'loginizer').' :</b><br>
					'.__('(Optional) You can specify 6 digit emergency codes seperated by a comma. Each can be used only once. You can specify upto 10.', 'loginizer').'
				</td>
				<td><input type="text" name="lz_2fa_emergency" value="'.lz_POSTval('lz_2fa_emergency', (empty($settings['2fa_emergency']) ? '' : implode(', ', $settings['2fa_emergency']) ) ).'" placeholder="e.g. 124667, 976493, 644335" /></td>
			</tr>
		</table>
		<br><br>

		<table border="0" cellpadding="8" cellspacing="1" width="500" style="background: #FFF" align="center">
			<tr>
				<td colspan="2"><b>'.__('If you enable app based Two Factor Auth, then verify that your application is showing the same One Time Password (OTP) as shown on this page before you log out.', 'loginizer').'</b>
			</tr>
			<tr>
				<td>
					<b>'.__('Current OTP', 'loginizer').' :</b><br>
					<a href="javascript:loginizer_2fa_app_load(2)">'.__('Refresh', 'loginizer').'</a>
				</td>
				<td><h1 id="loginizer_2fa_app_otp">'.loginizer_2fa_app_key($app2fa).'</h1></td>
			</tr>
			<tr>
				<td width="30%" valign="top"><b>QR Code :</b></td>
				<td><div id="loginizer_2fa_app_qr" data-qrcode="'.esc_attr($app2fa['2fa_qr']).'"></div></td>
			</tr>
		</table>
	</div>

	<div id="loginizer_question" class="loginizer_upd">

		<h2>'.__('Security Question Settings', 'loginizer').'</h2>

		<p>'.__('A secondary question set by you will be asked on a successful login', 'loginizer').'</p>

		<table border="0" cellpadding="8" cellspacing="1">
			<tr>
				<td><b>Question :</b></td>
				<td><input type="text" name="lz_question" value="'.(empty($settings['question']) ? '' : $settings['question']).'" size="40"  placeholder="e.g. The name of my pet is ?" /></td>
			</tr>
			<tr>
				<td><b>Answer :</b><br />Is case sensitive</td>
				<td><input type="text" name="lz_answer" value="'.(empty($settings['answer']) ? '' : base64_decode($settings['answer'])).'" placeholder="e.g. tommy" /></td>
			</tr>
		</table>

	</div>

	<div id="loginizer_2fa_email" class="loginizer_upd">

		<h2>'.__('Email Two Factor Auth Code Settings', 'loginizer').'</h2>

		<p>
			'.__('A One Time Password (OTP) will be asked on a successful login. The OTP will be emailed to your email address', 'loginizer').' : <br>
			<h2>'.$user->data->user_email.'</h2>
		</p>

	</div>

	<div id="loginizer_2fa_sms" class="loginizer_upd">

		<h2>'.__('SMS Two Factor Auth Code Settings', 'loginizer').'</h2>

		<p>
			'.__('A One Time Password (OTP) will be asked on a successful login. The OTP will be sent via SMS to your mobile.', 'loginizer').' <br>
		</p>

		<table border="0" cellpadding="8" cellspacing="1">
			<tr>
				<td><b>'.__('Mobile Number', 'loginizer').' :</b></td>
				<td><input type="text" name="lz_mobile" value="'.(empty($settings['mobile']) ? '' : base64_decode($settings['mobile'])).'" placeholder="e.g. +18557852145" /></td>
			</tr>
		</table>

	</div>';

	//WooCommerce Loginizer Security Settings - Switch between the normal submit button and the submit button that's only available in the admin panel
	if ( ! is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
		echo '<br><input type="submit" name="submit" value="Submit" />';

	}else{
		submit_button();
	}


	echo '</form>';

}

// Do the checksum of the core
function loginizer_checksums(){

	global $loginizer, $loginizer_allowed, $wp_local_package;

	// Update the time
	update_option('loginizer_checksums_last_run', time());

	// Get the locale and version
	$version = $GLOBALS['wp_version'];

	$locale = 'en_US';
	if(!empty($wp_local_package)){
		$locale = $wp_local_package;
	}
	//echo $version.' - '.$locale;

	// Load the checksums
	$resp = wp_remote_get('https://api.wordpress.org/core/checksums/1.0/?version='.$version.'&locale='.$locale,
					array('timeout' => 10));
	//lz_print($resp);

	if(!is_array($resp)){
		return false;
	}

	$checksums = json_decode($resp['body'], true);//lz_print($checksums);
	$checksums = $checksums['checksums'];
	//lz_print($checksums);

	// WP-content could be renamed !
	$wp_content = basename(dirname(dirname(dirname(__FILE__))));

	$diffed = array();

	// Loop through and check
	foreach($checksums as $ck => $md5){

		// Do not check for plugins and themes as some user might not have updated files
		if(substr($ck, 0, 18) == 'wp-content/plugins'){
			continue;
		}

		if(substr($ck, 0, 17) == 'wp-content/themes'){
			continue;
		}

		if(substr($ck, 0, 20) == 'wp-content/languages'){
			continue;
		}

		if(in_array($ck, array('readme.html', 'license.txt', 'wp-config-sample.php'))){
			continue;
		}

		if(substr($ck, 0, 10) == 'wp-content'){
			$ck = substr_replace($ck, $wp_content, 0, 10);
			//echo $ck."\n";
		}

		$path = lz_cleanpath(ABSPATH.'/'.$ck);

		// Skip checksum for the file that does not exists, it is possible that the theme/plugin is deleted by the user
		if(!file_exists($path) && preg_match('#/(themes|plugins)#is', $path)){
			continue;
		}

		$cur_md5 = @md5_file($path);
		if($cur_md5 != $md5){
			$diffed[$ck]['cur_md5'] = $cur_md5;
			$diffed[$ck]['md5'] = $md5;
		}

	}

	//lz_print($diffed);

	// Store the diffed ones
	update_option('loginizer_checksums_diff', $diffed);

	// Load any ignored files
	$ignores = get_option('loginizer_checksums_ignore');

	// Create a final diff list to email the admin
	if(is_array($ignores)){

		foreach($ignores as $ck => $path){
			unset($diffed[$path]);
		}

	}

	// Send an email to the admin, IF we are to email
	if(!empty($diffed) && is_array($diffed) && count($diffed) > 0 && empty($loginizer['no_checksum_email'])){

		// Send the email
		$site_name = get_bloginfo('name');
		$email = lz_is_multisite() ? get_site_option('admin_email') : get_option('admin_email');
		$subject = __("File Checksum Mismatch - $site_name", 'loginizer');
		$message = "Hi,

Loginizer has just completed checking the MD5 checksums of your WordPress site :
$site_name - ".get_site_url()."

The following files have been found that do not match the MD5 checksums as per your version :
";

		foreach($diffed as $path => $val){
			$message .= "Path: ".$path."
Expected MD5: ".$val['md5']."
Found MD5: ".$val['cur_md5']."

";
		}

		$message .= "
It is recommended you check this ASAP and download the files again to replace them.
If you are aware of modifications made to the above files, please update the Ignored files list in Loginizer.

Regards,
$site_name";

		//echo $message;

		$sent = wp_mail($email, $subject, $message);

	}

}


// Is the Username blacklisted
function loginizer_user_blacklisted($username){

	global $wpdb, $loginizer, $lz_error;

	$username_blacklist = $loginizer['username_blacklist'];
	$username_blacklist = is_array($username_blacklist) ? $username_blacklist : array();

	// Are you blacklisted ?
	foreach($username_blacklist as $user_to_match){

		$user_to_match = str_replace('*', '(.*?)', $user_to_match);

		if(preg_match('/^'.$user_to_match.'$/is', $username)){
			$match_found = 1;
		}

	}

	// Did we get a match ?
	if(empty($match_found)){
		return false;
	}

	// Lets make sure there is no username in the database by that name
	$user_search = get_user_by('login', $username);

	// If not found then search by email
	if(!empty($user_search)){
		return false;
	}

	$blacklist = get_option('loginizer_blacklist');
	$newid = ( empty($blacklist) ? 0 : max(array_keys($blacklist)) ) + 1;

	// Add to the blacklist
	$blacklist[$newid]['start'] = lz_getip();
	$blacklist[$newid]['end'] = lz_getip();
	$blacklist[$newid]['time'] = time();

	// Update the database
	update_option('loginizer_blacklist', $blacklist);

	// Reload
	$loginizer['blacklist'] = get_option('loginizer_blacklist');

	// Show the error
	$lz_error['user_blacklisted'] = __('This username has been blacklisted, and so have you been blacklisted !', 'loginizer');

	return true;
}

function loginizer_plugin_row_links($pluginMeta, $pluginFile){

	global $loginizer;

	$isRelevant = $pluginFile == 'loginizer-security/loginizer-security.php';

	if (!empty($isRelevant) && current_user_can('update_plugins')){

		// Show the Renew License link if the license is expired
		if(!empty($loginizer['license']['expires']) && ($loginizer['license']['expires'] <= date('Ymd'))){

			$linkUrl = 'https://www.softaculous.com/clients?ca=loginizer_buy&plan='.$loginizer['license']['plan'].'&license='.$loginizer['license']['license'];

			$linkText = __('Renew License', 'loginizer');

			$pluginMeta[] = sprintf('<a href="%s" target="_blank" style="color:red;">%s</a>', esc_attr($linkUrl), $linkText);

		}
	}

	return $pluginMeta;
}

// Check if any one 2FA option is enabled by admin
function loginizer_is_2fa_enabled(){

	global $loginizer;

	return (!empty($loginizer['2fa_app']) || !empty($loginizer['2fa_email']) || !empty($loginizer['2fa_sms']) || !empty($loginizer['question']));
}

// Add TFA Settings Column to the Users Table
function loginizer_2fa_columns_users($column){

	if(loginizer_is_2fa_enabled()){
		$column['tfa'] = __('Loginizer 2FA', 'loginizer');
	}

	return $column;
}

// Update the users' selected 2FA preference
function loginizer_2fa_column_data($val, $column_name, $user_id){

	if(loginizer_is_2fa_enabled()){

		switch($column_name){

			case 'tfa' :

				$settings = get_user_meta($user_id, 'loginizer_user_settings', true);

				if(empty($settings) || empty($settings['pref']) || ($settings['pref'] == 'none')){
					return __('<i>None</i>', 'loginizer');
				}

				switch($settings['pref']){

					case 'question':
					return __('Security Questions', 'loginizer');
					break;

					case '2fa_app':
					return __('Google Authenticator / Authy', 'loginizer');
					break;

					case '2fa_email':
					return __('Email Auth Code', 'loginizer');
					break;

					case '2fa_sms':
					return __('SMS Auth Code', 'loginizer');
					break;
				}

			default:
		}

	}

    return $val;
}

function loginizer_2fa_ajax_redirect(){
	$url = get_transient('loginizer_2fa_' . get_current_user_id());

	set_transient('loginizer_2fa_' . get_current_user_id(), '2fa', 600);

	wp_safe_redirect($url);
	die('Didn\'t redirect');
}

// WooCommerce Loginizer My Security Page Functions - Start
// These functions are required in order for the My Security page to be created in WooCommerce User section

function loginizer_add_premium_security_endpoint(){
    add_rewrite_endpoint( 'loginizer-security', EP_ROOT | EP_PAGES );
}

// Add new query var
function loginizer_premium_security_query_vars( $vars ) {
    $vars[] = 'loginizer-security';
    return $vars;
}

// Insert the link into the My Account menu
function loginizer_add_premium_security_link_my_account( $items ) {
    $items['loginizer-security'] = 'Security';
    return $items;
}

function loginizer_ultimatemember_security_tab($tabs){

	$tabs[800]['loginizer-security']['icon'] = 'um-faicon-key';
	$tabs[800]['loginizer-security']['title'] = 'Security';
	$tabs[800]['loginizer-security']['show_button'] = false;
	$tabs[800]['loginizer-security']['custom'] = true;
	return $tabs;
}

function loginizer_ultimatemember_security_tab_content(){

	global $output;
	ob_start();

	echo'<div class="um-field">';
	loginizer_user_page();
	echo'</div>';
	$output .= ob_get_contents();
	ob_end_clean();
	return $output;
}

// Rewrite rules
function loginizer_woocommerce_rewrite_rule(){
	add_rewrite_rule( 'loginizer-security(/(.*))?/?$', 'index.php?&loginizer-security=$matches[2]', 'top' );
	add_rewrite_rule( '(.?.+?)/loginizer-security(/(.*))?/?$', 'index.php?pagename=$matches[1]&loginizer-security=$matches[3]', 'top' );
	flush_rewrite_rules();
}

//WooCommerce Loginizer Security Page Functions - End

/*********************************************
*  CSRF Protection Session Functions - Starts
**********************************************/
function loginizer_csrf_sess_init(){
	global $loginizer;

	if(!is_user_logged_in()){
		return;
	}

	$login_slug = 'wp-login.php';
	if($loginizer['login_slug']){
		$login_slug = $loginizer['login_slug'];
	}

	if(loginizer_cur_page() == $login_slug && strpos($_SERVER['REQUEST_URI'], 'action') === FALSE){
		wp_logout();
		return;
	}

	if(!is_admin()){
		return;
	}

	preg_match('/(?:lzs.{20})/U', esc_url_raw($_SERVER['REQUEST_URI']), $matches);

	if(empty($matches)){
		wp_redirect(wp_login_url());
		die('Didn\'t had security protection');
	}

	loginizer_csrf_verify_session($matches[0]);
}

// Destroys session for CSRF protection
function loginizer_destroy_csrf_session($user_id){
	delete_user_meta($user_id, 'loginizer_csrf_session');

	$sess_key = get_user_meta($user_id, 'loginizer_csrf_session', true);

	@setCookie('lz_csrf_sess', $sess_key, time() - 3600, COOKIEPATH, COOKIE_DOMAIN, true); // deleting the cookie by setting past time
}

// Creates session for CSRF protection
function loginizer_csrf_create_session($user_id){
	if(!empty($_COOKIE['lz_csrf_sess']) && sanitize_key($_COOKIE['lz_csrf_sess']) == get_option('loginizer_session_'. $user_id) && !empty($_REQUEST['interim-login'])){
		return;
	}

	$sess_key = loginizer_sess_key();

	update_user_meta($user_id, 'loginizer_csrf_session', $sess_key);
	@setCookie('lz_csrf_sess', $sess_key, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, true);
}

// Verifies that the session is active
function loginizer_csrf_verify_session($key) {

	$key = trim($key, '/');
	$user_id = get_current_user_id();

	$session_key = get_user_meta($user_id, 'loginizer_csrf_session', true);

	if(trim($session_key) != trim($key)){
		wp_logout();
		wp_redirect(wp_login_url());
		die('Died');
	}
}


// Checks if .htaccess file have the mod rewrite for CSRF protection
function loginizer_is_csrf_prot_mod_set(){

	$is_mod_set = get_transient('loginizer_csrf_mod_rewrite');

	if(!empty($is_mod_set)){
		return true;
	}

	$htaccess = file_get_contents(ABSPATH . '/.htaccess');

	preg_match('/^# BEGIN Loginizer/m', $htaccess, $matches);

	if(empty($matches)){
		return false;
	}

	// set transient to expire after 1 hour
	set_transient('loginizer_csrf_mod_rewrite', true, 3600);

	return true;

}

/*
If the user is logged in and its session ends then if the user is redirected to login page then it has a redirect_to url, which has the old csrf session key in the redirect URL, this function removes that session key from the redirect_to parameter in the url
*/
function loginizer_csrf_wp_redirects($location){

	global $loginizer;

	$admin_slug = 'wp-admin';

	/*
	WordPress redirects to https://host/wp-login?redirect_to=VALUES if you are not logged in and enter the URL https://host/wp-admin so then if I tried to login, I was not able to get user id in anyway hence removing the redirect_to was the possible solution bcoz this dosent cause the same issue
	*/
	if(strpos($location, '?redirect_to') !== FALSE && strpos($location, 'lzs') === FALSE){
		$location = preg_replace('/\?redirect_to=.*/', '', $location);
		return $location;
	}

	if(!empty($loginizer['admin_slug'])){
		$admin_slug = $loginizer['admin_slug'];
	}

	// Return if it does not have CSRF key or is not admin url
	if(strpos($location, 'lzs') === FALSE || strpos($location, $admin_slug) === FALSE){
		return $location;
	}

	// If the query with redirect_to has csrf key then remove that string
	$url = parse_url($location);

	if(empty($url['query'])){
		return $location;
	}

	if(strpos($url['query'], 'redirect_to') === FALSE){
		$location = preg_replace('/\?redirect_to=.*/', '', $location);
		return $location;
	}

	$query = rawurldecode($url['query']);
	$query = preg_replace('/'.$admin_slug.'-lzs.{20}/U', $admin_slug . '/', $query);
	$location = preg_replace('/\?.+/', '?' . rawurlencode($query), $location);

	return $location;

}

// Generates CSRF session string
function loginizer_sess_key(){
	return 'lzs' . wp_generate_password(20, false);
}

// Updates redirects with session string
function loginizer_csrf_admin_redirects($url, $path, $scheme){

	global $loginizer;

	$admin_slug = 'wp-admin';

	if(!is_user_logged_in()){
		return $url;
	}

	$user_id = get_current_user_id();
	$session_key = get_user_meta($user_id, 'loginizer_csrf_session', true);

	if(strpos($url, 'lzs') !== FALSE){
		return $url;
	}

	if(!empty($loginizer['admin_slug'])){
		$admin_slug = $loginizer['admin_slug'];
	}

	$url = preg_replace('/(?:-?lzs.{20})/U', '/', $url);
	if(!empty($session_key)){
		$url = str_replace($admin_slug, $admin_slug . '-' . $session_key, $url);
	}

	return $url;

}

// Sets session on login redirect URL
function loginizer_login_csrf_redirect($redirect, $request, $user){

	global $loginizer;

	$admin_slug = 'wp-admin';

	if(isset($user->roles) && is_array($user->roles)){

		$session_key = get_user_meta($user->ID, 'loginizer_csrf_session', true);

		if(!empty($loginizer['admin_slug'])){
			$admin_slug = $loginizer['admin_slug'];
		}

		$redirect = preg_replace('/(?:-?lzs.{20})/U', '/', $redirect);
		if(!empty($session_key)){
			$redirect = str_replace($admin_slug, $admin_slug . '-' . $session_key, $redirect);
		}
	}

	return $redirect;
}

// Updates the url if required with the CSRF string
function loginizer_csrf_change_url($url, $uid){

	global $loginizer;

	if(!empty($loginizer['enable_csrf_protection']) && loginizer_is_csrf_prot_mod_set()){
		$admin_slug = 'wp-admin';

		if(!empty($loginizer['admin_slug'])){
			$admin_slug = $loginizer['admin_slug'];
		}

		$session_key = get_user_meta($uid, 'loginizer_csrf_session', true);

		if(strpos($url, $session_key) !== FALSE || empty($session_key)){
			return $url;
		}

		$url = str_replace($admin_slug, $admin_slug . '-' . $session_key, $url);
	}

	return $url;
}

function loginizer_csrf_admin_bar_shortcut($admin_bar){

	$admin_bar->add_node( array(
		'id'    => 'loginizer-admin-shortcut',
		'parent' => null,
		'group'  => null,
		'title' => esc_html__('Open New Tab', 'loginizer'),
		'href'  => admin_url(),
		'meta' => [
			'title' => esc_html__('Opens Dashboard in new tab', 'loginizer'),
			'target' => '_blank'
		]
	) );
}


// CSRF Protection Session Functions - End

// Restricts user from logging in
function loginizer_limit_sessions($user){

	if(is_wp_error($user)){
		return $user;
	}

	// To prevent session check from happening twice, as we have set the check on wp_login as well
	if(!empty($GLOBALS['loginizer_pro_limit_session_happened'])){
		return $user;
	}

	$GLOBALS['loginizer_pro_limit_session_happened'] = true;

	$concurrent_sessions = get_option('loginizer_limit_session');

	if(empty($concurrent_sessions) || empty($concurrent_sessions['enable']) || !class_exists('WP_Session_Tokens')){
		return $user;
	}

	// Checks if we have excluded the role
	if(!empty($concurrent_sessions['roles']) && !empty($user->roles) && is_array($user->roles)){
		if(!empty(array_intersect($concurrent_sessions['roles'], $user->roles))){
			return $user;
		}
	}

	$concurrent_sessions['count'] = empty($concurrent_sessions['count']) ? $concurrent_sessions['count'] : 1; // if limit is not set then default it to 1

	// Blocking the user
	if(!empty($concurrent_sessions['type']) && $concurrent_sessions['type'] == 'block'){
		$session = WP_Session_Tokens::get_instance($user->ID);
		$count = count($session->get_all());

		if($count >= $concurrent_sessions['count']){
			return new WP_Error('loginizer_session_limit', __('You have reached maximum number of concurrent logins please logout from other devices to access', 'loginizer'));
		}

		return $user;
	}

	// Destroying all session
	if(!empty($concurrent_sessions['type']) && $concurrent_sessions['type'] == 'destroy'){
		$session = WP_Session_Tokens::get_instance($user->ID);
		$count = count($session->get_all());

		if($count >= $concurrent_sessions['count']){
			$session->destroy_all();
		}

		return $user;
	}

	return $user;

}

function loginizer_limit_sessions_wp_login($user_login = '', $user = null){

	if(!empty($GLOBALS['loginizer_pro_limit_session_happened'])){
		return $user;
	}

	if(empty($user) && empty($user_login)){
		return;
	}

	if(empty($user) && !empty($user_login)){
		$user = get_user_by('login', $user_login);
	}

	if(empty($user) || is_wp_error($user)){
		return;
	}

	$error = loginizer_limit_sessions($user);

	if(is_wp_error($error)){
		wp_die($error->get_error_message());
	}
}

// Destroys session when concurrent limit is reached
function loginizer_limit_destroy_sessions_handler($check, $password, $hash, $user_id){

	if(empty($check)){
		return false;
	}

	return loginizer_limit_destroy_sessions($user_id);
}

function loginizer_limit_destroy_sessions($user_id){

	if(empty($user_id)){
		return false;
	}

	if(!class_exists('WP_Session_Tokens')){
		return true;
	}

	$user = get_userdata($user_id);

	$concurrent_sessions = get_option('loginizer_limit_session');

	if(empty($concurrent_sessions) || empty($concurrent_sessions['enable'])){
		return true;
	}

	// Checks if we have excluded the role
	if(!empty($concurrent_sessions['roles']) && !empty($user->roles) && is_array($user->roles)){
		if(!empty(array_intersect($concurrent_sessions['roles'], $user->roles))){
			return true;
		}
	}

	if(!empty($concurrent_sessions['type']) && $concurrent_sessions['type'] == 'destroy'){
		$session = WP_Session_Tokens::get_instance($user_id);
		$count = count($session->get_all());

		if($count >= $concurrent_sessions['count']){
			$session->destroy_all();
		}

		return true;
	}

	return true;

}

//////////////////////////
//  BEGIN MasterStudy LMS
/////////////////////////

// Logins user when it happens via MasterStudy LMS
function loginizer_handle_stm_lms_login($res){

	global $loginizer;

	if($res['status'] != 'success'){
		return $res;
	}

	if(empty(loginizer_is_2fa_enabled())){
		return $res;
	}

	$request_body = file_get_contents('php://input');
	$data = json_decode($request_body, true); // wp_signon sanatizes the data so we don't need to do it here

	// Login the user again as this is the only way we can get user data which we need to set cookie and transient
	$user = wp_signon($data, is_ssl());

	if(is_wp_error($user)){
		wp_logout();
		return array('status' => 'error', 'message' => 'Login Failed!');
	}

	if(empty(loginizer_is_2fa_applicable($user))){
		return $res;
	}

	$user_pref = get_user_meta($user->ID, 'loginizer_user_settings');

	// If the user dosent have 2FA method set in My Security then we just let MasterStudy Work normally.
	if(empty($loginizer['2fa_email_force']) && (empty($user_pref) || (!empty($user_pref[0]['pref']) && $user_pref[0]['pref'] == 'none'))){
		return $res;
	}

	$admin_slug = 'wp-admin';

	if($loginizer['admin_slug']){
		$admin_slug = $loginizer['admin_slug'];
	}

	@setCookie('loginizer_2fa_' . $user->ID, time(), time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, true);
	set_transient('loginizer_2fa_' . $user->ID, time(), 600);

	$message = __('Redirecting you to verify 2FA', 'loginizer');

	$_POST['redirect_to'] = class_exists('STM_LMS_User') ? STM_LMS_User::user_page_url($user->ID, true) : admin_url();
	$_REQUEST['redirect_to'] = $_POST['redirect_to'];

	$url = loginizer_user_redirect($user, '', '');

	if(empty($url) || is_a($url, 'WP_User') || filter_var($url, FILTER_VALIDATE_URL) === FALSE){
		$message = esc_html__('Something went wrong unable to redirect you to verify your login via 2fa', 'loginizer');

		if(is_array($url) && !empty($url['message'])){
			$message = $url['message'];
		}

		$url = '';
		$res['status'] = 'error';
		wp_logout();
	} else {
		set_transient('loginizer_2fa_' . $user->ID, $url, 600);
	}

	$res['message'] = $message;
	$res['user_page'] = $url;
	return $res;
}
// END MasterStudy LMS

// Single Sign ON
function loginizer_create_sso($uid, $ttl = 600, $attempts = 1){
	$token = loginizer_generate_ssotoken($uid, $ttl, $attempts);
	$url = wp_login_url().'?uid='.$uid.'&ssotoken='.$token;

	$sso_links = get_option('loginizer_sso_links', []);
	$sso_links[$uid] = $url;

	update_option('loginizer_sso_links', $sso_links);

	if(!empty($_POST['sso_email'])){
		loginizer_sso_send_mail($url, sanitize_email($_POST['sso_email']), $ttl);
	}

	return $url;
}

function loginizer_sso_send_mail($url, $email, $ttl = 600){

	$ttl = (int) $ttl;

	$defined_ttl_duration = [
		300 => __('5 minutes', 'loginizer'),
		600 => __('10 minutes', 'loginizer'),
		1800 => __('30 minutes', 'loginizer'),
		3600 => __('1 hour', 'loginizer'),
		21600 => __('6 hours', 'loginizer'),
		43200 => __('12 hours', 'loginizer'),
		86400 => __('24 hours', 'loginizer'),
		172800 => __('2 days', 'loginizer'),
	];

	if(!empty($defined_ttl_duration[$ttl])){
		$ttl_duration = $defined_ttl_duration[$ttl];
	} else {
		$ttl_duration = $defined_ttl_duration[600];
	}

	$site_name = get_bloginfo('name');
	$sub = sprintf(__('Login at %s', 'loginizer'), $site_name);
	$msg = sprintf(__('Hi,

Please find below the Single Sign-On (SSO) link for our platform:
%1$s
This link will allow you to access the platform securely without needing to enter your login credentials, this link is valid for %2$s.

Thank you for using our platform.

Best regards,
%3$s', 'loginizer'), $url, $ttl_duration, $site_name);

	wp_mail($email, $sub, $msg);

}

function loginizer_generate_ssotoken($uid = 0, $ttl = 600, $attempts = 1){
	global $loginizer;

	// Variables
	$time = time();
	$expires = ($time + $ttl);
	$action =  'loginizer_sso_'.$uid;

	include_once(ABSPATH . '/'.$loginizer['wp-includes'].'/class-phpass.php');
	$wp_hasher = new \PasswordHash(8, TRUE);

	// Create the token with a random salt and the time
	$token  = wp_hash(wp_generate_password(20, false).$action.$time);

	// Create a hash of the token
	$stored_hash = $wp_hasher->HashPassword($expires.$token);

	// Store the hash and when it expires
	update_user_meta($uid, $action, $stored_hash);
	update_user_meta($uid, $action.'_expires', $expires);
	update_user_meta($uid, $action.'_attempts', $attempts);

	return $token;
}

function loginizer_verify_sso(){
	global $loginizer;

	if(empty($_GET['uid']) || empty($_GET['ssotoken'])){
		return false;
	}

	$uid = (int) sanitize_key($_GET['uid']);
	$token = sanitize_key($_GET['ssotoken']);
	$action = 'loginizer_sso_'.$uid;

	$hash = get_user_meta($uid, $action, true);
	$expires = get_user_meta($uid, $action.'_expires', true);
	$attempts = (int) get_user_meta($uid, $action.'_attempts', true);

	include_once(ABSPATH.'/'.$loginizer['wp-includes'].'/class-phpass.php');
	$wp_hasher = new \PasswordHash(8, TRUE);
	$time = time();

	if(!$wp_hasher->CheckPassword($expires.$token, $hash) || $expires < $time || $attempts > 15 || $attempts <= 0){
		$token_error_msg = __('The token is invalid or has expired.', 'loginizer');
		loginizer_update_attempt_stats(0);

		// Throw an error
		return new \WP_Error('token_invalid', $token_error_msg, 'loginizer_sso');

	}else{

		if(!empty($loginizer['limit_session']) && !empty($loginizer['limit_session']['enable'])){
			$limit_session = loginizer_limit_destroy_sessions($uid);

			if(empty($limit_session)){
				return new \WP_Error('loginizer_session_limit', __('User ID not found so can not proceed', 'loginizer'), 'loginizer_epl');
			}
		}

		// Deducting the count by 1
		if(!empty($attempts)){
			$attempts = $attempts - 1;
			update_user_meta($uid, $action.'_attempts', $attempts);
		}

		// Login the User
		wp_set_auth_cookie($uid);

		if(empty($attempts) || $expires < $time){
			// Delete the meta
			delete_user_meta($uid, $action);
			delete_user_meta($uid, $action.'_expires');
			delete_user_meta($uid, $action.'_attempts');
		}

		$redirect_to = loginizer_csrf_change_url(admin_url(), $uid);

		loginizer_update_attempt_stats(1);

		// Redirect and exit
		wp_safe_redirect($redirect_to);
		exit;

	}

	return false;

}

function loginizer_sso_authenticate($user, $username, $password){

	if(is_wp_error($user)){

		// Ignore certain codes
		$ignore_codes = array('empty_username', 'empty_password');

		if(is_wp_error($user) && !in_array($user->get_error_code(), $ignore_codes)) {
			return $user;
		}
	}

	$verified = loginizer_verify_sso();

	if(is_wp_error($verified)){
		return $verified;
	}
}

function loginizer_social_btn_woocommerce($return = false, $id = ''){
	loginizer_social_btn($return, 'woocommerce');
}

function loginizer_social_btn_comment($post_id){
	loginizer_social_btn(false, 'comment');
}

// For Ultimate Member plugin
function loginizer_social_btn_um($return = false, $id = ''){
	loginizer_social_btn(false, 'ultimate_member');
}

function loginizer_social_shortcode($atts){
	global $loginizer;

	if(is_user_logged_in()){
		return;
	}

	$atts = shortcode_atts([
		'type' => 'icon',
		'divider' => 'above',
		'shape' => 'square',
		'container_alignment' => 'left',
		'button_alignment' => 'left'
	], $atts);

	$errors = loginizer_social_login_error_handler();

	if(!empty($errors) || is_wp_error($errors)){
		$error = '<style>.notice{background: #fff;border: 1px solid #c3c4c7;border-left-width: 4px;box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);margin: 5px 15px 2px;padding: 1px 12px;}.notice p,.notice-title {margin: 0.5em 0;padding: 2px;}.notice-error{border-left-color: #d63638;}.login-error-list{list-style: none;}</style><div class="loginizer-social-shortcode-error">';

		$args = [
			'type' => 'error',
		];

		// Add the number of retires left as well
		if(count($errors->get_error_codes()) > 0 && isset($loginizer['retries_left'])){
			$errors->add('retries_left', loginizer_retries_left());
		}

		$messages = $errors->get_error_messages();
		$notice = '';
		if(count($messages) == 1){
			$notice .= '<p>'.wp_kses_post($messages[0]).'</p>';
		} else {
			$notice .= '<ul class="login-error-list">';
			foreach($messages as $message) {
				$notice .= '<li>'.wp_kses_post($message).'</li>';
			}
			$notice .= '</ul>';
		}

		$error .= wp_get_admin_notice($notice, $args);
		$error .= '</div>';
	}

	if(!empty($error)){
		return $error . loginizer_social_btn(true, 'login', $atts);
	}

	return loginizer_social_btn(true, 'login', $atts);
}

function loginizer_social_update_avatar($avatar, $id_or_email, $size, $default, $alt){
	global $wpdb, $blog_id;

	$user = false;

	if(empty($id_or_email)){
		return $avatar;
	}

	if(is_numeric($id_or_email)){
		$id = (int) $id_or_email;
		$user = get_user_by('id' , $id);
	} elseif(is_object($id_or_email)){
		if(!empty($id_or_email->user_id)){
			$id = (int) $id_or_email->user_id;
			$user = get_user_by('id' , $id);
		}
	} else {
		$user = get_user_by('email', $id_or_email);
	}

	if(empty($user) || !is_object($user) || empty($user->ID)){
		return $avatar;
	}

	// Fetching the Image now
	$avatar_id = get_user_meta($user->ID, $wpdb->get_blog_prefix($blog_id) . 'lz_avatar', true);

	if(!wp_attachment_is_image($avatar_id)){
		return $avatar;
	}

	$avatar_size = 'thumbnail';
	if(!empty($size)){
		$avatar_size = is_numeric($size) ? [$size, $size] : $size;
	}

	$avatar_url = wp_get_attachment_image_src($avatar_id, $avatar_size);

	if(empty($avatar_url) || empty($avatar_url[0])){
		return $avatar;
	}

	$avatar = $avatar_url[0];
	$avatar = '<img alt="'.esc_attr($alt).'" src="'.esc_url($avatar).'" class="avatar avatar-'.esc_attr($size).' photo" height="'.esc_attr($size).'" width="'.esc_attr($size).'" />';

	return $avatar;
}

function loginizer_security_load_translation_vars(){
	global $loginizer;

	$loginizer['pl_d_sub'] = __('Login at $site_name', 'loginizer');
	$loginizer['pl_d_msg'] = __('Hi,

A login request was submitted for your account $email at :
$site_name - $site_url

Login at $site_name by visiting this url :
$login_url

If you have not requested for the Login URL, please ignore this email.

Regards,
$site_name', 'loginizer');

	if(empty($loginizer['passwordless_sub'])){
		$loginizer['passwordless_sub'] = $loginizer['pl_d_sub'];
	}

	if(empty($loginizer['passwordless_msg'])){
		$loginizer['passwordless_msg'] = $loginizer['pl_d_msg'];
	}

	$loginizer['wp_admin_d_msg'] = __('LZ : Not allowed via WP-ADMIN. Please access over the new Admin URL', 'loginizer');

	if(empty($loginizer['captcha_text'])){
		$loginizer['captcha_text'] = __('Math Captcha', 'loginizer');
	}
}

function loginizer_hide_wp_admin(){
	global $loginizer;

	if(function_exists('wp_doing_ajax') && wp_doing_ajax()){
		return;
	}

	if(!is_admin() || is_user_logged_in()){
		return;
	}

	$redirect_slug = !empty($loginizer['login_redirect_url']) ? $loginizer['login_redirect_url'] : '';

	if(get_option('permalink_structure')){
		wp_safe_redirect(trailingslashit(home_url() . '/' . $redirect_slug));
		die();
	}

	wp_safe_redirect(home_url() . '?' . $redirect_slug);
	die();
}

function loginizer_pro_social_auth_load(){
	include_once LOGINIZER_PRO_DIR .'/main/social-api-login.php';
}
