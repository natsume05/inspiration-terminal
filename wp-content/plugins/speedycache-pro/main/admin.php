<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

// Show menu with error if speedy cache is installed but is older than 1.1.0
// as after that we dont short circuit the free version
if(!defined('SPEEDYCACHE_VERSION') || version_compare(SPEEDYCACHE_VERSION, '1.1.1') < 0){
	add_action('admin_menu', 'speedycachepro_add_menu');
	return; // Return else going forward will break things.
}

if(!defined('SITEPAD')){
	add_action('speedycache_license_tmpl', '\SpeedyCache\SettingsPage::license_tab');
	add_action('speedycache_object_cache_tmpl', '\SpeedyCache\SettingsPage::object_tab');
	add_action('speedycache_bloat_tmpl', '\SpeedyCache\SettingsPage::bloat_tab');
}

add_action('speedycache_db_tmpl', '\SpeedyCache\SettingsPage::db_tab');
add_action('speedycache_pro_logs_tmpl', '\SpeedyCache\SettingsPage::logs');
add_action('speedycache_pro_stats_tmpl', '\SpeedyCache\SettingsPage::stats');
add_action('speedycache_image_optm_tmpl', '\SpeedyCache\SettingsPage::image_optm');

include_once SPEEDYCACHE_PRO_DIR . '/main/premium.php';

if(defined('SPEEDYCACHE_PRO') && file_exists(SPEEDYCACHE_PRO_DIR . '/main/image.php')){
	\SpeedyCache\Image::init();
	add_action('wp_ajax_speedycache_download_cwebp', '\SpeedyCache\Image::download_cwebp');
	add_action('add_meta_boxes_attachment', 'speedycache_pro_media_metabox');
}

add_action('admin_init', 'speedycache_pro_schedule_test_event');
add_action('admin_notices', 'speedycache_pro_notices');
add_filter('softaculous_expired_licenses', 'speedycache_pro_plugins_expired');
add_action('admin_enqueue_scripts', 'speedycache_pro_enqueue_admin_scripts');

// ----- AJAX ACTIONS ----- //
add_action('wp_ajax_speedycache_save_db_settings', 'speedycache_pro_save_db_settings');
add_action('wp_ajax_speedycache_statics_ajax_request', 'speedycache_pro_img_stats');
add_action('wp_ajax_speedycache_optimize_image_ajax_request', 'speedycache_pro_optimize_image');
add_action('wp_ajax_speedycache_update_image_settings', 'speedycache_pro_save_img_settings');
add_action('wp_ajax_speedycache_update_image_list_ajax_request', 'speedycache_pro_list_imgs');
add_action('wp_ajax_speedycache_revert_image_ajax_request', 'speedycache_pro_revert_img');
add_action('wp_ajax_speedycache_img_revert_all', 'speedycache_pro_revert_all_imgs');
add_action('wp_ajax_speedycache_verify_license', 'speedycache_pro_verify_license');
add_action('wp_ajax_speedycache_copy_test_settings', 'speedycache_pro_copy_test_settings');
add_action('wp_ajax_speedycache_dismiss_test_notice','speedycache_pro_dismiss_test_notice');
add_action('wp_ajax_speedycache_pro_dismiss_expired_licenses', 'speedycache_pro_dismiss_expired_licenses');
add_action('wp_ajax_speedycache_pro_get_db_optm', 'speedycache_pro_get_db_optm');

function speedycachepro_add_menu(){
	add_menu_page('SpeedyCache Settings', 'SpeedyCache', 'activate_plugins', 'speedycache', 'speedycachepro_menu_page');
}

function speedycachepro_menu_page(){
	echo '<div style="color: #333;padding: 50px;text-align: center;">
		<h1 style="font-size: 2em;margin-bottom: 10px;">Update Speedycache to Latest Version!</h>
		<p style=" font-size: 16px;margin-bottom: 20px; font-weight:400;">SpeedyCache Pro depends on the free version of SpeedyCache, so you need to update the free version to use SpeedyCache without any issue.</p>
		<a href="'.admin_url('plugin-install.php?s=speedycache&tab=search').'" style="text-decoration: none;font-size:16px;">Install Now</a>
	</div>';
}

