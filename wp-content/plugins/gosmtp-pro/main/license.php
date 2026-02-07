<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

include_once(GOSMTP_DIR.'/main/settings.php');

function gosmtp_license(){
	
	global $lic_resp;
	
	if(!wp_verify_nonce($_POST['gosmtp_license_nonce'], 'gosmtp_license')){
		gosmtp_notify( __('Security Check Failed'), 'error');
		return;
	}

	$license = sanitize_key($_POST['gosmtp_license']);
	
	if(empty($license)){
		gosmtp_notify(__('The license key was not submitted'), 'error');
		return;
	}
	
	gosmtp_pro_load_license($license);
	
	if(!is_array($lic_resp)){
		gosmtp_notify(__('The response was malformed<br>'.var_export($lic_resp, true)), 'error');
		return;
	}

	$json = json_decode($lic_resp['body'], true);

	if(empty($json['license'])){
		gosmtp_notify(__('The license key is invalid'), 'error');
		return;
	}
	
	gosmtp_notify(__('Successfully updated the license key'));
	
}
	
function gosmtp_notify($message, $type = 'updated', $dismissible = true){
	$is_dismissible = '';
	
	if(!empty($dismissible)){
		$is_dismissible = 'is-dismissible';
	}
	
	if(!empty($message)){
		echo '<div class="'.$type.' '.$dismissible.' notice">
			<p>'.$message.'</p>
		</div>';
	}
}
	
if(isset($_REQUEST['save_gosmtp_license'])){
	gosmtp_license();
}
?>
	
<div class="gosmtp-license-content wrap" >
	<?php gosmtp_page_header('GOSMTP License'); ?>
	<div class="gosmtp-tab-group"  style=" width:100% ;background:white; padding:50px; box-sizing:border-box;border:1px solid #c3c4c7;">
		<h3><?php _e('System Information'); ?></h3>
		<table class="wp-list-table fixed striped users gosmtp-license-table" cellspacing="1" border="0" width="100%" cellpadding="10" align="center">
			<tbody>
				<tr>				
					<th align="left" width="25%"><?php esc_html_e('GOSMTP Version', 'gosmtp'); ?></th>
					<td><?php
						echo GOSMTP_PRO_VERSION.' (Pro Version)';
					?>
					</td>
				</tr>
				<tr>			
					<th align="left" valign="top"><?php esc_html_e('GOSMTP License', 'gosmtp'); ?></th>
					<td align="left">
						<form method="post" action="">
							<?php echo (defined('GOSMTP_PREMIUM') && empty($gosmtp->license['license']) ? '<span style="color:red">Unlicensed</span> &nbsp; &nbsp;' : '')?>
							<input type="hidden" name="gosmtp_license_nonce" value="<?php echo wp_create_nonce('gosmtp_license');?>"/>
							<input type="text" name="gosmtp_license" value="<?php echo (empty($gosmtp->license['license']) ? '': $gosmtp->license['license'])?>" size="30" placeholder="e.g. GSMTP-11111-22222-33333-44444" style="width:300px;"> &nbsp; 
							<input name="save_gosmtp_license" class="button button-primary dosmtp-sumbit-licence" value="Update License" type="submit">
						</form>
						<?php if(!empty($gosmtp->license['license'])){
								
								$expires = $gosmtp->license['expires'];
								$expires = substr($expires, 0, 4).'/'.substr($expires, 4, 2).'/'.substr($expires, 6);
								
								echo '<div style="margin-top:10px;">License Status : '.(empty($gosmtp->license['status_txt']) ? 'N.A.' : wp_kses_post($gosmtp->license['status_txt'])).' &nbsp; &nbsp; &nbsp; 
								'.($gosmtp->license['expires'] <= date('Ymd') ? 'License Expires : <span style="color:var(--red)">'.esc_attr($expires).'</span>' : (empty($gosmtp->license['has_plid']) ? 'License Expires : '.esc_html($expires) : '')).'
								</div>';
								
						}?>
					</td>
				</tr>
				<tr>
					<th align="left">URL</th>
					<td><?php echo get_site_url(); ?></td>
				</tr>
				<tr>				
					<th align="left">Path</th>
					<td><?php echo ABSPATH; ?></td>
				</tr>
				<tr>
					<th align="left"><?php _e('Server\'s IP Address') ?></th>
					<td><?php echo esc_url($_SERVER['SERVER_ADDR']); ?></td>
				</tr>
				<tr>				
					<th align="left">.htaccess <?php _e('is writable') ?></th>
					<td><?php echo (is_writable(ABSPATH.'/.htaccess') ? '<span style="color:var(--gosmtp-red)">Yes</span>' : '<span style="color:green">No</span>');?></td>
				</tr>		
			</tbody>
		</table>
	</div>
	<?php gosmtp_page_footer(); ?>
</div>
	 
	