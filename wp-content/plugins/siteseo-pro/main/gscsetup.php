<?php
/*
* SITESEO
* https://siteseo.io
* (c) SiteSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

class GSCSetup{
	
	static function connect(){
		
		// Handling the callback from API after Google OAuth
		if(isset($_GET['page']) && $_GET['page'] === 'siteseo-search-statistics' && isset($_GET['gsc_auth_callback']) && $_GET['gsc_auth_callback'] == '1' && isset($_GET['siteseo_auth_code'])){
			$state = isset($_GET['state_data']) ? sanitize_text_field(wp_unslash($_GET['state_data'])) : '';

			if(empty($state) || !self::verify_csrf_token($state)){
				wp_die(esc_html__('Security token verification failed token expired, try again!', 'siteseo-pro'));
			}
		}
		
		// Handling the redirect of the user for Google OAuth, through our API.
		if(!isset($_POST['siteseo_pro_connect_btn'])){
			return;
		}

		check_admin_referer('siteseo_pro_connect_google');

		if(!current_user_can('manage_options')){
			wp_die(esc_html__('You do not have sufficient privileges to perform this action.', 'siteseo-pro'));
		}

		$redirect_type = isset($_POST['redirect_type']) ? sanitize_text_field(wp_unslash($_POST['redirect_type'])) : 'settings';

		if($redirect_type === 'onboarding'){
			$redirect_uri = admin_url('admin.php?page=siteseo-onboarding&step=advanced');
		} else{
			$redirect_uri = admin_url('admin.php?page=siteseo-search-statistics');
		}

		$auth_url = SITESEO_API . '/search-console/token.php?action=authenticate_search_console&url=' . rawurlencode($redirect_uri) . '&softtoken=' . rawurlencode(self::generate_csrf_token());

		if(!empty($auth_url)){
			wp_redirect($auth_url);
			exit;
		}

		wp_die(esc_html__('Failed to generate authentication URL', 'siteseo-pro'));
	}
	
	static function generate_csrf_token(){
		$token = bin2hex(openssl_random_pseudo_bytes(32));
		set_transient('siteseo_csrf_token', $token, 300);

		return $token;
	}

	static function verify_csrf_token($token){
		$stored_token = get_transient('siteseo_csrf_token');
		delete_transient('siteseo_csrf_token');
		
		return !empty($stored_token) && hash_equals($stored_token, $token);
	}

	// Adds the website to the Google Search console.
	static function create_property($site_url){

		$tokens = \SiteSEOPro\GoogleConsole::get_tokens();
	
		if(empty($tokens) || empty($tokens['access_token'])){
			return new \WP_Error('no_token', __('Google access token not found. Please connect your Google account first.', 'siteseo-pro'));
		}

		$api_url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . urlencode($site_url);

		$args = [
			'method' => 'PUT',
			'headers' => [
				'Authorization' => 'Bearer ' . $tokens['access_token'],
				'Content-Type'  => 'application/json',
			],
			'body' => '{}',
			'timeout' => 30,
		];

		$response = wp_remote_request($api_url, $args);

		if(is_wp_error($response)){
			return $response;
		}
		
		$code = wp_remote_retrieve_response_code($response);

		if($code < 200 || $code >= 300){
			return new \WP_Error('api_error', wp_remote_retrieve_body($response));
		}

		return true;
	}
	
	// Get the token needed to verify the ownership of the website on Google search console.
	// We are doing verification through meta tag method.
	static function get_verification_token($site_url){
		
		$tokens = \SiteSEOPro\GoogleConsole::get_tokens();
		
		if(empty($tokens) || empty($tokens['access_token'])){
			return new \WP_Error('no_token', __('Google access token not found.', 'siteseo-pro'));
		}

		$api_url = 'https://www.googleapis.com/siteVerification/v1/token';
		$body = [
			'site' => [
				'type' => 'SITE',
				'identifier' => $site_url,
			],
			'verificationMethod' => 'META',
		];

		$args = [
			'method' => 'POST',
			'headers' => [
				'Authorization' => 'Bearer ' . $tokens['access_token'],
				'Content-Type'  => 'application/json',
			],
			'body' => json_encode($body),
			'timeout' => 30,
		];

		$response = wp_remote_post($api_url, $args);
		
		if(is_wp_error($response)){
			return $response;
		}
		
		$code = wp_remote_retrieve_response_code($response);
		
		$body = json_decode(wp_remote_retrieve_body($response), true);

		if($code != 200 || empty($body['token'])){
			return new \WP_Error('token_error', __('Failed to get verification token.', 'siteseo-pro'));
		}

		$allowed = [
			'meta' => [
				'name' => true,
				'content' => true,
			],
		];

		return wp_kses(wp_unslash($body['token']), $allowed);

	}
	
	// Setting the verification code we got through get_verification_token in Advanced settings google meta for verification.
	static function save_verification_meta($token){

		$token_meta = get_option('siteseo_advanced_option_name', []);

		$token = \SiteSEO\Settings\Util::extract_content($token);
		
		$token_meta['advanced_google'] = $token;

		$saved = update_option('siteseo_advanced_option_name', $token_meta);

		if($saved === false && get_option('siteseo_advanced_option_name') !== $token_meta){
			return new \WP_Error('save_error', __( 'Failed to save verification meta.', 'siteseo-pro'));
		}

		return true;
	}

	// Requesting Google to verify the owenership using the html metatag method.
	static function verify_property($site_url){
		
		$tokens = \SiteSEOPro\GoogleConsole::get_tokens();
		if(empty($tokens) || empty($tokens['access_token'])){
			return new \WP_Error('no_token', __('Google access token not found.', 'siteseo-pro'));
		}

		$api_url = 'https://www.googleapis.com/siteVerification/v1/webResource?verificationMethod=META';
		$body = [
			'site' => [
				'type' => 'SITE',
				'identifier' => $site_url,
			],
		];

		$args = [
			'method' => 'POST',
			'headers' => [
				'Authorization' => 'Bearer ' . $tokens['access_token'],
				'Content-Type'  => 'application/json',
			],
			'body'	=> json_encode($body),
			'timeout' => 30,
		];

		$response = wp_remote_post($api_url, $args);
		
		if(is_wp_error($response)){
			return $response;
		}
		
		$code = wp_remote_retrieve_response_code($response);

		if($code != 200){
			return new \WP_Error('verification_failed', __('Verification failed.', 'siteseo-pro'));
		}
		
		$body = json_decode(wp_remote_retrieve_body($response), true);

		return $body;
	}
	
	static function submit_sitemap(){

		$options = get_option('siteseo_xml_sitemap_option_name', []);

		// Making sure Sitemaps are enabled.
		if(empty($options['xml_sitemap_general_enable'])){
			return new \WP_Error('sitemap_disabled', __('SiteSEO sitemap is disabled. Please enable it to submit sitemap automatically.', 'siteseo-pro'));
		}

		$site_url = \SiteSEOPro\GoogleConsole::get_site_url();
		

		// Making sure the sitemap does not return 404 for newly created website
		flush_rewrite_rules(false);

		// If we are using the sc-domain, we need to extract just the host of the website.
		if(strpos($site_url, 'sc-domain') === 0){
			$sitemap_base = str_replace('sc-domain:', '', $site_url);
			$sitemap_base = (is_ssl() ? 'https://': 'http://').$sitemap_base;
		} else {
		    $sitemap_base = $site_url;
		}

		$sitemap_url = trailingslashit($sitemap_base) . 'sitemaps.xml';

		$tokens = \SiteSEOPro\GoogleConsole::get_tokens();
		
		// Getting list of sitemaps to submitted to Google already
		// So that we dont end up sending the same sitemap again.
		$list_endpoint = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($site_url) . '/sitemaps';
		$list_response = wp_remote_get($list_endpoint, [
			'headers' => [
				'Authorization' => 'Bearer ' . $tokens['access_token'],
			],
		]);

		if(is_wp_error($list_response)){
			return $list_response;
		}

		$body = json_decode(wp_remote_retrieve_body($list_response), true);
		$existing_sitemaps = !empty($body['sitemap']) ? $body['sitemap'] : [];

		foreach($existing_sitemaps as $sitemap){
			if(isset($sitemap['path']) && $sitemap['path'] === $sitemap_url){
				return; // current sitemap already exists so no need to go further.
			}
		}

		// Submit sitemap if not found
		$submit_endpoint = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($site_url) . '/sitemaps/' . urlencode($sitemap_url);
		$response = wp_remote_request($submit_endpoint, [
			'method'  => 'PUT',
			'headers' => [
				'Authorization' => 'Bearer ' . $tokens['access_token'],
				'Content-Type'  => 'application/json',
			],
			'body'	=> '{}',
		]);

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if(isset($body['error'])){
			return new \WP_Error('sitemap_submission', $body['error']['message']);
		}
	}

	static function get_pre_connected_sites(){

		$tokens = \SiteSEOPro\GoogleConsole::get_tokens();

		// Get sites from Google Search Console
		$response = wp_remote_get('https://www.googleapis.com/webmasters/v3/sites', [
			'headers' => [
				'Authorization' => 'Bearer ' . $tokens['access_token'],
				'Accept' => 'application/json',
			],
			'timeout' => 30
		]);

		if(is_wp_error($response)){
			return ['error' => $response->get_error_message()];
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if(isset($body['error'])){
			return ['error' => $body['error']['message']];
		}

		if(!isset($body['siteEntry'])){
			return [];
		}

		$sites = $body['siteEntry'];

		// Current site domain
		$current_site_url  = get_site_url();
		$current_host = wp_parse_url($current_site_url, PHP_URL_HOST);
		$current_host_length = strlen($current_host) + 1; // the +1 is for a . at the start

		$matched = [];

		foreach($sites as $site){

			if(!isset($site['siteUrl'])){
				continue;
			}

			$site_host = wp_parse_url($site['siteUrl'], PHP_URL_HOST);

			if(!$site_host){
				continue;
			}

			// Check if same domain or sub-domain
			if($site_host === $current_host || substr($site_host, - $current_host_length) === '.' . $current_host){
				$matched[] = $site;
			}

		}

		return $matched;
	}

	static function execute_setup_process(){

		$site_url = trailingslashit(get_site_url());
		
		self::save_site($site_url); // save site in db

		$pre_connected_sites = self::get_pre_connected_sites();

		$current_site_exists = false;

		if(!empty($pre_connected_sites) && !isset($pre_connected_sites['error'])){
			foreach($pre_connected_sites as $site){
				if($site['siteUrl'] === $site_url){
					$current_site_exists = true;
					break;
				}
			}
		}

		// site exits then on gsc then don't create property again on gsc
		if($current_site_exists){
			$sitemap = self::submit_sitemap();

			if(is_wp_error($sitemap)){
				return $sitemap;
			}
			
			$analytics = \SiteSEOPro\GoogleConsole::get_all_analytics();

			if(is_wp_error($analytics)){
				return $analytics;
			}
			
			return true;
		}

		// Step 1: Create property
		$property_result = self::create_property($site_url);
		if(is_wp_error($property_result)){
			return $property_result;
		}

		// Step 2: Get verification token
		$verification_token = self::get_verification_token($site_url);
		if(is_wp_error($verification_token)){
			return $verification_token;
		}

		// Step 3: Save meta tag
		$meta_saved = self::save_verification_meta($verification_token);
		if(is_wp_error($meta_saved)){
			return $meta_saved;
		}

		// Step 4: Verify property
		$verification_result = self::verify_property($site_url);
		if(is_wp_error($verification_result)){
			return $verification_result;
		}

		// Step 5: Submit sitemap
		$sitemap_result = self::submit_sitemap();
		if(is_wp_error($sitemap_result)){
			return $sitemap_result;
		}

		// Step 6: Fetch analytics data
		$analytics_result = \SiteSEOPro\GoogleConsole::get_all_analytics();
		if(is_wp_error($analytics_result)){
			return $analytics_result;
		}

		return true;
	}

	static function wizard(){
		$already_connected = \SiteSEOPro\GoogleConsole::is_connected();
		
		if(!empty($already_connected)){
			return;
		}
		$google_auth = isset($_GET['siteseo_auth_code']) ? sanitize_text_field(wp_unslash($_GET['siteseo_auth_code'])) : '';
		if(!empty($google_auth)){
			\SiteSEOPro\GoogleConsole::generate_tokens();
		}
		echo'<div class="siteseo-gsc-section">
			<div class="siteseo-gsc-header">
				<div class="siteseo-gsc-logo">
					<span class="dashicons dashicons-google"></span>
				</div>
				<div class="siteseo-gsc-titles">
					<h4>'.esc_html__('Connect to Google Search Console', 'siteseo-pro').'</h4>
					<p class="siteseo-gsc-subtitle">'.esc_html__('Unlock Powerful Insight and Automate Your SEO Tasks', 'siteseo-pro').'</p>
				</div>
				<span class="siteseo-badge-new">'.esc_html__('New Integration', 'siteseo-pro').'</span>
			</div>
			<div class="siteseo-gsc-list" id="siteseo-onboarding-logs" style="margin-top:20px; max-height:200px; overflow-y:auto; color:#fff;">
				<div class="siteseo-gsc-list-item">
					<svg class="siteseo-check-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
					</svg>
					<span>'.esc_html__('Automatically create and verify your Google Search Console property.', 'siteseo-pro').'</span>
				</div>
				<div class="siteseo-gsc-list-item">
					 <svg class="siteseo-check-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
					</svg>
					<span>'.esc_html__('Automatically submit your sitemap to Google for indexing.', 'siteseo-pro').'</span>
				</div>
				<div class="siteseo-gsc-list-item">
					 <svg class="siteseo-check-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
					</svg>
					<span>'.esc_html__('Fetch Search Console insights.', 'siteseo-pro').'</span>
				</div>
			</div>';
			wp_nonce_field('siteseo_pro_connect_google');
			echo '<input type="hidden" name="redirect_type" value="onboarding">
			<button type="submit" name="siteseo_pro_connect_btn" class="siteseo-btn-gsc">'.esc_html__('Connect', 'siteseo-pro').'</button>
		</div>';
	}
	
	static function save_site($site){
		$site_url = get_option('siteseo_google_tokens', []);
		
		if(!is_array($site_url)){
			$site_url = [];
		}
		
		$site_url['site_url'] = $site;

		update_option('siteseo_google_tokens', $site_url);
	}
}
