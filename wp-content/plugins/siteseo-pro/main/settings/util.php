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

class Util{

    static function render_toggle($toggle_key, $toggle_state, $nonce, $label = false){
		$is_active = $toggle_state ? 'active' : '';
		$state_text = $toggle_state ? 'Click to disable this feature' : 'Click to enable this feature';

		// for dashbord screen
		if($label){
			echo'<div class="siteseo-toggleCnt">
					<div class="siteseo-toggleSw '.esc_attr($is_active).'" id="siteseo-toggleSw-' . esc_attr($toggle_key) . '" data-nonce="' . esc_attr($nonce) . '" data-toggle-key="'.esc_attr($toggle_key).'" data-action="siteseo_pro_save_'.esc_attr($toggle_key).'"></div>
					<input type="hidden" name="siteseo_options['.esc_attr($toggle_key) . ']" id="'.esc_attr($toggle_key).'" value="'.esc_attr($toggle_state).'">
				</div>';
		} else{
			
			echo'<div class="siteseo-toggleCnt">
					<div class="siteseo-toggleSw '.esc_attr($is_active).'" id="siteseo-toggleSw-'.esc_attr($toggle_key).'" data-nonce="' . esc_attr($nonce) . '" data-toggle-key="'.esc_attr($toggle_key).'" data-action="siteseo_pro_save_'.esc_attr($toggle_key).'"></div>
					<span id="siteseo-arrow-icon" class="dashicons dashicons-arrow-left-alt siteseo-arrow-icon"></span>
					<p class="toggle_state_'.esc_attr($toggle_key).'">'.esc_html($state_text).'</p>
					<input type="hidden" name="siteseo_options['.esc_attr($toggle_key).']" id="'.esc_attr($toggle_key).'" value="'.esc_attr($toggle_state).'">
				</div>';
		}

	}
	
	static function get_logs(){
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'siteseo_redirect_logs';

		self::maybe_create_404_table();
	
		$results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC");
		return ['items' => $results];
		
	}

	static function maybe_create_404_table(){
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."siteseo_redirect_logs` (
		    id mediumint(9) NOT NULL AUTO_INCREMENT,
		    url varchar(255) NOT NULL,
		    ip_address varchar(46),
		    timestamp datetime DEFAULT CURRENT_TIMESTAMP,
		    user_agent text,
		    referer varchar(255),
		    hit_count int DEFAULT 1,
		    PRIMARY KEY  (id),
		    KEY url (url)
		) $charset_collate;";

		$path = !defined('SITEPAD') ? ABSPATH . 'wp-admin/includes/upgrade.php' : ABSPATH . 'site-admin/includes/upgrade.php';

		require_once($path);
		dbDelta($sql);
	}
	
	static function get_virtual_robots($output, $public){
		$robots_path = ABSPATH . 'robots.txt';
		
		if(file_exists($robots_path)){
			return $output;
		}
		
		$virtual_content = get_option('siteseo_pro_virtual_robots_txt', '');
		
		if(!empty($virtual_content)){
			$output = wp_strip_all_tags($virtual_content) . PHP_EOL;
		} else {
			
			$output  = "User-agent: *\n";
			$output .= "Disallow: /wp-admin/\n";
			$output .= "Allow: /wp-admin/admin-ajax.php\n\n";
			$output .= "Sitemap: ".esc_url(home_url('/sitemaps.xml'))."\n";
		}
		
		return $output;
	}
	
}
