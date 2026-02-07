<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class Ajax{

	static function hooks(){
		add_action('wp_ajax_siteseo_pro_get_pagespeed_insights', '\SiteSEOPro\Ajax::get_pagespeed');
		add_action('wp_ajax_siteseo_pro_pagespeed_insights_remove_results', '\SiteSEOPro\Ajax::delete_speed_scores');

		//toogle option pro
		add_action('wp_ajax_siteseo_pro_save_woocommerce', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_kkart', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_edd', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_dublin', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_local', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_structured' , '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_404_monitoring', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_google_news', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_video_sitemap', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_save_rss_sitemap', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_update_htaccess', '\SiteSEOPro\Ajax::update_htaccess');
		add_action('wp_ajax_siteseo_pro_update_robots', '\SiteSEOPro\Ajax::update_robots');
		add_action('wp_ajax_siteseo_pro_export_redirect_csv', '\SiteSEOPro\Ajax::export_csv_redirect_logs');
		add_action('wp_ajax_siteseo_pro_clear_all_logs', '\SiteSEOPro\Ajax::redirect_clear_all_logs');
		add_action('wp_ajax_siteseo_pro_remove_selected_logs', '\SiteSEOPro\Ajax::delete_selected_log');
		add_action('wp_ajax_siteseo_pro_delete_robots_txt', '\SiteSEOPro\Ajax::delete_robots_txt');
		add_action('wp_ajax_siteseo_pro_ai_generate', '\SiteSEOPro\Ajax::generate_ai');
		add_action('wp_ajax_siteseo_pro_refresh_tokens', '\SiteSEOPro\Ajax::refresh_ai_tokens');
		add_action('wp_ajax_siteseo_pro_save_schema', '\SiteSEOPro\Ajax::save_global_schema');
		add_action('wp_ajax_siteseo_import_schema', '\SiteSEOPro\Ajax::import_schema');

		add_action('wp_ajax_siteseo_pro_get_schema', '\SiteSEOPro\Ajax::get_schema');
		add_action('wp_ajax_siteseo_pro_delete_schema', '\SiteSEOPro\Ajax::delete_schema');
		add_action('wp_ajax_siteseo_pro_save_llm_txt', '\SiteSEOPro\Ajax::save_toggle');
		add_action('wp_ajax_siteseo_pro_disconnect_google', '\SiteSEOPro\Ajax::google_disconnection');
		add_action('wp_ajax_siteseo_pro_create_gsc_property', '\SiteSEOPro\Ajax::create_gsc_property_handler');
		add_action('wp_ajax_siteseo_pro_refresh_search_stats', '\SiteSEOPro\Ajax::refresh_search_stats');
		
		// This is just to make sure, close of update notice works.
		if(isset($_GET['action']) && 'siteseo_close_update_notice' === sanitize_text_field(wp_unslash($_GET['action']))){
			add_filter('softaculous_plugin_update_notice', 'siteseo_pro_plugin_update_notice_filter');
		}

		add_action('wp_ajax_siteseo_pro_version_notice', '\SiteSEOPro\Ajax::version_notice');
		add_action('wp_ajax_siteseo_pro_dismiss_expired_licenses', '\SiteSEOPro\Ajax::dismiss_expired_licenses');
		
	}
	
	static function delete_schema(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required privilege', 'siteseo-pro'));
		}
		
		$id = sanitize_text_field($_POST['id']);
		$options = get_option('siteseo_auto_schema', ['schemas' => []]);
		
		if(isset($options['schemas'][$id])){
			unset($options['schemas'][$id]);
			update_option('siteseo_auto_schema', $options);
			wp_send_json_success(__('Schema deleted successfully!', 'siteseo-pro'));
		}

		wp_send_json_error(__('Schema not found!', 'siteseo-pro'));
	}
	
	static function get_schema(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required privilege', 'siteseo-pro'));
		}
		
		$id = sanitize_text_field($_POST['id']);
		$options = get_option('siteseo_auto_schema', ['schemas' => []]);
        
		if(isset($options['schemas'][$id])){
			wp_send_json_success($options['schemas'][$id]);
		}
		
		wp_send_json_error(__('Schema not found!', 'siteseo-pro'));
	}

	// Schema Import values
	static function import_schema(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required privilege', 'siteseo-pro'));
		}

		$schema_type = sanitize_text_field(wp_unslash($_POST['type']));
		$is_manual = sanitize_text_field(wp_unslash($_POST['is_manual'])); // flag for the tab

		// Selecting the input based on the tab type
		if(empty($is_manual)){
			$schema_url = sanitize_url(wp_unslash($_POST['url']));
			$schema_type = 'url';
		} else {
			$schema_html = wp_unslash($_POST['html']);
			$schema_json = wp_unslash($_POST['json']);
		}

		if($schema_type === 'url'){
			if(empty($schema_url)){
				wp_send_json_error(__('URL not found!', 'siteseo-pro'));
			}

			$schemas = ImportSchema::extract_schema_from_url($schema_url);
		}else if($schema_type === 'html'){
			if (empty($schema_html)){
				wp_send_json_error(__('HTML not found!', 'siteseo-pro'));
			}
			$schemas = ImportSchema::extract_schemas_from_html($schema_html);
		}else if($schema_type === 'json'){
			if(empty($schema_json)){
				wp_send_json_error(__('JSON not found', 'siteseo-pro'));
			}
			$schemas = ImportSchema::extract_schema_from_json($schema_json);
		}

		if(is_wp_error($schemas)){
			wp_send_json_error($schemas->get_error_message());
		}

		if(empty($schemas)){
			wp_send_json_error(__('No Schema Found!', 'siteseo-pro'));
		}
		
		wp_send_json_success([
			'saved'   => true,
			'schemas' => $schemas,
			'message' => __('Schema Import Successfully!', 'siteseo-pro'),
		]);
	}

	static function save_global_schema(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required privilege', 'siteseo-pro'));
		}

		$schema_data = [];
		if(isset($_POST['schema_data'])){
			parse_str($_POST['schema_data'], $schema_data);
		}
		
		$options = get_option('siteseo_auto_schema', []);

		$schema_id = !empty($schema_data['schema_id']) ? sanitize_text_field(wp_unslash($schema_data['schema_id'])) : uniqid();

		$properties = [];
		$display_on = [];
		$display_not_on = [];
		
		if(!empty($schema_data['schema_properties']) && is_array($schema_data['schema_properties'])){
			$properties = \SiteSEOPro\StructuredData::process_nested_properties($schema_data['schema_properties']);
		}
		
		// Process display_on rules
		if(!empty($schema_data['display_on']) && is_array($schema_data['display_on'])){
			$display_on_index = 0;
			foreach($schema_data['display_on'] as $value){
				$value = sanitize_text_field(wp_unslash($value));
				
				// Check if this is a specific target
				if($value === 'specific_targets'){
					// Get the specific target value
					if(!empty($schema_data['specific_targets'][$display_on_index])){
						$display_on[] = [
							'type' => 'specific_targets',
							'targets' => sanitize_text_field(wp_unslash($schema_data['specific_targets'][$display_on_index]))
						];
					}
					$display_on_index++;
				} else {
					$display_on[] = $value;
				}
			}
		}
		
		// display_not_on rules
		if(!empty($schema_data['display_not_on']) && is_array($schema_data['display_not_on'])){
			$display_not_on_index = 0;
			foreach($schema_data['display_not_on'] as $value){
				$value = sanitize_text_field(wp_unslash($value));
				
				// Check if this is a specific target
				if($value === 'specific_targets'){
					// Get specific target value
					if(!empty($schema_data['specific_targets_not'][$display_not_on_index])){
						$display_not_on[] = [
							'type' => 'specific_targets',
							'targets' => sanitize_text_field(wp_unslash($schema_data['specific_targets_not'][$display_not_on_index]))
						];
					}
					$display_not_on_index++;
				} else {
					$display_not_on[] = $value;
				}
			}
		}
		
		$schema = [
			'id' => $schema_id,
			'name' => isset($schema_data['schema_name']) ? sanitize_text_field(wp_unslash($schema_data['schema_name'])) : '',
			'type' => isset($schema_data['schema_type']) ? sanitize_text_field(wp_unslash($schema_data['schema_type'])) : '',
			'properties' => $properties,
			'display_on' =>  $display_on,
			'display_not_on' => $display_not_on,
		];

		$options['schemas'][$schema_id] = $schema;

		update_option('siteseo_auto_schema', $options);

		wp_send_json_success([
			'id' => $schema['id'],
			'name' => $schema['name'],
			'type' => $schema['type'],
		]);
	}

	static function refresh_ai_tokens(){
		global $siteseo;
		
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required privilege', 'siteseo-pro'));
		}
		
		$license = !empty($siteseo->license['license']) ? $siteseo->license['license'] : '';

		if(empty($license)){
			wp_send_json_error(__('Please link a license to keep using the AI feature', 'siteseo-pro'));
		}
		
		if(empty($siteseo->license['active'])){
			wp_send_json_error(__('Please activate your license to continue.', 'siteseo-pro'));
		}
		
		$res = wp_remote_post(SITESEO_PRO_AI_API, [
			'timeout' => 30,
			'body' => [
				'license' => $license,
				'url' => site_url(),
				'request_type' => 'token_refresh'
			]
		]);

		if(empty($res)){
			wp_send_json_error(__('Unable to complete this request', 'siteseo-pro'));
		}

		if(is_wp_error($res)){
			$error_string = $res->get_error_message();
			wp_send_json_error($error_string);
		}

		$res_code = wp_remote_retrieve_response_code($res);
		$body = wp_remote_retrieve_body($res);

		if(empty($body)){
			wp_send_json_error(__('The AI API responded with empty response, Response Code:', 'siteseo-pro') . $res_code);
		}

		$token_data = json_decode($body, true);
		
		if(empty($token_data) || json_last_error() !== JSON_ERROR_NONE){
			wp_send_json_error(__('The AI API responded with invalid JSON. Response Code -', 'siteseo-pro') . $res_code);
		}
		
		if($res_code > 299){
			$error = !empty($token_data['error']) ? sanitize_text_field($token_data['error']) : __('Unexpected response code returned from AI API: ', 'siteseo-pro') . $res_code;
			
			wp_send_json_error($error);
		}

		if(!isset($token_data['remaining_tokens'])){
			wp_send_json_error(__('No token data returned', 'siteseo-pro'));
		}
		
		update_option('siteseo_ai_tokens', [
			'remaining_tokens' => sanitize_text_field($token_data['remaining_tokens'])
		], false);

		wp_send_json_success(esc_html($token_data['remaining_tokens']));
		
	}
	
	static function generate_ai(){
		global $siteseo;
		
		check_ajax_referer('siteseo_pro_nonce', 'nonce');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required privilege', 'siteseo-pro'));
		}
		
		set_time_limit(100);

		$prompt = !empty($_REQUEST['prompt']) ? sanitize_textarea_field(wp_unslash($_REQUEST['prompt'])) : '';

		$license = $siteseo->license['license'];

		if(empty($license)){
			wp_send_json_error(__('Please link a license to keep using the AI feature', 'siteseo-pro'));
		}

		if(empty($siteseo->license['active'])){
			wp_send_json_error(__('Please activate your license to continue.', 'siteseo-pro'));
		}

		if(empty($prompt)){
			wp_send_json_error(__('Please provide a prompt for the AI to generate content', 'siteseo-pro'));
		}

		$res = wp_remote_post(SITESEO_PRO_AI_API, [
			'timeout' => 90,
			'body' => [
				'prompt' => $prompt,
				'license' => $license,
				'url' => site_url()
			]
		]);
		
		if(is_wp_error($res)){
			wp_send_json_error($res->get_error_message());
		}

		$res_code = wp_remote_retrieve_response_code($res);
		$body = wp_remote_retrieve_body($res);
		
		if(empty($body)){
			wp_send_json_error(__('Empty response from AI API', 'siteseo-pro'));
		}

		$ai_content = json_decode($body, true);
		
		if(empty($ai_content) || json_last_error() !== JSON_ERROR_NONE){
			wp_send_json_error(__('The AI API responded with invalid JSON. Response Code', 'siteseo-pro') . $res_code);
		}

		if($res_code > 299){
			$error = !empty($ai_content['error']) ? sanitize_text_field($ai_content['error']) : __('AI API error', 'siteseo-pro');
			wp_send_json_error($error);
		}
		
		if(!empty($ai_content['error'])){
			wp_send_json_error($ai_content['error']);
		}

		// Update token count
		if(!empty($ai_content['remaining_tokens'])){
				update_option('siteseo_ai_tokens', [
				'remaining_tokens' => sanitize_text_field($ai_content['remaining_tokens'])
			], false);
		}
		
		if(!empty($ai_content['ai'])){
			$ai_content['ai']  = json_decode($ai_content['ai'], true);
			
			if(!empty($ai_content['ai'])){
				// Sanitizing contents
				if(!empty($ai_content['ai']['titles']) && is_array($ai_content['ai']['titles'])){
					$ai_content['ai']['titles'] = array_filter($ai_content['ai']['titles'], function($title) {
						return sanitize_text_field($title);
					});
				}
			
				if(!empty($ai_content['ai']['descriptions']) && is_array($ai_content['ai']['descriptions'])){
					$ai_content['ai']['descriptions'] = array_filter($ai_content['ai']['descriptions'], function($descriptions) {
						return sanitize_text_field($descriptions);
					});
				}
			}
		}

		wp_send_json_success($ai_content);
	}

	static function save_toggle(){

		check_ajax_referer('siteseo_pro_toggle_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(['message' => esc_html__('Insufficient permissions', 'siteseo-pro')]);
		}

		$action = sanitize_text_field(wp_unslash($_POST['action']));
		switch($action){
			case 'siteseo_pro_save_woocommerce':
				$toggle_key = 'toggle_state_woocommerce';
				break;
			case 'siteseo_pro_save_kkart':
				$toggle_key = 'toggle_state_kkart';
				break;
			case 'siteseo_pro_save_edd':
				$toggle_key = 'toggle_state_easy_digital';
				break;
			case 'siteseo_pro_save_dublin':
				$toggle_key = 'toggle_state_dublin_core';
				break;
			case 'siteseo_pro_save_local':
				$toggle_key = 'toggle_state_local_buz';		
				break;
			case 'siteseo_pro_save_structured':
				$toggle_key = 'toggle_state_stru_data';
				break;
			case 'siteseo_pro_save_404_monitoring':
				$toggle_key = 'toggle_state_redirect_monitoring';
				break;
			case 'siteseo_pro_save_google_news':
				$toggle_key = 'toggle_state_google_news';
				break;
			case 'siteseo_pro_save_video_sitemap':
				$toggle_key = 'toggle_state_video_sitemap';
				break;
			case 'siteseo_pro_save_rss_sitemap':
				$toggle_key = 'toogle_state_rss_sitemap';
				break;
			case 'siteseo_pro_save_llm_txt':
				$toggle_key = 'toggle_state_llm_txt';
				break;
			default:
				wp_send_json_error(['message' => 'Invalid action']);
				return;
		}

		$toggle_value = isset($_POST['toggle_value']) ? sanitize_text_field(wp_unslash($_POST['toggle_value'])) : '0';

		$options = get_option('siteseo_pro_options', []);
		$options[$toggle_key] = $toggle_value;
		
		if($toggle_key == 'toggle_state_redirect_monitoring'){
			\SiteSEOPro\Settings\Util::maybe_create_404_table();
		}

		$updated = update_option('siteseo_pro_options', $options);

		if($updated){
			$response = [
				'message' => ucfirst($toggle_key) . ' toggle state saved successfully',
				'value' => $toggle_value
			];
		
			// Only reload page for structure data toggle
			if($toggle_key == 'toggle_state_stru_data'){
				$response['reload'] = true;
			}
		
			wp_send_json_success($response);
		} else{
			wp_send_json_error(['message' => 'Failed to save toggle state']);
		}
	
	}
	
	static function get_pagespeed(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have enough privilege to use this feature', 'siteseo-pro'));
		}

		global $siteseo;

		$api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
		$api_key = $siteseo->pro['ps_api_key'];
		$site_url = isset($_POST['test_url']) ? sanitize_url($_POST['test_url']) : site_url();
		
		if(empty($api_key)){
			wp_send_json_error(__('You have not saved the API key', 'siteseo-pro'));
		}
		
		if(empty($site_url)){
			wp_send_json_error(__('The URL you have provided is not valid', 'siteseo-pro'));
		}
		
		$device = (!empty($_REQUEST['is_mobile']) && $_REQUEST['is_mobile'] != 'false') ? 'mobile' : 'desktop';
		$request_url = $api_url . '?url=' . urlencode($site_url) . '&strategy='.$device.'&key='.$api_key;

		$response = wp_remote_get($request_url, array('timeout' => 60)); // 60 sec wait time 

		if(is_wp_error($response)){
			$error_message = $response->get_error_message();

			wp_send_json_error($error_message);
		}

		$body = wp_remote_retrieve_body($response);

		if(empty($body)){
			wp_send_json_error(__('Response body is empty', 'siteseo-pro'));
		}

		$result = json_decode($body, true);
		
		$page_speed = get_option('siteseo_pro_page_speed', []);

		// Handling Pagespeed insight result.
		foreach($result['lighthouseResult']['audits'] as $key => $audit){

			if(isset($audit['title']) && isset($audit['description']) && !isset($audit['details']['type'])){
				$page_speed[$device][$key] = [
					'id' => $audit['id'],
					'score' => $audit['score'],
					'title' => $audit['title'],
					'description' => $audit['description']
				];
			}

			if(isset($audit['details']['type']) && $audit['details']['type'] === 'opportunity'){
				$page_speed[$device]['opportunities'][] = [
					'title' => $audit['title'],
					'description' => $audit['description'],
					'score' => isset($audit['score']) ? $audit['score'] : null
				];
			}

			if(!isset($page_speed[$device]['diagnostics'])){
				$page_speed[$device]['diagnostics'] = [];
			}

			if(isset($audit['score']) && isset($audit['details']['type']) && $audit['score'] <= 0.89 && $audit['details']['type'] != 'opportunity'){
				$page_speed[$device]['diagnostics'][] = [
					'title' => $audit['title'],
					'description' => $audit['description'],
					'score' => isset($audit['score']) ? $audit['score'] : null
				];
			}
		}

		$page_speed[$device]['fetchTime'] = $result['lighthouseResult']['fetchTime'];
		$page_speed[$device]['score'] = $result['lighthouseResult']['categories']['performance']['score'];
		
		update_option('siteseo_pro_page_speed', $page_speed);
		
		wp_send_json_success();
	}
	
	static function delete_speed_scores(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have enough privilege to use this feature', 'siteseo-pro'));
		}
		
		delete_option('siteseo_pro_page_speed');
		wp_send_json_success();
	}

	static function update_htaccess(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission to edit this file.', 'siteseo-pro'));
		}

		$htaccess_enable = isset($_POST['htaccess_enable']) ? intval(sanitize_text_field(wp_unslash($_POST['htaccess_enable']))) : 0;
		$htaccess_rules = isset($_POST['htaccess_code']) ? sanitize_textarea_field(wp_unslash($_POST['htaccess_code'])) : '';

		if(empty($htaccess_enable)){
			wp_send_json_error(__('Please accept the warning first before proceeding with saving the htaccess', 'siteseo-pro'));
		}

		$htaccess_file = ABSPATH . '.htaccess';
		$backup_file = ABSPATH . '.htaccess_backup.siteseo';

		if(!is_writable($htaccess_file)){
			wp_send_json_error(__('.htaccess file is not writable so the ', 'siteseo-pro'));
		}

		// Backup .htaccess file
		if(!copy($htaccess_file, $backup_file)){
			wp_send_json_error(__('Failed to create backup of .htaccess file.', 'siteseo-pro'));
		}

		// Update the .htaccess file
		if(file_put_contents($htaccess_file, $htaccess_rules) === false){
			wp_send_json_error(__('Failed to update .htaccess file.', 'siteseo-pro'));
		}

		$response = wp_remote_get(site_url());
		$response_code = wp_remote_retrieve_response_code($response);
		
		// Restore the backup if something goes wrong.
		if($response_code > 299){
			copy($backup_file, $htaccess_file);
			wp_send_json_error(__('There was a syntax error in the htaccess rules you provided as the response to your website with the new htaccess gave response code of', 'siteseo-pro') . ' ' . $response_code);
		}

		wp_send_json_success(__('Successfully updated .htaccess file', 'siteseo-pro'));
	}
	
	static function update_robots(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission to edit this file.', 'siteseo-pro'));
		}
		
		$robots_txt = '';
		if(!empty($_POST['robots'])){
			$robots_txt = sanitize_textarea_field(wp_unslash($_POST['robots']));
		}
		
		// Updating the physical robots
		if(file_exists(ABSPATH . 'robots.txt')){
			if(!is_writable(ABSPATH . 'robots.txt')){
				wp_send_json_error(__('robots.txt file is not writable', 'siteseo-pro'));
			}
			
			if(file_put_contents(ABSPATH . 'robots.txt', $robots_txt)) {
				wp_send_json_success(__('Successfully updated the physical robots.txt file', 'siteseo-pro'));
			}
			
			wp_send_json_error(__('Unable to update the robots.txt file', 'siteseo-pro'));
		}

		// Updating option for virtual robots
		if(update_option('siteseo_pro_virtual_robots_txt', $robots_txt)) {
			wp_send_json_success(__('Successfully updated the virtual robots.txt rules', 'siteseo-pro'));
		}
		
		wp_send_json_error(__('Unable to update the virtual robots.txt rules', 'siteseo-pro'));
	}
	
	static function export_csv_redirect_logs(){
		
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission to edit this file.', 'siteseo-pro'));
		}
		
		$file_name = 'siteseo-redirect-data-' . current_time('Y-m-d') . '.csv';
		
		global $wpdb;

		$results = $wpdb->get_results("SELECT url, ip_address, timestamp, user_agent, referer, hit_count FROM {$wpdb->prefix}siteseo_redirect_logs ORDER BY timestamp DESC", ARRAY_A);
		
		if(empty($results)){
			wp_send_json_error('No data found');
			exit;
		}
		
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="' . $file_name . '"');
		header('Pragma: no-cache');
		header('Expires: 0');
		
		$output = fopen('php://output', 'w');
		
		// Add headers
		fputcsv($output, array('URL', 'IP Address', 'Timestamp', 'User Agent', 'Referer', 'Hit Count'));
		
		foreach($results as $row){
			fputcsv($output, $row);
		}
		
		fclose($output);
		exit;
	}
	
	static function redirect_clear_all_logs(){
		
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have permission to clear logs.', 'siteseo-pro'));
		}
		
		global $wpdb;
		$table_name = $wpdb->prefix . "siteseo_redirect_logs";
		
		$result = $wpdb->query("TRUNCATE TABLE $table_name");

		if($result !== false){
			wp_send_json_success(__('All logs have been cleared.', 'siteseo-pro'));
		}
		
		wp_send_json_error(__('Failed to clear logs.', 'siteseo-pro'));
	}
	
	static function delete_selected_log(){
		global $wpdb;
		
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have permission to clear logs.', 'siteseo-pro'));
		}
		
		$selected_ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
		
		if(empty($selected_ids)){
			wp_send_json_error('No logs selected');
			return;
		}
		
		$placeholders = array_fill(0, count($selected_ids), '%d');
		$placeholders_string = implode(',', $placeholders);
				
		// Delete
		$result = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}siteseo_redirect_logs WHERE id IN ($placeholders_string)", $selected_ids));
		
		if($result !== false){
			wp_send_json_success(array(
				'message' => 'Selected logs deleted successfully',
				'deleted_count' => $result
			));
		} else{
			wp_send_json_error(__('Failed to delete logs', 'siteseo-pro'));
		}
	}
	
	static function delete_robots_txt(){
		
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have permission for delete file.', 'siteseo-pro'));
		}
		
		$robots_txt_path = ABSPATH . 'robots.txt';
		
		if(file_exists($robots_txt_path)){
			if(!unlink($robots_txt_path)){
				wp_send_json_error(__('Failed to delete robots.txt file.', 'siteseo-pro'));
			}
		}
		
		wp_send_json_success();
	}
	
	// Version nag ajax
	static function version_notice(){
		check_ajax_referer('siteseo_version_notice', 'security');

		if(!current_user_can('activate_plugins')){
			wp_send_json_error(__('You do not have required access to do this action', 'siteseo-pro'));
		}
		
		$type = '';
		if(!empty($_REQUEST['type'])){
			$type = sanitize_text_field(wp_unslash($_REQUEST['type']));
		}

		if(empty($type)){
			wp_send_json_error(__('Unknown version difference type', 'siteseo-pro'));
		}
		
		update_option('siteseo_version_'. $type .'_nag', time() + WEEK_IN_SECONDS);
		wp_send_json_success();
	}
	
	static function dismiss_expired_licenses(){
		check_ajax_referer('siteseo_expiry_notice', 'security');

		if(!current_user_can('activate_plugins')){
			wp_send_json_error(__('You do not have required access to do this action', 'siteseo-pro'));
		}

		update_option('softaculous_expired_licenses', time());
		wp_send_json_success();
	}
	
	static function google_disconnection(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required access to do this action', 'siteseo-pro'));
		}

		\SiteSEOPro\GoogleConsole::disconnect();
		
		wp_send_json_success('Successfully disconnected from Google Search Console');
	}

	static function create_gsc_property_handler(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have permission to create property.', 'siteseo-pro'));
		}
		
		// If the request is for already existing website then we will get the site url
		if(!empty($_POST['site_url'])){
		    if(strpos($_POST['site_url'], 'sc-domain') === 0){
		        $site_url = sanitize_text_field(wp_unslash($_POST['site_url']));
		    } else {
		        $site_url = trailingslashit(sanitize_url(wp_unslash($_POST['site_url'])));
		    }
		} else {
		    $site_url = trailingslashit(get_site_url());
		}
		
		\SiteSEOPro\GSCSetup::save_site($site_url);
		
		$step = isset($_POST['step']) ? sanitize_text_field(wp_unslash($_POST['step'])) : '';
		
		if(empty($site_url)){
			wp_send_json_error(__('Site URL is missing.', 'siteseo-pro'));
		}
		
		if(empty($step)){
			wp_send_json_error(__('No step specified.', 'siteseo-pro'));
		}

		// Step 1: Create property
		if($step === 'create_property'){
			$property_result = \SiteSEOPro\GSCSetup::create_property($site_url);
			if(is_wp_error($property_result)){

				$property_error = $property_result->get_error_message();
				if(!empty($property_error) && defined('WP_DEBUG') && WP_DEBUG){
					error_log($property_error);
				}

				wp_send_json_error([
					'message' => __('Failed to create property in Search Console.', 'siteseo-pro'),
				]);
			}

			// Getting the verification meta tag
			$verification_tag = \SiteSEOPro\GSCSetup::get_verification_token($site_url);
			if(is_wp_error($verification_tag)){

				$tag_error = $verification_tag->get_error_message();
				if(!empty($tag_error) && defined('WP_DEBUG') && WP_DEBUG){
					error_log($tag_error);
				}

				wp_send_json_error([
					'message' => __('Failed to get verification token.', 'siteseo-pro'),
				]);
			}

			// Saving the verification code after stripping it out from the meta tag.
			$verification_tag_saved = \SiteSEOPro\GSCSetup::save_verification_meta($verification_tag);

			if(is_wp_error($verification_tag_saved)){
				$tag_save_error = $verification_tag_saved->get_error_message();

				wp_send_json_error([
					'message' => !empty($tag_save_error) ? esc_html($tag_save_error) : __('Failed to save verification meta.', 'siteseo-pro'),
				]);
			}

			wp_send_json_success(['message' => __('Property created successfully.', 'siteseo-pro')]);
		}

		// Step 2: Verify property
		if($step === 'verify'){
			// Verification could be slow as the target site could be slow to load making this process slower
			// That is the reason it has a separate request
			$verification_result = \SiteSEOPro\GSCSetup::verify_property($site_url);

			if(is_wp_error($verification_result)){
				if(defined('WP_DEBUG') && WP_DEBUG){
					error_log($verification_result->get_error_message());
				}

				wp_send_json_error([
					'message' => __('Property created but verification failed. Please check manually in Search Console.', 'siteseo-pro'),
					'error' => esc_html($verification_result->get_error_message()),
				]);
			}

			wp_send_json_success(['message' => __('Property verified successfully', 'siteseo-pro')]);
			
		}

		// Step 3: Submit Sitemap
		if($step === 'submit_sitemap'){

			$sitemap_submit = \SiteSEOPro\GSCSetup::submit_sitemap();
			
			if(is_wp_error($sitemap_submit)){
				$sitemap_error = $sitemap_submit->get_error_message();

				wp_send_json_error([
					'message' => !empty($sitemap_error) ? esc_html($sitemap_error) : __('Sitemap submission failed.', 'siteseo-pro'),
				]);
			}

			wp_send_json_success(['message' => __('Sitemap submitted successfully.', 'siteseo-pro')]);
		}

		// Step 4: Get Analytics
		if($step === 'fetch_analytics'){
			\SiteSEOPro\GoogleConsole::update_gsc_connection_status();
			\SiteSEOPro\GoogleConsole::get_all_analytics();
		}

		wp_send_json_success(['message' => __('Connected successfully...', 'siteseo-pro')]);

	}
	
	static function refresh_search_stats(){
		check_ajax_referer('siteseo_pro_nonce', 'nonce');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have permission to create property.', 'siteseo-pro'));
		}

		$rate_limit = get_transient('siteseo_search_stat_rate_limit');
		$now = time();

		if(empty($rate_limit)){
			$rate_limit = [];
			$rate_limit['count'] = 0;
			$rate_limit['expires'] = $now + 300;
		}
		
		// Rate limiting the process to 3 requests / 5 minutes, in real use case, even 1 call per hour should be enough.
		if($rate_limit['count'] >= 3){
			wp_send_json_error(__('You have reached a rate limit, please try after 5 minutes.', 'siteseo-pro'));
		}
		
		// For auto refresh every 12 hours, request comes with cron as a flag
		// so we know the request is coming from a auto refresh request not a manual one.
		if(!empty($_POST['cron'])){
			set_transient('siteseo_search_console_cron', true, 12*HOUR_IN_SECONDS);
		}

		// Updating the ratelimit
		$rate_limit['count']++;
		$transient_time = $rate_limit['expires'] - $now;
		if($transient_time < 10){
			$transient_time = 30;
		}

		$error = \SiteSEOPro\GoogleConsole::get_all_analytics();
		set_transient('siteseo_search_stat_rate_limit', $rate_limit, $transient_time);
		
		if(is_wp_error($error)){
			wp_send_json_error($error->get_error_message());
		}
		
		wp_send_json_success(__('Analytics have been successfully updated.', 'siteseo-pro'));
		
	}
}
