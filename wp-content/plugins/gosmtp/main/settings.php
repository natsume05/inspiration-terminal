<?php
/*
* GoSMTP
* https://gosmtp.net
* (c) Softaculous Team
*/

if(!defined('GOSMTP_VERSION')){
	die('Hacking Attempt!');
}
	
// The Gosmtp Header
function gosmtp_page_header($title = 'GoSMTP'){
	
    $promos = apply_filters('gosmtp_review_link', true);
	
	wp_enqueue_style( 'gosmtp-admin' );
	wp_enqueue_script( 'gosmtp-admin' );
	
	echo '
<div class="gosmtp-box-container" style="margin:0">
	<h2>
		<table cellpadding="2" cellspacing="1" width="100%" class="fixed" border="0">
			<tr>
				<td valign="top">
					<h1>'.esc_html($title).'</h1>
				</td>
				'.($promos ? '
				<td align="right"><a target="_blank" class="button button-primary" href="https://wordpress.org/support/view/plugin-reviews/gosmtp">Review GoSMTP</a></td>' : '').'
				<td align="right" width="40"><a target="_blank" href="https://twitter.com/gosmtp"><img src="'.GOSMTP_URL.'/images/twitter.png" /></a></td>
				<td align="right" width="40"><a target="_blank" href="https://www.facebook.com/gosmtp/"><img src="'.GOSMTP_URL.'/images/facebook.png" /></a></td>
			</tr>
		</table>
	</h2>
	'.gosmtp_show_notices().'
	<hr/>
	<!--Main Table-->
	<table cellpadding="8" cellspacing="1" width="100%" class="fixed">
	<tr>
		<td valign="top">';

}

// The Gosmtp Settings footer
function gosmtp_page_footer($no_twitter = 0){
	
	echo '</td>';
	
	$promos = apply_filters('pagelayer_right_bar_promos', true);

	if($promos){

		echo '
	<td width="200" valign="top" id="pagelayer-right-bar">
		<div class="gosmtp-promotion" style="width:100%;" >
			<div class="gosmtp-promotion-content" style="background:white; border:1px solid #c3c4c7; padding:10px 10px 20px 10px; border-radius:6px;">
				<h2 class="gosmtp-promotion-logo">
					<span><a target="_blank" href="https://pagelayer.com/?from=gosmtp-plugin"><img src="'. GOSMTP_URL.'/images/pagelayer_product.png" width="100%"></a></span>
				</h2>
				<div>
					<em>The Best WordPress <b>Site Builder</b> </em>:<br>
					<ul style="font-size:13px;">
						<li>Drag &amp; Drop Editor</li>
						<li>Widgets</li>
						<li>In-line Editing</li>
						<li>Styling Options</li>
						<li>Animations</li>
						<li>Easily customizable</li>
						<li>Real Time Design</li>
						<li>And many more ...</li>
					</ul>
					<center><a class="button button-primary" target="_blank" href="https://pagelayer.com/?from=gosmtp-plugin">Visit Pagelayer</a></center>
				</div>
			</div>

			<div class="gosmtp-promotion-content" style="margin-top: 20px; background:white; border:1px solid #c3c4c7; padding:10px 10px 20px 10px; border-radius:6px;">
				<h2 class="gosmtp-promotion-logo">
					<span><a target="_blank" href="https://loginizer.com/?from=gosmtp-plugin"><img src="'.GOSMTP_URL.'/images/loginizer_product.png" width="100%"></a></span>
				</h2>
				<div>
					<em>Protect your WordPress website from <b>unauthorized access and malware</b> </em>:<br>
					<ul style="font-size:13px;">
						<li>BruteForce Protection</li>
						<li>reCaptcha</li>
						<li>Two Factor Authentication</li>
						<li>Black/Whitelist IP</li>
						<li>Detailed Logs</li>
						<li>Extended Lockouts</li>
						<li>2FA via Email</li>
						<li>And many more ...</li>
					</ul>
					<center><a class="button button-primary" target="_blank" href="https://loginizer.com/?from=gosmtp-plugin">Visit Loginizer</a></center>
				</div>
			</div>
		</div>';
		
	}
	
	echo '</tr>
	</table>
	<br />';
	if(!defined('SITEPAD')){       
		if(empty($no_twitter)){
			echo '
				<div style="width:45%;background:#FFF;padding:15px; margin:20px auto; border:1px solid #c3c4c7;">
					<b>Let your followers know that you use GOSMTP to Sent mail on your website :</b>
					<form method="get" action="https://twitter.com/intent/tweet" id="tweet" onsubmit="return dotweet(this);">
						<textarea name="text" cols="45" row="3" style="resize:none;">I increased email deliverability of my #WordPress #site using @gosmtp</textarea>&nbsp; &nbsp; 
						<input type="submit" value="Tweet!" class="button button-primary" onsubmit="return false;" id="twitter-btn" style="margin-top:20px;"/>
					</form>	
				</div>
				<br/>

				<script>
						function dotweet(ele){
						window.open(jQuery("#"+ele.id).attr("action")+"?"+jQuery("#"+ele.id).serialize(), "_blank", "scrollbars=no, menubar=no, height=400, width=500, resizable=yes, toolbar=no, status=no");
						return false;
					}
				</script>
				
				<a href="'.GOSMTP_WWW_URL.'" target="_blank">GOSMTP</a><span> v'.GOSMTP_VERSION.' You can report any bugs </span><a href="http://wordpress.org/support/plugin/gosmtp" target="_blank">here</a>.
			</div>';
		
		}
	}
}

