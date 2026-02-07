<?php

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

if(!class_exists('SpeedyCache')){
#[\AllowDynamicProperties]
class SpeedyCache{
	public $options = array();
	public $brand_name = 'SpeedyCache';
	public $logs;
	public $settings;
	public $license;
	public $image;
	public $mobile_cache;
	public $columnjs;
	public $js;
	public $css_util;
	public $render_blocking;
	public $enhanced;
	public $object;
	public $bloat;
}
}

// Prevent update of speedycache free
// This also work for auto update
if(!defined('SITEPAD')){
	add_filter('site_transient_update_plugins', 'speedycache_pro_disable_manual_update_for_plugin');
	add_filter('pre_site_transient_update_plugins', 'speedycache_pro_disable_manual_update_for_plugin');

	// Auto update free version after update pro version
	add_action('upgrader_process_complete', 'speedycache_pro_update_free_after_pro', 10, 2);
}

add_action('plugins_loaded', 'speedycache_pro_load_plugin');

register_activation_hook( __FILE__, 'speedycache_pro_activate');

function speedycache_pro_load_plugin(){
	global $speedycache;
	
	if(empty($speedycache)){
		$speedycache = new \SpeedyCache();
	}
	
	speedycache_pro_load_license();

	// DB Automatic Optimization Via Cron
	add_action('speedycache_optimize_db', '\SpeedyCache\DB::db_auto_optm_handler');

	// Actions to handle WP Cron schedules
	add_action('speedycache_auto_optm', '\SpeedyCache\Image::auto_optimize', 10, 1);
	add_action('speedycache_img_delete', '\SpeedyCache\Image::scheduled_delete', 10, 1);
	
	add_action('speedycache_test_event', 'speedycache_pro_run_test');
	
	include_once SPEEDYCACHE_PRO_DIR . '/main/admin.php';
	
	if(!defined('SITEPAD')){
		add_action('speedycache_generate_ccss', '\SpeedyCache\CriticalCss::generate', 10, 1);
		add_action('speedycache_unused_css', '\SpeedyCache\UnusedCss::generate', 10, 1);
		
		speedycache_pro_update_check();

		// Check for updates
		include_once(SPEEDYCACHE_PRO_DIR . '/main/plugin-update-checker.php');
		$speedycache_updater = SpeedyCache_PucFactory::buildUpdateChecker(speedycache_pro_api_url().'/updates.php?version='.SPEEDYCACHE_PRO_VERSION, SPEEDYCACHE_PRO_FILE);

		// Add the license key to query arguments
		$speedycache_updater->addQueryArgFilter('speedycache_pro_updater_filter_args');
			
		// Show the text to install the license key
		add_filter('puc_manual_final_check_link-speedycache-pro', 'speedycache_pro_updater_check_link', 10, 1);
		
		if(!is_admin() || !current_user_can('activate_plugins')){
			return;
		}

		add_action('admin_notices', 'speedycachepro_free_version_nag');

		// === Plugin Update Notice === //
		$plugin_update_notice = get_option('softaculous_plugin_update_notice', []);
		$available_update_list = get_site_transient('update_plugins'); 
		$plugin_path_slug = 'speedycache-pro/speedycache-pro.php';

		if(
			!empty($available_update_list) &&
			is_object($available_update_list) && 
			!empty($available_update_list->response) &&
			!empty($available_update_list->response[$plugin_path_slug]) && 
			(empty($plugin_update_notice) || empty($plugin_update_notice[$plugin_path_slug]) || (!empty($plugin_update_notice[$plugin_path_slug]) &&
			version_compare($plugin_update_notice[$plugin_path_slug], $available_update_list->response[$plugin_path_slug]->new_version, '<'))) &&
			(class_exists('\SpeedyCache\Promo') && method_exists('\SpeedyCache\Promo', 'update_notice'))
		){
			add_action('admin_notices', '\SpeedyCache\Promo::update_notice');
			add_filter('softaculous_plugin_update_notice', 'speedycache_pro_update_notice_filter');
		}
		// === Plugin Update Notice === //
	}

	add_action('add_attachment', '\SpeedyCache\Image::convert_on_upload');
	add_action('delete_attachment', '\SpeedyCache\Image::revert_on_delete');

}
	
