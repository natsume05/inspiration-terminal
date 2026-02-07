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

// license page display
class License{
	static function template(){
		global $siteseo;
		
		// Add header
		echo'<div id="siteseo-root">';
		if(function_exists('siteseo_admin_header')){
			siteseo_admin_header();
		}

		if(isset($_REQUEST['save_siteseo_pro_license'])){
			self::save();
		}

		echo '<div class="siteseo-license-tab">
			<div class="siteseopro-setting-content">
			<div class="siteseopro-tab-group">
				<table class="wp-list-table fixed striped users siteseopro-license-table" cellspacing="1" border="0" width="78%" cellpadding="10" align="center">
					<tbody>
						<tr>
							<th align="left" width="25%">' . esc_html__('Siteseo Version', 'siteseo-pro') . '</th>
							<td>' . (defined('SITESEO_PRO_VERSION') ? esc_html(SITESEO_PRO_VERSION) . ' (Pro Version)' : 'N/A') . '</td>
						</tr>
						<tr>
							<th align="left" valign="top">' . esc_html__('Siteseo License', 'siteseo-pro') . '</th>
							<td align="left">
								<form method="post" action="">
									<span style="color:red">' . (defined('SITESEO_PRO_VERSION') && empty($siteseo->license['active']) ? '<span style="color:red">Unlicensed</span> &nbsp; &nbsp;' : '') . '</span>
									<input type="hidden" name="siteseo_pro_license_nonce" value="' . wp_create_nonce('siteseo_pro_license') . '"/>
									<input type="text" name="siteseo_pro_license" value="' . (empty($siteseo->license['license']) ? (empty($_POST['siteseo_pro_license']) ? '' : esc_html($_POST['siteseo_pro_license'])) : esc_html($siteseo->license['license'])) . '" size="30" placeholder="e.g. SITESEO-11111-22222-33333-44444" style="width:300px;"> &nbsp; 
									<br><br><input name="save_siteseo_pro_license" class="siteseopro-btn siteseopro-btn-primary" value="Update License" type="submit">
								</form>';
								if(!empty($siteseo->license)){
									$expires = $siteseo->license['expires'];
									$expires = substr($expires, 0, 4) . '/' . substr($expires, 4, 2) . '/' . substr($expires, 6);
									echo '<div style="margin-top:10px;">License Status : ' . (empty($siteseo->license['status_txt']) ? 'N.A.' : wp_kses_post($siteseo->license['status_txt'])) . ' &nbsp; &nbsp; &nbsp; 
									' . ($siteseo->license['expires'] <= gmdate('Ymd') ? 'License Expires : <span style="color:red">' . esc_attr($expires) . '</span>' : (empty($siteseo->license['has_plid']) ? 'License Expires : ' . esc_html($expires) : '')) . '</div>';
								}
							echo '</td>
						</tr>
						<tr>
							<th align="left">URL</th>
							<td>' . esc_url(get_site_url()) . '</td>
						</tr>
						<tr>
							<th align="left">Path</th>
							<td>' . esc_html(ABSPATH) . '</td>
						</tr>
						<tr>
							<th align="left">Server\'s IP Address</th>
							<td>' . esc_html($_SERVER['SERVER_ADDR']) . '</td>
						</tr>
						<tr>
							<th align="left">.htaccess is writable</th>
							<td>' . (is_writable(ABSPATH . '.htaccess') ? '<span style="color:red">Yes</span>' : '<span style="color:green">No</span>') . '</td>
						</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		</div>';
	}
	
	static function save(){
		global $siteseo, $lic_resp;
		
		if(!wp_verify_nonce($_POST['siteseo_pro_license_nonce'], 'siteseo_pro_license')){
			echo '<div class="notice notice-error is-dismissible"><p>';
			echo esc_html__('Security Check Failed', 'siteseo-pro');
			echo '</p></div>';
			return;
		}

		$license = sanitize_text_field(wp_unslash($_POST['siteseo_pro_license']));

		if(empty($license)){
			echo '<div style="margin-top:65px;" class="notice notice-error is-dismissible"><p>';
			echo esc_html__('The license key was not submitted', 'siteseo-pro');
			echo '</p></div>';
			return;
		}
		
		siteseo_pro_load_license($license);
		
		if(is_wp_error($lic_resp) || 200 !== wp_remote_retrieve_response_code($lic_resp)){
			if(is_wp_error($lic_resp)){
				echo '<div style="margin-top:65px;" class="notice notice-error is-dismissible"><p>';
				echo esc_html($lic_resp->get_error_message());
				echo '</p></div>';
				return;
			} else{
				echo '<div style="margin-top:65px;" class="notice notice-error is-dismissible"><p>';
				echo esc_html__('An error occurred, please try again. Response code: ', 'siteseo-pro') . esc_attr(wp_remote_retrieve_response_code($lic_resp));
				echo '</p></div>';
				return;
			}
		} else {
			$tmp = json_decode(wp_remote_retrieve_body($lic_resp), true);
			 if(empty($tmp)){
				echo '<div style="margin-top:65px;" class="notice notice-error is-dismissible"><p>';
				echo esc_html__('The license key is invalid', 'siteseo-pro');
				echo '</p></div>';
				return;
			}
			
			echo'<div class="siteseo-notice is-success">
				<p>'. esc_html__('Your license has been successfully activated!', 'siteseo-pro').'
				</p>
			</div>';
		}
	}
}