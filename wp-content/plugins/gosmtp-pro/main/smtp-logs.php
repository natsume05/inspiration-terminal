<?php
// We need the ABSPATH
if (!defined('ABSPATH')) exit;

global $gosmtp;

if(empty($gosmtp->options['logs']['enable_logs']) ){

	echo '<h1>'.__('Email logs is disabled').'</h1>
	<div class="error notice">
		<p >'.__('To store and view email logs, please enable email logs from GoSMTP').' <a href="'.admin_url('admin.php?page=gosmtp#logs-settings').'">'.__('settings').'</a>.</p>
	</div>';
	
	return;
}

// Styles and Scripts
wp_enqueue_style( 'gosmtp-admin' );
wp_enqueue_script( 'gosmtp-admin' );

$filter = gosmtp_optget('filter');
$start = gosmtp_optget('from');
$end = gosmtp_optget('to');
$search = gosmtp_optget('search');

$default = array(
	'subject' => 'on',
	'date_send' => 'on',
	'action' => 'on'
);

$columns = !empty( $gosmtp->options['logs']['log_columns'] ) ?  maybe_unserialize($gosmtp->options['logs']['log_columns']) : array();

$columns = array_merge($default, $columns);

?>

<div class="wrap">
    <div class="wrap_header gosmtp-relative">
    	<h1><?php _e('SMTP LOGS') ?></h1>
    	<button id="gosmtp-testmail-btn" class="button button-primary">
		<i class="dashicons-before dashicons-email-alt" aria-hidden="true"></i>
		<span><?php _e('Test Mail') ?></span>
	</button>
    </div>
    <div style="width:100%;margin-top:20px;">
		<form action="<?php echo admin_url('admin.php') ?>">
			<input type="hidden" name="page" value="gosmtp-logs">
			<div class="gosmtp-search-wrap">
				<div class="gosmtp-search-container">
					<h3><?php _e('What are you searching for') ?></h3>
					<div class="gosmtp-search-list-icon">
						<span class="dashicons dashicons-search"></span>
						<input type="text" id="gosmtp-search-box" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search" />
					</div>
				</div>
				<div class="gosmtp-date-container">
					<h3><?php _e('Select date') ?></h3>
					<div class='gosmtp-flex gosmtp-margin-auto'>
						<input type="date" name="from" id="gosmtp-filter-start" min="2023-01-01" value="<?php echo esc_attr($start); ?>" max="<?php echo date('Y-m-d') ?>" /> 
						<input type="date" name="to" id="gosmtp-filter-end" min="2023-01-01" value="<?php echo esc_attr($end); ?>" max="<?php echo date('Y-m-d') ?>" />
					</div>
				</div>
				<div class="gosmtp-filter-container">
					<h3><?php _e('Status') ?></h3>
					<select id="gosmtp-search-filter" name="filter">
						<option value="all" <?php selected( $filter , 'all'); ?> ><?php _e('All') ?></option>
						<option value="success" <?php selected( $filter , 'success'); ?> ><?php _e('Success') ?></option>
						<option value="failed" <?php selected( $filter , 'failed'); ?> ><?php _e('Failed') ?></option>
					</select>
				</div>
				<input type="submit" class="gosmtp-search-trigger" id="gosmtp-search" value="Search" /> 
			</div>
			<table cellspacing="0" cellpadding="8" border="0" width="100%" class="wp-list-table widefat striped gosmtp-log-table" id="gosmtp-logs-table">
				<tr class="gomtp-logs_tr">
					<th width="10"><input type="checkbox" class="gosmtp-multi-check"></th>
					<?php
					
						$logs_th = array(
							'subject' => '<th class="subject_th">'.__('Subject').'</th>',
							'from' => '<th class="from_th">'.__('From').'</th>',
							'to' => '<th class="to_th">'.__('To').'</th>',
							'source' => '<th class="source_th">'.__('Source').'</th>',
							'provider' => '<th class="provider_th">'.__('Provider').'</th>',
							'date_send' => '<th>'.__('Date Send').'</th>',
							'action' => '<th>'.__('Actions').'</th>',
						);	
						
						foreach($logs_th as $key => $col ){
							if(!empty($columns) && array_key_exists($key,$columns)){
								echo $col;
							}
						}
						
					?>
				</tr>
				<?php
				
					$logger = new GOSMTP\Logger();
					$curpage = (int) gosmtp_optget('paged', 1);
					
					$options = array(
						'filter' => !empty($filter) && $filter != 'all' ? ($filter == 'success' ? 'sent' : 'failed') : '',
						'interval' => array(
							'start' => $start,
							'end' => $end
						), 
						'search' => $search,
					);

					// Pagination
					$perpage = 10;
					$records_ct = (int) $logger->get_logs('count', '', $options)->records;
					$tpages = ceil($records_ct / $perpage);
					$offset = ($curpage - 1) * $perpage;

					$options['limit'] = $perpage;
					$options['offset'] = $offset;
							
					$args = array(
						'base' => '%_%',
						'format' => '?paged=%#%',
						'total' => $tpages,
						'current' => $curpage,
						'show_all' => false,
						'end_size' => 1,
						'mid_size' => 2,
						'prev_next' => true,
						'type' => 'array',
						'add_args' => false
					);

					$pagination = null;
					$pages = paginate_links( $args );
					
					if( is_array( $pages ) ){
						$paged = ( get_query_var('paged') == 0 ) ? 1 : get_query_var('paged');
						$pagination .= '<div class="gosmtp-pagination"><ul class="gosmtp-pagination-wrap">';
						
						foreach ( $pages as $page ) {
							$pagination .=  '<li class="gosmtp-pagination-links">'.$page.'</li>';
						}
						
						$pagination .=  '</ul></div>';
					}

					$mails = $logger->get_logs('records', 0, $options);
					
					if(!empty($mails)){
						foreach($mails as $key => $mail){
							
							$id = $mail->id;
							$tos = maybe_unserialize($mail->to);
							$to_list = [];
							
							if(is_array($tos)){
								foreach($tos as $key => $to){
									$to_list[] = $to[0];
								}
								
								$to_list = implode(',',$to_list);
							}else{
								$to_list = $tos;
							}

							$created_at =  date("M d, Y", strtotime($mail->created_at)).' at '. date('h:i A', strtotime($mail->created_at));
							$status = $mail->status == 'sent' ? __('Sent') : __('Failed');
							$resend_retry = $mail->status == 'sent' ? __('Resend') : __('Retry');
							$backup_text = !empty($mail->parent_id) ? __('(Backup)') : '';

							$logs_td = array(
								'subject' => '<td class="gosmtp-flex">
									<span class="dashicons '.( $status == 'Sent' ? 'dashicons-yes-alt' : 'dashicons-warning').' gosmtp-mail-status '.esc_attr(strtolower($status)).'"></span>
									<span>'. (!empty($mail->subject) ? esc_attr($mail->subject) : __('[No Subject]')) .'</span>
								</td>',
								'from' => '<td>'.(!empty($mail->from) ? esc_html($mail->from) : __('NA')).'</td>',
								'to' =>  '<td>'.esc_html($to_list).'</td>',
								'source' => '<td>'.(!empty($mail->source) ? esc_html($mail->source) : __('NA')).'</td>',
								'provider' => '<td>'.(!empty($mail->provider) ? ucwords(esc_html($mail->provider)).' '.$backup_text : __('NA')).'</td>',
								'date_send' => '<td>'.esc_html($created_at).'</td>',
								'action' => '<td class="gosmtp-mail-actions">
									<button type="button" data-id="'. esc_attr($id).'" class="gosmtp-'. esc_attr(strtolower($resend_retry)).'">
										<i class="dashicons '.($resend_retry == 'Retry' ? 'dashicons-update-alt' : 'dashicons-image-rotate' ).'"></i>
										<span>'.esc_html($resend_retry).'</span>
									</button>
									<button type="button" data-id="'. esc_attr($id).'" class="gosmtp-forward">
										<i class="dashicons dashicons-share-alt2"></i>
									</button>
									<button class="gosmtp-mail-delete" type="button" data-id="'.esc_attr($id).'">
										<i class="dashicons dashicons-trash"></i>
									</button>
								</td>'
							);
							?>
							<tr data-id="<?php echo esc_attr($id); ?>" class="gosmtp-mail-details">
								<td>
									<input type="checkbox" value="<?php echo esc_attr($id); ?>" class="gosmtp-checkbox" />
								</td>
								<?php
								
								foreach($logs_td as $key => $col ){
									if(!empty($columns) && array_key_exists($key,$columns)){
										echo $col;
									}
								}

								?>
								
							</tr>
						<?php
						}
					}else{
						?>
						<tr>
							<td  colspan="8" class="gosmtp-empty-row"><?php _e('Logs not found!'); ?></td>
						</tr>
						<?php
					}
				
				?>
			</table>
			<div class="gosmtp-table-footer">
				<div class="gosmtp-log-options-wrap">
					<div class="gosmtp-log-options">
						<select id="gosmtp-table-options">
							<option value="delete"><?php _e('Delete'); ?></option>
						</select>&nbsp;&nbsp;
						<button id="gosmtp-table-opt-btn" type="button" class="button button-primary"><?php _e('Go'); ?></button>
					</div>
				</div>
				<?php echo $pagination; ?>
			</div>
		</div>	
	</form>