function speedycache_pro_save_db_settings(){
	check_ajax_referer('speedycache_ajax_nonce');

	if(!current_user_can('manage_options')){
		wp_send_json_error(__('You do not have required permission.', 'speedycache'));
	}

	global $speedycache;

	// Automatic Optimization
	$options = get_option('speedycache_options', []);
	
	$options['db_purge_interval'] = \SpeedyCache\Util::sanitize_request('db_purge_interval', '');
	$options['db_post_revisions'] = isset($_REQUEST['db_post_revisions']);
	$options['db_trashed_contents'] = isset($_REQUEST['db_trashed_contents']);
	$options['db_trashed_spam_comments'] = isset($_REQUEST['db_trashed_spam_comments']);
	$options['db_trackbacks_pingback'] = isset($_REQUEST['db_trackbacks_pingback']);
	$options['db_transient_options'] = isset($_REQUEST['db_transient_options']);
	$options['db_expired_transient'] = isset($_REQUEST['db_expired_transient']);

	wp_clear_scheduled_hook('speedycache_optimize_db'); // Cleaning up any old hook

	$speedycache->options = $options;
	update_option('speedycache_options', $options);
	
	// If no option is selected then there is nothing to schedule for.
	if(
		empty($options['db_post_revisions']) && 
		empty($options['db_trashed_contents']) &&
		empty($options['db_trashed_spam_comments']) &&
		empty($options['db_trackbacks_pingback']) &&
		empty($options['db_transient_options']) &&
		empty($options['db_expired_transient'])
	){
		wp_send_json_success();
	}
	
	// ['event_name', 'event_time_offset']
	$cron_map = [
		'daily' => ['daily', DAY_IN_SECONDS] ,
		'weekly' => ['weekly', WEEK_IN_SECONDS],
		'fortnight' => ['speedycache_fortnight', 2*WEEK_IN_SECONDS],
		'monthly' => ['speedycache_monthly', MONTH_IN_SECONDS],
	];

	if(array_key_exists($options['db_purge_interval'], $cron_map)){
		$event_name = $cron_map[$options['db_purge_interval']][0];
		$base_event_offset = $cron_map[$options['db_purge_interval']][1];
		wp_schedule_event(time() + $base_event_offset, $cron_map[$options['db_purge_interval']][0], 'speedycache_optimize_db');
	}

	wp_send_json_success();
}

function speedycache_pro_img_stats(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	if(!class_exists('\SpeedyCache\Image')){
		wp_send_json_error(__('The file required to Process Image optimization is not present', 'speedycache'));
	}

	$res = \SpeedyCache\Image::statics_data();
	wp_send_json($res);
}

function speedycache_pro_optimize_image(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	if(!class_exists('\SpeedyCache\Image')){
		wp_send_json_error(__('The file required to Process Image optimization is not present', 'speedycache'));
	}
	
	$id = null;
	if(!empty($_POST['img_id'])){
		$id = (int) sanitize_text_field(wp_unslash($_POST['img_id']));
		
		if(empty($id)){
			wp_send_json_error('Empty Image ID');
		}
	}
	

	$res = \SpeedyCache\Image::optimize_single($id);
	$res[1] = isset($res[1]) ? $res[1] : '';
	$res[2] = isset($res[2]) ? $res[2] : '';
	$res[3] = isset($res[3]) ? $res[3] : '';
	
	$response = array(
		'message' => $res[0],
		'success' => $res[1],
		'id' => $res[2],
		'percentage' => $res[3],
	);
	
	wp_send_json($response);
}

function speedycache_pro_save_img_settings(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');
	
	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	global $speedycache;

	$settings = speedycache_optpost('settings');
	
	foreach($settings as $key => $setting){		
		$new_key = str_replace('img_', '', $key);
		
		$settings[$new_key] = $setting;
		unset($settings[$key]);
	}

	$speedycache->image['settings'] = $settings;
	
	if(update_option('speedycache_img', $speedycache->image['settings'])){
		wp_send_json_success();
	}
	
	wp_send_json_error();
}