// GoSMTP Setting page
function gosmtp_settings_page(){

	$action = gosmtp_optget('auth');
	$state = gosmtp_optget('state');
	$code = gosmtp_optget('code');
	
	// For authentication of Gmail, Zoho and Outlook
	if(!empty($action) && !empty($state) && !empty($code)){
	
		$resp = explode('-',$state);

		$uri = admin_url().'admin.php?page=gosmtp&auth_code='.$code.'&auth='.$action;

		if(!empty($resp[1])){
			$uri.='&type=edit&&conn_id='.$resp[1].'#gosmtp-connections-settings';
		}
	
		echo '<script>window.location.href="'.$uri.'";</script>';
		return;
	}
	
	if(defined('GOSMTP_PREMIUM')){
		include_once GOSMTP_PRO_DIR .'/main/settings.php';
	}
	
	$all_mailers = gosmtp_load_mailer_list();
	$options = get_option('gosmtp_options', array());
	
	//do_action('gosmtp_before_settings_page_load');
	
	// Save SMTP details
	if (isset($_REQUEST['save'])) {
		
		// Check nonce
		check_admin_referer('gosmtp-options');

		$force_from_email = !empty($_REQUEST['force_from_email']) ? 1 : '';
		$force_from_name = !empty($_REQUEST['force_from_name']) ? 1 : '';
		$return_path = !empty($_REQUEST['return_path']) ? 1 : '';

		$save_mailer = gosmtp_optreq('mailer');
		$option['mail_type'] = $save_mailer;
		
		if(isset($_REQUEST['nickname'])){
			$option['nickname'] = gosmtp_optreq('nickname');
		}

		if(isset($_REQUEST['backup_mailer'])){
			$option['backup_connection'] = gosmtp_optreq('backup_mailer');
		}
		
		if(!isset($options['mailer'])){
			$options['mailer'] = [];
		}
		
		$mailer_id = 0;
		$redirect = false;
	
		// Update fields of mailer
		if(!empty($save_mailer) && !method_exists($all_mailers[$save_mailer], 'save_option')){
			
			// Set for primary email
			if(!isset($options['mailer'][0]) || (isset($_REQUEST['conn_id']) && is_numeric($_REQUEST['conn_id']) && $_REQUEST['conn_id'] == 0)){
				$options['from_email'] = gosmtp_optreq('from_email');
				$options['force_from_email'] = $force_from_email;
				$options['from_name'] = gosmtp_optreq('from_name');
				$options['force_from_name'] = $force_from_name;
				$options['return_path'] = $return_path;	
				$options['mailer'][0] = $all_mailers[$save_mailer]->save_options($option);
			}else{
			
				if(!empty($_REQUEST['conn_id'])){
					$mailer_id = $_REQUEST['conn_id'];
				}else{
					$redirect = true;
					$mailer_id = gosmtp_RandomId();
				}
				
				$option['from_email'] = gosmtp_optreq('from_email');
				$option['force_from_email'] = $force_from_email;
				$option['from_name'] = gosmtp_optreq('from_name');
				$option['force_from_name'] = $force_from_name;
				$option['return_path'] = $return_path;
				$options['mailer'][$mailer_id] = $all_mailers[$save_mailer]->save_options($option);
			}
		}

		$options = apply_filters( 'gosmtp_save_options', $options );
		
		if(update_option( 'gosmtp_options', $options )){
			$msg['success'] = 1;
			
			if($redirect){
				$uri = admin_url().'admin.php?page=gosmtp&type=edit&conn_id='.$mailer_id.'#gosmtp-connections-settings';
				echo '<script>window.location.href="'.$uri.'";</script>';
			}
		}
	}

	if(!empty($_REQUEST['delete_connection'])){
	
		// Check nonce
		check_admin_referer('gosmtp-options');

		$options = get_option('gosmtp_options', array());
		$conn_id = gosmtp_optreq('delete_connection');
		
		if(!empty($conn_id)){
			$backup_id = isset($options['mailer'][0]['backup_connection']) ? $options['mailer'][0]['backup_connection'] : '';

			if($backup_id == $conn_id){
				unset($options['mailer'][0]['backup_connection']);
			}

			// Remove connection
			unset($options['mailer'][$conn_id]);

			if(update_option( 'gosmtp_options', $options )){
				$msg['success'] = 1;
			}
		}
	}

	if(!empty($_REQUEST['make_backup_connection'])){
	
		// Check nonce
		check_admin_referer('gosmtp-options');

		$options = get_option('gosmtp_options', array());
		$conn_id = gosmtp_optreq('make_backup_connection');

		$options['mailer'][0]['backup_connection'] = $conn_id;

		if(update_option( 'gosmtp_options', $options )){
			$msg['success'] = 1;
		}
	}

	if(!empty($_REQUEST['clear_backup_connection'])){
	
		// Check nonce
		check_admin_referer('gosmtp-options');

		$options = get_option('gosmtp_options', array());
		$conn_id = gosmtp_optreq('clear_backup_connection');

		if(isset($options['mailer'][0]) && $options['mailer'][0]['backup_connection'] == $conn_id){
			unset($options['mailer'][0]['backup_connection']);
		}

		if(update_option( 'gosmtp_options', $options )){
			$msg['success'] = 1;
		}
	}

	// Save Notification Settings
	if(defined('GOSMTP_PRO_VERSION') && isset($_REQUEST['save_notification_settings'])){
		do_action('gosmtp_pro_save_notification_settings');
	}

	// SMTP Settings
	if(isset($_REQUEST['save_settings'])){

		// Check nonce
		check_admin_referer('gosmtp-settings');

		$enable_logs = '';
		if(!empty($_REQUEST['enable_logs'])){
			
			$enable_logs = 1;
			
			if(class_exists('\GOSMTP\Logger')){
				$logger = new \GOSMTP\Logger();
				$logger->create_table();
			}
			
		}
		
		$save_attach = '';
		if(!empty($_REQUEST['log_attachments'])){
			$save_attach = 1;
		}
		
		$ret_period = '';
		if(!empty($_REQUEST['retention_period'])){
			$ret_period = sanitize_text_field($_REQUEST['retention_period']);
			
			// Cron already scheduled?
			if( ! empty($_REQUEST['enable_logs']) && ! wp_next_scheduled( 'gosmtp_log_retention_cron' ) ){
				// Set cron schedule
				wp_schedule_event( time(), 'daily', 'gosmtp_log_retention_cron' );
			}
		}
		
		// Is log retention cron scheduled?
		if(wp_next_scheduled( 'gosmtp_log_retention_cron' ) && (empty($_REQUEST['enable_logs']) || empty($_REQUEST['retention_period']))){
			wp_clear_scheduled_hook('gosmtp_log_retention_cron');
		}
		
		$log_columns = '';
		if(!empty($_REQUEST['log_columns'])){
			$log_columns = maybe_serialize(($_REQUEST['log_columns']));
		}
		
		$enable_weekly_reports = '';
		if(!empty($_REQUEST['enable_weekly_reports'])){
			$enable_weekly_reports = 1;
			
			// Cron already scheduled?
			if(!wp_next_scheduled( 'gosmtp_weekly_email_reports_cron', array(true) ) ){
				// Set cron schedule
				wp_schedule_event( time(), 'daily', 'gosmtp_weekly_email_reports_cron', array(true));
			}
			
		// Is log weekly email cron scheduled?
		}elseif( wp_next_scheduled( 'gosmtp_weekly_email_reports_cron' , array(true) ) ){
			wp_clear_scheduled_hook('gosmtp_weekly_email_reports_cron', array(true));
		}

		$weekday = '';
		$timestamp = '';
		
		if(!empty($_REQUEST['weekday']) && $enable_weekly_reports){
			$weekday = $_REQUEST['weekday'];
			$timestamp = strtotime("next ".$weekday);
		}	
				
		$options['logs'] = [];
		$options['logs']['enable_logs'] = $enable_logs;
		$options['logs']['log_attachments'] = $save_attach;
		$options['logs']['retention_period'] = $ret_period;
		$options['logs']['log_columns'] = $log_columns;
		$options['weekly_reports'] = [];
		$options['weekly_reports']['enable_weekly_reports'] = $enable_weekly_reports;
		$options['weekly_reports']['weekday'] = $weekday;
		$options['weekly_reports']['timestamp'] = $timestamp;
		
		$options = apply_filters( 'gosmtp_save_logs_settings', $options );
		
		if(update_option( 'gosmtp_options', $options )){
			$msg['success'] = 1;
		}
		
	}
	
	$smtp_options = get_option('gosmtp_options', array());
	
	// Default mailer set mail
	if(!isset($smtp_options['mailer']) || !is_array($smtp_options['mailer']) || empty($smtp_options['mailer'][0])){
		$smtp_options['mailer'] = [];
		$smtp_options['mailer'][0]['mail_type'] = 'mail';
	} 
	
	// Used to show error / success message for the settings
	settings_errors();

	echo '<div class="wrap">';
	
	gosmtp_page_header();
	
	if(!empty($msg['success'])){
		echo '<div id="message" class="updated notice is-dismissible"><p>'.__('SMTP settings have been saved successfully!', 'gosmtp').'</p></div>';
	}

	if(!empty($send_mail['success'])){
		echo '<div id="message" class="updated notice is-dismissible"><p>'.__('Mail sent successfully!', 'gosmtp').'</p></div>';
	}else if(!empty($send_mail['error'])){
		echo '<div id="message" class="error notice is-dismissible">
		<p>'.( !empty($send_mail['error_msg']) ? wp_kses_post($send_mail['error_msg']) : __('Unable to send mail, Please check your SMTP details', 'gosmtp') ).'</p></div>';
	}
	
	$tabs_nav = array(
		'smtpsetting' => __('SMTP Settings', 'gosmtp'), 
		'test-mail' => __('Test Mail', 'gosmtp')	
	);

	if(!defined('SITEPAD')){
		$tabs_nav['support'] = __('Support', 'gosmtp');
	}
	
	$tabs_nav = apply_filters('gosmtp_settings_tabs_nav', $tabs_nav);
	?>
	<div class="gosmtp-setting-content">
		<div class="tabs-wrapper">
			<h2 class="nav-tab-wrapper gosmtp-wrapper">
				<?php
					foreach($tabs_nav as $id => $title){
						$classes = 'nav-tab';
						
						if($id == 'smtpsetting'){
							$classes .= ' nav-tab-active';
						}
						
						echo '<a href="#'. esc_attr($id) .'" class="'.esc_attr($classes).'">'.esc_html($title).'</a>';
					}
				?>
			</h2>
			<div class="gosmtp-tab-panel" id="smtpsetting">
				<form class="gosmtp-smtp-mail" name="smtp-details" method="post" action="">
					<?php  
						gosmtp_mailer_settings($smtp_options);
					?>
				</form>	
			</div>
			<div class="gosmtp-tab-panel" id="test-mail" style="display:none">
				<form class="gosmtp-smtp-mail" id="smtp-test-mail" name="test-mail" method="post" action="">
					<table class="form-table">
						<tr>
							<th scope="row"><?php _e('To', 'gosmtp'); ?>:</th>
							<td>
								<input type="email" name="reciever_test_email" class="regular-text" placeholder="example@example.com" required />
								<p class="description" id="tagline-description"><?php _e( 'Enter the recipient\'s email address.', 'gosmtp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php _e('Subject', 'gosmtp'); ?>:</th>
							<td>
								<input type="text" name="smtp_test_subject" class="regular-text" placeholder="Enter Subject" value="Test Mail" required />
								<p class="description" id="tagline-description"><?php _e( 'Enter a subject for your message.', 'gosmtp' ); ?></p>
							</td>
						</tr>
						<tr class="gosmtp-test-message">
							<th scope="row"><?php _e('Message', 'gosmtp'); ?></th>
							<td>
								<textarea name="smtp_test_message" placeholder="Enter Message" class="regular-text" rows="10"required ><?php _e('This is a test mail!', 'gosmtp'); ?></textarea>
								<p class="description" id="tagline-description"><?php _e( 'Write your email message', 'gosmtp' ); ?> </p>
							</td>
						</tr>
						<?php
						do_action('gosmtp_pro_test_connection_and_template');
						?>
					</table>
					<p>
						<input type="submit" name="send_mail" id="send_mail" class="button button-primary" value="Send Mail">
					</p>
				</form>
			</div>
			<div class="gosmtp-tab-panel" id="support" style="display:none">
				<div style="width:70%; margin:20px auto; display:flex; justify-content:center; flex-direction:column; align-items:center; line-height:1.5;">
					<img src="<?php echo esc_url(GOSMTP_URL) .'/images/gosmtp-text.png'?>" width="200"/>
					<h2><?php esc_html_e('You can contact the GoSMTP Team via email. Our email address is', 'gosmtp'); ?> <a href="mailto:support@gosmtp.net">support@gosmtp.net</a> <?php esc_html_e('or through Our Premium Support Ticket System at', 'gosmtp'); ?> <a href="https://softaculous.deskuss.com" target="_blank"><?php _e('here', 'gosmtp'); ?></a></h2>
				</div>
			</div>
			
			<?php do_action('gosmtp_after_settings_tab_panel')?>
		</div>
	</div>
	<?php gosmtp_page_footer(); ?>
</div>

<script>
	// TODO: move this in init.php
	var gosmtp_ajaxurl = "<?php echo admin_url( 'admin-ajax.php' ) ?>?";
	var gosmtp_ajax_nonce = "<?php echo wp_create_nonce('gosmtp_ajax') ?>";
</script>

<?php

}

function gosmtp_mailer_settings($smtp_options, $is_new_connection = false){
	$all_mailers = gosmtp_load_mailer_list();

	// For default mailer
	$settings = !empty($smtp_options['mailer'][0]) ? $smtp_options['mailer'][0] : array();
	$settings['from_email'] = isset($smtp_options['from_email']) ? $smtp_options['from_email'] : '';
	$settings['force_from_email'] = isset($smtp_options['from_email']) ? $smtp_options['force_from_email'] : '';
	$settings['from_name'] = isset($smtp_options['from_name']) ? $smtp_options['from_name'] : '';
	$settings['force_from_name'] = isset($smtp_options['force_from_name']) ? $smtp_options['force_from_name'] : '';
	$settings['return_path'] = isset($smtp_options['return_path']) ? $smtp_options['return_path'] : '';
	$brand_name = !defined('SITEPAD') ? __('WordPress', 'gosmtp') : BRAND_SM;
	
	// Is new connection?
	if($is_new_connection){
	 	if(empty($smtp_options)){
	 		$smtp_options = [];
	 		$smtp_options['mail_type'] = 'mail';
	 	}

	 	// For backup mailer
		$settings = $smtp_options;
	}

	wp_nonce_field('gosmtp-options'); 
?>
	<table class="form-table">
		<?php
		if($is_new_connection){
		?>
		<tr>
			<th scope="row"><?php _e('Connection Nickname', 'gosmtp'); ?></th>
			<td>
				<input name="nickname" type="text" class="regular-text always_active" placeholder="Default Connection"  value="<?php if(!empty($settings['nickname'])){
					echo esc_attr($settings['nickname']);
				}?>"> 
			</td>
		</tr>
		<?php
		}
		?>
		<tr>
			<th scope="row"><?php _e('From Email', 'gosmtp'); ?></th>
			<td>
				<input name="from_email" type="text" class="regular-text always_active" placeholder="notifications@example.com"  value="<?php if(!empty($settings['from_email'])){
					echo esc_attr($settings['from_email']);
				}?>"> 
				<p class="description" id="tagline-description">
				<?php
					printf(
						__('Set the from email address for your %s emails. If you\'re using an email provider (Gmail, Outlook.com, etc.), this should be your email address for that account', 'gosmtp'),
						esc_html($brand_name)
					);
				?>
				</p>
				<p class="description" id="tagline-description"><i><?php _e("Please note, enable the below setting to apply this setting.", 'gosmtp'); ?></i></p>
				<br>
				<input name="force_from_email" type="checkbox" <?php if(!empty($settings['force_from_email'])){
					echo "checked";
				}?>>
				<label><?php _e('Force From Email', 'gosmtp');?></label>
				<p class="description" id="tagline-description"><?php _e( 'If checked, From email set by other plugins will be ignored and the above Form Email setting will be used.', 'gosmtp'); ?></p>
			
			</td>
		</tr>

		<tr>
			<th scope="row"><?php _e('From Name', 'gosmtp'); ?></th>
			<td>
				<input name="from_name" type="text" class="regular-text always_active" placeholder="My Website"  value="<?php if(!empty($settings['from_name'])){
					echo esc_attr($settings['from_name']);
				}?>"> 
				<p class="description" id="tagline-description"><?php _e( "Set the from name that emails are sent from.", 'gosmtp' ); ?></p>
				<br>
				<input name="force_from_name" type="checkbox" <?php if(!empty($settings['force_from_name'])){
					echo "checked";
				}?>>
				<label><?php _e('Force From Name', 'gosmtp');?></label>
				<p class="description" id="tagline-description"><?php _e( 'If checked, From name set by other plugins will be ignored and the above Form Name setting will be used.', 'gosmtp' ); ?></p>
			
			</td>
		</tr>

		<tr>
			<th scope="row"><?php _e('Return Path', 'gosmtp'); ?></th>
			<td>
				<input class="mail sendlayer mailgun smtp" name="return_path" type="checkbox" <?php if(!empty($settings['return_path'])){
					echo "checked";
				}?>>
				<label><?php _e('Set return-path', 'gosmtp');?></label>
				<p class="description" id="tagline-description"><?php _e( 'Set return-path to match the From Email, the return path indicates where non-delivery receipts or bounce messages are to be sent. If unchecked, non-delivery messages may be lost.', 'gosmtp' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php _e('Mailer', 'gosmtp'); ?></th>
			<td class="mailer_container">

			<?php
			$mailer_list = gosmtp_get_mailer_list();
			
			foreach($mailer_list as $key => $mailer){
				$is_pro = $disabled = $after_icon = '';
				$active_mailer = (isset($settings['mail_type']) && $settings['mail_type'] == $key) ? 'mail_active' : '';
				$icon = isset($mailer['icon']) ? $mailer['icon'] : GOSMTP_URL .'/images/'.$key.'.svg';

				if(!class_exists($mailer['class'])){
					$is_pro = 'pro';
					$disabled = 'disabled';
					$after_icon='<div class="lock_icon">
						<span class="dashicons dashicons-lock"></span>
					</div>';
				}
				
				echo '<div class="gosmtp-mailer-input always_active '.esc_attr($is_pro).'">
					<label class="label">'. esc_html($mailer['title']) .'</label>
					<div for="'.esc_attr($key).'" class="mailer_label '.esc_attr($active_mailer).'" data-name="'.esc_attr($key).'">
						<img src="'. esc_attr($icon) .'" class="mailer" >
						'.wp_kses_post($after_icon).'
					</div>
					<input id="'.esc_attr($key).'" class="mailer_check" data-name="'.esc_attr($key).'" name="mailer" type="radio" '.esc_attr($disabled).' value="'. esc_attr($key) .'" '. checked( $key, (isset($settings['mail_type'])  ? $settings['mail_type'] : ''),false ).'>
				</div>';
			}
			?>
			</td>
		</tr>
		<?php
		
			foreach($all_mailers as $key => $mailer){
				
				if(!method_exists($mailer, 'load_field')){
					continue;
				}

				echo '<tr>
					<td><h1 class="'.esc_attr($key).' smtp_heading">'. esc_html($mailer->title) .'</h1><td>
				</tr>';
				
				// Load current options
				$mailer->loadOptions();
				
				$conn_id = $is_new_connection ? '' : 0;

				if($is_new_connection){
					$conn_id = gosmtp_optget('conn_id');
				}

				// Set connection id
				$mailer->conn_id = $conn_id;

				echo gosmtp_create_field($mailer->load_field(), $mailer);
			}
		?>
		<?php 
		if(defined('GOSMTP_PREMIUM') && isset($smtp_options['mailer'][0])){
		?>
		<tr class="always_active">
			<th scope="row"><?php _e('Backup Connection', 'gosmtp'); ?></th>
			<td>
				<select name="backup_mailer" class="regular-text">
					<option value=""><?php _e('None', 'gosmtp'); ?></option>
			<?php
			if(count($smtp_options['mailer']) > 1){
			?>
				
				<?php foreach($smtp_options['mailer'] as $key => $mailer){
					if($key === 0){
						continue;
					}
					$con_id = !empty($settings['backup_connection']) ? $settings['backup_connection'] : '';
					$conn_name = !empty($mailer['nickname']) ? $mailer['nickname'] : __('(No Name)', 'gosmtp');
					$conn_type = !empty($mailer['mail_type']) ? ucfirst($mailer['mail_type']) : '';
					echo "<option value='".esc_attr($key)."' ".selected($con_id, $key, true).">".esc_html($conn_name).' - ['.esc_html($conn_type)."]</option>";
				}
			}?>
				</select>
				<p class="description" id="tagline-description"><?php 
					printf(
						'Set backup email address for your %s emails. If primary mailer fails then backup mailer will be used to send the mail.',
						esc_html($brand_name)
					);
				?>
				</p>
			</td>
		</tr>
		<?php
		}
		?>
	</table>
	<p>
		<?php
			if(!empty($smtp_options['mailer'][0]) || !empty($settings['conn_id'])){
		?>
			<input type="hidden" name="conn_id" value="<?php echo esc_attr($is_new_connection && !empty($settings['conn_id']) ? $settings['conn_id'] : 0); ?>">
		<?php
			}
		?>
		<input type="submit" name="save" class="button button-primary" value="Save Changes">
	</p>
<?php
}