</div>
<div class="gosmtp-dialog" id="gosmtp-logs-dialog">
	<div class="gosmtp-dialog-wrap">
		<div class="gosmtp-dialog-container">
			<div class="gosmtp-dialog-header">
				<div class="gosmtp-dialog-header-content">
				<div class="gosmtp-dialog-title"><div class="gosmtp-status-icon"></div><span><?php _e('GOSMTP LOGS') ?></span></div>
				<div class="gosmtp-dialog-actions"></div>
				<div class="gosmtp-forward-dialog"></div>
				<button type="button" class="gosmtp-dialog-close"><span class="dashicons dashicons-no-alt"></span></button>
				</div>
			</div>
			
			<div class="gosmtp-dialog-content">
				<div class="gosmtp-log-details">
					<div class="gosmtp-row">
						<div class="gosmtp-col  gosmtp-col-6">
							<label><?php _e('Mailer / Source') ?>:</label><span class="gosmtp-message-mailer"></span>
						</div>
						<div class="gosmtp-col gosmtp-col-6">
							<label><?php _e('Created') ?>:</label>
							<span class="gosmtp-message-created"></span>
						</div>
					</div>

					<div class="gosmtp-row">
						<div class="gosmtp-col gosmtp-col-6">
							<label><?php _e('From') ?>:</label>
							<span class="gosmtp-message-from"></span>
						</div>
						<div class="gosmtp-col  gosmtp-col-6">
							<label><?php _e('To') ?>:</label>
							<span class="gosmtp-message-tos"></span>
						</div>
					</div>
					<div class="gosmtp-row">
						<div class="gosmtp-col gosmtp-col-12">
							<label><?php _e('Subject') ?>:</label><span class="gosmtp-message-subject"></span>
						</div>
					</div>
					
					<div class="gosmtp-row">
						<div class="gosmtp-col gosmtp-col-12">
							<label><?php _e('Body') ?>:</label>
						</div>
						<div class="gosmtp-col gosmtp-col-12 gosmtp-message-body"></div>
					</div>
				</div>
			</div>
			
			<div class="gosmtp-accordion">
				<div class="gosmtp-accordion-item">
					<div class="gosmtp-accordion-header">
						<strong><?php _e('Headers') ?></strong>
						<i class="dashicons dashicons-arrow-down-alt2"></i>
					</div>
					<div class="gosmtp-accordion-content">
						<div class="gosmtp-log-headers"></div>
					</div>    
				</div>
				
				<div class="gosmtp-accordion-item">
					<div class="gosmtp-accordion-header">
						<strong><?php _e('Attachments') ?> <span class="gosmtp-attachment-count"></span></strong>
						<i class="dashicons dashicons-arrow-down-alt2"></i>
					</div>
					<div class="gosmtp-accordion-content">
						<div class="gosmtp-log-attachments"></div>
					</div>    
				</div>
				
				<div class="gosmtp-accordion-item">
					<div class="gosmtp-accordion-header">
						<strong><?php _e('Response') ?></strong>
						<i class="dashicons dashicons-arrow-down-alt2"></i>
					</div>
					<div class="gosmtp-accordion-content">
						<div class="gosmtp-log-response"></div>
					</div>    
				</div>
			</div>
		</div>
	</div>