function speedycache_pro_list_imgs(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');
	
	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	$query_images_args = array();
	$query_images_args['offset'] = intval(speedycache_optget('page')) * intval(speedycache_optget('per_page'));
	$query_images_args['order'] = 'DESC';
	$query_images_args['orderby'] = 'ID';
	$query_images_args['post_type'] = 'attachment';
	$query_images_args['post_mime_type'] = array('image/jpeg', 'image/png', 'image/gif');
	$query_images_args['post_status'] = 'inherit';
	$query_images_args['posts_per_page'] = speedycache_optget('per_page');
	$query_images_args['meta_query'] = array(
								array(
									'key' => 'speedycache_optimisation',
									'compare' => 'EXISTS'
									)
								);

	$query_images_args['s'] = speedycache_optget('search');

	if(!empty($_GET['filter'])){
		if(speedycache_optget('filter') == 'error_code'){
			
			$filter = array(
				'key' => 'speedycache_optimisation',
				'value' => base64_encode('"error_code"'),
				'compare' => 'LIKE'
			);

			$filter_second = array(
				'key' => 'speedycache_optimisation',
				'compare' => 'NOT LIKE'
			);

			array_push($query_images_args['meta_query'], $filter);
			array_push($query_images_args['meta_query'], $filter_second);
		}
	}

	$result = array(
		'content' => \SpeedyCache\Image::list_content($query_images_args),
		'result_count' => \SpeedyCache\Image::count_query($query_images_args)
	);

	wp_send_json($result);
}

function speedycache_pro_revert_img(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must Be admin');
	}

	global $speedycache;

	if(!empty($_GET['id'])){
		$speedycache->image['id'] = (int) speedycache_optget('id');
	}

	wp_send_json(\SpeedyCache\Image::revert());
}

