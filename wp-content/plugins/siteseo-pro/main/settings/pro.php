<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

namespace SiteSEOPro\Settings;

// Are we being accessed directly ?
if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

use \SiteSEOPro\Settings\PageSpeed;

class Pro{

	// Display page
	static function home(){
		global $siteseo;

		echo '<div id="siteseo-root">';
		if(function_exists('siteseo_admin_header')){
			siteseo_admin_header();
		}

		$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'tab_siteseopro_woocommerce'; // Default tab
		$structure_data_toggle = !empty($siteseo->pro['toggle_state_stru_data']) ? true : '';

		$siteseopro_settings_tabs = [
			'tab_siteseopro_woocommerce' => !defined('SITEPAD') ? esc_html__('WooCommerce', 'siteseo-pro') : esc_html__('Kkart', 'siteseo-pro'),
			'tab_siteseopro_pagespeed_insights' => esc_html__('PageSpeed Insights', 'siteseo-pro'),
			'tab_siteseopro_dublin_core' => esc_html__('Dublin Core', 'siteseo-pro'),
			'tab_siteseopro_local_business' => esc_html__('Local Business', 'siteseo-pro'),
			'tab_siteseopro_structured_data' => esc_html__('Structured Data Types', 'siteseo-pro'),
			'tab_siteseopro_breadcrumbs' => esc_html__('Breadcrumbs','siteseo-pro'),
			'tab_siteseopro_robots_txt' => esc_html__('robots.txt','siteseo-pro'),
			'tab_siteseopro_redirect_monitor' => esc_html__('Redirections / 404 monitoring', 'siteseo-pro'),
			'tab_google_news' => esc_html__('Google News', 'siteseo-pro'),
			'tab_video_sitemap' => esc_html__('Video Sitemap', 'siteseo-pro'),
			'tab_rss_sitemap' => esc_html__('RSS Sitemap', 'siteseo-pro'),
		];
		
		if(!empty($structure_data_toggle)){
			$siteseopro_settings_tabs['tab_auto_schema'] = esc_html__('Global Schema', 'siteseo-pro');
		}
		
		$siteseopro_settings_tabs['tab_llm_txt'] = esc_html__('LLMs txt', 'siteseo-pro');

		if (!defined('SITEPAD')) {
			$siteseopro_settings_tabs['tab_siteseopro_easydigital_downloads'] = esc_html__('Easy Digital Downloads', 'siteseo-pro');
			$siteseopro_settings_tabs['tab_siteseopro_htaccess'] = esc_html__('htaccess','siteseo-pro');
		}

		echo'<form method="post" style="margin-right:20px;" class="siteseo-option" name="siteseo-flush">';

		$sitesepro_license = get_option('siteseo_license');
		// TODO:: Will make it visible later.
		// if(empty($sitesepro_license['license'])){
			// echo'<div class="siteseopro_license_notices">';
				// $docs = siteseo_get_docs_links();

				// echo'<div class="siteseo-notice is-success">
				// <p><strong>' . __('Welcome to SiteSEO PRO!', 'siteseo-pro') . '</strong></p>
				// <p>' . __('Please activate your license to receive automatic updates and get premium support.', 'siteseo-pro') . '</p>
				// <p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=siteseo-license')) . '">' . __('Activate License', 'siteseo-pro') . '</a></p>
				// </div>
				
			// </div>';
		// }
		echo'<span id="siteseo-tab-title"><strong>'.esc_html__('SiteSEO - PRO', 'siteseo-pro').'</strong></span>';
		wp_nonce_field('sitseo_pro_settings');

		echo '<div id="siteseo-tabs" class="wrap">
		<div class="siteseo-nav-tab-wrapper">';

		foreach ($siteseopro_settings_tabs as $tab_key => $tab_caption) {
			$active_class = ($current_tab === $tab_key) ? ' siteseo-nav-tab-active' : '';
			
			 $new_badge = '';
			if(in_array($tab_key, ['tab_auto_schema', 'tab_llm_txt'])) {
				$new_badge = (time() < strtotime('30 November 2025')) ? ' <span style="color:red;padding-left:8px;font-size:12px;">New</span>' : '';
			}
			
			echo '<a id="' . esc_attr($tab_key) . '-tab" class="siteseo-nav-tab' . esc_attr($active_class) . '" data-tab="' . esc_attr($tab_key) . '">' . esc_html($tab_caption) . wp_kses_post($new_badge) . '</a>';
		}

		echo '</div>
		<div class="siteseo-tab' .($current_tab == 'tab_siteseopro_woocommerce' ? ' active' : '') . '" id="tab_siteseopro_woocommerce" style="display: none;">';
		if(!defined('SITEPAD')){
			self::woocommerce_tab();
		}else{
			self::kkart_tab();
		}

		echo '</div>';
		if(!defined('SITEPAD')){
			echo '<div class="siteseo-tab' . ($current_tab == 'tab_siteseopro_easydigital_downloads' ? ' active' : '') . '" id="tab_siteseopro_easydigital_downloads" style="display: none;">';
			self::easy_digital_downloads_tab();
			echo '</div>';
		}
		echo '<div class="siteseo-tab' . ($current_tab == 'tab_siteseopro_pagespeed_insights' ? ' active' : '') . '" id="tab_siteseopro_pagespeed_insights" style="display: none;">';
		self::pagespeed_insights_tab();
		echo '</div>
		<div class="siteseo-tab' . ($current_tab == 'tab_siteseopro_dublin_core' ? ' active' : '') . '" id="tab_siteseopro_dublin_core" style="display: none;">';
		self::dublin_core_tab();
		echo '</div>
		<div class="siteseo-tab' . ($current_tab == 'tab_siteseopro_local_business' ? ' active' : '') . '" id="tab_siteseopro_local_business" style="display: none;">';
		self::local_business_tab();
		echo '</div>
		<div class="siteseo-tab' .($current_tab == 'tab_siteseopro_structured_data' ? ' active' : ''). '" id="tab_siteseopro_structured_data" style="display: none;">';
		self::structured_data();
		echo'</div>
		<div class="siteseo-tab' .($current_tab == 'tab_siteseopro_breadcrumbs' ? ' active' : ''). '" id="tab_siteseopro_breadcrumbs" style="display: none;">';
		self::breadcrumbs();
		echo'</div>
		<div class="siteseo-tab' .($current_tab == 'tab_siteseopro_redirect_monitor' ? ' active' : ''). '" id="tab_siteseopro_redirect_monitor" style="display: none;">';
		self::redirect_monitoring();
		echo '</div>
		<div class="siteseo-tab' .($current_tab == 'tab_siteseopro_robots_txt' ? ' active' : ''). '" id="tab_siteseopro_robots_txt" style="display: none;">';
		self::robots();
		echo'</div>';
		
		if(!defined('SITEPAD')){
			echo '<div class="siteseo-tab' .($current_tab == 'tab_siteseopro_htaccess' ? ' active' : ''). '" id="tab_siteseopro_htaccess" style="display: none;">';
			self::htaccess();
			echo'</div>';
		}
		
		echo '<div class="siteseo-tab' .($current_tab == 'tab_google_news' ? ' active' : ''). '" id="tab_google_news" style="display: none;">';
		self::google_news();
		echo '</div>
		<div class="siteseo-tab'. ($current_tab == 'tab_video_sitemap' ? ' active' : '').'" id="tab_video_sitemap" style="display : none">';
		self::video_sitemap();
		echo '</div>
		<div class="siteseo-tab'. ($current_tab =='tab_rss_sitemap' ? 'active' : '').'" id="tab_rss_sitemap" style="display:none">';
		self::rss_sitemap();
		echo'</div>
		<div class="siteseo-tab'. ($current_tab =='tab_llm_txt' ? ' active' : '').'" id="tab_llm_txt" style="display:none">';
		self::llm_txt();
		echo'</div>';
		
		if(!empty($structure_data_toggle)){
			echo'<div class="siteseo-tab'. ($current_tab =='tab_auto_schema' ? 'active' : '').'" id="tab_auto_schema" style="display:none">';
			self::schema();
			echo'</div>';
		}
		
		echo'</div>';

		siteseo_submit_button(__('Save changes', 'siteseo-pro'));
		echo '</form>
		</div>';

	}

	static function woocommerce_tab(){
		global $siteseo;

		if(!empty($_POST['submit'])){
			self::save_settings();
		}

		$options = $siteseo->pro;
		// Check if settings are enable
		$cart_page = !empty($options['woocommerce_cart_page_no_index']);
		$checkout_page = !empty($options['woocommerce_checkout_page_no_index']);
		$account_page = !empty($options['woocommerce_customer_account_page_no_index']);
		$woo_og_price = !empty($options['woocommerce_product_og_price']);
		$woo_og_currency = !empty($options['woocommerce_product_og_currency']);
		$woo_meta_generator = !empty($options['woocommerce_meta_generator']);
		$schema_output = !empty($options['woocommerce_schema_output']);
		$schema_breadcrumbs = !empty($options['woocommerce_schema_breadcrumbs_output']);
		$toggle_state_woocommerce = !empty($options['toggle_state_woocommerce']) ? $options['toggle_state_woocommerce'] : '';
		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');

		echo'<h3 class="siteseo-tabs">'.esc_html__('WooCommerce', 'siteseo-pro').'</h3>';

		Util::render_toggle('woocommerce', $toggle_state_woocommerce, $nonce);

		if(!is_plugin_active('woocommerce/woocommerce.php')){
			echo'<div class="siteseo-notice is-warning"><p>'.wp_kses_post(__('You need to enable <strong>WooCommerce</strong> to apply these settings.', 'siteseo-pro')).'</p></div>';
		}

		echo'<table class="form-table"><tbody>
			<tr><th scope="row">Cart page</th>
			<td><label for="siteseo_woocommerce_cart_page_no_index">
				<input id="siteseo_woocommerce_cart_page_no_index" name="siteseo_pro_options[woocommerce_cart_page_no_index]" type="checkbox"' . 
			  (!empty($cart_page) ? 'checked="yes"' : '') . 
			  ' value="1"/>' . 
			  esc_html__('noindex', 'siteseo-pro') . 
			'</label>
			<p class="description">' . esc_html__('If your theme or plugin displays the cart across your entire WordPress site, don\'t enable this option.', 'siteseo-pro') . '</p></td></tr>
		  
			<tr><th scope="row">Checkout page</th>
			<td><label for="siteseo_woocommerce_checkout_page_no_index">
				<input id="siteseo_woocommerce_checkout_page_no_index" name="siteseo_pro_options[woocommerce_checkout_page_no_index]" type="checkbox"' . (!empty($checkout_page) ? 'checked="yes"' : '') . ' value="1"/>' . esc_html__('noindex', 'siteseo-pro') . 
			'</label></td></tr>
		  
			<tr><th scope="row">Customer account pages</th>
			<td><label for="siteseo_woocommerce_customer_account_page_no_index">
				<input id="siteseo_woocommerce_customer_account_page_no_index" name="siteseo_pro_options[woocommerce_customer_account_page_no_index]" type="checkbox"'.(!empty($account_page) ? 'checked="yes"' : '').' value="1"/>'.esc_html__('noindex', 'siteseo-pro') . 
			'</label></td></tr>
	   
			<tr><th scope="row">OG Price</th>
			<td><label for="siteseo_woocommerce_product_og_price">
				<input id="siteseo_woocommerce_product_og_price" name="siteseo_pro_options[woocommerce_product_og_price]" type="checkbox"'.(!empty($woo_og_price) ? 'checked="yes"' : '').' value="1"/>' .esc_html__('Add product:price:amount meta for product', 'siteseo-pro') . 
			'</label>

			<div class="siteseo-styles pre"><pre>' . esc_html('<meta property="product:price:amount" content="99" />') . '</pre></div></td></tr>
	 
			<tr><th scope="row">OG Currency</th>
			<td><label for="siteseo_woocommerce_product_og_currency">
				<input id="siteseo_woocommerce_product_og_currency" name="siteseo_pro_options[woocommerce_product_og_currency]" type="checkbox"'.(!empty($woo_og_currency) ? 'checked="yes"' : '').' value="1"/>'.esc_html__('Add product:price:currency meta for product', 'siteseo-pro').
			'</label>
			<div class="siteseo-styles pre"><pre>' . esc_html('<meta property="product:price:currency" content="EUR" />') .'</pre></div></td></tr>

			<tr><th scope="row">Remove WooCommerce generator tag in your head</th>
			<td><label for="siteseo_woocommerce_meta_generator">
				<input id="siteseo_woocommerce_meta_generator" name="siteseo_pro_options[woocommerce_meta_generator]" type="checkbox"'.(!empty($woo_meta_generator) ? 'checked="yes"' : '').' value="1"/>'.esc_html__('Remove WooCommerce meta generator', 'siteseo-pro').
			'</label>
			<div class="siteseo-styles pre"><pre>' . esc_html('<meta name="generator" content="WooCommerce 7.5" />') . '</pre></div></td>

			<tr><th scope="row">Remove WooCommerce Schemas</th>
			<td><label for="siteseo_woocommerce_schema_output">
				<input id="siteseo_woocommerce_schema_output" name="siteseo_pro_options[woocommerce_schema_output]" type="checkbox"'.(!empty($schema_output) ? 'checked="yes"' : '').' value="1"/>'.esc_html__('Remove default JSON-LD structured data (WooCommerce 3+)', 'siteseo-pro') . 
			'</label>
			<p class="description">'.
				// Translators: %s is automatic product schema.
				wp_kses_post(sprintf(__('The default product schema added by WooCommerce generates errors in Google Search Console. Disable it and create your own <a href="%s">automatic product schema</a>.', 'siteseo-pro'), esc_url(admin_url('edit.php?post_type=siteseo_schemas')))) . 
			'</p></td></tr>