</div>
<div class="gosmtp-dialog" id="gosmtp-testmail-dialog">
	<div class="gosmtp-dialog-wrap">
		<div class="gosmtp-dialog-container">
			<div class="gosmtp-dialog-header">
				<div class="gosmtp-dialog-header-content">
				<div class="gosmtp-dialog-title"><div class="gosmtp-status-icon"></div><span><?php _e('GOSMTP Test Mail') ?></span></div>
				<button type="button" class="gosmtp-dialog-close"><span class="dashicons dashicons-no-alt"></span></button>
				</div>
			</div>
			
			<div class="gosmtp-dialog-content">
				<!--start -->
				<form class="gosmtp-smtp-mail" id="smtp-test-mail" name="test-mail" method="post" action="">
					<div class="gosmtp-row">
						<div class="gosmtp-col gosmtp-col-12 gosmtp-borderless">
							<label><?php _e('Recipient Email') ?>:</label>
						</div>
						<div class="gosmtp-col gosmtp-col-12 gosmtp-borderless">
							<input type="email" name="reciever_test_email" class="regular-text gosmtp-full-width" placeholder="<?php _e('example@example.com') ?>" required />
						</div>
					</div>
					<div class="gosmtp-row">
						<div class="gosmtp-col gosmtp-col-12 gosmtp-borderless">
							<label><?php _e('Subject') ?>:</label>
						</div>
						<div class="gosmtp-col gosmtp-col-12 gosmtp-borderless">
							<input type="text" name="smtp_test_subject" class="regular-text gosmtp-full-width" placeholder="<?php _e('Enter Subject') ?>" value="Test Mail" required />
						</div>
					</div>
					<div class="gosmtp-row">
						<div class="gosmtp-col gosmtp-col-12 gosmtp-borderless">
							<label><?php _e('Message') ?>:</label>
						</div>
						<div class="gosmtp-col gosmtp-col-12 gosmtp-borderless">
							<textarea name="smtp_test_message" placeholder="Enter Message" class="regular-text gosmtp-full-width" rows="10"required >This is a test mail!</textarea>
						</div>
					</div>
					<div class="gosmtp-row">
						<div class="gosmtp-col gosmtp-col-12 gosmtp-borderless gosmtp-text-right">
							<button type="submit" name="send_mail" id="send_mail" class="button button-primary"><?php  _e("Send Mail") ?></button>
						</div>
					</div>
				</form>
			<!--end -->
			</div>
		</div>
	</div>