// Nag when plugins dont have same version.
function speedycachepro_free_version_nag(){
	
	if(!defined('SPEEDYCACHE_VERSION')){
		return;
	}

	$dismissed_free = (int) get_option('speedycache_version_free_nag');
	$dismissed_pro = (int) get_option('speedycache_version_pro_nag');

	// Checking if time has passed since the dismiss.
	if(!empty($dismissed_free) && time() < $dismissed_pro && !empty($dismissed_pro) && time() < $dismissed_pro){
		return;
	}

	$showing_error = false;
	if(version_compare(SPEEDYCACHE_VERSION, SPEEDYCACHE_PRO_VERSION) > 0 && (empty($dismissed_pro) || time() > $dismissed_pro)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="speedycache-pro-version-notice" onclick="speedycache_pro_dismiss_notice(event)" data-type="pro">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of SpeedyCache Pro. We recommend updating to the latest version to ensure seamless and uninterrupted use of the application.', 'speedycache').'</p>
	</div>';
	}elseif(version_compare(SPEEDYCACHE_VERSION, SPEEDYCACHE_PRO_VERSION) < 0 && (empty($dismissed_free) || time() > $dismissed_free)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="speedycache-pro-version-notice" onclick="speedycache_pro_dismiss_notice(event)" data-type="free">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of SpeedyCache. We recommend updating to the latest free version to ensure smooth and uninterrupted use of the application.', 'speedycache').'</p>
	</div>';
	}
	
	if(!empty($showing_error)){
		wp_register_script('speedycache-pro-version-notice', '', array('jquery'), SPEEDYCACHE_PRO_VERSION, true );
		wp_enqueue_script('speedycache-pro-version-notice');
		wp_add_inline_script('speedycache-pro-version-notice', '
	function speedycache_pro_dismiss_notice(e){
		e.preventDefault();
		let target = jQuery(e.target);

		if(!target.hasClass("notice-dismiss")){
			return;
		}

		let jEle = target.closest("#speedycache-pro-version-notice"),
		type = jEle.data("type");

		jEle.slideUp();
		
		jQuery.post("'.admin_url('admin-ajax.php').'", {
			security : "'.wp_create_nonce('speedycache_version_notice').'",
			action: "speedycache_pro_version_notice",
			type: type
		}, function(res){
			if(!res["success"]){
				alert(res["data"]);
			}
		}).fail(function(data){
			alert("There seems to be some issue dismissing this alert");
		});
	}');
	}
}

// Version nag ajax
function speedycache_pro_version_notice(){
	check_admin_referer('speedycache_version_notice', 'security');

	if(!current_user_can('activate_plugins')){
		wp_send_json_error(__('You do not have required access to do this action', 'speedycache-pro'));
	}
	
	$type = '';
	if(!empty($_REQUEST['type'])){
		$type = sanitize_text_field(wp_unslash($_REQUEST['type']));
	}

	if(empty($type)){
		wp_send_json_error(__('Unknown version difference type', 'speedycache-pro'));
	}
	
	update_option('speedycache_version_'. $type .'_nag', time() + WEEK_IN_SECONDS);
	wp_send_json_success();
}

if(!defined('SITEPAD')){
	add_action('wp_ajax_speedycache_pro_version_notice', 'speedycache_pro_version_notice');
}

function speedycache_pro_run_test() {
	
	// If we have already attempted the test then return
	if(get_option('speedycache_test_executed', 0)){
		return;
	}

	update_option('speedycache_test_executed', 1);

	$speedycache_options = get_option('speedycache_options', []);
 	$should_test_optimization = (empty($speedycache_options['delay_js']) || empty($speedycache_options['render_blocking']));

	if (!$should_test_optimization){
		return;
	}

	$url = site_url();

	$old_score_arr = speedycache_pro_test_score($url);
	$new_score_arr = speedycache_pro_test_score($url . '?test_speedycache=1');

	$old_score = intval($old_score_arr['score']);
	$new_score = intval($new_score_arr['score']);

	if($new_score <= $old_score){
		return;
	} 

	$test_results = [
		'old_score' => $old_score,
		'new_score' => $new_score,
	];
	
	// Deleting the test file
	$test_dir = \SpeedyCache\Util::cache_path('test');

	if(file_exists($test_dir) && file_exists($test_dir .'/index.html')){
		unlink($test_dir .'/index.html');
	}
	
	update_option('speedycache_test_results', $test_results);
}

function speedycache_pro_test_score($url) {

	$parsed_url = parse_url($url);
	$parsed_query = isset($parsed_url['query']) ? $parsed_url['query'] : '';
	parse_str($parsed_query, $query_params);

	$is_test_mode = isset($query_params['test_speedycache']) && $query_params['test_speedycache'] === '1';

	if($is_test_mode){
		global $speedycache;

		$speedycache_test_options = ['minify_html' => true, 'delay_js' => true, 'render_blocking' => true, 'minify_js' => true, 'critical_images' => true, 'lazy_load' => true, 'delay_js_mode' => 'selected', 'delay_js_scripts' => ['fbevents.js', 'google-analytics.com', 'adsbygoogle.js', 'googletagmanager.com', 'fbq(', "ga( '", "ga('", '/gtm.js', '/gtag/js', 'gtag(', '/gtm-', '/gtm.']];

		$speedycache->options = array_merge($speedycache->options, $speedycache_test_options);
		$test_folder = \SpeedyCache\Util::cache_path('test');

		if(!file_exists($test_folder)){
			mkdir($test_folder, 0755, true);
		}
		
		$cache_content = speedycache_pro_generate_test_cache($url);
		if($cache_content){
			file_put_contents($test_folder . '/index.html', $cache_content);
		}
	}

	$api_url = SPEEDYCACHE_API . 'pagespeed.php?url=' . $url;

	$res = wp_remote_post($api_url, [
		'sslverify' => false,
		'timeout' => 30
	]);

	if(is_wp_error($res) || empty($res['body'])){
		return 0;
	}

	$body = json_decode($res['body'], true);

	return !empty($body['success']) && !empty($body['results']) ? $body['results'] : 0;
}

function speedycache_pro_generate_test_cache($url) {
	$response = wp_safe_remote_get($url);
	if(is_wp_error($response) || empty($response['body'])){
		return false;
	}
	return $response['body'];
}


//register_deactivation_hook( __FILE__, '\SpeedyCache\Install::deactivate');

// Load WP CLI command(s) on demand.
if(defined('WP_CLI') && !empty(WP_CLI) && defined('SPEEDYCACHE_PRO')){
	include_once SPEEDYCACHE_PRO_DIR.'/main/cli.php';
}