function gosmtp_create_field($fields, $mailer){
	$html = '';
	
	if(!is_array($fields)){
		return $html;
	}
	
	foreach($fields as $key => $field){
		
		$attrs = '';
		
		if(!empty($field['attr'])){
			$attrs .= esc_attr($field['attr']);
		}
		
		// Added attributes
		if(!in_array($field['type'], array('button', 'notice'))){
			
			$_type = ($field['type'] == 'copy') ? 'text' : $field['type'];
			
			$attrs .= ' type="'.esc_attr($_type).'"';
			$attrs .= ' name="'.esc_attr($mailer->mailer.'['.$key.']').'"';
		}
		
		// Added Classes
		$classes = esc_attr($mailer->mailer);
		
		if(!empty($field['class'])){
			$classes .= ' '.esc_attr($field['class']);
		}
		
		// Get value
		$val = $mailer->getOption($key, $mailer->mailer);
		
		if(empty($val)){
			$val = isset($field['default']) ? $field['default'] : '';
		}
		
		$placeholder = empty($field['place_holder']) ? '' : esc_attr($field['place_holder']);
		$list = empty($field['list']) ? array() : $field['list'];
		
		$input_html = '';
		
		switch($field['type']){
			case 'radio':
			
				foreach($list as $list_key => $list_value){
					$input_html .= '<input class="'.$classes.'" value="'.esc_attr($list_key).'" '.$attrs.''.checked($list_key, $val, false).'>
					<label>'.esc_html($list_value).'</label>';
				}
				
				break;

			case 'checkbox':
				
				$input_html .= '<input value="1" class="regular-text '.$classes.'" '.$attrs.' '.checked('1', $val, false).'>';
				
				break;

			case 'button':
			
				$href = empty($field['href']) ? '#' : esc_url($field['href']);
				
				$input_html .= '<a href="'.$href.'"class="button '.$classes.'" '.$attrs.'>'.esc_html($val).'</a>';
				
				break;
				
			case 'notice':

				$input_html .= '<div id="message" class="notice_container '.$classes.'">'.esc_html($val).'</div>';
				
				break;

			case 'select':

				$input_html .='<select class="regular-text '.$classes.'" '.$attrs.'>';
				
				foreach($list as $list_key => $list_value){
					$input_html .= '<option value="'.esc_attr($list_key).'" '.selected($list_key, $val, false).'>'.esc_html($list_value).'</option>';
				}
				
				$input_html .='</select>';
				
				break;
			
			 case 'copy':
			 
				$id = empty($field['id']) ? '' : esc_attr($field['id']);
				
				$input_html .= '<input class="regular-text gosmtp_copy '.$classes.'" placeholder="'.$placeholder.'" id="'.$id.'" value="'.esc_attr($val).'" '.$attrs.'>
				<span class="dashicons dashicons-admin-page " title="Copy" onclick="gosmtp_copy_url(\''.$id.'\')"></span>
				<p class="gosmtp_copy_message '.$id.'">'. __('Message coppied Successfully', 'gosmtp') .'.</p>';
				
				break;


			default:
			
				$input_html .= '<input class="regular-text '.$classes.'" placeholder="'.$placeholder.'" value="'.esc_attr($val).'" '.$attrs.'>';
				break;
		}
		
		$description = empty($field['desc']) ? '' : wp_kses_post($field['desc']);
		
		$html .= '<tr class='.(empty($field['tr_class']) ? '' : esc_attr($field['tr_class'])).'>
			<th scope="row">'.esc_html($field['title']).'</th>
			<td>
				'.$input_html.'
				<p class="description" id="tagline-description">'.wp_kses_post($description).'</p>
			</td>
		</tr>';
	}
	
	return $html;
}