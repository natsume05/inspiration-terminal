<?php
/*
* GoSMTP Export Logs
* https://gosmtp.net
* (c) Softaculous Team
*/

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

// Filter HTML data
function gosmtp_filterData(&$str){ 
    $str = preg_replace("/\t/", "\\t", $str); 
    $str = preg_replace("/\r?\n/", "\\n", $str);
	
    if(strstr($str, '"')){
		$str = '"' . str_replace('"', '""', $str) . '"';
	}
}

// Export CSV files
function gosmtp_export_csv($data){
	
	$fileName = "gosmtp_export_".date('Y_m_d') . ".csv";
	
	header('Content-Type: text/csv; charset=utf-8');
	header("Content-Disposition: attachment; filename=$fileName");
	header("Content-Description: File Transfer");
	
	$file = fopen('php://output', 'w');
	
	// We have all the field sequences in the first line
	// Get the key of attachments form the sequences
	$attachment_key = array_search('attachments', $data[0], true);

	foreach($data as $kk => $val){
		
		// Skip attachment explode for first row
		if( $kk != 0 && $attachment_key !== false && !empty($val[$attachment_key]) ){
			
			$attachment = explode(',', $val[$attachment_key]);
			$attach = ''; 
			
			foreach($attachment as $attach_val){
				$attach .= explode('*', $attach_val)[2].', ';
			}
			
			$val[$attachment_key] = rtrim($attach, ', ');
		}
		
		fputcsv($file, array_values($val), ',', '"', '\\');
	}
	
	fclose($file);
	wp_die();
}

// Export XML files
function gosmtp_export_xls($data){
	
	$filename = "gosmtp_export_".date('Ymd') . ".xls";
	
	header("Content-Type: application/vnd.ms-excel");
	header("Content-Disposition: attachment; filename=\"$filename\"");
	
	// We have all the field sequences in the first line
	// Get the key of attachments form the sequences
	$attachment_key =  array_search('attachments', $data[0], true);

	foreach($data as $kk => $val) {
		
		array_walk($val, 'gosmtp_filterData');
		
		// Skip attachment explode for first row
		if( $kk != 0 && $attachment_key !== false && !empty($val[$attachment_key]) ){
			$attachment = explode(',', $val[$attachment_key]);
			$attach ="";
			
			foreach($attachment as $attach_val){
				$attach .= explode('*', $attach_val)[2].', ';
			}
			
			$val[$attachment_key] = rtrim($attach, ", ");
		}
		
		echo esc_html(implode("\t", array_values($val)) . "\n");
	}

	wp_die();
}

// Export EML files
function gosmtp_export_eml($data){
	
	$files = array();
	$first_row = $data[0];
	
	// Replace key with value
	$fields = array_flip($data[0]);
	
	$attachment_key = $fields['attachments'];
	
	foreach($data as $key => $value){
		
		// Skip for first row
		if($key == 0){
			continue;
		}
		
		$files[$key] = "gosmtp_export_".$key."_".date('Ymd') . ".eml";	
		
		header("Content-Type: application/application/eml");
		header("Content-Disposition: attachment; filename=\"$files[$key]\"");
		
		$output_buffer = fopen('php://output', 'w');
		$boundary = md5(uniqid(mt_rand()));
		
		$output_buffer = 
'MIME-Version: 1.0
Date: '.date_format( date_create( $value[$fields['created_at']] ), 'd-m-y H:i:s').' +0100
From: '.$value[$fields['from']].'
To: '.$value[$fields['to']].'
Cc: '.$value[$fields['cc']].'
Ccc: '.$value[$fields['bcc']].'
Subject: '.$value[$fields['subject']].'
Content-Type: multipart/mixed; boundary="'.$boundary.'"

This is a message with multiple parts in MIME format.

--'.$boundary.'
Content-Type: '.$value[$fields['content-type']].' 

'.$value[$fields['body']];
		
		// Added attachment if any
		if(isset($value[$attachment_key]) && !empty($value[$attachment_key])){
			
			$attachment = explode(',', $value[$attachment_key]);
			
			foreach($attachment as $attach){

				$all_attach = explode('*', $attach);
				$attachment_file = file_get_contents(trim($all_attach[0]));
				
				if(empty($attachment_file)){
					continue;
				}
				
				$encoded_attach = base64_encode($attachment_file);
				$type = $all_attach[4];
				$base_name = $all_attach[2];
				
				$output_buffer .=
'
--'.$boundary.'
Content-Type: '.$type.';name="'.$base_name.'"
Content-Transfer-Encoding: base64
Content-Disposition: attachment;filename="'.$base_name.'"


'.$encoded_attach;
			}
		}
		
		file_put_contents($files[$key], $output_buffer);  
	}
		
	$zipname = "gosmtp_export_".date('Ymd') . ".zip";		
	$zip = new ZipArchive;
	$zip->open($zipname, ZipArchive::CREATE);
	
	foreach($files as $file){
		$zip->addFile($file);
	}
	
	$zip->close();
	
	header('Content-Type: application/zip');
	header('Content-disposition: attachment; filename='.$zipname);
	header('Content-Length: ' . filesize($zipname));
	
	readfile($zipname);
	unlink($zipname);
	
	wp_die();
}

