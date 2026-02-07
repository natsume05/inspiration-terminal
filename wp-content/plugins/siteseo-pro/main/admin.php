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

class Admin{
	
	static function init(){
		global $siteseo;

		add_action('admin_enqueue_scripts', '\SiteSEOPro\Admin::enqueue_script');
		add_action('admin_menu', '\SiteSEOPro\Admin::add_menu', 100);
		add_action('init', '\SiteSEOPro\RedirectManager::setup_log_scheduled');
		add_action('siteseo_structured_data_types_enqueue', '\SiteSEOPro\Admin::enqueue_metabox');
		add_action('siteseo_display_structured_data_types', '\SiteSEOPro\StructuredData::display_metabox');
		add_action('siteseo_display_video_sitemap', '\SiteSEOPro\VideoSitemap::display_metabox');
		add_action('siteseo_display_google_news', '\SiteSEOPro\GoogleNews::display_metabox');
		add_action('admin_notices', '\SiteSEOPro\Admin::free_version_nag');
		add_action('siteseo_pro_show_ai_tokens_sidebar', '\SiteSEOPro\Admin::dashbord_sidebar');
		add_action('siteseo_gsc_onboarding', '\SiteSEOPro\GSCSetup::wizard');
		add_action('admin_init', '\SiteSEOPro\GSCSetup::connect');
	
		// For wizard
		if(!empty($_GET['page']) && $_GET['page'] == 'siteseo-onboarding'){
			add_action('wp_print_scripts', '\SiteSEOPro\Admin::onboarding_enqueue');
		}

		if(current_user_can('activate_plugins') && !empty($siteseo->license) && empty($siteseo->license['active']) && !empty($siteseo->license['license']) && strpos($siteseo->license['license'], 'SOFTWP') !== FALSE){
			add_action('admin_notices', '\SiteSEOPro\Admin::license_expired_notice');
			add_filter('softaculous_expired_licenses', '\SiteSEOPro\Admin::plugins_expired');
		}

		// === Plugin Update Notice === //
		if(current_user_can('administrator')){
			$plugin_update_notice = get_option('softaculous_plugin_update_notice', []);
			$available_update_list = get_site_transient('update_plugins');
			$plugin_path_slug = 'siteseo-pro/siteseo-pro.php';

			if(
				!empty($available_update_list) &&
				is_object($available_update_list) && 
				!empty($available_update_list->response) &&
				!empty($available_update_list->response[$plugin_path_slug]) && 
				(empty($plugin_update_notice) || empty($plugin_update_notice[$plugin_path_slug]) || (!empty($plugin_update_notice[$plugin_path_slug]) &&
				version_compare($plugin_update_notice[$plugin_path_slug], $available_update_list->response[$plugin_path_slug]->new_version, '<'))) &&
				(method_exists('\SiteSEO\Admin', 'update_notice'))
			){
				
				add_action('admin_notices', '\SiteSEO\Admin::update_notice');
				add_filter('softaculous_plugin_update_notice', 'siteseo_pro_plugin_update_notice_filter');
			}
		}
		// === Plugin Update Notice === //
		
	}
	
	static function enqueue_metabox(){
		global $siteseo, $pagenow;
		
		$post_id = get_the_ID();
		
		wp_enqueue_style('siteseo-pro-metabox', SITESEO_PRO_ASSETS_URL.'/css/metabox.css', [], SITESEO_PRO_VERSION);
		
		wp_enqueue_script('siteseo-pro-metabox', SITESEO_PRO_ASSETS_URL.'/js/metabox.js', ['jquery'], SITESEO_PRO_VERSION);
		
		wp_localize_script('siteseo-pro-metabox', 'siteseo_pro', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('siteseo_pro_nonce'),
			'buy_link' => esc_url(SITESEO_PRO_AI_BUY.'&softwp_lic='.(!empty($siteseo->license['license']) ? $siteseo->license['license'] : ''))
		]);
		
		wp_enqueue_script('siteseo-index-highlight', SITESEO_PRO_ASSETS_URL.'/js/index-highlight.js', ['jquery'], SITESEO_PRO_VERSION);
		
		wp_localize_script('siteseo-index-highlight', 'structuredDataMetabox', [
			'propertyTemplates' => \SiteSEOPro\StructuredData::get_schema_properties(),
			'currentPostUrl' => get_permalink($post_id)
		]);
	
