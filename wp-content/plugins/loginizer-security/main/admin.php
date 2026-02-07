<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

if(current_user_can('activate_plugins')){
	add_action('admin_notices', 'loginizer_pro_free_version_nag');
	add_action('admin_notices', 'loginizer_pro_notices');
	add_filter('softaculous_expired_licenses', 'loginizer_pro_plugins_expired');
	add_action('loginizer_pro_social_um_settings', 'loginizer_pro_ultimate_member_settings');
	add_action('loginizer_pro_social_api_settings', 'loginizer_pro_social_api_settings', 10, 2);
	add_action('loginizer_pro_social_auth_notice', 'loginizer_pro_social_auth_notice');
	add_action('admin_notices', 'loginizer_pro_social_auth_release_notice');
	
	add_action('admin_enqueue_scripts', 'loginizer_pro_assets_enqueue');

}

function loginizer_pro_free_version_nag(){
	
	if(!defined('LOGINIZER_VERSION')){
		return;
	}

	$dismissed_free = (int) get_option('loginizer_version_free_nag');
	$dismissed_pro = (int) get_option('loginizer_version_pro_nag');

	// Checking if time has passed since the dismiss.
	if(!empty($dismissed_free) && time() < $dismissed_pro && !empty($dismissed_pro) && time() < $dismissed_pro){
		return;
	}

	$showing_error = false;
	if(version_compare(LOGINIZER_VERSION, LOGINIZER_PRO_VERSION) > 0 && (empty($dismissed_pro) || time() > $dismissed_pro)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="loginizer-pro-version-notice" onclick="loginizer_pro_dismiss_notice(event)" data-type="pro">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of Loginizer Security. We recommend updating to the latest version to ensure seamless and uninterrupted use of the application.', 'loginizer').'</p>
	</div>';
	}elseif(version_compare(LOGINIZER_VERSION, LOGINIZER_PRO_VERSION) < 0 && (empty($dismissed_free) || time() > $dismissed_free)){
		$showing_error = true;

		echo '<div class="notice notice-warning is-dismissible" id="loginizer-pro-version-notice" onclick="loginizer_pro_dismiss_notice(event)" data-type="free">
		<p style="font-size:16px;">'.esc_html__('You are using an older version of Loginizer. We recommend updating to the latest free version to ensure smooth and uninterrupted use of the application.', 'loginizer').'</p>
	</div>';
	}
	
	if(!empty($showing_error)){
		wp_register_script('loginizer-pro-version-notice', '', array('jquery'), LOGINIZER_PRO_VERSION, true );
		wp_enqueue_script('loginizer-pro-version-notice');
		wp_add_inline_script('loginizer-pro-version-notice', '
	function loginizer_pro_dismiss_notice(e){
		e.preventDefault();
		let target = jQuery(e.target);

		if(!target.hasClass("notice-dismiss")){
			return;
		}

		let jEle = target.closest("#loginizer-pro-version-notice"),
		type = jEle.data("type");

		jEle.slideUp();
		
		jQuery.post("'.admin_url('admin-ajax.php').'", {
			security : "'.wp_create_nonce('loginizer_version_notice').'",
			action: "loginizer_pro_version_notice",
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

function loginizer_pro_plugins_expired($plugins){
	global $loginizer;

	if(!empty($loginizer['license']) && empty($loginizer['license']['active']) && strpos($loginizer['license']['license'], 'SOFTWP') !== FALSE){
		$plugins[] = 'Loginizer';
	}

	return $plugins;
}

function loginizer_pro_notices(){
	global $loginizer;
	
	// We won't show this if not a SOFTWP license.
	if(empty($loginizer['license']) || !empty($loginizer['license']['active']) || strpos($loginizer['license']['license'], 'SOFTWP') === FALSE){
		return;
	}

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

		if(!empty($loginizer['license']['has_plid'])){
			if(!empty($soft_rebranding['sn']) && $soft_rebranding['sn'] != 'Softaculous'){
				
				$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact %3$s to continue receiving uninterrupted updates and support for %4$s.', 'loginizer-security'),
					'<font style="color:red;"><b>',
					'</b></font>',
					esc_html($soft_rebranding['sn']),
					esc_html(implode(', ', $expired_plugins))
				);
				
			}else{
				$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please contact your hosting provider to continue receiving uninterrupted updates and support for %3$s.', 'loginizer-security'),
					'<font style="color:red;"><b>',
					'</b></font>',
					esc_html(implode(', ', $expired_plugins))
				);
			}
		}else{
			$msg = sprintf(__('Your SoftWP license has %1$sexpired%2$s. Please %3$srenew%4$s it to continue receiving uninterrupted updates and support for %5$s.', 'loginizer-security'),
				'<font style="color:red;"><b>',
				'</b></font>',
				'<a href="'.esc_url($soft_wp_buy.'&license='.$loginizer['license']['license'].'&plan='.$loginizer['license']['plan']).'" target="_blank">',
				'</a>',
				esc_html(implode(', ', $expired_plugins))
			);
		}

		echo '<div class="notice notice-error is-dismissible" id="loginizer-pro-expiry-notice">
				<p>'.$msg.'</p>
			</div>';

		wp_register_script('loginizer-pro-expiry-notice', '', array('jquery'), LOGINIZER_PRO_VERSION, true );
		wp_enqueue_script('loginizer-pro-expiry-notice');
		wp_add_inline_script('loginizer-pro-expiry-notice', '
		jQuery(document).ready(function(){
			jQuery("#loginizer-pro-expiry-notice").on("click", ".notice-dismiss", function(e){
				e.preventDefault();
				let target = jQuery(e.target);

				let jEle = target.closest("#loginizer-pro-expiry-notice");
				jEle.slideUp();
				
				jQuery.post("'.admin_url('admin-ajax.php').'", {
					security : "'.wp_create_nonce('loginizer_expiry_notice').'",
					action: "loginizer_pro_dismiss_expired_licenses",
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

function loginizer_pro_ultimate_member_settings(){

	$social_settings = get_option('loginizer_social_settings', []);

	// Saving Settings
	if(isset($_POST['ultimate_member_settings'])){

		if(check_admin_referer('loginizer_social_nonce', 'security') && current_user_can('manage_options')){
			$social_settings['ultimate_member']['enable_buttons'] = lz_optpost('enable_buttons');
			$social_settings['ultimate_member']['button_style'] = lz_optpost('button_style');
			$social_settings['ultimate_member']['button_shape'] = lz_optpost('button_shape');
			$social_settings['ultimate_member']['button_position'] = lz_optpost('button_position');
			$social_settings['ultimate_member']['alignment'] = lz_optpost('alignment');
			$social_settings['ultimate_member']['button_alignment'] = lz_optpost('button_alignment');
			
			update_option('loginizer_social_settings', $social_settings);
		}
	}	

	echo '<form method="POST">
		<table class="form-table">
			<tr>
				<th scope="row"><label for="button_style_full">'.esc_html__('Show buttons', 'loginizer-security').'</label></th>
				<td class="loginizer-general-settings">
					<label><input type="checkbox" name="enable_buttons" id="enable_buttons" value="yes" '.(!empty($social_settings['ultimate_member']['enable_buttons']) ? 'checked' : '').'/></label>
					<p>'.esc_html__('Do you want to show social buttons on Ultimate Member forms.', 'loginizer'). '</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="button_style_full">'.esc_html__('Button Style', 'loginizer-security').'</label></th>
				<td class="loginizer-general-settings">
					<label><input type="radio" name="button_style" id="button_style_full" value="full" '.(empty($social_settings['ultimate_member']['button_style']) ? 'checked' : checked($social_settings['ultimate_member']['button_style'], 'full', false)).'/>'.esc_html__('Full Length', 'loginizer').'</label>
					<label><input type="radio" name="button_style" value="icon" '.(!empty($social_settings['ultimate_member']['button_style']) ? checked($social_settings['ultimate_member']['button_style'], 'icon', false) : '').'/>'.esc_html__('Icon', 'loginizer-security').'</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="button_shape_square">'.esc_html__('Button Shape', 'loginizer-security').'</label></th>
				<td class="loginizer-general-settings">
					<label><input type="radio" name="button_shape" id="button_shape_square" value="square" '.(empty($social_settings['ultimate_member']['button_shape']) ? 'checked' : checked($social_settings['ultimate_member']['button_shape'], 'square', false)).'>'.esc_html__('Square', 'loginizer-security').'</label>
					<label><input type="radio" name="button_shape" value="circle" '.(!empty($social_settings['ultimate_member']['button_shape']) ? checked($social_settings['ultimate_member']['button_shape'], 'circle', false) : '').'>'.esc_html__('Pill/Circle', 'loginizer').'</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="button_position">Button Position</label></th>
				<td class="loginizer-general-settings">
					<label><input type="radio" name="button_position" id="button_position" value="below" '.(empty($social_settings['ultimate_member']['button_position']) ? 'checked' : checked($social_settings['ultimate_member']['button_position'], 'below', false)).'>Below</label>
					<label><input type="radio" name="button_position" value="below_plus" '.(!empty($social_settings['ultimate_member']['button_position']) ? checked($social_settings['ultimate_member']['button_position'], 'below_plus', false) : '').'>'.esc_html__('Below with Seperator', 'loginizer-security').'</label>
					<label><input type="radio" name="button_position" value="above" '.(!empty($social_settings['ultimate_member']['button_position']) ? checked($social_settings['ultimate_member']['button_position'], 'above', false) : '').'>'.esc_html__('Above', 'loginizer-security').'</label>
					<label><input type="radio" name="button_position" value="above_plus" '.(!empty($social_settings['ultimate_member']['button_position']) ? checked($social_settings['ultimate_member']['button_position'], 'above_plus', false) : '').'>'.esc_html__('Above with Seperator', 'loginizer-security').'</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="social-alignment">'.esc_html__('Container Alignment', 'loginizer-security').'</label></th>
				<td class="loginizer-general-settings">
					<label><input type="radio" name="alignment" id="social-alignment" value="left" '.(empty($social_settings['ultimate_member']['alignment']) ? 'checked' : checked($social_settings['ultimate_member']['alignment'], 'left', false)).'>Left</label>
					<label><input type="radio" name="alignment" value="center" '.(!empty($social_settings['ultimate_member']['alignment']) ? checked($social_settings['ultimate_member']['alignment'], 'center', false) : '').'>'.esc_html__('Center', 'loginizer-security').'</label>
					<label><input type="radio" name="alignment" value="right" '.(!empty($social_settings['ultimate_member']['alignment']) ? checked($social_settings['ultimate_member']['alignment'], 'right', false) : '').'>'.esc_html__('Right', 'loginizer-security').'</label>
				</td>
			</tr>
			<tr>
			<th scope="row"><label for="social-alignment">'.esc_html__('Button Alignment', 'loginizer').'</label></th>
				<td class="loginizer-general-settings">
					<label><input type="radio" name="button_alignment" id="social-alignment" value="left" '.(empty($social_settings['ultimate_member']['button_alignment']) ? 'checked' : checked($social_settings['ultimate_member']['button_alignment'], 'left', false)).'>Left</label>
					<label><input type="radio" name="button_alignment" value="center" '.(!empty($social_settings['ultimate_member']['button_alignment']) ? checked($social_settings['ultimate_member']['button_alignment'], 'center', false) : '').'>'.esc_html__('Center', 'loginizer').'</label>
					<label><input type="radio" name="button_alignment" value="right" '.(!empty($social_settings['ultimate_member']['button_alignment']) ? checked($social_settings['ultimate_member']['button_alignment'], 'right', false) : '').'>'.esc_html__('Right', 'loginizer').'</label>
				</td>
			</tr>
		</table>';
		wp_nonce_field('loginizer_social_nonce', 'security');
		echo '<input type="submit" class="button button-primary" name="ultimate_member_settings" value="'.esc_html__('Save Settings', 'loginizer').'"/>
		</form>';

}

/**
 * @param array $provider_settings  This is the settings of the specific provider
 * @param string $provider Name of the provider.
 */
function loginizer_pro_social_api_settings($provider_settings, $provider){
	global $loginizer;

	$allowed_providers = loginizer_pro_social_auth_providers();

	if(!in_array($provider, $allowed_providers)){
		return;
	}

	echo '<tr>
		<th scope="row"><label for="loginizer_social_key">'.esc_html__('Use Loginizer\'s Social Auth', 'loginizer-security').'</label></th>
		<td><input type="checkbox" name="loginizer_social_key" id="loginizer_social_key" '.(!empty($provider_settings['loginizer_social_key']) ? 'checked' : '').' value="1" onChange="loginizer_toggle_social_keys(event)"/>'.((time() < strtotime('31 July 2025')) ? ' <span style="background-color:red; color:white;margin-left:5px; padding:3px; font-size:10px;border-radius:3px;">New</span>' : '').'
		<p class="description">'.esc_html__('Uses Loginizer\'s keys — no need to add your own.', 'loginizer-security').'</p>';
		if(defined('SITEPAD')){
			echo '<p class="description">'.esc_html__('An active license is required.', 'loginizer-security') .'</p>';
		}else{
			echo '<p class="description">'.esc_html__('An active license is required.', 'loginizer-security').(!empty($loginizer['license']) && !empty($loginizer['license']['active']) ? '<span class="dashicons dashicons-yes" style="color:green;"></span>' : ' <a href="'.esc_url(admin_url('admin.php?page=loginizer')).'">Update License</a>').'</p>';
		}
	echo '</td>
	</tr>';
	
	wp_register_script( 'loginizer-social-api', '', ['jquery'], '', true );
	wp_enqueue_script('loginizer-social-api');
	wp_add_inline_script('loginizer-social-api', "function loginizer_toggle_social_keys(e){
		let ms_account_type = jQuery('#loginizer_social_ms_account_type');
		if(e.target.checked){
			jQuery('#loginizer_social_client_id').closest('tr').hide();
			jQuery('#loginizer_social_client_secret').closest('tr').hide();

			if(ms_account_type.length){
				ms_account_type.closest('tr').hide();
			}

			return;
		}

		jQuery('#loginizer_social_client_id').closest('tr').show();
		jQuery('#loginizer_social_client_secret').closest('tr').show();
		if(ms_account_type.length){
			ms_account_type.closest('tr').show();
		}
	}");
}

function loginizer_pro_social_auth_notice(){
	
	if(!logininizer_pro_show_social_auth_notice()){
		if(!logininizer_pro_is_social_icons_visible()){
			return;
		}

		echo '<div class="notice inline notice-warning notice-alt" style="margin-left:0; margin-right:0;">
		<p style="display:flex; align-items:center; gap:5px;">'.esc_html__('Loginizer Social Login is Enabled, to disable it', 'loginizer-security').' <button id="loginizer-pro-disable-social" class="button button-primary">Click Here</button><img src="'.esc_url(admin_url('/images/spinner.gif')).'" style="display:none;"/></p>
		</div>';

	} else {
		echo '<div class="notice inline notice-info notice-alt" style="margin-left:0; margin-right:0;">
	<p style="display:flex; align-items:center; gap:5px;">'.esc_html__('Enable Loginizer Social Auth in a single click. This button will enable Social Auth for Google, GitHub, LinkedIn and X (Twitter).', 'loginizer-security').' <button class="loginizer-pro-quick-social button button-primary">Enable Now</button><img src="'.esc_url(admin_url('/images/spinner.gif')).'" style="display:none;"/></p>
</div>';
	}

	wp_register_script('loginizer-pro-quick-social', '', ['jquery'], '', true);
	wp_enqueue_script('loginizer-pro-quick-social');
	wp_add_inline_script('loginizer-pro-quick-social', "jQuery(document).ready(function(){
		// Enabling Loginizer Social Auth
		jQuery('.loginizer-pro-quick-social').on('click', function(e){
			e.preventDefault();

			jQuery(e.target).next('img').show();

			jQuery.ajax({
				url : '".admin_url('admin-ajax.php')."',
				method: 'GET',
				data : {
					action : 'loginizer_pro_quick_social',
					security : '".wp_create_nonce('loginizer_quick_social')."'
				},
				success: function(res){
					if(res.success){
						window.location.reload();
						return;
					}

					jQuery(e.target).next('img').hide();
				}
			});
		});

		// Disabling Social buttons
		jQuery('#loginizer-pro-disable-social').on('click', function(e){
			e.preventDefault();

			jQuery(e.target).next('img').show();

			jQuery.ajax({
				url : '".admin_url('admin-ajax.php')."',
				method: 'GET',
				data : {
					action : 'loginizer_pro_disable_social',
					security : '".wp_create_nonce('loginizer_quick_social')."'
				},
				success: function(res){
					if(res.success){
						alert('Social login has been disabled');
						window.location.reload();
						return;
					}

					jQuery(e.target).next('img').hide();
				}
			});
		});
	})");
}

function loginizer_pro_social_auth_release_notice(){

	if(
		get_option('loginizer_keyless_social_auth_notice', 0) < 0 || 
		empty(get_option('loginizer_pro_less_than_201', false)) || 
		!logininizer_pro_show_social_auth_notice()
	){
		return;
	}

	echo '<div class="notice notice-info is-dismissible" id="loginizer-pro-social-auth-notice">
		<p style="display:flex; align-items:center; gap:5px;">'.sprintf(esc_html__('Loginizer now supports Social Login for Google, GitHub, LinkedIn and X (Twitter) via its own %sAuthentication layer%s requiring %sZero Configuration.%s', 'loginizer-security'), '<a href="'.LOGINIZER_DOCS.'social-login/how-to-setup-social-login-with-loginizer-social-auth/" target="_blank">', '</a>', '<b>', '</b>').' <button class="loginizer-pro-quick-social button button-primary">Enable Now</button><img src="'.esc_url(admin_url('/images/spinner.gif')).'" style="display:none;"/></p> 
	</div>';

	wp_register_script('loginizer-pro-social-auth', '', ['jquery'], '', true);
	wp_enqueue_script('loginizer-pro-social-auth');
	wp_add_inline_script('loginizer-pro-social-auth', "jQuery(document).ready(function(){
		
		// Disable Social Login
		jQuery('#loginizer-pro-social-auth-notice .notice-dismiss').on('click', function(e){
			e.preventDefault();

			jQuery.ajax({
				url : '".admin_url('admin-ajax.php')."',
				method: 'GET',
				data : {
					action : 'loginizer_pro_social_auth_notice',
					security : '".wp_create_nonce('loginizer_social_auth')."'
				},
				success: function(res){
					//console.log(res);
				}
			});
		});
		
		// Enable Social Login
		jQuery('.loginizer-pro-quick-social').on('click', function(e){
			e.preventDefault();

			jQuery(e.target).next('img').show();

			jQuery.ajax({
				url : '".admin_url('admin-ajax.php')."',
				method: 'GET',
				data : {
					action : 'loginizer_pro_quick_social',
					security : '".wp_create_nonce('loginizer_quick_social')."'
				},
				success: function(res){
					if(res.success){
						alert('".esc_html__('Loginizer Social Authentication is now enabled !', 'loginizer-security')."');
						window.location.reload();
						return;
					}

					jQuery(e.target).next('img').hide();
				}
			});
			
		});
	})");
}

function logininizer_pro_show_social_auth_notice(){
	global $loginizer;
	
	// We should not show the notice if the loginizer auth option is enabled for any social provider
	// As that would mean the user is aware of this feature.
	$provider_settings = get_option('loginizer_provider_settings', []);
	$allowed_providers = loginizer_pro_social_auth_providers();
	
	if(!empty($provider_settings)){
		foreach($allowed_providers as $provider){
			if(!empty($provider_settings[$provider]) && !empty($provider_settings[$provider]['enabled']) && !empty($provider_settings[$provider]['loginizer_social_key'])){
				return false;
			}
		}
	}
	
	return true;
}

function logininizer_pro_is_social_icons_visible(){
	global $loginizer;

	$social_settings = get_option('loginizer_social_settings', []);

	if(!empty($social_settings)){
		foreach($social_settings as $setting){
			if(!empty($setting['enable_buttons']) || !empty($setting['login_form']) || !empty($setting['registration_form'])){
				return true;
			}
		}
	}

	return false;
}

function loginizer_pro_assets_enqueue(){
	wp_enqueue_script('loginizer-pro-admin', LOGINIZER_PRO_DIR_URL .'/assets/js/admin.js', ['jquery'], LOGINIZER_PRO_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
}