</div>
<div class="gosmtp-dialog" id="gosmtp-forward-dialog">
	<div class="gosmtp-dialog-wrap">
		<div class="gosmtp-dialog-container">
			<div class="gosmtp-dialog-header">
				<div class="gosmtp-dialog-header-content">
				<div class="gosmtp-dialog-title"><div class="gosmtp-status-icon"></div><span><?php _e('GOSMTP FORWARD EMAIL') ?></span></div>
				<button type="button" class="gosmtp-dialog-close"><span class="dashicons dashicons-no-alt"></span></button>
				</div>
			</div>
			
			<div class="gosmtp-dialog-content">
				<div class="gosmtp-log-details">
					<div class="gosmtp-row">
						<div class="gosmtp-col  gosmtp-col-12">
							<form class = "gosmtp-forward-form" id= "gosmtp-forward-form">
								<h2><?php _e('Enter Recipient Email') ?>:</h2>
								<b><?php _e('Note: Use coma(,) for separate two emails') ?></b>
								<div class='gosmtp-forward-email'>
									<span class="dashicons dashicons-email"></span>
									<input type='email' required class="gosmtp-recipient-email" placeholder = "example@example.com" multiple>
								</div>
								<input type='submit' name='forward-mail' value = "Send" class="button forward-mail">
								<input type='button' name='cancel' value = "Cancel" class="button cancel-button">
							</form>

						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="gosmtp-loader">
	<div class="gosmtp-loader-circle"></div>
</div>
<script>
    var gosmtp_ajaxurl = "<?php echo admin_url( 'admin-ajax.php' ) ?>?";
    var gosmtp_ajax_nonce = "<?php echo wp_create_nonce('gosmtp_ajax') ?>";
    
</script>