function speedycache_pro_revert_all_imgs(){
	check_ajax_referer('speedycache_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	\SpeedyCache\Image::revert_all();
}


function speedycache_pro_verify_license(){

	if(!wp_verify_nonce($_GET['security'], 'speedycache_license')){
		wp_send_json_error(__('Security Check Failed', 'speedycache'));
	}
	
	if(!current_user_can('manage_options')){
		wp_send_json_error(__('You do not have required permission.', 'speedycache'));
	}
	
	global $speedycache;

	$license = sanitize_key($_GET['license']);
	
	if(empty($license)){
		wp_send_json_error(__('The license key was not submitted', 'speedycache'));
	}
	
	$resp = wp_remote_get(SPEEDYCACHE_API.'license.php?license='.$license.'&url='.rawurlencode(site_url()), array('timeout' => 30));
	
	if(!is_array($resp)){
		wp_send_json_error(__('The response was malformed<br>'.var_export($resp, true), 'speedycache'));
	}

	$json = json_decode($resp['body'], true);

	// Save the License
	if(empty($json['license'])){
		wp_send_json_error(__('The license key is invalid', 'speedycache'));
	}
	
	$speedycache->license = $json;
	update_option('speedycache_license', $json, false);
	
	wp_send_json_success();
}

function speedycache_pro_enqueue_admin_scripts(){
	wp_enqueue_style('speedycache-admin-pro', SPEEDYCACHE_PRO_URL . '/assets/css/admin.css', [], SPEEDYCACHE_PRO_VERSION);
	wp_enqueue_script('speedycache-admin-pro', SPEEDYCACHE_PRO_URL . '/assets/js/admin.js', [], SPEEDYCACHE_PRO_VERSION);
	wp_localize_script('speedycache-admin-pro', 'speedycache_pro_ajax', [
		'url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('speedycache_pro_ajax_nonce'),
		'premium' => defined('SPEEDYCACHE_PRO'),
	]);
}

// Sets speedycache settings to our predefined defaults.
function speedycache_pro_copy_test_settings(){
	check_ajax_referer('speedycache_pro_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}

	global $speedycache;

	$test_settings = ['minify_html' => true, 'delay_js' => true, 'render_blocking' => true, 'minify_js' => true, 'critical_images' => true, 'lazy_load' => true, 'delay_js_mode' => 'selected', 'delay_js_scripts' => ['fbevents.js', 'google-analytics.com', 'adsbygoogle.js', 'googletagmanager.com', 'fbq(', "ga( '", "ga('", '/gtm.js', '/gtag/js', 'gtag(', '/gtm-', '/gtm.']];
	
	$speedycache->options = array_merge($speedycache->options, $test_settings);
	
	update_option('speedycache_options', $speedycache->options);
	delete_option('speedycache_test_results');
	
	wp_send_json_success(__('Settings applied successfully.', 'speedycache-pro'));
}	

function speedycache_pro_dismiss_test_notice(){
	check_ajax_referer('speedycache_pro_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_die('Must be admin');
	}
	
	delete_option('speedycache_test_results');
}

function speedycache_pro_schedule_test_event() {
	$install_time = get_option('speedycache_free_installed', 0);
	$event_executed = get_option('speedycache_test_executed', 0);
	if($install_time && !$event_executed){
		if(($install_time + WEEK_IN_SECONDS) < time()){
			$event_time = time() + DAY_IN_SECONDS;
		} else {
			$event_time = $install_time + WEEK_IN_SECONDS;
		}

		if(!wp_next_scheduled('speedycache_test_event')){
			wp_schedule_single_event($event_time, 'speedycache_test_event');
		}
	}
}

function speedycache_pro_test_results_notice() {
	
	if(!current_user_can('manage_options')){
		return;
	}

	$current_screen = get_current_screen();
	if(!isset($current_screen->id) || strpos($current_screen->id, 'speedycache') === false){
		return;
	}

	$test_results = get_option('speedycache_test_results');

	if(empty($test_results)){
		return;
	}
	
	$old_score = $test_results['old_score'];
	$new_score = $test_results['new_score'];

	$stroke_old = !empty($old_score) ? 100 - $old_score : 0;
	$stroke_new = !empty($new_score) ? 100 - $new_score : 0;

	$old_color = speedycache_pro_get_score_color($old_score);
	$new_color = speedycache_pro_get_score_color($new_score);

	echo '<div class="notice notice-success is-dismissible speedycache-test-notice">
	<p class="speedycache-notice-title"><strong>'.esc_html__('Speed Test Results:', 'speedycache-pro').'</strong></p>

	<div class="speedycache-test-chart-wrap">
		<!-- Before Optimization -->
		<div class="speedycache-donut-wrap speedycache-before-optimization">
			<svg width="150" height="150" viewBox="0 0 40 40" class="speedycache-donut">
				<circle class="donut-hole" cx="20" cy="20" r="15.91549430918954" fill="'.esc_attr($old_color[1]).'"></circle>
				<circle class="speedycache-donut-segment" cx="20" cy="20" r="15.91549430918954" fill="transparent" stroke-width="3" stroke-linecap="round" stroke-dasharray="'.esc_attr($old_score).' '.esc_attr($stroke_old).'" stroke-dashoffset="25" style="stroke:'.esc_attr($old_color[0]).';"></circle>
				<g class="speedycache-test-donut-text">
					<text y="55%" transform="translate(0, 2)">
						<tspan x="50%" text-anchor="middle" class="speedycache-donut-percent" style="fill:'.esc_attr($old_color[2]).';">'.esc_attr($old_score).'</tspan>
					</text>
				</g>
			</svg>
			<p class="speedycache-donut-label">'.esc_html__('Before Optimization', 'speedycache-pro').'</p>
		</div>

		<!-- After Optimization -->
		<div class="speedycache-donut-wrap speedycache-after-optimization">
			<svg width="150" height="150" viewBox="0 0 40 40" class="speedycache-donut">
				<circle class="donut-hole" cx="20" cy="20" r="15.91549430918954" fill="'.esc_attr($new_color[1]).'"></circle>
				<circle class="speedycache-donut-segment" cx="20" cy="20" r="15.91549430918954" fill="transparent" stroke-width="3" stroke-linecap="round" stroke-dasharray="'.esc_attr($new_score).' '.esc_attr($stroke_new).'" stroke-dashoffset="25" style="stroke:'.esc_attr($new_color[0]).';"></circle>
				<g class="speedycache-test-donut-text">
					<text y="55%" transform="translate(0, 2)">
						<tspan x="50%" text-anchor="middle" class="speedycache-donut-percent" style="fill:'.esc_attr($new_color[2]).';">'.esc_attr($new_score).'</tspan>
					</text>
				</g>
			</svg>
			<p class="speedycache-donut-label">'.esc_html__('After Optimization', 'speedycache-pro').'</p>
		</div>
	</div>

	<div class="speedycache-test-action">
		'.esc_html__('Want to enable the SpeedyCache settings used for this test?', 'speedycache-pro').' 
		<button class="speedycache-enable-btn speedycache-copy-test-settings">'.esc_html__('Enable Now', 'speedycache-pro').'</button>
	</div>

	<button type="button" class="notice-dismiss speedycache-custom-dismiss">
		<span class="screen-reader-text">'.esc_html__('Dismiss this notice.', 'speedycache-pro').'</span>
	</button>
	</div>';
}

// Returns the name of plugin to be shown in Common Expiry notice 
function speedycache_pro_plugins_expired($plugins){
	global $speedycache;

	if(!empty($speedycache->license) && empty($speedycache->license['active']) && strpos($speedycache->license['license'], 'SOFTWP') !== FALSE){
		$plugins[] = 'SpeedyCache';
	}

	return $plugins;
}

function speedycache_pro_notices(){
	global $speedycache;
	
	// Don't need to show any notice to sitepad users
	if(defined('SITEPAD')){
		return;
	}
	
	if(!current_user_can('activate_plugins')){
		return;
	}

	$current_screen = get_current_screen();
	
	// Test result notice
	if(isset($current_screen->id) && strpos($current_screen->id, 'speedycache') !== false){
		speedycache_pro_test_results_notice();
	}
	
	// If the license is active then we do not need to show any notice.
	if(!empty($speedycache->license) && empty($speedycache->license['active']) && (empty($speedycache->license['license']) || strpos($speedycache->license['license'], 'SOFTWP') !== FALSE)){
		speedycache_pro_expiry_notice();
	}
	
}

function speedycache_pro_expiry_notice(){
	global $speedycache;

	// The combined notice for all Softaculous plugin to show that the license has expired
	$dismissed_at = get_option('softaculous_expired_licenses', 0);
	$expired_plugins = apply_filters('softaculous_expired_licenses', []);
	$soft_wp_buy = 'https://www.softaculous.com/clients?ca=softwp_buy';
	
	if(
		!empty($expired_plugins) && 
		is_array($expired_plugins) && 
		!defined('SOFTACULOUS_EXPIRY_LICENSES') && 
		(empty($dismissed_at) || ($dismissed_at + WEEK_IN_SECONDS) < time())
	){

		define('SOFTACULOUS_EXPIRY_LICENSES', true); // To make sure other plugins don't return a Notice
		$soft_rebranding = get_option('softaculous_pro_rebranding', []);

		if(!empty($speedycache->license['has_plid'])){
			if(!empty($soft_rebranding['sn']) && $soft_rebranding['sn'] != 'Softaculous'){
				
				$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact %3$s to continue receiving uninterrupted updates and support for %4$s.', 'speedycache-pro'),
					'<font style="color:red;"><b>',
					'</b></font>',
					esc_html($soft_rebranding['sn']),
					esc_html(implode(', ', $expired_plugins))
				);
				
			}else{
				$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact your hosting provider to continue receiving uninterrupted updates and support for %3$s.', 'speedycache-pro'),
					'<font style="color:red;"><b>',
					'</b></font>',
					esc_html(implode(', ', $expired_plugins))
				);
			}
		}else{
			$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please %3$srenew%4$s it to continue receiving uninterrupted updates and support for %5$s.', 'speedycache-pro'),
				'<font style="color:red;"><b>',
				'</b></font>',
				'<a href="'.esc_url($soft_wp_buy.'&license='.$speedycache->license['license'].'&plan='.$speedycache->license['plan']).'" target="_blank">',
				'</a>',
				esc_html(implode(', ', $expired_plugins))
			);
		}
		
		echo '<div class="notice notice-error is-dismissible" id="speedycache-pro-expiry-notice">
				<p>'.$msg. '</p>
			</div>';

		wp_register_script('speedycache-pro-expiry-notice', '', array('jquery'), SPEEDYCACHE_PRO_VERSION, true );
		wp_enqueue_script('speedycache-pro-expiry-notice');
		wp_add_inline_script('speedycache-pro-expiry-notice', '
		jQuery(document).ready(function(){
			jQuery("#speedycache-pro-expiry-notice").on("click", ".notice-dismiss", function(e){
				e.preventDefault();
				let target = jQuery(e.target);

				let jEle = target.closest("#speedycache-pro-expiry-notice");
				jEle.slideUp();

				jQuery.post("'.admin_url('admin-ajax.php').'", {
					security : "'.wp_create_nonce('speedycache_expiry_notice').'",
					action: "speedycache_pro_dismiss_expired_licenses",
				}, function(res){
					if(!res["success"]){
						alert(res["data"]);
					}
				}).fail(function(data){
					alert("There seems to be some issue dismissing this alert");
				});
			});
		})');
	}
}

