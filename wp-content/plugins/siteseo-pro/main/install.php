<?php
/*
* SITESEO
* https://siteseo.io
* (c) SiteSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
    die('HACKING ATTEMPT!');
}

class Install{

	static function activate(){
		self::default_settings();
		update_option('siteseo_pro_version', SITESEO_PRO_VERSION);
	}
	
	static function deactivate(){
		global $wpdb;

		wp_clear_scheduled_hook('siteseo_send_404_report_email');
		wp_clear_scheduled_hook('siteseo_404_cleanup');
	}
	
	static function uninstall(){
		global $wpdb;
		
		$wpdb->query("DROP TABLE IF EXISTS `".$wpdb->prefix."siteseo_redirect_logs`");

		delete_option('siteseo_pro_version');
		delete_option('siteseo_pro_options');
		delete_option('siteseo_pro_page_speed');
		delete_option('siteseo_license');
		delete_option('siteseo_google_tokens');
		delete_option('siteseo_search_console_data');
	}
	
	static function default_settings(){
		
		// We do not need to set defaults if we just upgrading the plugin
		$current_version = get_option('siteseo_pro_version');
		
		if(empty($current_version)){
			$pro_settings = get_option('siteseo_pro_options', []);
			
			$pro_settings['toggle_state_stru_data'] = !isset($pro_settings['toggle_state_stru_data']) ? true : '';
			
			update_option('siteseo_pro_options', $pro_settings);
		}
		
		 // If it's a new installation (no version set), or upgrading from version less than 1.3.0
		if(empty($current_version) || version_compare($current_version, '1.3.0', '<')){
			self::default_schema();
		}
	}
	
	static function default_schema(){
		$global_schema = get_option('siteseo_auto_schema', ['schemas' => []]);

		if(!empty($global_schema['schemas'])){
			return;
		}

		$auto_schema = \SiteSEOPro\StructuredData::auto_schema();

		foreach($auto_schema as $type => $fields){
			// Assign display_rule based on type
			$display_rule = '';

			if(in_array($type, ['WebSite', 'WebPage', 'SearchAction', 'BreadcrumbList'])){
				$display_rule = 'entire_website';
			} elseif(in_array($type, ['Person', 'Article'])){
				$display_rule = 'all_posts';
			} elseif ($type === 'Product'){
				$display_rule = 'all_product';
			} elseif(!empty($type)){
				$display_rule = 'all_singulars';
			}

			if(empty($display_rule)){
				continue;
			}

			$id = uniqid();
			$properties = \SiteSEOPro\StructuredData::process_nested_properties($fields);

			// Add the schema entry
			$global_schema['schemas'][$id] = [
				'id' => $id,
				'name' => $type,
				'type' => $type,
				'properties' => $properties,
				'display_on' => [$display_rule],
				'display_not_on' => ['none'],
			];
		}

		update_option('siteseo_auto_schema', $global_schema);
	}
}