			<tr><th scope="row">Remove WooCommerce breadcrumbs schemas only</th>
			<td><label for="siteseo_woocommerce_schema_breadcrumbs_output">
				<input id="siteseo_woocommerce_schema_breadcrumbs_output" name="siteseo_pro_options[woocommerce_schema_breadcrumbs_output]" type="checkbox"'.(!empty($schema_breadcrumbs) ? 'checked="yes"' : '').' value="1"/>' . esc_html__('Remove default breadcrumbs JSON-LD structured data (WooCommerce 3+)', 'siteseo-pro').
			'</label><p class="description">' . esc_html__('If "Remove default JSON-LD structured data (WooCommerce 3+)" option is already checked, the breadcrumbs schema is already removed from your source code.', 'siteseo-pro') . 
			'</p></td></tr>
		</tbody></table>
		<input type="hidden" name="woocommerce_settings" value="1"/>';

	}
	
	static function kkart_tab(){
		global $siteseo;

		if(!empty($_POST['submit'])){
			self::save_settings();
		}

		$options = $siteseo->pro;
		// Check if settings are enable
		$cart_page = !empty($options['kkart_cart_page_no_index']);
		$checkout_page = !empty($options['kkart_checkout_page_no_index']);
		$account_page = !empty($options['kkart_customer_account_page_no_index']);
		$kkart_og_price = !empty($options['kkart_product_og_price']);
		$kkart_og_currency = !empty($options['kkart_product_og_currency']);
		$kkart_meta_generator = !empty($options['kkart_meta_generator']);
		$schema_output = !empty($options['kkart_schema_output']);
		$schema_breadcrumbs = !empty($options['kkart_schema_breadcrumbs_output']);
		$toggle_state_kkart = !empty($options['toggle_state_kkart']) ? $options['toggle_state_kkart'] : '';
		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');

		echo'<h3 class="siteseo-tabs">Kkart</h3>';

		Util::render_toggle('kkart', $toggle_state_kkart, $nonce);

		if(!is_plugin_active('kkart-pro/kkart-pro.php')){
			echo'<div class="siteseo-notice is-warning"><p>'.wp_kses_post(__('You need to enable <strong>Kkart</strong> to apply these settings.', 'siteseo-pro')).'</p></div>';
		}

		echo'<table class="form-table"><tbody>
			<tr><th scope="row">Cart page</th>
			<td><label for="siteseo_kkart_cart_page_no_index">
				<input id="siteseo_kkart_cart_page_no_index" name="siteseo_pro_options[kkart_cart_page_no_index]" type="checkbox"' . 
			  (!empty($cart_page) ? 'checked="yes"' : '') . 
			  ' value="1"/>' . 
			  esc_html__('noindex', 'siteseo-pro') . 
			'</label>
			<p class="description">' . esc_html__('If your theme or plugin displays the cart across your entire WordPress site, don\'t enable this option.', 'siteseo-pro') . '</p></td></tr>
		  
			<tr><th scope="row">Checkout page</th>
			<td><label for="siteseo_kkart_checkout_page_no_index">
				<input id="siteseo_kkart_checkout_page_no_index" name="siteseo_pro_options[kkart_checkout_page_no_index]" type="checkbox"' . (!empty($checkout_page) ? 'checked="yes"' : '') . ' value="1"/>' . esc_html__('noindex', 'siteseo-pro') . 
			'</label></td></tr>
		  
			<tr><th scope="row">Customer account pages</th>
			<td><label for="siteseo_kkart_customer_account_page_no_index">
				<input id="siteseo_kkart_customer_account_page_no_index" name="siteseo_pro_options[kkart_customer_account_page_no_index]" type="checkbox"'.(!empty($account_page) ? 'checked="yes"' : '').' value="1"/>'.esc_html__('noindex', 'siteseo-pro') . 
			'</label></td></tr>
	   
			<tr><th scope="row">OG Price</th>
			<td><label for="siteseo_kkart_product_og_price">
				<input id="siteseo_kkart_product_og_price" name="siteseo_pro_options[kkart_product_og_price]" type="checkbox"'.(!empty($kkart_og_price) ? 'checked="yes"' : '').' value="1"/>' .esc_html__('Add product:price:amount meta for product', 'siteseo-pro') . 
			'</label>

			<div class="siteseo-styles pre"><pre>' . esc_html('<meta property="product:price:amount" content="99" />') . '</pre></div></td></tr>
	 
			<tr><th scope="row">OG Currency</th>
			<td><label for="siteseo_kkart_product_og_currency">
				<input id="siteseo_kkart_product_og_currency" name="siteseo_pro_options[kkart_product_og_currency]" type="checkbox"'.(!empty($kkart_og_currency) ? 'checked="yes"' : '').' value="1"/>'.esc_html__('Add product:price:currency meta for product', 'siteseo-pro').
			'</label>
			<div class="siteseo-styles pre"><pre>' . esc_html('<meta property="product:price:currency" content="EUR" />') .'</pre></div></td></tr>

			<tr><th scope="row">Remove kkart generator tag in your head</th>
			<td><label for="siteseo_kkart_meta_generator">
				<input id="siteseo_kkart_meta_generator" name="siteseo_pro_options[kkart_meta_generator]" type="checkbox"'.(!empty($kkart_meta_generator) ? 'checked="yes"' : '').' value="1"/>'.esc_html__('Remove kkart meta generator', 'siteseo-pro').
			'</label>
			<div class="siteseo-styles pre"><pre>' . esc_html('<meta name="generator" content="kkart 1.0.4" />') . '</pre></div></td>

			<tr><th scope="row">Remove kkart Schemas</th>
			<td><label for="siteseo_kkart_schema_output">
				<input id="siteseo_kkart_schema_output" name="siteseo_pro_options[kkart_schema_output]" type="checkbox"'.(!empty($schema_output) ? 'checked="yes"' : '').' value="1"/>'.esc_html__('Remove default JSON-LD structured data (kkart)', 'siteseo-pro') . 
			'</label>
			<p class="description">'.
				// Translators: %s is automatic product schema.
				wp_kses_post(sprintf(__('The default product schema added by kkart generates errors in Google Search Console. Disable it and create your own <a href="%s">automatic product schema</a>.', 'siteseo-pro'), esc_url(admin_url('edit.php?post_type=siteseo_schemas')))) . 
			'</p></td></tr>

			<tr><th scope="row">Remove kkart breadcrumbs schemas only</th>
			<td><label for="siteseo_kkart_schema_breadcrumbs_output">
				<input id="siteseo_kkart_schema_breadcrumbs_output" name="siteseo_pro_options[kkart_schema_breadcrumbs_output]" type="checkbox"'.(!empty($schema_breadcrumbs) ? 'checked="yes"' : '').' value="1"/>' . esc_html__('Remove default breadcrumbs JSON-LD structured data (kkart)', 'siteseo-pro').
			'</label><p class="description">' . esc_html__('If "Remove default JSON-LD structured data (kkart)" option is already checked, the breadcrumbs schema is already removed from your source code.', 'siteseo-pro') . 
			'</p></td></tr>
		</tbody></table>
		<input type="hidden" name="kkart_settings" value="1"/>';

	}

	static function easy_digital_downloads_tab() {

		if(!empty($_POST['submit'])){
			self::save_settings();
		}

		$options = get_option('siteseo_pro_options');

		// check settings enable
		$option_og_price = isset($options['edd_product_og_price']) ? $options['edd_product_og_price'] : '';
		$option_og_currency = isset($options['edd_product_og_currency']) ? $options['edd_product_og_currency'] : '';
		$option_meta_generator = isset($options['edd_meta_generator']) ? $options['edd_meta_generator'] : '';
		$toggle_state_easy_digital = isset($options['toggle_state_easy_digital']) ? $options['toggle_state_easy_digital'] : '';

		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');

		echo'<h3 class="siteseo-tabs">'.esc_html__('Easy Digital Downloads', 'siteseo-pro').'</h3>';

		Util::render_toggle('edd', $toggle_state_easy_digital, $nonce);
		
		echo'<p>'.esc_html__('Improve Easy Digital Downloads SEO', 'siteseo-pro').'</p>';

		if(!is_plugin_active('easy-digital-downloads/easy-digital-downloads.php')){
			echo '<div class="siteseo-notice is-warning"><p>'.
			wp_kses_post(__('You need to enable <strong>Easy Digital Downloads</strong> to apply these settings.', 'siteseo-pro'))
			.'</p></div>';
		}

		echo'<table class="form-table">
			<tbody>
				<tr><th scope="row">OG price</th>
					<td><label for="siteseo_edd_product_og_price">
						<input id="siteseo_edd_product_og_price" name="siteseo_pro_options[edd_product_og_price]" type="checkbox" ' . (empty(!$option_og_price) ? 'checked="yes"' : '') . ' value="1"/>' . esc_html__('Add product:price:amount meta for product', 'siteseo-pro') .
						 '</label>' .
						'<div class="siteseo-styles pre"><pre>' . esc_html('<meta property="product:price:amount" content="99" />') . '</pre></div>
					</td>
				</tr>' .
				'<tr><th scope="row">OG Currency</th>' .
					'<td><label for="siteseo_edd_product_og_currency">' .
						'<input id="siteseo_edd_product_og_currency" name="siteseo_pro_options[edd_product_og_currency]" type="checkbox"'. (!empty($option_og_currency) ? 'checked="yes"' : '') . ' value="1"/>' . esc_html__('Add product:price:currency meta for product', 'siteseo-pro') . '</label>' .
						'<div class="siteseo-styles pre"><pre>' . esc_html('<meta property="product:price:currency" content="EUR" />') . '</pre></div>
					</td>
				</tr>' .
				'<tr>
					<th scope="row">'.esc_html__('Remove Easy Digital Downloads generator tag in your head', 'siteseo-pro').'</th>' .
					'<td><label for="siteseo_edd_meta_generator">' .
						'<input id="siteseo_edd_meta_generator" name="siteseo_pro_options[edd_meta_generator]" type="checkbox" ' . (!empty($option_meta_generator) ? 'checked="yes"' : '') . ' value="1"/>' . esc_html__('Remove EDD meta generator', 'siteseo-pro') .
						'</label>' .
					 '<div class="siteseo-styles pre"><pre>' . esc_html('<meta name="generator" content="Easy Digital Downloads v3.0" />') . '</pre></div>
					</td>
				</tr>
			</tbody>
		</table>

		<input type="hidden" name="digital_download_settings" value="1"/>';

	}

	static function pagespeed_insights_tab(){

		global $siteseo;

		if(!empty($_POST['submit'])){
			self::save_settings();	
		}

		// check settings enable
		$check_api_key = !empty($siteseo->pro['ps_api_key']) ? $siteseo->pro['ps_api_key'] : '';
		$docs = function_exists('siteseo_get_docs_links') ? siteseo_get_docs_links() : '';
 

		echo'<h3 class="siteseo-tabs">'.esc_html__('PageSpeed Insights', 'siteseo-pro').'</h3>
			<p>'.esc_html__('Check your site performance with Google PageSpeed Insights.', 'siteseo-pro').'</p>
			<p>'.esc_html__('Learn how your site has performed, based on data from your actual users around the world.', 'siteseo-pro').'</p>
		 <table class="form-table">
			<tbody>' .
				'<tr><th scope="row">'.esc_html__('Enter your own Google Page Speed API key', 'siteseo-pro').'</th>';

		echo sprintf(
			'<td><input id="siteseo_ps_api_key" type="text" name="siteseo_pro_options[ps_api_key]" aria-label="%s" placeholder="%s" value="%s">',
			esc_html__('Google Page Speed Insights API key', 'siteseo-pro'),
			esc_html__('Enter your Page Speed Insights API key', 'siteseo-pro'),
			esc_html($check_api_key)
		);

		echo '<p class="siteseo-help description">
			<span class="dashicons dashicons-external"></span>
			<a href="' . esc_url($docs['page_speed']['api']) . '" target="_blank">' . esc_html__('Learn how to create a free Google Page Speed API key', 'siteseo-pro') . '</a>
		  </p>
		  <p class="siteseo-help description">
			<span class="dashicons dashicons-external"></span>
			<a href="' . esc_url($docs['page_speed']['google']) . '" target="_blank">' . esc_html__('A Page Speed Insights key is required to avoid quota errors.', 'siteseo-pro') . '</a>
		  </p></tr>';
		  
		echo'</th></tbody></table>';

		echo '<br/><div class="siteseo-pagespeed-input-wrapper">
			<input id="siteseo_ps_url" type="text" name="siteseo_pro_options[siteseo_ps_url]" placeholder="' . esc_attr__('Enter a URL to analyse with Page Speed Insights', 'siteseo-pro') . '" value="' . esc_html(get_home_url()) . '">
			<button type="button" id="siteseopro-pagespeed-btn" class="siteseo-request-page-speed btn btnPrimary">' .
			esc_html__('Analyse performance', 'siteseo-pro') . '</button><div style="position: absolute;left:99.5%;margin-top:1%;" class="spinner"></div>
			<button type="button" id="siteseopro-clear-Page-speed-insights" class="btn btnTertiary">' .
			esc_html__('Remove last analysis', 'siteseo-pro') . '</button>
		  </div>';

		echo'<div id="siteseopro-pagespeed-results">';
		$page_speed = get_option('siteseo_pro_page_speed', []); 

		if(!empty($page_speed['mobile']) && !empty($page_speed['desktop'])){
			PageSpeed::analysis();
		}

		echo'<br/><br/></div>
		<input type="hidden" name="pagespeed_settings" value="1"/>';
	}

	static function dublin_core_tab(){
		global $siteseo;

		if(!empty($_POST['submit'])){
			self::save_settings();
		}

		$dublin_core = !empty($siteseo->pro['dublin_core_enable']);
		$toggle_state_dublin_core = !empty($siteseo->pro['toggle_state_dublin_core']) ? $siteseo->pro['toggle_state_dublin_core'] : '';
		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');

		echo'<h3 class="siteseo-tabs">'.esc_html__('Dublin Core', 'siteseo-pro').'</h3>';

		Util::render_toggle('dublin', $toggle_state_dublin_core, $nonce);

		 echo'<br/>' . esc_html__('Dublin Core is a set of meta tags to describe your content','siteseo-pro') .
		 '<br/>' . esc_html__('These tags are automatically generated. Recognized by states / governments, they are used by directories, Bing, Baidu and Yandex.','siteseo-pro') .
		 '<table class="form-table">
			<tbody>
				<tr>' .
					'<th scope="row" style="user-select: auto;">'.esc_html__('Enable Dublin Core', 'siteseo-pro').'</th>'.
					'<td><label for="siteseo_dublin_core_enable">'.
						'<input id="siteseo_dublin_core_enable" name="siteseo_pro_options[dublin_core_enable]" type="checkbox" ' .
							(!empty($dublin_core) ? 'checked="yes"' : '') . ' value="1"/>' . esc_html__('Enable Dublin Core meta tags (dc.title, dc.description, dc.source, dc.language, dc.relation, dc.subject)', 'siteseo-pro') .
						'</label>
					</td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" name="dublin_code_settings" value="1"/>';
	}
	
	static function local_business_tab(){
		global $siteseo;
		
		if(!empty($_POST['submit'])){
			self::save_settings();
		}
		
		// Time slots
		$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
	
		$hours = range('00', '23');
		$mins = ['00', '15', '30', '45', '59'];
		
		if(isset($_POST['siteseo_pro_options']) && is_array($_POST['siteseo_pro_options'])){
			$siteseo_pro_options = map_deep(wp_unslash($_POST['siteseo_pro_options']), 'sanitize_text_field');
		}
		
		$business_type['LocalBusiness'] = 'Local Business (default)';
		$business_type['AnimalShelter'] = 'Animal Shelter';
		$business_type['ChildCare'] = 'Child Care';
		$business_type['DryCleaningOrLaundry'] = 'Dry Cleaning Or Laundry';
		$business_type['EmergencyService'] = 'Emergency Service';
		$business_type['FireStation'] = '|-FireStation';
		$business_type['Hospital'] = '|-Hospital';
		$business_type['PoliceStation'] = '|-Police Station';
		$business_type['EmploymentAgency'] = 'Employment Agency';
		$business_type['Entertainment Business'] = 'Entertainment Business';
		$business_type['AdultEntertainment'] = '|-AdultEntertainment';
		$business_type['AmusementPark'] = '|-Amusement Park';
		$business_type['ArtGallery'] = '|-Art Gallery';
		$business_type['Casino'] = '|-Casino';
		$business_type['ComedyClub'] = '|-Comedy Club';
		$business_type['MovieTheater'] = '|-Movie Theater';
		$business_type['NightClub'] = '|-Night Club';
		$business_type['FinancialService'] = '|-Financial Service';
		$business_type['AccountingService'] = '|-Accounting Service';
		$business_type['AutomatedTeller'] = '|-Automated Teller';
		$business_type['BankOrCreditUnion'] = '|-Bank Or CreditUnion';
		$business_type['InsuranceAgency'] = '|-Insurance Agency';
		$business_type['FoodEstablishment'] = 'Food Establishment';
		$business_type['Bakery'] = '|-Bakery';
		$business_type['BarOrPub'] = '|-Bar Or Pub';
		$business_type['Brewery'] = 'Brewery';
		$business_type['CafeOrCoffeeShop'] = '|-Cafe Or CoffeeShop';
		$business_type['FastFoodRestaurant'] = '|-Fast Food Restaurant';
		$business_type['IceCreamShop'] = '|-Ice Cream Shop';
		$business_type['Restaurant'] = '|-Restaurant';
		$business_type['Winery'] = '|-Winery';
		$business_type['GovernmentOffice'] = 'Government Office';
		$business_type['PostOffice'] = '|-PostOffice';
		$business_type['HealthAndBeautyBusiness'] = 'Health And Beauty Business';
		$business_type['BeautySalon'] = '|-Beauty Salon';
		$business_type['DaySpa'] = '|-DaySpa';
		$business_type['HairSalon'] = '|-Hair Salon';
		$business_type['HealthClub'] = '|-Health Club';
		$business_type['NailSalon'] = '|-Nail Salon';
		$business_type['TattooParlor'] = '|-Tattoo Parlor';
		$business_type['HomeAndConstructionBusiness'] = '|-Home And Construction Business';
		$business_type['Electrician'] = '|-Electrician';
		$business_type['HVACBusiness'] = '|-HVAC Business';
		$business_type['HousePainter'] = '|-House Painter';
		$business_type['Locksmith'] = '|-Locksmith';
		$business_type['MovingCompany'] = '|-MovingCompany';
		$business_type['Plumber'] = '|-Plumber';
		$business_type['RoofingContractor'] = '|-Roofing Contractor';
		$business_type['InternetCafe'] = '|-Internet Cafe';
		$business_type['MedicalBusiness'] = '|-Medical Business';
		$business_type['CommunityHealth'] = '|-Community Health';
		$business_type['Dentist'] = '|-Dentist';
		$business_type['Dermatology'] = '|-Dermatology';
		$business_type['DietNutrition'] = '|-Diet Nutrition';
		$business_type['Emergency'] = '|-Emergency';
		$business_type['Gynecologic'] = '|-Gynecologic';
		$business_type['MedicalClinic'] = '|-MedicalClinic';
		$business_type['Midwifery'] = '|-Midwifery';
		$business_type['Nursing'] = '|-Nursing';
		$business_type['Obstetric'] = '|-Obstetric';
		$business_type['Oncologic'] = '|-Oncologic';
		$business_type['Optician'] = '|-Optician';
		$business_type['Otolaryngologic'] = '|-Otolaryngologic';
		$business_type['Pediatric'] = '|-Pediatric';
		$business_type['Pharmacy'] = '|-Pharmacy';
		$business_type['Physiotherapy'] = '|-Physiotherapy';
		$business_type['PlasticSurgery'] = '|-PlasticSurgery';
		$business_type['Podiatric'] = '|-Podiatric';	
		$business_type['PrimaryCare'] = '|-PrimaryCare';
		$business_type['Psychiatric'] = '|-Psychiatric';
		$business_type['PublicHealth'] = '|-PublicHealth';
		$business_type['VeterinaryCare'] = '|-VeterinaryCare';
		$business_type['LegalService'] = '|-LegalService';	
		$business_type['Attorney'] = '|-Attorney';	
		$business_type['Notary'] = '|-Notary';
		$business_type['Library'] = 'Library';	
		$business_type['LodgingBusiness'] = 'LodgingBusiness';
		$business_type['BedAndBreakfast'] = '|-Bed And Breakfast';
		$business_type['Campground'] = '|-Campground';
		$business_type['Hostel'] = '|-Hostel';
		$business_type['Hotel'] = '|-Hotel';
		$business_type['Motel'] = '|-Motel';
		$business_type['Resort'] ='|-Resort';
		$business_type['ProfessionalService'] ='Professional Service';
		$business_type['RadioStation'] ='Radio Station';
		$business_type['RealEstateAgent'] ='Real Estate Agent';
		$business_type['RecyclingCenter'] ='Recycling Center';
		$business_type['SelfStorage'] ='Real Self Storage';
		$business_type['ShoppingCenter'] ='ShoppingCenter';
		$business_type['SportsActivityLocation'] ='Sports Activity Location';	
		$business_type['BowlingAlley'] ='|-Bowling Alley';
		$business_type['ExerciseGym'] = '|-Exercise Gym';
		$business_type['GolfCourse'] = '|-Golf Course';
		$business_type['HealthClub'] = '|-HealthClub';
		$business_type['PublicSwimmingPool'] = '|-Public Swimming Pool';
		$business_type['SkiResort'] = '|-Ski Resort';
		$business_type['SportsClub'] = '|-Sports Club';
		$business_type['StadiumOrArena'] = '|-Stadium Or Arena';
		$business_type['TennisComplex'] = '|-Tennis Complex';
		$business_type['Store'] = '|-Store';
		$business_type['AutoPartsStore'] = '|-Auto Parts Store';
		$business_type['BikeStore'] = '|-Bike Store';
		$business_type['BookStore'] = '|-Book Store';
		$business_type['ClothingStore'] = '|-Clothing Store';
		$business_type['ComputerStore'] = '|-Computer Store';
		$business_type['ConvenienceStore'] = '|-Convenience Store';
		$business_type['DepartmentStore'] = '|-Department Store';
		$business_type['ElectronicsStore'] = '|-Electronics Store';
		$business_type['Florist'] = '|-Florist';
		$business_type['FurnitureStore'] = '|-Furniture Store';
		$business_type['GardenStore'] = '|-Garden Store';
		$business_type['GroceryStore'] = '|-Grocery Store';
		$business_type['HardwareStore'] = '|-Hardware Store';
		$business_type['HobbyShop'] = '|-Hobby Shop';
		$business_type['HomeGoodsStore'] = '|-Home Goods Store';
		$business_type['JewelryStore'] = '|-Jewelry Store';
		$business_type['LiquorStore'] = '|-Liquor Store';
		$business_type['MensClothingStore'] = '|-Mens Clothing Store';
		$business_type['MobilePhoneStore'] = '|-Mobile Phone Store';
		$business_type['MovieRentalStore'] = '|-Movie Rental Store';
		$business_type['MusicStore'] = '|-Music Store';
		$business_type['OfficeEquipmentStore'] = '|-Office Equipment Store';
		$business_type['OutletStore'] = '|-Outlet Store';
		$business_type['PawnShop'] = '|-Pawn Shop';
		$business_type['PetStore'] = '|-PetStore';
		$business_type['ShoeStore'] = '|-Shoe Store';
		$business_type['SportingGoodsStore'] = '|-Sporting Goods Store';
		$business_type['TireShop'] = '|-Tire Shop';
		$business_type['ToyStore'] = '|-Toy Store';
		$business_type['WholesaleStore'] = '|-Whole sale Store';
		$business_type['TelevisionStation'] = '|-Whole sale Store';
		$business_type['TouristInformationCenter'] = 'Tourist Information Center';
		$business_type['TravelAgency'] = 'Travel Agency';
		$business_type['AutomotiveBusiness'] = 'Automotive Business';
		$business_type['AutoBodyShop'] = '|-Auto Body Shop';
		$business_type['AutoDealer'] = '|-Auto Dealer';
		$business_type['AutoPartsStore'] = '|-Auto Parts Store';
		$business_type['AutoRental'] = '|-Auto Rental';
		$business_type['AutoRepair'] = '|-Auto Repair';
		$business_type['AutoWash'] = '|-AutoWash';
		$business_type['GasStation'] = '|-Gas Station';
		$business_type['MotorcycleDealer'] = '|-Motorcycle Dealer';
		$business_type['MotorcycleRepair'] = '|-MotorcycleRepair';
		
		// Get saved settings
		$options = $siteseo->pro;
		
		$display_schema = isset($options['local_business_display_schema']) ? esc_attr($options['local_business_display_schema']) : '';
		$set_street_address = isset($options['street_address']) ? esc_attr($options['street_address']): '';
		$set_city = isset($options['city']) ? esc_attr($options['city']): ''; 
		$set_state = isset($options['state']) ? esc_attr($options['state']): ''; 
		$set_postal_code = isset($options['postal_code']) ? esc_attr($options['postal_code']) : '' ; 
		$set_country = isset($options['country']) ? esc_attr($options['country']): '';
		$set_latitude = isset($options['latitude'])? esc_attr($options['latitude']): ''; 
		$set_longitude = isset($options['longitude']) ? esc_attr($options['longitude']) : '';
		$set_place_id = isset($options['place_id']) ? esc_attr($options['place_id']) : '';
		$set_url = isset($options['url']) ? esc_attr($options['url']) : '';
		$set_telephone = isset($options['telephone']) ? esc_attr($options['telephone']): '';
		$set_price_range = isset($options['price_range']) ? esc_attr($options['price_range']) : '';
		$set_cuisine_served = isset($options['cuisine_served'])? esc_attr($options['cuisine_served']) : '';
		$set_accepts_reser = isset($options['accepts_reser']) ? esc_attr($options['accepts_reser']) : '';
		$set_business_type = isset($options['business_type']) ? esc_attr($options['business_type']) : 'LocalBusiness';
		$toggle_state_local_buz = isset($options['toggle_state_local_buz']) ? esc_html($options['toggle_state_local_buz']): '';
		
		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');

		$docs = siteseo_get_docs_links();

		// Display the settings form
		echo'<h3 class="siteseo-tabs">'.esc_html__('Local Business', 'siteseo-pro').'</h3>';

		Util::render_toggle('local', $toggle_state_local_buz, $nonce);

		echo'<p>'.esc_html__('Local Business data type for Google', 'siteseo-pro').'</p>
		
		<form method="post" action="">';
		wp_nonce_field('sitseo_pro_settings', 'sitseo_pro_settings_nonce');
	
		echo'<table class="form-table">
			<tbody>
				<tr>
					<th scope="row" style="user-select: auto;">Business type</th>
				<td>
					<select id="siteseo_pro_rich_snippets_lb_type" name="siteseo_pro_options[business_type]">';
					
					foreach ($business_type as $type_value => $type_i18n) {
						$selected = ($type_value == $set_business_type) ? 'selected' : '';
						echo '<option value="'.esc_attr($type_value).'" '.esc_attr($selected).'>'.esc_html($type_i18n).'</option>';
					}
		
					echo'</select>
						</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">Street Address</th>
					<td><input type="text" name="siteseo_pro_options[street_address]" placeholder="eg: Place Bellevue" value="'.esc_attr($set_street_address).'">
						<p class="description">' . sprintf('<span class="field-required">%s</span> %s.', esc_html__('Required', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
		
				<tr><th scope="row" style="user-select: auto;">City</th>
					<td><input type="text" name="siteseo_pro_options[city]" placeholder="Biarritz" value="'.esc_attr($set_city).'">
						<p class="description">' . sprintf('<span class="field-required">%s</span> %s.', esc_html__('Required', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">State</th>
					<td><input type="text" name="siteseo_pro_options[state]" placeholder="eg: Nouvelle Aquitaine" value="'.esc_attr($set_state).'">
						<p class="description">' . sprintf('<span class="field-required">%s</span> %s.', esc_html__('Required', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">Postal code</th>
					<td><input type="text" name="siteseo_pro_options[postal_code]" placeholder="eg: 64200" value="'.esc_attr($set_postal_code).'">
						<p class="description">' . sprintf('<span class="field-required">%s</span> %s.', esc_html__('Required', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">Country</th>
					<td><input type="text" name="siteseo_pro_options[country]" placeholder="eg: France" value="'.esc_attr($set_country).'">
						<p class="description">' . sprintf('<span class="field-required">%s</span> %s.', esc_html__('Required', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">Latitude</th>
					<td><input type="text" name="siteseo_pro_options[latitude]" placeholder="eg: 43.4831389" value="'.esc_attr($set_latitude).'">
						 <p class="description">' . sprintf('<span class="field-recommended">%s</span> %s.', esc_html__('Recommended', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">Longitude</th>
					<td><input type="text" name="siteseo_pro_options[longitude]" placeholder="eg: -1.5630987" value="'.esc_attr($set_longitude).'">
						<p class="description">' . sprintf('<span class="field-recommended">%s</span> %s.', esc_html__('Recommended', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">Place ID</th>
					<td><input type="text" name="siteseo_pro_options[place_id]" placeholder="eg: Biarrit" value="'.esc_attr($set_place_id).'">
						<p class="description">' . wp_kses_post(__('<a href="https://developers.google.com/places/web-service/place-id" target="_blank">Click here to find your Google Maps Place ID</a><span class="siteseo-help dashicons dashicons-external"></span> for your Local Business. <br>This ID will be used to display the Google Maps link from the LB widget.', 'siteseo-pro')) . '</p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">URL</th>
					<td><input type="text" name="siteseo_pro_options[url]" placeholder="default:'.esc_url(get_home_url()).'" value="'.esc_attr($set_url).'">
						<p class="description"> '. esc_html__('Default: homepage. Google recommends to include your business details (address, phone, website...) for your visitors too.', 'siteseo-pro') .' </p>
						<p class="description">' . sprintf('<span class="field-recommended">%s</span> %s.', esc_html__('Recommended', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">Telephone</th>
					<td><input type="text" name="siteseo_pro_options[telephone]" placeholder="eg: +33559240138" value="'.esc_attr($set_telephone).'">
						<p class="description">' . sprintf('<span class="field-recommended">%s</span> %s.', esc_html__('Recommended', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>            
				
				<tr><th scope="row" style="user-select: auto;">Price range</th>
					<td><input type="text" name="siteseo_pro_options[price_range]" placeholder="eg: $$, €€€, or ££££..." value="'.esc_attr($set_price_range).'">
						<p class="description">' . sprintf('<span class="field-recommended">%s</span> %s.', esc_html__('Recommended', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto;">'.esc_html__('Cuisine served', 'siteseo-pro').'</th>
					<td><input type="text" name="siteseo_pro_options[cuisine_served]" placeholder="French, Mediterranean, or American" value="'.esc_attr($set_cuisine_served).'">
						<p class="description"> '. esc_html__('Only to be filled if the business type is: "FoodEstablishment", "Bakery", "BarOrPub", "Brewery", "CafeOrCoffeeShop", "FastFoodRestaurant", "IceCreamShop", "Restaurant" or "Winery".', 'siteseo-pro').' </p>
						<p class="description">' . sprintf('<span class="field-recommended">%s</span> %s.', esc_html__('Recommended', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>            
				
				<tr><th scope="row" style="user-select: auto;">'.esc_html__('Accepts Reservations', 'siteseo-pro').'</th>
					<td><input type="text" name="siteseo_pro_options[accepts_reser]" placeholder="eg.True" value="'.esc_attr($set_accepts_reser).'">
						<p class="description"> '. esc_html__('Indicates whether a FoodEstablishment accepts reservations. Values can be Boolean (True or False), an URL at which reservations can be made or (for backwards compatibility) the strings Yes or No.', 'siteseo-pro').' </p>
						<p class="description">' . sprintf('<span class="field-recommended">%s</span> %s.', esc_html__('Recommended', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p>
					</td>
				</tr>
			
				<tr><th scope="row" style="user-select: auto;">Opening hours</th>    
					<td><div class="siteseo-notice-pro">
						<p>' . wp_kses_post(__('<strong>Morning and Afternoon are just time slots</strong>', 'siteseo-pro')) . '</p>
						<p>' . esc_html__('Eg: if you\'re opened from 10:00 AM to 9:00 PM, check Morning and enter 10:00 / 21:00.', 'siteseo-pro') . '</p>
						<p>' . esc_html__('If you are open non-stop, check Morning and enter 0:00 / 23:59.', 'siteseo-pro') . '</p>
						</div>
					
					<ul style="list-style-type: none;">';
					
					foreach($days as $key => $day) {

						$is_closed = !empty($options['opening_hours'][$key]['closed']) ? $options['opening_hours'][$key]['closed'] : 0;
						$open_morning = !empty($options['opening_hours'][$key]['open_morning']) ? $options['opening_hours'][$key]['open_morning'] : 0;
						$open_afternoon = !empty($options['opening_hours'][$key]['open_afternoon']) ? $options['opening_hours'][$key]['open_afternoon'] : 0;
						
						// Get saved start and end times
						$open_morning_start_hour = !empty($options['opening_hours'][$key]['open_morning_start_hour']) ? $options['opening_hours'][$key]['open_morning_start_hour'] : '';
						$open_morning_start_min = !empty($options['opening_hours'][$key]['open_morning_start_min']) ? $options['opening_hours'][$key]['open_morning_start_min'] : '';
	
						$open_morning_end_hour = !empty($options['opening_hours'][$key]['open_morning_end_hour']) ? esc_attr($options['opening_hours'][$key]['open_morning_end_hour']) : '';
						$open_morning_end_min = !empty($options['opening_hours'][$key]['open_morning_end_min']) ? esc_attr($options['opening_hours'][$key]['open_morning_end_min']) : '';
						
						$open_afternoon_start_hour = !empty($options['opening_hours'][$key]['open_afternoon_start_hour']) ? esc_attr($options['opening_hours'][$key]['open_afternoon_start_hour']) : '';
						$open_afternoon_start_min = !empty($options['opening_hours'][$key]['open_afternoon_start_min']) ? esc_attr($options['opening_hours'][$key]['open_afternoon_start_min']) : '';
						$open_afternoon_end_hour = !empty($options['opening_hours'][$key]['open_afternoon_end_hour']) ? esc_attr($options['opening_hours'][$key]['open_afternoon_end_hour']) : '';
						$open_afternoon_end_min = !empty($options['opening_hours'][$key]['open_afternoon_end_min']) ? esc_attr($options['opening_hours'][$key]['open_afternoon_end_min']) : '';
	
						echo'<li><h3>' . esc_html($day) . '</h3></li><hr><br>
						<input type="checkbox"'.(!empty($is_closed) ? 'checked="yes"' : '').' name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][closed]" value="1"> Closed all the day?</input><br /><br />
						<input type="checkbox"'.(!empty($open_morning) ? 'checked="yes"' : '').' name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][open_morning]" value="1">Open in the morning?</input><br /><br />
						<div style="display:flex;">
						<select style="width:auto;" name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][open_morning_start_hour]">';

						foreach($hours as $hour){
							$selected = ($hour == $open_morning_start_hour) ? 'selected' : '';
							echo '<option value="' . esc_attr($hour) . '" ' . esc_attr($selected) . '>' . esc_html($hour) . '</option>';
						}
						
						echo '</select>:<select style="width:auto;" name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][open_morning_start_min]">';

						foreach($mins as $min){
							$selected = ($min == $open_morning_start_min) ? 'selected' : '';
							echo '<option value="' . esc_attr($min) . '" ' . esc_attr($selected) . '>' . esc_html($min) . '</option>';
						}
						
						echo '</select>-<select style="width:auto;" name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][open_morning_end_hour]">';
						
						foreach($hours as $hour){
							$selected = ($hour == $open_morning_end_hour) ? 'selected' : '';
							echo '<option value="'. esc_attr($hour) .'" '.esc_attr($selected).'>'.esc_html($hour).'</option>';
						}
						
						echo '</select>:<select style="width:auto;" name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][open_morning_end_min]">';
						
						foreach($mins as $min){
							$selected = ($min == $open_morning_end_min) ? 'selected' : '';
							echo '<option value="' . esc_attr($min) . '" ' . esc_attr($selected) . '>' . esc_html($min) . '</option>';
						}
		
						echo '</select><br><br></div>';
				
						echo'<br><input type="checkbox"'.(!empty($open_afternoon) ? 'checked="yes"' : '').' name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][open_afternoon]" value="1">Open in the afternoon?</input><br /><br />
						 <div style="display:flex;"><select style="width:auto;" name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][open_afternoon_start_hour]">';
							
							// Get saved start and end times	
							foreach($hours as $hour){
								$selected = ($hour == $open_afternoon_start_hour) ? 'selected' : '';
								echo '<option value="' . esc_attr($hour) . '" ' . esc_attr($selected) . '>' . esc_html($hour) . '</option>';
							}
							
							echo'</select>:<select style="width:auto;" name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][open_afternoon_start_min]">';
								foreach($mins as $min){
									$selected = ($min == $open_afternoon_start_min) ? 'selected' : '';
									echo '<option value="' . esc_attr($min) . '" ' . esc_attr($selected) . '>' . esc_html($min) . '</option>';
								}
	
							echo'</select>-<select style="width:auto;" name="siteseo_pro_options[opening_hours]['.esc_html($key).'][open_afternoon_end_hour]">';
								foreach($hours as $hour){
								   $selected = ($hour == $open_afternoon_end_hour) ? 'selected' : '';
									echo '<option value="' . esc_attr($hour) . '" ' . esc_attr($selected) . '>' . esc_html($hour) . '</option>';
								}
								
							echo'</select>:<select style="width:auto;" name="siteseo_pro_options[opening_hours]['.esc_attr($key).'][open_afternoon_end_min]">';
								foreach($mins as $min){
									$selected = ($min == $open_afternoon_end_min) ? 'selected' : '';
									echo '<option value="' . esc_attr($min) . '" ' . esc_attr($selected) . '>' . esc_attr($min) . '</option>';
								}

							echo'</select></div>';

						}
						echo '<br><br><p class="description">' . sprintf('<span class="field-recommended">%s</span> %s.', esc_html__('Recommended', 'siteseo-pro'), esc_html__('property by Google', 'siteseo-pro')) . '</p></td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" name="local_business_settings" value="1"/>';
	}


	static function structured_data(){
		global $siteseo;

		if(!empty($_POST['sumbit'])){
			self::save_settings();
		}

		$options = $siteseo->pro;

		//load settings
		$toggle_state_stru_data = isset($options['toggle_state_stru_data']) ? $options['toggle_state_stru_data'] : '';
		$option_set_enable_structured_data = isset($options['enable_structured_data']) ? $options['enable_structured_data'] : '';
		$option_logo_url = isset($options['structured_data_image_url']) ? $options['structured_data_image_url'] : '';
		$option_desciption = isset($options['org_desciption']) ? $options['org_desciption'] : '';
		$option_email_id = isset($options['org_email']) ? $options['org_email'] : '';
		$option_phone = isset($options['org_phone_no']) ? $options['org_phone_no'] : '';
		$option_legal_name = isset($options['org_legal']) ? $options['org_legal'] : '';
		$option_establish_date = isset($options['establish_date']) ? $options['establish_date'] : '';
		$option_number_emp = isset($options['number_emp']) ? $options['number_emp'] : '';
		$option_vat_id = isset($options['vat_id']) ? $options['vat_id'] : '';
		$option_tax_id = isset($options['tax_id']) ? $options['tax_id'] : ''; 
		$option_iso_id = isset($options['iso_code']) ? $options['iso_code'] : '';
		$option_let_code = isset($options['let_code']) ? $options['let_code'] : '';
		$option_duns_number = isset($options['duns_number']) ? $options['duns_number'] : '';
		$option_naics_code = isset($options['naics_code']) ? $options['naics_code'] : '';

		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');
		
		echo'<h3 class="siteseo-tabs">'.esc_html__('Structured Data Types (schema.org)', 'siteseo-pro').'</h3>';

		Util::render_toggle('structured', $toggle_state_stru_data, $nonce);

		echo'<table class="form-table">
			<tbody>
				<tr>
					<th scope="row" style="user-select: auto;">Enable Structured Data</th>
					<td>
						<label for="siteseo_structured_data">
						<input id="siteseo_structured_data" name="siteseo_pro_options[enable_structured_data]" type="checkbox" ' . (empty(empty($option_set_enable_structured_data)) ? 'checked="yes"' : '') .' value="1"/>' .
						esc_html__('Enable Structured Data Types metabox for your posts, pages and custom post types', 'siteseo-pro') .'</label>
					</td>
				</tr>
				<tr>
					<th scope="row" style="user-select: auto">Upload your publisher logo</th>
					<td>
						<input id="structured_data_image_url" autocomplete="off" type="text" value="'. esc_url($option_logo_url) .'" name="siteseo_pro_options[structured_data_image_url]" aria-label="'. esc_html__('Upload your publisher logo', 'siteseo-pro').'" placeholder="'. esc_html__('Select your logo', 'siteseo-pro') .'" />	
						<input id="siteseopro_structured_data_upload_img" class="btn btnSecondary" type="button" value="'. esc_html__('Upload an Image', 'siteseo-pro') .'" />
					</td>
				</tr>

				<tr><th scope="row" style="user-select:auto"></th>
					<td>
						<p><img style="width:auto;height:auto;max-width:560px;margin-top:15px;display:inline-block;" src="'.esc_url($option_logo_url) .'" /></p>
					</td>
				</tr>
				
				<tr><th scope="row" style="user-select: auto"></th>
					<td>
						<div class="siteseo-notice-pro">
							<h3> '. esc_html__('Make sure your image follow these Google guidelines', 'siteseo-pro').' </h3>
							<ul>
								<li> '. esc_html__('A logo that is representative of the organization.', 'siteseo-pro').' </li>
								<li> '. esc_html__('Files must be BMP, GIF, JPEG, PNG, WebP or SVG.', 'siteseo-pro') .' </li>
								<li> '. esc_html__('The image must be 112x112px, at minimum.', 'siteseo-pro') .' </li>
								<li> '. esc_html__('The image URL must be crawlable and indexable.', 'siteseo-pro') .' </li>
								<li> '. esc_html__('Make sure the image looks how you intend it to look on a purely white background (for example, if the logo is mostly white or gray, it may not look how you want it to look when displayed on a white background).', 'siteseo-pro') .' </li>
							</ul>
							<p>
								<span class="siteseo-help dashicons dashicons-external"></span>
								<a class="siteseo-help" href="https://developers.google.com/search/docs/appearance/structured-data/logo#structured-data-type-definitions" target="_blank"> '. esc_html__('Learn more', 'siteseo-pro') .' </a>
							</p>
						</div>
					</td>
				</tr>
				
				<tr>
					<th scope="row" style="user-select:auto;">'.esc_html__('Organization Legal Name', 'siteseo-pro').'</th>
					<td>
						<input type="text" name="siteseo_pro_options[org_legal]" placeholder="'.esc_attr__('Enter Organizations legal name', 'siteseo-pro').'" value="'.esc_attr($option_legal_name).'">
						<p class="description">' . esc_html__('The official registered name of your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select: auto;">'.esc_html__('Organization Description', 'siteseo-pro').'</th>
					<td>
						<input type="text" name="siteseo_pro_options[org_desciption]" placeholder="'.esc_attr__('Enter Organizations description', 'siteseo-pro').'" value="'.esc_attr($option_desciption).'">
						<p class="description">' . esc_html__('A brief description of your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">'.esc_html__('Organization Email Address', 'siteseo-pro').' </th>
					<td>
						<input type="text" name="siteseo_pro_options[org_email]" placeholder="'.esc_attr__('Enter Organizations email Id', 'siteseo-pro').'" value="'.esc_attr($option_email_id).'">
						<p class="description">' . esc_html__('The primary email address for your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">'.esc_html__('Organization Phone Number', 'siteseo-pro').'</th>
					<td>
						<input type="text" name="siteseo_pro_options[org_phone_no]" placeholder="'.esc_attr__('Enter Organizations contact number', 'siteseo-pro').'" value="'.esc_attr($option_phone).'">
						<p class="description">' . esc_html__('The main contact number for your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">'.esc_html__('Organization Establish Date', 'siteseo-pro').'</th>
					<td>
						<input type="date" id="org_establish_date" style="width: 560px; height: 40px;"  name="siteseo_pro_options[establish_date]" placeholder="Select Organization establish date" value="'.esc_attr($option_establish_date).'">
						<p class="description">' . esc_html__('The date when your organization was established.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">Number Of Employees</th>
					<td>
						<input type="text" name="siteseo_pro_options[number_emp]" placeholder="Enter Organizations legal name" value="'.esc_attr($option_number_emp).'">
						<p class="description">' . esc_html__('The total number of employees in your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;"><h2>Organization Identifiers</h2><br/></th>
					<td>
						<p class="description">' . esc_html__('We would like to know more about your organization’s identifiers. This information will assist Google in providing accurate and relevant details about your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">VAT ID</th>
					<td>
						<input type="text" name="siteseo_pro_options[vat_id]" placeholder="Enter Organization VAT ID" value="'.esc_attr($option_vat_id).'">
						<p class="description">' . esc_html__('The VAT identification number for your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">Tax ID</th>
					<td>
						<input type="text" name="siteseo_pro_options[tax_id]" placeholder="Enter Organization Tax ID" value="'.esc_attr($option_tax_id).'">
						<p class="description">' . esc_html__('The tax identification number for your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">ISO 6523</th>
					<td>
						<input type="text" name="siteseo_pro_options[iso_code]" placeholder="Enter Organization ISO 6523" value="'.esc_attr($option_iso_id).'">
						<p class="description">' . esc_html__('The ISO 6523	identification number for your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">LEI Code</th>
					<td>
						<input type="text" name="siteseo_pro_options[let_code]" placeholder="'.esc_html__('Enter Organization LEI Code', 'siteseo-pro').'" value="'.esc_attr($option_let_code).'">
						<p class="description">' . esc_html__('The LET identification number for your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">DUNS</th>
					<td>
						<input type="text" name="siteseo_pro_options[duns_number]" placeholder="'.esc_html__('Enter Organization DUNS code', 'siteseo-pro').'" value="'.esc_attr($option_duns_number).'">
						<p class="description">' . esc_html__('The DUNS identification number for your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>

				<tr>
					<th scope="row" style="user-select:auto;">NAICS</th>
					<td>
						<input type="text" name="siteseo_pro_options[naics_code]" placeholder="'.esc_html__('Enter Organization NAICS', 'siteseo-pro').'" value="'.esc_attr($option_naics_code).'">
						<p class="description">' . esc_html__('The NAICS identification number for your organization.', 'siteseo-pro') . '</p>
					</td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" name="structured_data_settings" value="1"/>';
	}


	static function breadcrumbs(){
		global $siteseo;
	
		if(!empty($_POST['submit'])){
			self::save_settings();
		}
		
		$options = $siteseo->pro;
		$advanced_option = get_option('siteseo_advanced_option_name');
		$options = isset($options['breadcrumbs_enable']) ? $options : $advanced_option;
		
		$enabled = !empty($options['breadcrumbs_enable']) ? $options['breadcrumbs_enable'] : '';
		$separators = ['-', '|', '/', '←', '→', '↠', '⇒', '►', '—', '•', '»', '›', '–'];
		$separator = !empty($options['breadcrumbs_seperator']) ? $options['breadcrumbs_seperator'] : '';
		$custom_separator = !empty($options['breadcrumbs_custom_seperator']) ? $options['breadcrumbs_custom_seperator'] : '';
		$hide_home = isset($options['breadcrumbs_home']) ? $options['breadcrumbs_home'] : false;
		$home_label = !empty($options['breadcrumb_home_label']) ? $options['breadcrumb_home_label'] : __('Home', 'siteseo-pro');
		$prefix = !empty($options['breadcrumb_prefix']) ? $options['breadcrumb_prefix'] : '';

		
		echo'<h3 class="siteseo-tabs">' . esc_html__('Breadcrumbs', 'siteseo-pro') . '</h3>
		<p>'.esc_html__('Breadcrumbs work as a navigation tool for users, helping them know their current location and providing quick links to their previous browsing path, which improves the user experience.', 'siteseo-pro').'</p>

		<table class="form-table">
			<tr>
				<th scope="row">'.esc_html__('Enable Breadcrumbs', 'siteseo-pro').'</th>
				<td>
					<label>
						<input type="checkbox" value="1" id="siteseo_breadcrumbs_enable" name="siteseo_pro_options[breadcrumbs_enable]" ' . checked($enabled, true, false) . '/>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">'.esc_html__('Breadcrumbs Display Methods', 'siteseo-pro').'</th>
				<td>
					<div class="siteseo-inner-tabs-wrap">';
						if(!defined('SITEPAD')){
							echo '<input type="radio" id="siteseo-breadcrumbs-gutenberg" name="siteseo-inner-tabs" checked>';
						}
						echo '<input type="radio" id="siteseo-breadcrumbs-shortcode" name="siteseo-inner-tabs" ' . (defined('SITEPAD') ? 'checked' : '') . '>';
						echo '<input type="radio" id="siteseo-breadcrumbs-php" name="siteseo-inner-tabs">
						<ul class="siteseo-inner-tabs">';
						if(!defined('SITEPAD')){
						echo '<li class="siteseo-inner-tab"><label for="siteseo-breadcrumbs-gutenberg"><span class="dashicons dashicons-block-default"></span>'.esc_html__('Gutenberg Blocks', 'siteseo-pro').'</label></li>';
						}
						echo '<li class="siteseo-inner-tab"><label for="siteseo-breadcrumbs-shortcode"><span class="dashicons dashicons-shortcode"></span>'.esc_html__('Shortcode', 'siteseo-pro').'</label></li>';
						echo '<li class="siteseo-inner-tab"><label for="siteseo-breadcrumbs-php"><span class="dashicons dashicons-editor-code"></span>'.esc_html__('PHP Code', 'siteseo-pro').'</label></li>
						</ul>';
						if(!defined('SITEPAD')){
							echo '<div class="siteseo-inner-tab-content">
								<h4>'.esc_html__('Gutenberg Block', 'siteseo-pro').'</h4>
								<p>'.esc_html__('Generate Block can be accessed by going to edit post using the Gutenberg Editor, the default editor of WordPress. There search for Breadcrumbs block.', 'siteseo-pro').'</p>
							</div>';
						}
						
						echo '<div class="siteseo-inner-tab-content">
							<h4>'.esc_html__('Shortcode', 'siteseo-pro').'</h4>
							<p>'.esc_html__('WordPress shortcodes are shortcuts ([shortcode]) that insert features without coding. You can use these shortcodes with Classic Editor, Gutenberg, or any other editor. Copy the shortcode below and use it in the editor.', 'siteseo-pro').'</p>
							<pre>[siteseo_breadcrumbs]</pre>
						</div>
						
						<div class="siteseo-inner-tab-content">
							<h4>'.esc_html__('PHP Code', 'siteseo-pro').'</h4>
							<p>'.esc_html__('You can add the breadcrumbs by directly adding PHP code. Make sure you are aware of what you are doing. Use the code below anywhere in your theme.', 'siteseo-pro').'</p>
							<pre>'.esc_html("<?php if(function_exists('siteseo_render_breadcrumbs')){ echo siteseo_render_breadcrumbs(); } ?>").'</pre>
						</div>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">'.esc_html__('Separator', 'siteseo-pro').'</th>
				<td>
					<div class="siteseo_breadcrumbs_seperator_callback">
					   <div class="siteseo-seperator-btns">';
						foreach($separators as $sep){
							$checked = ($separator == $sep) ? 'checked' : '';
							echo '<label>
								<input type="radio" name="siteseo_pro_options[breadcrumbs_seperator]" value="' . esc_attr($sep) . '" '.esc_attr($checked).'/>
								'.esc_html($sep).'</label>';
						}
						echo '</div>
						<input type="text" style="width:200px" name="siteseo_pro_options[breadcrumbs_custom_seperator]" placeholder="'.esc_html__('Custom Separator', 'siteseo-pro').'" value="'.esc_attr($custom_separator).'"/>
					</div>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">'.esc_html__('Home Settings', 'siteseo-pro').'</th>
				<td>
					<div>
						<label style="margin:10px 0;">
							<input type="checkbox" name="siteseo_pro_options[breadcrumbs_home]" ' . checked($hide_home, true, false).'/>
							'.esc_html__('Hide Home', 'siteseo-pro') . '
						</label>
						<br/><br/>
						<label>
							<input type="text" name="siteseo_pro_options[breadcrumb_home_label]" placeholder="'.esc_attr__('Homepage label', 'siteseo-pro').'" value="'.esc_attr($home_label).'"/>
							<p class="description">'.esc_html__('Home label', 'siteseo-pro').'</p>
						</label>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">'.esc_html__('Prefix', 'siteseo-pro').'</th>
				<td>
					<div>
						<label>
							<input type="text" id="siteseo_breadcrumbs_prefix" name="siteseo_pro_options[breadcrumb_prefix]" placeholder="'.esc_attr__('Breadcrumb Prefix', 'siteseo-pro'). '" value="'.esc_attr($prefix).'"/>
						</label>
					</div>
				</td>
			</tr>
		</table><input type="hidden" name="breadcrumbs_tab" value="1"/>';

	}
	
	static function robots(){

		echo '<h3 class="siteseo-tabs">'.esc_html__('robots.txt','siteseo-pro').'</h3><p>'. esc_html__('Manage your robots.txt file here. Adjust settings according to your SEO requirements.', 'siteseo-pro') . '</p>';
		echo '<table class="form-table">';
		
		echo '<tr><th class="row">'.esc_html__('Preview', 'siteseo-pro').'</th><td colspan="2">
		<a href="'.esc_url(home_url('/robots.txt')).'" class="btn btnSecondary" style="text-decoration:none;" target="_blank">'.esc_html__('View Robots.txt', 'siteseo-pro').'</a>';

		
		if(file_exists(ABSPATH. 'robots.txt')){
			echo '<button type="button" id="siteseopro-delete-robots-txt" class="btn btnTertiary">'.esc_html__('Delete Robots.txt', 'siteseo-pro').'</button>';
		}
		
		echo '</td></tr>';
		
		$robots_txt = '';
		if(file_exists(ABSPATH. 'robots.txt')){
			$robots_txt = file_get_contents(ABSPATH . 'robots.txt');
		}else if(get_option('siteseo_pro_virtual_robots_txt') !== false){
			$robots_txt = get_option('siteseo_pro_virtual_robots_txt');
		}else{
			$robots_txt = apply_filters('robots_txt', '', get_option('blog_public'));
		}
		
		echo '<tr>
				<th class="row">'.esc_html__('robots.txt File', 'siteseo-pro').'</th>
				<td colspan="2">
					<textarea id="siteseo_robots_file_content" placeholder="'.esc_attr__('Enter your robots.txt rules here', 'siteseo-pro').'" rows="15" cols="50">'.esc_textarea($robots_txt).'</textarea>
				</td>
			</tr>
			<tr>
				<th class="row" style="'. (defined('SITEPAD') ? "display:table-cell" : "") .'"></th>
				<td class="wrap-tags">
					<button class="tag-title-btn" id="siteseo_googlebots" data-tag="User-agent: Googlebot
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK GOOGLE BOTS', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_bingbots" data-tag="User-agent: Bingbot
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK BING BOTS', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_yandex_bots" data-tag="User-agent: Yandex
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK YANDEX BOTS', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_semrushbot" data-tag="User-agent: SemrushBot 
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK SEMRUSHBOT BOTS', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_rss_feeds" data-tag="User-agent: *
Disallow: /feed/
Disallow: /comments/feed/"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK RSS FEEDS', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_gptbots" data-tag="User-agent: GPTBot 
Disallow: /

User-agent: Google-Extended
Disallow: /

User-agent: ClaudeBot
Disallow: /

User-agent: PerplexityBot
Disallow: /

User-agent: CCBot
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK AI BOTS', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_link_sitemap" data-tag="'.esc_url(get_site_url()).'/sitemaps.xml"><span class="dashicons dashicons-insert"></span>'.esc_html__('LINK TO YOUR SITEMAP', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_wp_rule" data-tag="User-agent: * 
Disallow: /wp-admin/ Allow: /wp-admin/admin-ajax.php"><span class="dashicons dashicons-insert"></span>'.esc_html__('DEFAULT WP RULES', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_majesticsbots" data-tag="User-agent: MJ12bot 
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK MAJESTICSEOBOT', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_ahrefsbot" data-tag="User-agent: AhrefsBot 
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK AHREFS BOT', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_mangools" data-tag="User-agent: MangoolsBot
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK MANGOOLS BOT', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_google_ads_bots" data-tag="User-agent: Mediapartners-Google
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK GOOGLE ADSENSE BOT', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_google_img_bot" data-tag="User-agent: Googlebot-Image 
Disallow: /"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK GOOGLE IMAGE BOT', 'siteseo-pro').'</button>
				</td>
			</tr>
			<tr>
				<th></th>
				<td colspan="2">
					<button class="btn btnSecondary" id="siteseo-update-robots">'.esc_html__('Update robots.txt', 'siteseo-pro').'</button>
					<span class="spinner"></span>
				</td>
			</tr>	
		</table>';
	}
	
	static function htaccess(){
		global $siteseo;

		$home_path = get_home_path();
		$htaccess_file = $home_path . '.htaccess';

		if(!empty($_POST['submit'])){
			self::save_settings();
		}

		echo '<h3 class="siteseo-tabs">'.esc_html__('htaccess','siteseo-pro').'</h3>
		<p class="description">'.esc_html__('Edit your .htaccess file to configure advanced settings for your site','siteseo-pro').'</p>';


		if(!file_exists($htaccess_file) || !is_writable($htaccess_file)){
			echo '<table class="siteseo-notice-table">
					<tr>
						<td class="siteseo-notice is-error"><p>'.esc_html__('The .htaccess file does not exist or You do not have permission to edit the .htaccess file', 'siteseo-pro').'</p>
						</td>
					</tr>
				</table>';
			return;
		}

		echo '<table class="siteseo-notice-table" style="width: 82%;padding-left:42%">
				<tr>
					<th class="row"></th>
						<td colspan="2" class="siteseo-notice is-error">
							<p>'.esc_html__('Be careful editing this file. If any incorrect edits are made, your site could go down. You can restore the htaccess file by replacing it with the backup copy created by SiteSEO with name .htaccess_backup.siteseo', 'siteseo-pro').'
							<br/><input type="checkbox" value="1" id="siteseo_htaccess_enable"/><strong>'.esc_html__('I understand the risk and I want to edit this file','siteseo-pro').'</strong>
							</p>
							
						</td>
				</tr>
			</table>';

		$htaccess_code = file_get_contents($htaccess_file);

		echo '<table class="form-table" style="width: 100%;">
			<tr>
				<th class="row">'.esc_html__('Edit your htaccess file','siteseo-pro').'</th>
				<td>
					<textarea id="siteseo_htaccess_file" name="siteseo_advanced_option_names[htaccess_code]" rows="22" style="width: 100%;">'.esc_textarea($htaccess_code).'</textarea>
				</td>
			</tr>
			
			<tr>
				<th class="row"></th>
				<td class="wrap-tags">
					<button class="tag-title-btn" id="siteseo_wp_config" data-tag="Options -Indexes"><span class="dashicons dashicons-insert"></span>'.esc_html__('BLOCK DIRECTORY BROWSING', 'siteseo-pro').'</button>
					<button class="tag-title-btn" data-tag="Redirect 301 /your-old-url/ https://www.example.com/your-new-url" id="siteseo_error_300"><span class="dashicons dashicons-insert"></span>'.esc_html__('301 REDIRECT', 'siteseo-pro').'</button>
					<button class="tag-title-btn" id="siteseo_block_dir" data-tag="<files wp-config.php>
 order allow,deny
 deny from all
</files>"><span class="dashicons dashicons-insert"></span>'.esc_html__('PROTECT WP-CONFIG.PHP FILE', 'siteseo-pro').'</button>
				</td>
			</tr>
			
			<tr>
				<th class="row">
					<td style="padding-top: 10px;">
						<div style="display: flex; align-items: center;">
							<button id="siteseo_htaccess_btn" class="btn btnSecondary">'.esc_html__('Update htaccess.txt', 'siteseo-pro').'</button>
							<span class="spinner" style="margin-left: 10px;"></span>
						</div>
					</td>
				</th>
			</tr>
		</table>';
	}
	
	static function redirect_monitoring(){
		global $siteseo;
		
		if(!empty($_POST['submit'])){
			self::save_settings();
		}
		
		$options = $siteseo->pro;
		
		$enable_404 = isset($options['enable_404_log']) ? $options['enable_404_log'] : '';
		$clean_logs = isset($options['clean_404_logs']) ? $options['clean_404_logs'] : '';
		$set_log_limit = isset($options['log_limits']) ? $options['log_limits'] : '100';
		$redirect_type = isset($options['redirect_type']) ? $options['redirect_type'] : '';
		$custom_redirect_url = isset($options['custom_redirect_url']) ? $options['custom_redirect_url'] : '';
		$disable_guess_redirect = isset($options['guess_redirect']) ? $options['guess_redirect'] : '';
		$enable_email_notify = isset($options['email_notify']) ? $options['email_notify'] : '';
		$email_id = isset($options['send_email_to']) ? $options['send_email_to'] : '';
		$disable_ip_logging = isset($options['disable_ip_logging']) ? $options['disable_ip_logging'] : '';
		$redirect_code = isset($options['status_code']) ? $options['status_code'] : '';
		$toggle_state_redirect = !empty($options['toggle_state_redirect_monitoring']) ? $options['toggle_state_redirect_monitoring'] : '';
		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');
		
		$default_email_id =  get_option('admin_email');
		
		if(!empty($toggle_state_redirect) && !empty($enable_404)){
			$logs_data = Util::get_logs();
		}
		
		echo'<h3 class="siteseo-tabs">'.esc_html__('Redirections / 404 monitoring', 'siteseo-pro').'</h3>
		<p>'.esc_html__('Optimize Your Site with Smart Redirects & 404 Monitoring', 'siteseo-pro').'</p>';
		
		Util::render_toggle('404_monitoring', $toggle_state_redirect, $nonce);
		
		echo'<table class="form-table">
			<tbody>
				<tr>'.
					'<th scope="row" style="user-select: auto;">'.esc_html__('404 Log', 'siteseo-pro').'</th>'.
					'<td><label>'.
						'<input name="siteseo_pro_options[404_log]" type="checkbox" ' .(!empty($enable_404) ? 'checked="yes"' : '') . ' value="1"/>'.esc_html__('Enable 404 monitoring', 'siteseo-pro') .
						'</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('404 Cleaning', 'siteseo-pro').'</th>
					<td><label>
						<input name="siteseo_pro_options[clean_404_logs]" type="checkbox" '.(!empty($clean_logs) ? 'checked="yes"' : '').' value="1"/>'.
							esc_html__('Automatically delete 404 after 30 days (useful if you have a lot of 404)','siteseo-pro')
						.'</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Log Limits', 'siteseo-pro').'</th>
					<td><label>
						<input type="number" name="siteseo_pro_options[log_limits]" value="'.esc_attr($set_log_limit).'"/>
					</label>
					</td>
				<tr>
					<th scope="row">'.esc_html__('Redirect 404 to','siteseo-pro').'</th>
					<td>
						<select name="siteseo_pro_options[redirect_to]">
							<option value="homepage" '.selected($redirect_type, 'Homepage', false).'>'.esc_html__('Homepage','siteseo-pro').'</option>
							<option value="CustomURL" '.selected($redirect_type, 'CustomURL', false).'>'.esc_html__('Custom URL','siteseo-pro').'</option>
						</select>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Redirect to specific URL	','siteseo-pro').'</th>
					<td><label>
							<input name="siteseo_pro_options[redirect_url]" type="text" value="'.esc_attr($custom_redirect_url).'" placeholder="Enter your custom URL"/>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Status code of redirection','siteseo-pro').'</th>
					<td><label>
							<select name="siteseo_pro_options[status_code]">
								<option value="301redirect" '.selected($redirect_code, '301redirect', false).'>'.esc_html__('301 redirect','siteseo-pro').'</option>
								<option value="302redirect" '.selected($redirect_code, '302redirect', false).'>'.esc_html__('302 redirect','siteseo-pro').'</option>
								<option value="307redirect" '.selected($redirect_code, '307redirect', false).'>'.esc_html__('307 redirect','siteseo-pro').'</option>
							</select>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Enable email notification', 'siteseo-pro').'</th>
					<td>
					<input name="siteseo_pro_options[email_notify]" type="checkbox" ' .(!empty($enable_email_notify) ? 'checked="yes"' : '').' value="1"/>'.esc_html__('1 email per week with the top 404 errors, and the latest logged (within a limit of 10)', 'siteseo-pro') .
						'</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Send email to', 'siteseo-pro').'</th>
					<td>
						<input name="siteseo_pro_options[email_id]" type="text" value="'.esc_attr($email_id).'" placeholder="Enter your email"/>
						<p>'.esc_html__('If you put empty it send default admin email', 'siteseo-pro').'</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Disable guess redirect url for 404','siteseo-pro').'</th>
					<td>
						<input name="siteseo_pro_options[eanble_guess_redirect]" type="checkbox" '.(!empty($disable_guess_redirect) ? 'checked="yes"' : '').' value="1"/>'. esc_html__('Stop WordPress to attempt to guess a redirect URL for a 404 request', 'siteseo-pro') .'
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Disable ip logging','siteseo-pro').'</th>
					<td>
						<select name="siteseo_pro_options[disable_ip_logging]">
							<option value="no_ip_logging" '.selected($disable_ip_logging, 'no_ip_logging', false).'>'.esc_html__('No IP logging', 'siteseo-pro').'</option>
							<option value="full_ip_logging" '.selected($disable_ip_logging, 'full_ip_logging', false).'>'.esc_html__('Full IP logging', 'siteseo-pro').'</option>
							<option value="anonymize_the_last_part" '.selected($disable_ip_logging, 'anonymize_the_last_part', false).'>'.esc_html__('Anonymize the last part', 'siteseo-pro').'</option>
					</td>
				
				</tr>';
				
					if(!empty($enable_404)){
				
						echo'<tr>
						<th scope="row">'.esc_html__('Redirections logs', 'siteseo-pro').'</th>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th style="width: 30px; text-align: center;"><input type="checkbox" id="select-all-logs"></th>
									<th>'.esc_html__('URL', 'siteseo-pro').'</th>
									<th>'.esc_html__('IP Address', 'siteseo-pro').'</th>
									<th>'.esc_html__('Time', 'siteseo-pro').'</th>
									<th>'.esc_html__('User Agent', 'siteseo-pro').'</th>
									<th>'.esc_html__('Referer', 'siteseo-pro').'</th>
									<th>'.esc_html__('Hit count', 'siteseo-pro').'</th>
								</tr>
							</thead>
							
							<tbody>';
								if(!empty($logs_data['items'])){
									foreach($logs_data['items'] as $log){
										 echo'<tr>
											<td><input type="checkbox" class="log-selector" value="'.esc_attr($log->id).'"></td>
											<td>'.esc_html($log->url).'</td>
											<td>'.esc_html($log->ip_address).'</td>
											<td>'.esc_html($log->timestamp).'</td>
											<td>'.esc_html($log->user_agent).'</td>
											<td>'.esc_html($log->referer).'</td>
											<td>'.esc_html($log->hit_count).'</td>
										</tr>';
									}
								}
							echo'</tbody>
						</table>
				</tr></br/>
					<th sope="row">
						<button type="button" id="siteseo-remove-selected-log" class="siteseo-request-page-speed btn btnPrimary">'.
						esc_html__('Remove From Logs', 'siteseo-pro').'</button>
						<button type="button" id="siteseo_redirect_all_logs"  class="siteseo-request-page-speed btn btnPrimary">'.
						esc_html__('Clear All Logs', 'siteseo-pro').'</button>
						<button type="button" id="siteseo-export-csv" class="btn btnTertiary">'.esc_html__('Export CSV', 'siteseo-pro').'</button>
						
					</th>';
					}
			echo'</tbody>
		</table>
		<br/><br/><input type="hidden" name="404_monitoring" value="1"/>';		
	}
	
	static function google_news(){
		global $siteseo;
		
		if(!empty($_POST['submit'])){
			self::save_settings();
		}
		
		$settings = $siteseo->pro;
		$toggle_state_google_news = !empty($settings['toggle_state_google_news']) ? $settings['toggle_state_google_news'] : '';
		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');
		$enable_google_news = isset($settings['google_news']) ? $settings['google_news'] : '';
		$publication_name = isset($settings['publication_name']) ? $settings['publication_name'] : '';
		
		echo'<h3 class="siteseo-tabs">'.esc_html__('Google News','siteseo-pro').'</h3>
		<p>'.esc_html__('Enable your google News Sitemap','siteseo-pro').'</p>';
		
		Util::render_toggle('google_news', $toggle_state_google_news, $nonce);
		
		echo'<div class="siteseo-styles pre"><pre><span class="dashicons dashicons-external"></span><a href="'.esc_url(get_option('home')).'/news.xml" target="_blank">' . esc_url(get_option('home')) . '/news.xml</a></pre></div>
		
		<table class="form-table">
			<div class="siteseo-notice">
				<span id="siteseo-dash-icon" class="dashicons dashicons-info"></span>
                <p>'.wp_kses_post('If you have not enabled sitemap settings, this feature will not be useful. <a href="?page=siteseo-sitemaps">Click here</a> to configure the settings.').'</p>
			</div>
				<tbody>
					<tr>
						<th scope="row">'.esc_html__('Enable google news', 'siteseo-pro').'</th>
						<td>
							<label>
							<input type="checkbox" name="siteseo_pro_options[google_news]" '.(!empty($enable_google_news) ? 'checked="yes"' : '') . ' value="1"/>'.esc_html__('Enable Google News sitemap', 'siteseo-pro') .
							'</label>
						</td>
					</tr>
					
					<tr>
						<th scope="row">'.esc_html__('Enter publication name', 'siteseo-pro').'</th>
						<td>
							<input type="text" placeholder="Enter your google news publication name" name="siteseo_pro_options[publication_name]" value="'.esc_attr($publication_name).'" />
						</td>
					</tr>
					
					<tr>
						<th scope="row">'.esc_html__('Select post types', 'siteseo-pro').'</th>
						<td>';
							$post_types = siteseo_post_types();
							$selected_types = isset($settings['post_types']) && is_array($settings['post_types']) ? $settings['post_types'] : [];
							foreach($post_types as $post){
								$post_name = $post->name;
								$post_label = $post->label;
								$is_checked = in_array($post->name, $selected_types) ? 'checked' : '';
									
								echo'<input type="checkbox" id="post_type_'.esc_attr($post_name).'" name="siteseo_pro_options[post_types][]" value="'.esc_attr($post_name).'" ' . esc_attr($is_checked).'/>';
								echo'<label for="post_type_'.esc_attr($post_name).'">'.esc_html($post_label).'</label><br /><br />';
							}
						echo'</td>
					</tr>
				</tbody>
			</table><input type="hidden" name="google_news_tab" value="1"/>';
	}
	
	static function video_sitemap(){
		global $siteseo;
		
		if(!empty($_POST['submit'])){
			self::save_settings();
		}
		
		$settings = $siteseo->pro;
		$toggle_state_video_sitemap = !empty($settings['toggle_state_video_sitemap']) ? $settings['toggle_state_video_sitemap'] : '';
		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');
		$enable_video_sitemap = isset($settings['enable_video_sitemap']) ? $settings['enable_video_sitemap'] : '';
		
		echo'<h3 class="siteseo-tabs">'.esc_html__('Video Sitemap', 'siteseo-pro').'</h3>
		<p>'.esc_html__('Enable your video sitemap', 'siteseo-pro').'</p>';
		
		Util::render_toggle('video_sitemap', $toggle_state_video_sitemap, $nonce);
		
		echo'<table class="form-table">
			<tbody>
				<div class="siteseo-notice">
					<span id="siteseo-dash-icon" class="dashicons dashicons-info"></span>
					<p>'.wp_kses_post('If you have not enabled sitemap settings, this feature will not be useful. <a href="?page=siteseo-sitemaps">Click here</a> to configure the settings.').'</p>
				</div>
			
				<tr>
					<th scope="row">'.esc_html__('Enable video sitemap','siteseo-pro').'</th>
					<td>
						<label>
							<input type="checkbox" name="siteseo_pro_options[enable_video_sitemap]" '.(!empty($enable_video_sitemap) ? 'checked="yes"' : '') . ' value="1"/>'.esc_html__('Enable video sitemap in your metabox', 'siteseo-pro').'
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Preview', 'siteseo-pro').'</th>
					<td>
						<div class="siteseo-styles pre"><pre><span class="dashicons dashicons-external"></span><a href="'.esc_url(get_option('home')).'/video-sitemap1.xml" target="_blank">' . esc_url(get_option('home')) . '/video-sitemap1.xml</a></pre></div>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Select post types', 'siteseo-pro').'</th>
					<td>';
					$post_types = siteseo_post_types();
						$selected_types = isset($settings['video_sitemap_posts']) && is_array($settings['video_sitemap_posts']) ? $settings['video_sitemap_posts'] : [];
						foreach($post_types as $post){
							$post_name = $post->name;
							$post_label = $post->label;
							$is_checked = in_array($post->name, $selected_types) ? 'checked' : '';
								
							echo'<input type="checkbox" id="post_type_'.esc_attr($post_name).'" name="siteseo_pro_options[video_sitemap_posts][]" value="'.esc_attr($post_name).'" ' . esc_attr($is_checked).'/>';
							echo'<label for="post_type_'.esc_attr($post_name).'">'.esc_html($post_label).'</label><br /><br />';
						}					
					echo'</td>
				</tr>
				
			</tbody>
		</table><input type="hidden" name="video_sitemap" value="1"/>';
	}
	
	static function rss_sitemap(){
		global $siteseo;
		
		if(!empty($_POST['submit'])){
			self::save_settings();
		}
		
		$settings = $siteseo->pro;
		
		$toogle_state_rss_sitemap = !empty($settings['toogle_state_rss_sitemap']) ? $settings['toogle_state_rss_sitemap'] : '';
		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');
		$enable_rss_sitemap = !empty($settings['enable_rss_sitemap']) ? $settings['enable_rss_sitemap'] : '';
		$rss_sitemap_limt = !empty($settings['rss_sitemap_limt']) ? $settings['rss_sitemap_limt'] : '50';
		
		echo'<h3 class="siteseo-tabs">'.esc_html__('RSS sitemap', 'siteseo-pro').'</h3>
		<p>'.esc_html__('Enable your rss sitemap', 'siteseo-pro').'</p>';
		
		Util::render_toggle('rss_sitemap', $toogle_state_rss_sitemap, $nonce);
		
		echo'<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">'.esc_html__('Enable rss sitemap', 'siteseo-pro').'</th>
					<td>
						<label>
							<input type="checkbox" name="siteseo_pro_options[enable_rss_sitemap]" '.(!empty($enable_rss_sitemap) ? 'checked="yes"' : '') . ' value="1"/>'.esc_html__('Enable rss sitemap for your site', 'siteseo-pro').'
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Preview', 'siteseo-pro').'</th>
					<td>
						<div class="siteseo-styles pre"><pre><span class="dashicons dashicons-external"></span><a href="'.esc_url(get_option('home')).'/sitemap.rss" target="_blank">' . esc_url(get_option('home')) . '/sitemap.rss</a></pre></div>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Number of Posts', 'siteseo-pro').'</th>
					<td>
						<label>
							<input type="number" name="siteseo_pro_options[rss_sitemap_limt]" min="10" max="1000" value="'.esc_attr($rss_sitemap_limt).'">
							<p class="description">'.esc_html__('Set the number of posts to include in RSS sitemaps (maximum limit: 1000 posts)', 'siteseo-pro').'</p>
						</label>
					</td>
				</tr>
				
				<tr>
					<th scope="row">'.esc_html__('Select post types', 'siteseo-pro').'</th>
					<td>';
					$post_types = siteseo_post_types();
						$selected_types = isset($settings['rss_sitemap_posts']) && is_array($settings['rss_sitemap_posts']) ? $settings['rss_sitemap_posts'] : [];
						foreach($post_types as $post){
							$post_name = $post->name;
							$post_label = $post->label;
							$is_checked = in_array($post->name, $selected_types) ? 'checked' : '';
								
							echo'<input type="checkbox" id="post_type_'.esc_attr($post_name).'" name="siteseo_pro_options[rss_sitemap_posts][]" value="'.esc_attr($post_name).'" ' . esc_attr($is_checked).'/>';
							echo'<label for="post_type_'.esc_attr($post_name).'">'.esc_html($post_label).'</label><br /><br />';
						}		
					echo'</td>
				</tr>
				
			</tbody>
		</table><input type="hidden" name="rss_sitemap" value="1">';
	}
	
	static function schema(){
		global $siteseo;
		
		$global_schema = \SiteSEOPro\StructuredData::auto_schema();
		
		$options = get_option('siteseo_auto_schema', ['schemas' => []]);
		
		echo'<h3 class="siteseo-tabs">'.esc_html__('Global Schema', 'siteseo-pro').'</h3>
		
		<div class="siteseo-notice">
			<span id="siteseo-dash-icon" class="dashicons dashicons-info"></span>
			<p>'.esc_html__('To disable this feature, turn off the Structured Data Types toggle.', 'siteseo-pro').'</p>
		</div><br/>
		
		<p>'.esc_html__('Add your global automatic schema', 'siteseo-pro').'</p>

		<button id="siteseo-auto-schema" class="btn btnSecondary">'.esc_html__('Add schema', 'siteseo-pro').'</button>
		<button id="siteseo-auto-schema-import-btn" class="btn btnSecondary">'.esc_html__('Import', 'siteseo-pro').'</button>';

		echo '<div id="siteseo-auto-schema-modal-import" title="'.esc_attr__('Import Schema', 'siteseo-pro').'">
			<div class="siteseo-inner-tabs-wrap">';
				echo '<input type="radio" id="siteseo-schema-import-url" name="siteseo-inner-tabs" checked>
				<input type="radio" id="siteseo-schama-import-manual" name="siteseo-inner-tabs">
				<ul class="siteseo-inner-tabs" style="justify-content:center;">
					<li class="siteseo-inner-tab"><label for="siteseo-schema-import-url"><span class="dashicons dashicons-admin-links"></span> '.esc_html__('Import from URL', 'siteseo-pro').'</label></li>
					<li class="siteseo-inner-tab"><label for="siteseo-schama-import-manual"><span class="dashicons dashicons-shortcode"></span> '.esc_html__('Manual Paste', 'siteseo-pro').'</label></li>
				</ul>';
				echo '<div class="siteseo-inner-tab-content">
					<div class="siteseo-schema-input-wrapper" id="siteseo-schema-input-wrapper">
						<label>'.esc_html__('Page URL', 'siteseo-pro').'
							<input class="siteseo-input siteseo-input-url" type="url" id="siteseo-text-control-url" placeholder="https://" autocomplete="on"/>
						</label>
					</div>
				</div>
				<div class="siteseo-inner-tab-content">
					<label>'.esc_html__('Select Manual Input type', 'siteseo-pro').'
						<select class="siteseo-select-control-input" id="siteseo-select-control-select">
							<option value="html">'.esc_html__('HTML Code', 'siteseo-pro').'</option>
							<option value="json">'.esc_html__('JSON-LD/Custom Code', 'siteseo-pro').'</option>
						</select>
					</label>
					<div class="siteseo-schema-input-html" style="margin-top:10px;">
						<div class="siteseo-textarea-control">
							<div class="siteseo-base-control-field">
								<label>'.esc_html__('HTML Code', 'siteseo-pro').'
									<textarea class="siteseo-textarea-control-input" id="siteseo-textarea-control-html" rows="10"></textarea>
								</label>
							</div>
						</div>
					</div>
					<div class="siteseo-schema-input-customcode" style="display:none; margin-top:10px;">
						<div class="siteseo-textarea-control">
							<div class="siteseo-base-control-field">
								<label>'.esc_html__('Custom JSON-LD Code', 'siteseo-pro').'
									<textarea class="siteseo-textarea-control-input" id="siteseo-textarea-control-json" rows="10"></textarea>
								</label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div id="siteseo-imported-schema-results" style="display:none;">
				<h4 id="siteseo-schema-count"><span class="dashicons dashicons-yes-alt"></span>'.esc_html__('Detected Schema', 'siteseo-pro').'</h4>
				<div id="siteseo-schemas-list"></div>
			</div>
		</div>';
		
		echo'<div id="siteseo-auto-schema-modal" title="Add Schema">
			<div class="siteseo-modal-loads-data">
				<span class="siteseo-loading-text"></span><span class="spinner"></span>
			</div>
			<div class="siteseo-option" id="siteseo-schema-form">
			<input type="hidden" id="schema_id" name="schema_id" value="">
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row" for="schema_name">'.esc_html__('Schema Name', 'siteseo-pro').'</th>
							<td>
								<input type="text" id="schema_name" name="schema_name">
							</td>
						</tr>
						
						<tr>
							<th scope="row" for="schema_type">'.esc_html__('Schema Type', 'siteseo-pro').'</th>
							<td>
								<select id="siteseo_schema_type" name="schema_type">
									<option>'.esc_html__('None', 'siteseo-pro').'</option>';
									foreach($global_schema as $type => $properties){
										if($type === 'Product' && (!class_exists('WooCommerce') && !class_exists('KKART'))){
											continue;
										}

										echo '<option>'.esc_html($type).'</option>';
									}
								echo '</select>
							</td>
						</tr>

						<tr>
							<th scope="row">'.esc_html__('Display rule', 'siteseo-pro').'</th>
							<td>
							</td>
						</tr>
						
						<tr>
							<th scope="row" for="display_on">'.esc_html__('Display On', 'siteseo-pro') .'</th>
							<td class="siteseo-schema-rule-container">
								<select name="display_on[]" id="display_on">
									<optgroup label="'.esc_html__('Basic', 'siteseo-pro') .'">
										<option value="entire_website">'.esc_html__('Entire website', 'siteseo-pro').'</option>
										<option value="all_singulars">'. esc_html__('All singulars', 'siteseo-pro') .'</option>
										<option value="front_page">'. esc_html__('Front page', 'siteseo-pro') .'</option>
									</optgroup>
									
									<optgroup label="'.esc_html__('Posts', 'siteseo-pro').'">
										<option value="all_posts">'.esc_html__('All posts', 'siteseo-pro').'</option>
									</optgroup>
									
									<optgroup label="'.esc_html__('Pages', 'siteseo-pro') .'">
										<option value="all_pages">'.esc_html__('All pages', 'siteseo-pro') .'</option>
									</optgroup>
									
									<optgroup label="'. esc_html__('Custom Layouts', 'siteseo-pro') .'">
										<option value="all_custom_layouts">'. esc_html__('All custom layouts', 'siteseo-pro') .'</option>
									</optgroup>
									
									<optgroup label="'. esc_html__('Specific Target', 'siteseo-pro') .'">
										<option value="specific_targets">'.esc_html__('Specific pages / posts / archives', 'siteseo-pro') .'</option>
									</optgroup>
									
									<optgroup label="'.esc_html__('WooCommerces Products', 'siteseo-pro').'">
										<option value="all_product">'.esc_html__('All products', 'siteseo-pro').'</option>
										<option value="all_product_categories">'.esc_html__('All products categories', 'siteseo-pro').'</option>
										<option value="all_product_tags">'.esc_html__('All products tags', 'siteseo-pro').'</option>
									</optgroup>
								</select>
							</td>
							<td  class="siteseo-schema-rule-actions" style="vertical-align: top;">
								<span title="Add more rules" class="dashicons dashicons-insert siteseo-add-schema-rule" style="margin-top:10px;"></span>
							</td>
							
						</tr>

						<tr>
							<th scope="row" for="display_not_on">'.esc_html__('Display Not On', 'siteseo-pro') .'</th>
							<td class="siteseo-schema-rule-container">
								<select name="display_not_on[]" id="display_not_on">
									<option value="none">'.esc_html__('None', 'siteseo-pro').'</option>
									<optgroup label="'.esc_html__('Basic', 'siteseo-pro') .'">
										<option value="entire_website">'.esc_html__('Entire website', 'siteseo-pro').'</option>
										<option value="all_singulars">'.esc_html__('All singulars', 'siteseo-pro').'</option>
										<option value="front_page">'.esc_html__('Front page', 'siteseo-pro').'</option>
									</optgroup>
									
									<optgroup label="'.esc_html__('Posts', 'siteseo-pro').'">
										<option value="all_posts">'.esc_html__('All posts', 'siteseo-pro').'</option>
									</optgroup>
									
									<optgroup label="'.esc_html__('Pages', 'siteseo-pro') .'">
										<option value="all_pages">'.esc_html__('All pages', 'siteseo-pro') .'</option>
									</optgroup>
									
									<optgroup label="'.esc_html__('Custom Layouts', 'siteseo-pro').'">
										<option value="all_custom_layouts">'. esc_html__('All custom layouts', 'siteseo-pro') .'</option>
									</optgroup>
									
									<optgroup label="'.esc_html__('Specific Target', 'siteseo-pro').'">
										<option value="specific_targets">'.esc_html__('Specific pages / posts / archives', 'siteseo-pro') .'</option>
									</optgroup>
									
									<optgroup label="'.esc_html__('WooCommerces Products', 'siteseo-pro').'">
										<option value="all_product">'.esc_html__('All products', 'siteseo-pro').'</option>
										<option value="all_product_categories">'.esc_html__('All products categories', 'siteseo-pro').'</option>
										<option value="all_product_tags">'.esc_html__('All products tags', 'siteseo-pro').'</option>
									</optgroup>
									
								</select>
							</td>
							<td class="siteseo-schema-rule-actions" style="vertical-align: top;">
								<span title="Add more rules" class="dashicons dashicons-insert siteseo-add-schema-rule" style="margin-top:10px;" ></span>
							</td>
							
						</tr>
						
						<tr id="siteseo-schema-properties-container" style="display:none;">
							<th scope="row">'.esc_html__('Add Schema Properties', 'siteseo-pro').'</th>
							<td>
								<tbody id="siteseo-schema-properties-show"></tbody>
							</td>
						</tr>
	
					</tbody>
				</table>
			</div></div><br/><br/>';
		
		if(!empty($options['schemas'])){
			// show table
			echo'<h3>'.esc_html__('Manage Schema', 'siteseo-pro').'</h3>
			<table class="wp-list-table widefat fixed striped siteseo-history-table"><thead><tr>
					<th>'.esc_html__('Schema Name', 'siteseo-pro').'</th>
					<th>'.esc_html__('Schema Type', 'siteseo-pro').'</th>
					<th>'.esc_html__('Actions', 'siteseo-pro').'</th>
				</tr>
				</thead>
			<tbody>';
			
			foreach($options['schemas'] as $id => $schema){

				if($schema['type'] == 'Product' && (!class_exists('WooCommerce') && !class_exists('KKART'))){
					continue; //if no woocommerce
				}

				echo'<tr data-id='.esc_attr($id).'>
					<td>'.esc_html($schema['name']).'</td>
					<td>'.esc_html($schema['type']).'</td>
					<td>
						<span class="siteseo-action siteseo-edit-schema">
							<span class="dashicons dashicons-edit"></span>
							<label>'.esc_html__('Edit', 'siteseo-pro').'</label>
						</span>

						<span class="siteseo-action siteseo-delete-schema">
							<span class="dashicons dashicons-trash"></span>
							<label>'.esc_html__('Delete','siteseo-pro').'</label>
						</span>
						<span class="spinner"></span>
					</td>
				</tr>';
			}

			echo'</tbody></table><br/><br/>';
		} else{
			echo '<p>'.esc_html__('No schemas added yet.', 'siteseo-pro').'</p>';
		}
		
		\SiteSEOPro\StructuredData::suggestion_schema_button();
	}

	static function llm_txt(){
		global $siteseo;

		$settings = $siteseo->pro;

		if(!empty($_POST['submit'])){
			self::save_settings();
		}

		$toggle_state_llm_txt = !empty($settings['toggle_state_llm_txt']) ? $settings['toggle_state_llm_txt'] : '';
		$nonce = wp_create_nonce('siteseo_pro_toggle_nonce');
		$llm_txt_limt = !empty($settings['llm_txt_limt']) ? $settings['llm_txt_limt'] : '10';
		$website_desc = !empty($siteseo->pro['llm_txt_web_desc']) ? $siteseo->pro['llm_txt_web_desc'] : '';

		echo'<h3 class="siteseo-tabs">'.esc_html__('LLMs txt', 'siteseo-pro').'</h3>
		<p>'.esc_html__('Enable your llm txt', 'siteseo-pro').'</p>';
		
		Util::render_toggle('llm_txt', $toggle_state_llm_txt, $nonce);

		echo'<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">'.esc_html__('Preview', 'siteseo-pro').'</th>
					<td>
						<div class="siteseo-styles pre"><pre><span class="dashicons dashicons-external"></span><a href="' . esc_url(home_url('/llms.txt')) . '" target="_blank" rel="noopener noreferrer">'. esc_url(home_url('/llms.txt')) .'</a></pre></div>
					</td>
				</tr>
				<tr>
					<th scope="row">'.esc_html__('Website Description', 'siteseo-pro').'</th>
					<td>
						<textarea name="siteseo_pro_options[llm_txt_web_desc]" rows="5" cols="100">'.(!empty($website_desc) ? esc_html($website_desc) : esc_html(get_bloginfo('description'))).'</textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">'.esc_html__('Number of Posts', 'siteseo-pro').'</th>
					<td>
						<label>
							<input type="number" name="siteseo_pro_options[llm_txt_limt]" min="10" max="50" value="'.esc_attr($llm_txt_limt).'">
							<p class="description">'.esc_html__('Set the number of posts to include in LLMs txt (maximum limit: 50 posts)', 'siteseo-pro').'</p>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">'.esc_html__('Select post types', 'siteseo-pro').'</th>
					<td>';
					$post_types = siteseo_post_types();
					$selected_types = isset($settings['llm_txt_posts']) && is_array($settings['llm_txt_posts']) ? $settings['llm_txt_posts'] : [];
					foreach($post_types as $post){
						$post_name = $post->name;
						$post_label = $post->label;
						$is_checked = in_array($post->name, $selected_types) ? 'checked' : '';
								
						echo'<input type="checkbox" id="llm_txt_post_type_'.esc_attr($post_name).'" name="siteseo_pro_options[llm_txt_posts][]" value="'.esc_attr($post_name).'" ' . esc_attr($is_checked).'/>';
						echo'<label for="llm_txt_post_type_'.esc_attr($post_name).'">'.esc_html($post_label).'</label><br /><br />';
					}
					echo'</td>
				</tr>
				<tr>
					<th scope="row">'.esc_html__('Select Taxonomies', 'siteseo-pro').'</th>
					<td>';
    				$disallowed_taxonomies = ['post_format'];
    				$taxonomies = get_taxonomies(['public' => true], 'objects');
    				foreach($taxonomies as $taxonomy){
        				if(in_array($taxonomy->name, $disallowed_taxonomies, true)){
            					continue;// skip post_format taxonomy
        				}

        				$tax_name = $taxonomy->name;
        				$tax_label = $taxonomy->label;
        				$selected_types = isset($settings['llm_txt_taxonomies']) && is_array($settings['llm_txt_taxonomies']) ? $settings['llm_txt_taxonomies'] : [];
        				$is_checked = in_array($taxonomy->name, $selected_types) ? 'checked' : '';
        				echo '<input type="checkbox" id="llm_txt_taxonomies_' . esc_attr($tax_name) . '" name="siteseo_pro_options[llm_txt_taxonomies][]" value="' . esc_attr($tax_name) . '" ' . esc_attr($is_checked) . '/>';
        				echo '<label for="llm_txt_taxonomies_' . esc_attr($tax_name) . '">' . esc_html($tax_label) . '</label><br /><br />';
    				}
				echo '</td>
				</tr>
			</tbody>
		</table>';
		echo'<input type="hidden" name="llm_txt" value="1">';
	}
	
	// save settings fun
	static function save_settings(){
		global $siteseo;
		
		check_admin_referer('sitseo_pro_settings');

		if(!current_user_can('manage_options') || !is_admin()){
			return;
		}

		$options = $siteseo->pro;

		if(empty($_POST['siteseo_pro_options'])){
			return;
		}

		// WooCommerce tab
		if(isset($_POST['woocommerce_settings'])){
			
			$options['woocommerce_cart_page_no_index'] = isset($_POST['siteseo_pro_options']['woocommerce_cart_page_no_index']);
			$options['woocommerce_checkout_page_no_index'] = isset($_POST['siteseo_pro_options']['woocommerce_checkout_page_no_index']);
			$options['woocommerce_customer_account_page_no_index'] = isset($_POST['siteseo_pro_options']['woocommerce_customer_account_page_no_index']);
			$options['woocommerce_product_og_price'] = isset($_POST['siteseo_pro_options']['woocommerce_product_og_price']);
			$options['woocommerce_product_og_currency'] = isset($_POST['siteseo_pro_options']['woocommerce_product_og_currency']);
			$options['woocommerce_meta_generator'] = isset($_POST['siteseo_pro_options']['woocommerce_meta_generator']);
			$options['woocommerce_schema_output'] = isset($_POST['siteseo_pro_options']['woocommerce_schema_output']);
			$options['woocommerce_schema_breadcrumbs_output'] = isset($_POST['siteseo_pro_options']['woocommerce_schema_breadcrumbs_output']);
		}
		
		if(isset($_POST['kkart_settings'])){
			$options['kkart_cart_page_no_index'] = isset($_POST['siteseo_pro_options']['kkart_cart_page_no_index']);
			$options['kkart_checkout_page_no_index'] = isset($_POST['siteseo_pro_options']['kkart_checkout_page_no_index']);
			$options['kkart_customer_account_page_no_index'] = isset($_POST['siteseo_pro_options']['kkart_customer_account_page_no_index']);
			$options['kkart_product_og_price'] = isset($_POST['siteseo_pro_options']['kkart_product_og_price']);
			$options['kkart_product_og_currency'] = isset($_POST['siteseo_pro_options']['kkart_product_og_currency']);
			$options['kkart_meta_generator'] = isset($_POST['siteseo_pro_options']['kkart_meta_generator']);
			$options['kkart_schema_output'] = isset($_POST['siteseo_pro_options']['kkart_schema_output']);
			$options['kkart_schema_breadcrumbs_output'] = isset($_POST['siteseo_pro_options']['kkart_schema_breadcrumbs_output']);
		}

		// Dublin Core settings
		if(isset($_POST['dublin_code_settings'])){
			
			$options['dublin_core_enable'] = isset($_POST['siteseo_pro_options']['dublin_core_enable']);
		}

		// Easy Digital Downloads
		if(isset($_POST['digital_download_settings'])){

			$options['edd_product_og_price'] = isset($_POST['siteseo_pro_options']['edd_product_og_price']);

			$options['edd_product_og_currency'] = isset($_POST['siteseo_pro_options']['edd_product_og_currency']);

			$options['edd_meta_generator'] = isset($_POST['siteseo_pro_options']['edd_meta_generator']);
		}

		// PageSpeed Settings
		if(isset($_POST['pagespeed_settings'])){
			$options['ps_api_key'] = !empty($_POST['siteseo_pro_options']['ps_api_key']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['ps_api_key'])) : '';
		}
		
		//local Business settings
		if(isset($_POST['local_business_settings'])){

			$options['local_business_display_schema'] = !empty($_POST['siteseo_pro_options']['local_business_display_schema']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']
				['local_business_display_schema'])) : '';

			$options['street_address'] = !empty($_POST['siteseo_pro_options']['street_address']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['street_address'])) : '';

			$options['city'] = !empty($_POST['siteseo_pro_options']['city']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['city'])) : '';

			$options['state'] = !empty($_POST['siteseo_pro_options']['state']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['state'])) : '';

			$options['postal_code'] = !empty($_POST['siteseo_pro_options']['postal_code']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['postal_code'])) : '';

			$options['country'] = !empty($_POST['siteseo_pro_options']['country']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['country'])) : '';

			$options['latitude'] = !empty($_POST['siteseo_pro_options']['latitude']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['latitude'])) : '';

			$options['longitude'] = !empty($_POST['siteseo_pro_options']['longitude']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['longitude'])) : '';

			$options['place_id'] = !empty($_POST['siteseo_pro_options']['place_id']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['place_id'])) : '';

			$options['url'] = !empty($_POST['siteseo_pro_options']['url']) ? sanitize_url(wp_unslash($_POST['siteseo_pro_options']['url'])) : '';

			$options['telephone'] = !empty($_POST['siteseo_pro_options']['telephone']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['telephone'])) : '';

			$options['price_range'] = !empty($_POST['siteseo_pro_options']['price_range']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['price_range'])) : '';

			$options['cuisine_served'] = !empty($_POST['siteseo_pro_options']['cuisine_served']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['cuisine_served'])) : '';

			$options['accepts_reser'] = !empty($_POST['siteseo_pro_options']['accepts_reser']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['accepts_reser'])) : '';
			
			// business type
			if(!empty($_POST['siteseo_pro_options']['business_type'])){
				$options['business_type'] = sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['business_type']));
			}

			// opening hours
			if(!empty($_POST['siteseo_pro_options']['opening_hours'])){
				$opening_hours = [];
				foreach($_POST['siteseo_pro_options']['opening_hours'] as $day => $hours){
					$opening_hours[$day] = [
						'closed' => !empty($hours['closed']),
						'open_morning' => !empty($hours['open_morning']),
						'open_morning_start_hour' => sanitize_text_field($hours['open_morning_start_hour']),
						'open_morning_start_min' => sanitize_text_field($hours['open_morning_start_min']),
						'open_morning_end_hour' => sanitize_text_field($hours['open_morning_end_hour']),
						'open_morning_end_min' => sanitize_text_field($hours['open_morning_end_min']),
						'open_afternoon' => !empty($hours['open_afternoon']) ? true : false,
						'open_afternoon_start_hour' => sanitize_text_field($hours['open_afternoon_start_hour']),
						'open_afternoon_start_min' => sanitize_text_field($hours['open_afternoon_start_min']),
						'open_afternoon_end_hour' => sanitize_text_field($hours['open_afternoon_end_hour']),
						'open_afternoon_end_min' => sanitize_text_field($hours['open_afternoon_end_min'])
					];
				}
				$options['opening_hours'] = $opening_hours;
			}
		}

		// Strutured data settings
		if(isset($_POST['structured_data_settings'])){
			
			$options['enable_structured_data'] = !empty($_POST['siteseo_pro_options']['enable_structured_data']);

			$options['structured_data_image_url'] = !empty($_POST['siteseo_pro_options']['structured_data_image_url']) ? sanitize_url(wp_unslash($_POST['siteseo_pro_options']['structured_data_image_url'])) : '';

			$options['org_desciption'] = !empty($_POST['siteseo_pro_options']['org_desciption']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['org_desciption'])) : '';

			$options['org_email'] = !empty($_POST['siteseo_pro_options']['org_email']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['org_email'])) : '';

			$options['org_phone_no'] = !empty($_POST['siteseo_pro_options']['org_phone_no']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['org_phone_no'])) : '';

			$options['org_legal'] = !empty($_POST['siteseo_pro_options']['org_legal']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['org_legal'])) : '';

			$options['establish_date'] = !empty($_POST['siteseo_pro_options']['establish_date']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['establish_date'])) : '';
		
			$options['number_emp'] = !empty($_POST['siteseo_pro_options']['number_emp']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['number_emp'])) : '';
			
			$options['vat_id'] = !empty($_POST['siteseo_pro_options']['vat_id']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['vat_id'])) : '';

			$options['tax_id'] = !empty($_POST['siteseo_pro_options']['tax_id']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['tax_id'])) : '';

			$options['iso_code'] = !empty($_POST['siteseo_pro_options']['iso_code']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['iso_code'])) : '';

			$options['let_code'] = !empty($_POST['siteseo_pro_options']['let_code']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['let_code'])) : '';

			$options['duns_number'] = !empty($_POST['siteseo_pro_options']['duns_number']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['duns_number'])) : '';

			$options['naics_code'] = !empty($_POST['siteseo_pro_options']['naics_code']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['naics_code'])) : '';
		}
		
		if(isset($_POST['breadcrumbs_tab'])){
			$options['breadcrumbs_enable'] = isset($_POST['siteseo_pro_options']['breadcrumbs_enable']);
			$options['breadcrumbs_seperator'] = isset($_POST['siteseo_pro_options']['breadcrumbs_seperator']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['breadcrumbs_seperator'])) : '';
			$options['breadcrumbs_custom_seperator'] = isset($_POST['siteseo_pro_options']['breadcrumbs_custom_seperator']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['breadcrumbs_custom_seperator'])) : '';
			$options['breadcrumbs_home'] = isset($_POST['siteseo_pro_options']['breadcrumbs_home']);
			$options['breadcrumb_home_label'] = isset($_POST['siteseo_pro_options']['breadcrumb_home_label']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['breadcrumb_home_label'])) : '';
			$options['breadcrumb_prefix'] = isset($_POST['siteseo_pro_options']['breadcrumb_prefix']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['breadcrumb_prefix'])) : '';
			
		}
		
		if(isset($_POST['404_monitoring'])){
			$options['enable_404_log'] = isset($_POST['siteseo_pro_options']['404_log']);
			$options['clean_404_logs'] = isset($_POST['siteseo_pro_options']['clean_404_logs']);
			$options['log_limits'] = isset($_POST['siteseo_pro_options']['log_limits']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['log_limits'])) : '';
			$options['email_notify'] = isset($_POST['siteseo_pro_options']['email_notify']);
			$options['send_email_to'] = isset($_POST['siteseo_pro_options']['email_id']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['email_id'])) : '';
			$options['guess_redirect'] = isset($_POST['siteseo_pro_options']['eanble_guess_redirect']);
			$options['custom_redirect_url'] = isset($_POST['siteseo_pro_options']['redirect_url']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['redirect_url'])) : '';
			$options['redirect_type'] = isset($_POST['siteseo_pro_options']['redirect_to']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['redirect_to'])) : '';
			$options['disable_ip_logging'] = isset($_POST['siteseo_pro_options']['disable_ip_logging']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['disable_ip_logging'])) : '';
			$options['status_code'] = isset($_POST['siteseo_pro_options']['status_code']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['status_code'])) : '';
			
			if(!empty($options['clean_404_logs'])){
				// Enabling cron for cleanup
				if(!wp_next_scheduled('siteseo_404_cleanup')){
					wp_schedule_event(time(), 'daily', 'siteseo_404_cleanup');
				}
			} else {
				wp_clear_scheduled_hook('siteseo_404_cleanup');
			}
		}

		if(isset($_POST['google_news_tab'])){
			$options['google_news'] = isset($_POST['siteseo_pro_options']['google_news']);
			$options['publication_name'] = isset($_POST['siteseo_pro_options']['publication_name']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['publication_name'])) : '';
			if(isset($_POST['siteseo_pro_options']['post_types'])){
				$options['post_types'] = map_deep(wp_unslash($_POST['siteseo_pro_options']['post_types']), 'sanitize_text_field');
			} else{
				$options['post_types'] = [];
			}
		}
		
		if(isset($_POST['video_sitemap'])){
			$options['enable_video_sitemap'] = isset($_POST['siteseo_pro_options']['enable_video_sitemap']);
			if(isset($_POST['siteseo_pro_options']['video_sitemap_posts'])){
				$options['video_sitemap_posts'] = map_deep(wp_unslash($_POST['siteseo_pro_options']['video_sitemap_posts']), 'sanitize_text_field');
			} else{
				$options['video_sitemap_posts'] = [];
			}
		}
		
		if(isset($_POST['rss_sitemap'])){
			$options['enable_rss_sitemap'] = isset($_POST['siteseo_pro_options']['enable_rss_sitemap']);
			$options['rss_sitemap_limt'] = isset($_POST['siteseo_pro_options']['rss_sitemap_limt']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['rss_sitemap_limt'])) : '';
			$options['rss_sitemap_limt'] = intval($options['rss_sitemap_limt']);
			
			if(isset($_POST['siteseo_pro_options']['rss_sitemap_posts'])){
				$options['rss_sitemap_posts'] = map_deep(wp_unslash($_POST['siteseo_pro_options']['rss_sitemap_posts']), 'sanitize_text_field');
			} else{
				$options['rss_sitemap_posts'] = [];
			}
		}

		if(isset($_POST['llm_txt'])){
			$options['llm_txt_web_desc'] = isset($_POST['siteseo_pro_options']['llm_txt_web_desc']) ? sanitize_textarea_field(wp_unslash($_POST['siteseo_pro_options']['llm_txt_web_desc'])) : '';
			$options['llm_txt_limt'] = isset($_POST['siteseo_pro_options']['llm_txt_limt']) ? sanitize_text_field(wp_unslash($_POST['siteseo_pro_options']['llm_txt_limt'])) : '';
			$options['llm_txt_limt'] = intval($options['llm_txt_limt']);
			
			$options['llm_txt_posts'] = [];
			if(isset($_POST['siteseo_pro_options']['llm_txt_posts'])){
				$options['llm_txt_posts'] = map_deep(wp_unslash($_POST['siteseo_pro_options']['llm_txt_posts']), 'sanitize_text_field');
			}
			
			$options['llm_txt_taxonomies'] = [];
			if(isset($_POST['siteseo_pro_options']['llm_txt_taxonomies'])){
				$options['llm_txt_taxonomies'] = map_deep(wp_unslash($_POST['siteseo_pro_options']['llm_txt_taxonomies']), 'sanitize_text_field');
			}
		}
		
		$siteseo->pro = $options; // Updates the global variable
		update_option('siteseo_pro_options', $options);
	}
}