function speedycache_pro_get_score_color($score) {

	$score_color_map = [
		0   => ['#c00', '#c003', '#c00'], // Red
		50  => ['#fa3', '#ffa50036', '#fa3'], // Orange
		90  => ['#0c6', '#00cc663b', '#080'], // Green
	];

	if ($score >= 0 && $score < 50) {
		return $score_color_map[0];
	} elseif ($score >= 50 && $score < 90) {
		return $score_color_map[50];
	} else {
		return $score_color_map[90];
	}
}

function speedycache_pro_media_metabox($post){
	
	if(empty($post)){
		return;
	}
	
	$allowed_img_types = [
		'image/png',
		'image/jpg',
		'image/jpeg',
	];
	
	if(!in_array($post->post_mime_type, $allowed_img_types)){
		return;
	}

	add_meta_box( 
		'speedycache-optm-img',
		__('SpeedyCache Image Optimization', 'speedycache-pro'),
		'speedycache_pro_img_optm_metabox',
		'attachment',
		'side',
		'default'
		);
}

function speedycache_pro_img_optm_metabox($post){
	
	if(empty($post)){
		echo 'No Post data';
		return;
	}
	
	$optimized_data = get_post_meta($post->ID, 'speedycache_optimisation', true);
	
	if(!empty($optimized_data)){
		$optimized_data = base64_decode($optimized_data);
		$optimized_data = json_decode($optimized_data, true);
		
		if(!empty($optimized_data) && !empty($optimized_data[0]) && !empty($optimized_data[0]['file']) && file_exists($optimized_data[0]['file'])){
			esc_html_e('Image has already been optimized', 'speedycache-pro');
			return;
		}
	}

	echo '<button class="button" id="speedycache-optm-attachment" data-id="'.esc_attr($post->ID).'">'.esc_html__('Optimize this Image', 'speedycache-pro').'</button>';
	
	wp_register_script('speedycache-img-optm-meta-box', '', array('jquery'), '', true);
	wp_enqueue_script('speedycache-img-optm-meta-box');
	wp_add_inline_script('speedycache-img-optm-meta-box', 'jQuery(document).ready(function(){
		jQuery("#speedycache-optm-attachment").on("click", function(e){
			e.preventDefault();
			
			let attachment_id = jQuery(e.target).data("id"),
			nonce = "'.wp_create_nonce('speedycache_ajax_nonce').'";
			
			jQuery.ajax({
				url : "'.esc_url(admin_url('admin-ajax.php')).'",
				method : "POST",
				data : {
					"action":"speedycache_optimize_image_ajax_request",
					"security": nonce,
					"img_id": attachment_id,
				},
				success: function(res){
					if(res.success){
						window.location.reload();
					}
				}
			});
			
		})
	})');
}

