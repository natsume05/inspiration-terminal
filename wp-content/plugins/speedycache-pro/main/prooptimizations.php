<?php

namespace SpeedyCache;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT');
}

class ProOptimizations{
	static $content = '';

	// function init(&$content){
		// self::$content = $content;
	// }
	
	static function defer_js(&$content){
		global $speedycache;

		\SpeedyCache\Enhanced::init();
		$content = \SpeedyCache\Enhanced::render_blocking($content);
	}
	
	static function delay_js(&$content){
		global $speedycache;

		\SpeedyCache\Enhanced::init();
		if(empty($speedycache->enhanced)){
			$speedycache->enhanced['html'] = $content;
		}

		$content = \SpeedyCache\Enhanced::delay_js($content);
	}
	
	static function unused_css(){
		$url = esc_url(speedycache_optserver('HTTP_HOST'). speedycache_optserver('REQUEST_URI'));

		if(strpos($url, '?test_speedycache') !== FALSE){
			\SpeedyCache\UnusedCss::generate(array($url));
		} else {
			\SpeedyCache\UnusedCss::schedule('speedycache_unused_css', array($url));
		}
	}
	
	static function critical_css(){
		$url = esc_url(speedycache_optserver('HTTP_HOST'). speedycache_optserver('REQUEST_URI'));

		if(strpos($url, '?test_speedycache') !== FALSE){
			\SpeedyCache\CriticalCss::generate(array($url));
		} else {
			\SpeedyCache\CriticalCss::schedule('speedycache_generate_ccss', array($url));
		}
	}
	
	static function img_lazy_load(&$content){

		// to disable for Ajax Load More on the pages
		if(speedycache_is_plugin_active('ajax-load-more/ajax-load-more.php') && !empty($_SERVER['REQUEST_URI']) && preg_match("/\/page\/\d+\//", sanitize_url(wp_unslash($_SERVER['REQUEST_URI'])))){
			return;
		}

		$content = \SpeedyCache\Enhanced::lazy_load($content);
		$lazy_load_js = '';
		
		if(file_exists(SPEEDYCACHE_PRO_DIR . '/main/lazyload.php')){
			$lazy_load_js = \SpeedyCache\LazyLoad::get_js_source();
		}

		$content = preg_replace("/\s*<\/head\s*>/i", $lazy_load_js.'</head>', $content, 1);
	}
	
	static function remove_gfonts(&$content){
		global $speedycache;

		if(!empty($speedycache->bloat['remove_gfonts'])){
			$content = preg_replace('/<link[^<>]*\/\/fonts\.(googleapis|google|gstatic)\.com[^<>]*>/i', '', $content);
		}
	}
}