<?php
/*
* SITESEO
* https://siteseo.io
* (c) SiteSEO Team
*/

namespace SiteSEOPro;

class RegisterBlocks{
	
	static function init(){
		global $siteseo;
		$settings = $siteseo->pro;
		$advanced_option = get_option('siteseo_advanced_option_name');
		$settings = isset($settings['breadcrumbs_enable']) ? $settings : $advanced_option;
		
		if(!empty($settings['breadcrumbs_enable'])){
			self::breadcrumbs();
		}
	}
	
	static function breadcrumbs(){
		global $siteseo;
		
		$settings = $siteseo->pro;
		$advanced_option = get_option('siteseo_advanced_option_name');
		$settings = isset($settings['breadcrumbs_enable']) ? $settings : $advanced_option;
		
		// Register Breadcrumbs block
		register_block_type(SITESEO_PRO_ASSETS_PATH . '/js/breadcrumbs/build', [
			'category' => 'siteseo',
			'render_callback' => '\SiteSEOPro\Breadcrumbs::render_block',
			'attributes' => [
				'hideHome' => [
					'type' => 'boolean',
					'default' => (!empty($settings) && !empty($settings['breadcrumbs_home']) ? true : false),
				],
				'homeLabel' => [
					'type'    => 'string',
					'default' => (!empty($settings) && !empty($settings['breadcrumb_home_label']) ? esc_html($settings['breadcrumb_home_label']) : esc_html__('Home', 'siteseo-pro')),
				],
				'seperator' => [
					'type' => 'string',
					'default' => \SiteSEOPro\Breadcrumbs::seperator(),
				],
				'prefix' => [
					'type' => 'string',
					'default' => (!empty($settings) && !empty($settings['breadcrumb_prefix']) ? esc_html($settings['breadcrumb_prefix']) : ''),
				],
			]
		]);
		wp_set_script_translations('siteseo/breadcrumbs', 'siteseo');
	}
}