function speedycache_pro_dismiss_expired_licenses(){
	check_admin_referer('speedycache_expiry_notice', 'security');

	if(!current_user_can('activate_plugins')){
		wp_send_json_error(__('You do not have required access to do this action', 'speedycache-pro'));
	}

	update_option('softaculous_expired_licenses', time());
	wp_send_json_success();
}

function speedycache_pro_get_db_optm(){
	global $wpdb;
	
	check_ajax_referer('speedycache_pro_ajax_nonce', 'security');

	if(!current_user_can('manage_options')){
		wp_send_json_error(__('You do not have required access to do this action', 'speedycache-pro'));
	}

	$result = $wpdb->get_row("SELECT 
		SUM(post_type = 'revision') AS post_revisions,
		SUM(post_status = 'trash') AS trashed_contents
	FROM `$wpdb->posts`", ARRAY_A);
	
	$statics = [];

	$statics['post_revisions'] = !empty($result['post_revisions']) ? $result['post_revisions'] : 0;
	$statics['trashed_contents'] = !empty($result['trashed_contents']) ? $result['trashed_contents'] : 0;

	$result = $wpdb->get_row("SELECT 
			SUM(comment_approved IN ('spam','trash')) AS trashed_spam_comments,
			SUM(comment_type IN ('trackback','pingback')) AS trackback_pingback
		FROM `$wpdb->comments`", ARRAY_A);
		
	$statics['trashed_spam_comments'] = !empty($result['trashed_spam_comments']) ? $result['trashed_spam_comments'] : 0;
	$statics['trackback_pingback'] = !empty($result['trackback_pingback']) ? $result['trackback_pingback'] : 0;

	$result = $wpdb->get_row("SELECT 
			COUNT(CASE WHEN option_name LIKE '%\_transient\_%' THEN 1 END) AS transient_options,
			COUNT(CASE WHEN option_name LIKE '_transient_timeout%' AND option_value < " . time() . " THEN 1 END) AS expired_transient
		FROM `$wpdb->options`", ARRAY_A);

	$statics['transient_options'] = ($result['transient_options'] > 20) ? $result['transient_options'] : 0;
	$statics['expired_transient'] = !empty($result['expired_transient']) ? $result['expired_transient'] : 0;
	$statics['all_warnings'] = array_sum($statics);

	wp_send_json_success($statics);

}