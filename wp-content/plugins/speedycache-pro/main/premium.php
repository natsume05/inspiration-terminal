<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT');
}

global $pagenow;
if(in_array($pagenow, ['post-new.php', 'post.php'], true)){
	if(!defined('SITEPAD')){
		add_action('admin_enqueue_scripts', '\SpeedyCache\MetaboxPro::enqueue_scripts');
		add_filter('speedycache_pro_metabox', '\SpeedyCache\MetaboxPro::html', 10, 2);
	}
	add_filter('speedycache_metabox_fields', '\SpeedyCache\MetaboxPro::options');
}