		// Load only on post edit screen or term edit screen
		if(!defined('SITEPAD') && ($pagenow == 'post.php' || $pagenow == 'post-new.php' || $pagenow == 'term.php' || $pagenow == 'edit-tags.php')){
			add_action('admin_footer', '\SiteSEOPro\AI::modal');
		}
		
	}
	
	static function onboarding_enqueue(){

		if(empty($_GET['page']) || strpos($_GET['page'], 'siteseo') === FALSE){
			return;
		}
		
		wp_enqueue_script('jquery-ui-dialog');
		
		wp_enqueue_script('siteseo-pro-onboarding', SITESEO_PRO_URL.'assets/js/admin.js', ['jquery'], SITESEO_PRO_VERSION, true);

		$data = [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('siteseo_pro_nonce'),
		];

		//auth_code only when it exists
		if(isset($_GET['siteseo_auth_code']) && ! empty($_GET['siteseo_auth_code'])){
			$data['auth_code'] = isset($_GET['siteseo_auth_code']) ? 1 : null;
		}

		wp_localize_script('siteseo-pro-onboarding', 'siteseo_pro', $data);
	}
	
	static function enqueue_script(){
		
		if(empty($_GET['page']) || strpos($_GET['page'], 'siteseo') === FALSE){
			return;
		}
		
		$siteseo_global_schema = \SiteSEOPro\StructuredData::auto_schema();

		wp_enqueue_media();
		
		wp_enqueue_script('jquery-ui-dialog'); // dialog(modal)
		wp_enqueue_style('wp-jquery-ui-dialog');

		if(!empty($_GET['page']) && $_GET['page'] === 'siteseo-pro-page'){
			wp_enqueue_script('siteseo-pro-highlight', SITESEO_PRO_ASSETS_URL.'/js/index-highlight.js', ['jquery'], SITESEO_PRO_VERSION, [
				'strategy'  => 'defer',
				'in_footer' => true
			]);
		}
		
		wp_enqueue_script('siteseo-pro-admin', SITESEO_PRO_URL.'assets/js/admin.js', ['jquery'], SITESEO_PRO_VERSION, true);
		
		// Flag to decide if we should load the search console stats or not
		$reload_search_console = false;
		$search_console_data = get_option('siteseo_google_tokens', []);
		
		if(!empty($search_console_data) && !empty($search_console_data['connected'])){
			$search_console_stats = get_transient('siteseo_search_console_cron');
			$reload_search_console = empty($search_console_stats); 
		}
		

		wp_localize_script('siteseo-pro-admin', 'siteseo_pro', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('siteseo_pro_nonce'),
			'schema' => $siteseo_global_schema,
			'reload_search_console_stats' => $reload_search_console
		]);

		wp_enqueue_style('siteseo-pro-admin', SITESEO_PRO_URL . 'assets/css/admin.css', [], SITESEO_PRO_VERSION);

	}
	
	static function add_menu(){
		$capability = 'manage_options';
		
		add_submenu_page('siteseo', __('PRO', 'siteseo-pro'), __('PRO', 'siteseo-pro') . ((time() < strtotime('30 November 2025')) ? ' <span style="color:yellow;margin-left:10px;">Updated</span>' : ''), $capability, 'siteseo-pro-page','\SiteSEOPro\Settings\Pro::home');

		if(!defined('SITEPAD')){
			add_submenu_page('siteseo', __('License', 'siteseo-pro'), __('License', 'siteseo-pro'), $capability, 'siteseo-license', '\SiteSEOPro\Settings\License::template');
		}
	}
	

	static function local_business_block(){

		wp_register_script('local-business-block-script',SITESEO_PRO_URL . 'assets/js/block.js', array('wp-blocks', 'wp-element', 'wp-editor'), filemtime(SITESEO_PRO_DIR . 'assets/js/block.js'));
		
		$data = \SiteSEOPro\Tags::local_business();
		
		// Localize
		wp_localize_script('local-business-block-script', 'siteseoProLocalBusiness', array(
			'previewData' => $data,
		));

		register_block_type('siteseo-pro/local-business', array(
			'editor_script' => 'local-business-block-script',
			'render_callback' => '\SiteSEOPro\Tags::load_data_local_business'
		));
	}
	
	// Nag when plugins dont have same version.
	static function free_version_nag(){

		if(!defined('SITESEO_VERSION')){
			return;
		}

		$dismissed_free = (int) get_option('siteseo_version_free_nag');
		$dismissed_pro = (int) get_option('siteseo_version_pro_nag');

		// Checking if time has passed since the dismiss.
		if(!empty($dismissed_free) && time() < $dismissed_pro && !empty($dismissed_pro) && time() < $dismissed_pro){
			return;
		}

		$showing_error = false;
		if(version_compare(SITESEO_VERSION, SITESEO_PRO_VERSION) > 0 && (empty($dismissed_pro) || time() > $dismissed_pro)){
			$showing_error = true;

			echo '<div class="notice notice-warning is-dismissible" id="siteseo-pro-version-notice" onclick="siteseo_pro_dismiss_notice(event)" data-type="pro">
			<p style="font-size:16px;">'.esc_html__('You are using an older version of SiteSEO Pro. We recommend updating to the latest version to ensure seamless and uninterrupted use of the application.', 'siteseo-pro').'</p>
		</div>';
		}elseif(version_compare(SITESEO_VERSION, SITESEO_PRO_VERSION) < 0 && (empty($dismissed_free) || time() > $dismissed_free)){
			$showing_error = true;

			echo '<div class="notice notice-warning is-dismissible" id="siteseo-pro-version-notice" onclick="siteseo_pro_dismiss_notice(event)" data-type="free">
			<p style="font-size:16px;">'.esc_html__('You are using an older version of SiteSEO. We recommend updating to the latest free version to ensure smooth and uninterrupted use of the application.', 'siteseo-pro').'</p>
		</div>';
		}
		
		if(!empty($showing_error)){
			wp_register_script('siteseo-pro-version-notice', '', ['jquery'], SITESEO_PRO_VERSION, true );
			wp_enqueue_script('siteseo-pro-version-notice');
			wp_add_inline_script('siteseo-pro-version-notice', '
		function siteseo_pro_dismiss_notice(e){
			e.preventDefault();
			let target = jQuery(e.target);

			if(!target.hasClass("notice-dismiss")){
				return;
			}

			let jEle = target.closest("#siteseo-pro-version-notice"),
			type = jEle.data("type");

			jEle.slideUp();

			jQuery.post("'.admin_url('admin-ajax.php').'", {
				security : "'.wp_create_nonce('siteseo_version_notice').'",
				action: "siteseo_pro_version_notice",
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
	
	static function dashbord_sidebar(){
		global $siteseo;
		
		$ai_tokens = get_option('siteseo_ai_tokens', []);
		$display_tokens = !empty($ai_tokens['remaining_tokens']) ? $ai_tokens['remaining_tokens'] : 0;
		$siteseo_license = isset($siteseo->license['license']) ? $siteseo->license['license'] : '';
		
		if(empty($ai_tokens)){
			echo '<div class="siteseo-pro-ai-promo-card">
			<div class="siteseo-pro-ai-card-header">
				<div class="siteseo-pro-ai-icon">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
					  <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.898 20.553L16.5 21.75l-.398-1.197a3.375 3.375 0 00-2.456-2.456L12.75 18l1.197-.398a3.375 3.375 0 002.456-2.456L16.5 14.25l.398 1.197a3.375 3.375 0 002.456 2.456L20.25 18l-1.197.398a3.375 3.375 0 00-2.456 2.456z" />
					</svg>
				</div>
				<h3>'.esc_html__('Introducing SiteSEO AI', 'siteseo-pro').'</h3>
			</div>

			<div class="siteseo-pro-ai-card-body">
				<p>
					'.esc_html__('Our new AI features are here to boost your productivity by helping you write better titles and meta descriptions.', 'siteseo-pro').'
				</p>
			</div>

			<div class="siteseo-ai-card-actions">
				<a href="https://siteseo.io/docs/ai/how-to-use-siteseo-ai/" target="_blank" class="siteseo-ai-doc-link primary">'.esc_html__('Learn to use SiteSEO AI', 'siteseo-pro').'</a>
			</div>
			</div>';
		} else {
			echo'<div class="siteseo-need-help" style="margin-bottom:20px;">
				<p>'.esc_html__('SiteSEO AI', 'siteseo-pro').'</p>
				<div class="siteseo-quick-links">
					<div class="siteseo-quick-access-item">
						<span class="dashicons dashicons-money-alt"></span>
						<span class="siteseo-sidebar-ai-tokens">Tokens Remaining '.esc_html(number_format((int)$display_tokens)).'</span>
						<span id="siteseo-sidebar-refersh-tokens" title="Refresh Tokens" class="dashicons dashicons-image-rotate"></span>
					</div>
					<div class="siteseo-quick-access-item">
						<span class="dashicons dashicons-cart"></span>
						<a href="'.esc_url(SITESEO_PRO_AI_BUY.'&softwp_lic='.$siteseo_license).'" target="_blank">'.esc_html__('Buy AI Tokens', 'siteseo-pro').'</a> | <a href="https://siteseo.io/docs/ai/how-to-buy-ai-tokens" target="_blank">'.esc_html__('How to Buy Tokens', 'siteseo-pro').'</a>
					</div>
				</div>
			</div>';
		}
	}

	static function plugins_expired($plugins){
		$plugins[] = 'SiteSEO';
		return $plugins;
	}

	static function license_expired_notice(){
		global $siteseo;

		// The combined notice for all Softaculous plugin to show that the license has expired
		$dismissed_at = get_option('softaculous_expired_licenses', 0);
		$expired_plugins = apply_filters('softaculous_expired_licenses', []);
		$soft_wp_buy = 'https://www.softaculous.com/clients?ca=softwp_buy';

		if(
			!empty($expired_plugins) && 
			is_array($expired_plugins) &&
			count($expired_plugins) > 0 && 
			!defined('SOFTACULOUS_EXPIRY_LICENSES') && 
			(empty($dismissed_at) || ($dismissed_at + WEEK_IN_SECONDS) < time())
		){

			define('SOFTACULOUS_EXPIRY_LICENSES', true); // To make sure other plugins don't return a Notice
			$soft_rebranding = get_option('softaculous_pro_rebranding', []);

			if(!empty($siteseo->license['has_plid'])){
				if(!empty($soft_rebranding['sn']) && $soft_rebranding['sn'] != 'Softaculous'){
					
					$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact %3$s to continue receiving uninterrupted updates and support for %4$s.', 'siteseo-pro'),
						'<font style="color:red;"><b>',
						'</b></font>',
						esc_html($soft_rebranding['sn']),
						esc_html(implode(', ', $expired_plugins))
					);
					
				}else{
					$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact your hosting provider to continue receiving uninterrupted updates and support for %3$s.', 'siteseo-pro'),
						'<font style="color:red;"><b>',
						'</b></font>',
						esc_html(implode(', ', $expired_plugins))
					);
				}
			}else{
				$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please %3$srenew%4$s it to continue receiving uninterrupted updates and support for %5$s.', 'siteseo-pro'),
					'<font style="color:red;"><b>',
					'</b></font>',
					'<a href="'.esc_url($soft_wp_buy.'&license='.$siteseo['license']['license'].'&plan='.$siteseo['license']['plan']).'" target="_blank">',
					'</a>',
					esc_html(implode(', ', $expired_plugins))
				);
			}
			
			
			echo '<div class="notice notice-error is-dismissible" id="siteseo-pro-expiry-notice">
					<p>'.$msg.'</p>
				</div>';

			wp_register_script('siteseo-pro-expiry-notice', '', array('jquery'), SITESEO_PRO_VERSION, true);
			wp_enqueue_script('siteseo-pro-expiry-notice');
			wp_add_inline_script('siteseo-pro-expiry-notice', '
			jQuery(document).ready(function(){
				jQuery("#siteseo-pro-expiry-notice").on("click", ".notice-dismiss", function(e){
					e.preventDefault();
					let target = jQuery(e.target);

					let jEle = target.closest("#siteseo-pro-expiry-notice");
					jEle.slideUp();
					
					jQuery.post("'.admin_url('admin-ajax.php').'", {
						security : "'.wp_create_nonce('siteseo_expiry_notice').'",
						action: "siteseo_pro_dismiss_expired_licenses",
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
}