// Export page HTML
function gosmtp_export_page(){

	// Styles and Scripts
	wp_enqueue_style( 'gosmtp-admin' );
	wp_enqueue_script( 'gosmtp-admin' );

	$common_field = array(
		'to' => __('To Address'),
		'from' => __('From Address'),
		'subject' => __('Subject'),
		'body' => __('Body'),
		'created_at' => __('Created At'),
		'attachments' => __('Attachments'),
	);

	$addtition_field = array(
		'status' => __('Status'),
		'reply-to' => __('Reply To'),
		'cc' => __('Carbon Copy (CC)'),
		'bcc' => __('Blind Carbon Copy (BCC)'),
		'provider' => __('Provider'),
		'response' => __('Response'),
		'source' => __('Source'),
		'content-type' => __('Content Type')
	);

	$search_field = array(
		'from' => __('from'),
		'to' => __('To'),
		'subject' => __('Subject'),
		'body' => __('Body'),
	);

	$all_field = array_merge(array_keys($common_field), array_keys($addtition_field));
	?>

	<div class="gosmtp-row gosmtp-export-container">
		<form action="" method="post" id="gosmtp_export">
			<input type="hidden" name="page" value="export">
			<input type="hidden" name="all_field" value="<?php echo implode(',', $all_field)?>">
			<div class="gosmtp-row">
				<div class="gosmtp-col-12">
					<h1> <span class="dashicons dashicons-media-archive"></span><?php _e('Export Settings'); ?></h1>
				</div>
			</div>
			<hr>
			<div class="gosmtp-row">
				<div class="gosmtp-col-3">
					<h3><?php _e('Format'); ?></h3>
				</div>
				<div class="gosmtp-col-6">
					<div class="gosmtp-radio-list">
						<input type="radio" name="format" value="csv" id="csv" checked> <label for="csv" class="active_radio_tab"><?php echo __('CSV').'(.csv)'; ?></label>
						<input type="radio" name="format" value="xls" id ="xls"> <label for="xls"><?php echo __('Microsoft Excel').'(.xls)'; ?></label>
						<input type="radio" name="format" value="eml" id="eml"> <label for="eml"><?php echo __('EML').'(.eml)'; ?> </label>
					</div>
				</div>
			</div>
			<div class="gosmtp-row ">
				<div class="gosmtp-col-3">
					<h3><?php _e('Export Custom Field'); ?></h3>
				</div>
				<div class="gosmtp-col-9">
					<label class="gosmtp-switch">
					<input type="checkbox" name="custom-field"  id="custom-field" checked>
						<span class="gosmtp-slider gosmtp-round"></span>
					</label>
				</div>
			</div>
			<div class="gosmtp-row can-hidden">
				<div class="gosmtp-col-3">
					<h3><?php _e('Common Information'); ?></h3>
				</div>
				<div class="gosmtp-col-6">
					<div class="gosmtp-fiter-container">
						<span class="multiselect"><?php _e('Select common field'); ?></span>
						<ul class="multiselect-options">
							<li><input type="checkbox" class="multiselect-checkbox" id="all" value="all" checked><label for="all"><?php _e('All'); ?></label></li>
						<?php
							foreach($common_field as $key => $val){
								echo"<li><input type='checkbox' checked class='multiselect-checkbox' name ='common_information[]'  id='$key' value='$key'><label for='$key'>$val</label></li>";
							}	
						?>
						</ul>
						<span class="dropdown dashicons dashicons-arrow-down-alt2"></span>
					</div>
				</div>
			</div>
			<div class="gosmtp-row can-hidden">
				<div class="gosmtp-col-3">
					<h3><?php _e('Additional Information'); ?></h3>
				</div>
				<div class="gosmtp-col-6">
					<div class="gosmtp-fiter-container">
						<span class="multiselect"><?php _e('Select common field'); ?></span>
						<ul class="multiselect-options">
							<li><input type="checkbox" class="multiselect-checkbox" id="all" value="all"><label for="all"><?php _e('All'); ?></label></li>
						<?php
							foreach($addtition_field as $key => $val){
								echo "<li><input type='checkbox' name='addtional_information[]' class='multiselect-checkbox' id='$key' value='$key'><label for='$key' checked>$val</label></li>";
							}	
						?>
						</ul>
						<span class="dropdown dashicons dashicons-arrow-down-alt2"></span>
					</div>
				</div>
			</div>
			<div class="gosmtp-row ">
				<div class="gosmtp-col-3">
					<h3><?php _e('Custom Date Range'); ?></h3>
				</div>
				<div class="gosmtp-col-6">
					<div class="gosmtp-date-container">
						<input type="date" name="start-date" id="gosmtp-start-date" placeholder="Start date" /> 
						<input type="date" name="end-date" id="gosmtp-end-date" placeholder="End date" />
					</div>
				</div>
			</div>
			<div class="gosmtp-row ">
				<div class="gosmtp-col-3">
					<h3><?php _e('Search In'); ?></h3>
				</div>
				<div class="gosmtp-col-6">
					<div class="gosmtp-inner-row ">
						<div class="gosmtp-col-8">
							<div class="gosmtp-fiter-container ">
								<span class="multiselect"><?php _e('Select addtitional field'); ?></span>
								<ul class="multiselect-options">
									<li><input type="checkbox" class="multiselect-checkbox" id="all" value="all" ><label for="all"><?php _e('All'); ?></label></li>
								<?php
									foreach($search_field as $key => $val){
										echo "<li><input type='checkbox' name='search_type[]' class='multiselect-checkbox' id='$key' value='$key'><label for='$key'>$val</label></li>";
									}	
								?>
								</ul>
								<span class="dropdown dashicons dashicons-arrow-down-alt2"></span>
							</div>
						</div>
						<div class="gosmtp-col-4">
							<div class="gosmtp-search-report-list-icon">
								<span class="dashicons dashicons-search"></span>
								<input type="search" id="gosmtp-search_email" placeholder="Search" name="search">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="gosmtp-row ">
				<div class="gosmtp-col-3">
					<button class="button button-primary gosmtp-loading-button">
						<span class="dashicons dashicons-image-rotate"></span>
						<input type="submit" name="export" value="Export" >
					</button>
				</div>
			</div>
		</form>
	</div>

	<script>
		var gosmtp_ajaxurl="<?php echo admin_url( 'admin-ajax.php' ) ?>?";
		var gosmtp_ajax_nonce="<?php echo wp_create_nonce('gosmtp_ajax') ?>"; 
	</script>
<?php
}