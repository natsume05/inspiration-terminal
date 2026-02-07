<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}


function gosmtp_admin_hooks(){
	
	if(current_user_can('manage_options') && !defined('SITEPAD')){
		// === Plugin Update Notice === //
		$plugin_update_notice = get_option('softaculous_plugin_update_notice', []);
		$available_update_list = get_site_transient('update_plugins'); 
		$plugin_path_slug = 'gosmtp/gosmtp.php';

		if(
			!empty($available_update_list) &&
			is_object($available_update_list) &&
			!empty($available_update_list->response) &&
			!empty($available_update_list->response[$plugin_path_slug]) && 
			(empty($plugin_update_notice) || empty($plugin_update_notice[$plugin_path_slug]) || (!empty($plugin_update_notice[$plugin_path_slug]) &&
			version_compare($plugin_update_notice[$plugin_path_slug], $available_update_list->response[$plugin_path_slug]->new_version, '<')))
		){
			add_action('admin_notices', 'gosmtp_plugin_update_notice');
			add_filter('softaculous_plugin_update_notice', 'gosmtp_plugin_update_notice_filter');
		}
		// === Plugin Update Notice === //
	}
}

function gosmtp_plugin_update_notice_filter($plugins = []){
	$plugins['gosmtp/gosmtp.php'] = 'GoSMTP';
	return $plugins;
}

function gosmtp_plugin_update_notice(){
	if(defined('SOFTACULOUS_PLUGIN_UPDATE_NOTICE')){
		return;
	}

	$to_update_plugins = apply_filters('softaculous_plugin_update_notice', []);

	if(empty($to_update_plugins)){
		return;
	}

	/* translators: %1$s is replaced with a "string" of name of plugins, and %2$s is replaced with "string" which can be "is" or "are" based on the count of the plugin */
	$msg = sprintf(__('New versions of %1$s %2$s available. Updating ensures better performance, security, and access to the latest features.', 'gosmtp'), '<b>'.esc_html(implode(', ', $to_update_plugins)).'</b>', (count($to_update_plugins) > 1 ? 'are' : 'is')) . ' <a class="button button-primary" href='.esc_url(admin_url('plugins.php?plugin_status=upgrade')).'>Update Now</a>';

	define('SOFTACULOUS_PLUGIN_UPDATE_NOTICE', true); // To make sure other plugins don't return a Notice
	echo '<div class="notice notice-info is-dismissible" id="gosmtp-plugin-update-notice">
		<p>'.$msg. '</p>
	</div>';

	wp_register_script('gosmtp-update-notice', '', ['jquery'], '', true);
	wp_enqueue_script('gosmtp-update-notice');
	wp_add_inline_script('gosmtp-update-notice', 'jQuery("#gosmtp-plugin-update-notice").on("click", function(e){
		let target = jQuery(e.target);

		if(!target.hasClass("notice-dismiss")){
			return;
		}

		var data;
		
		// Hide it
		jQuery("#gosmtp-plugin-update-notice").hide();
		
		// Save this preference
		jQuery.post("'.admin_url('admin-ajax.php?action=gosmtp_close_update_notice').'&security='.wp_create_nonce('gosmtp_promo_nonce').'", data, function(response) {
			//alert(response);
		});
	});');
}