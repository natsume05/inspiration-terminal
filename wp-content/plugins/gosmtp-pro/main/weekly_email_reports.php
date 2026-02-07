<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

// defining function
function gosmtp_cal_percentage($num_amount, $num_total) {
	
	if($num_total == 0){
		return '0%';
	}
	
	$count = ($num_amount / $num_total) * 100;
	$count = number_format($count, 0);
	
	return $count.'%';
}

// Weekly Report HTML
function gosmtp_send_email_reports($send_email = false){
    global $gosmtp;

    if(!($gosmtp->options['weekly_reports']['timestamp'] <= time()) && $send_email){
        return;
    }
	
    if(!function_exists('gosmtp_group_by')){
        include_once GOSMTP_PRO_DIR .'/main/email-reports.php';
    }
    	
	$logger = new GOSMTP\Logger();
	$week = $gosmtp->options['weekly_reports']['weekday'];
	$last_week = date("Y-m-d", strtotime('last '.$week, strtotime('tomorrow')));
	$start = date('Y-m-d', strtotime($last_week. ' -7 days'));
	$end = $last_week;
	$multiselect = array('subject');
	
	$options = array(
		'interval' => array(
			'start' => $start,
			'end' => $end
		),
		'pagination' => false,
	);
	
	$email_logs = $logger->get_logs('records', '', $options);
    $mails = gosmtp_group_by($email_logs, array(), $multiselect);
	
	$sent = $failed = $total = 0;
	
	foreach($mails as $key =>$val){
		$sent = $sent + $val['total']['sent'];
		$failed = $failed + $val['total']['failed'];
		$total = $total + $val['total']['total'];
	}   
	
	echo'<div style="padding:0px 180px">"';
	$email = '<html>
<head>
	<meta http-equiv=""Content-Type"" content=""text/html; charset=utf-8""/>
	<meta name=""viewport"" content=""width=device-width, initial-scale=1.0""/>
	<!--[if !mso]><!-->
	<meta http-equiv=""X-UA-Compatible"" content=""IE=edge""/>
	<!--<![endif]-->
	<meta name=""color-scheme"" content=""light dark"">
	<meta name=""supported-color-schemes"" content=""light dark"">
	<title>GOSMTP Weekly Email Summary</title>
	<style type=""text/css"">
	/* General styles. */
	*{
		box-sizing: border-box;
	}

	@media only screen and (max-width: 600px) {
		.total-container div h4{
			font-size:13px;
		}
		.total-container div h1{
			font-size:15px;
		}
		.total-container div h1{
			font-size:15px;
		}
		.total-container{
		border:1px solid #bdbdbd;
			
		}
		.total-container div{
			border: none !important;
			padding: 5px 3px !important;
			margin:0px !important;
			border-radius:5px
		}
		.total-container div .mail-icon *{
			width:25px !important;
		}
		.inner-table{
			padding:10px !important;
			width:100% !important;
		}
		.outer-table{
			padding:20px;
		}
		.list_mail div{
			padding:5px 3px !important;
		}
		.inner-table{
			border-spacing:10px !important;
		}
		td{
			padding:5px!important;
		}
		}
	}
</style>
</head>
<body>
	<table style="width:100%; background:#f1f1f1; padding:50px 0px" class="outer-table">
	<tr style="text-align: center;"><td><img src="https://gosmtp.net/sitepad-data/uploads/2023/02/gosmtp-text.png" style="width:250px;text-align: center;"></td></tr>
	<tr><td>
	<table class="inner-table" style="background:white;border-radius:5px;width:70%; padding:30px; table-layout: fixed !important;border-spacing:20px;border: 1px solid #dcdcdc;  margin: 20px auto;border:1px solid #bdbdbd;" >
	<tr>
		<td colspan="3">
			<h3>Hi there,</h3>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			<b>Let\'s take a look at how your emails performed over the last week.</b>
		</td>
	</tr>
	<tr >
		<td colspan="3" style="text-align:center">
		<div class="total-container"> 
			<div style="box-sizing: border-box; padding:20px; height:100%; width:30%; text-align: center; border-radius:5px; display:inline-block; margin:2% 1%;border:1px solid #bdbdbd;">
				<div class="mail-icon" style=" font-size:40px; color:#454545; width:100%; margin:5px 0px;">&#9993;</div>
				<h4 style="color:#454545;margin:5px 0px;">Total Emails</h4>
				<h1 style="margin:5px 0px;">'.$total.'</h1>
				<h2 style="color:#454545;margin:5px 0px;" > &#9650;'.gosmtp_cal_percentage($total, $total).'</h2>
			</div>
			<div style="box-sizing: border-box; padding:20px; height:100%; width:30%; text-align: center; border-radius:5px;display:inline-block;margin:2% 1%;border:1px solid #bdbdbd;">
				<div class="mail-icon" style="font-size:40px; color:#228b22  !important; width:100%;margin:5px 0px;">&#10004;</div>
				<h4 style="color:#228b22 ;margin:5px 0px;">Sent</h4>
				<h1 style="margin:5px 0px;">'.$sent.'</h1>
				<h2 style="color:#228b22 ;margin:5px 0px;"> &#9650;'.gosmtp_cal_percentage($sent, $total).'</h2>
			</div>
			<div style="box-sizing: border-box; padding:20px; height:100%; width:30%; text-align: center; border-radius:5px; display:inline-block;margin:2% 1%;border:1px solid #bdbdbd;">
				<div class="mail-icon" style="font-size:40px; color:red; width:100%;margin:5px 0px;">&#10006;</div>
				<h4 style="color:red;margin:5px 0px;">Failed</h4>
				<h1 style="margin:5px 0px;">'.$failed.'</h1>
				<h2 style="color:red;margin:5px 0px;"> &#9660;'.gosmtp_cal_percentage($failed, $total).'</h2>
			</div>
		</div>   
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<h2>Last Weeks Emails</h2>
		</td>
		<td colspan="1" style="text-align:right">
			<a href="'.admin_url().'/admin.php?page=email_reports&date=custom_date&start-date='.$start.'&end-date='.$end.'">View all Emails</a>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			<hr>
		</td>
	</tr>';
	
	if(!empty($mails)){
		foreach($mails as $key => $val){
			$email .= "<tr><td colspan='3' ><b>".$val['total']['subject']."</b></td></tr>
				<tr style='background:#f1f1f1;'>
				<td colspan='3'class='list_mail'>
					<div style='padding:7px; display:inline-block; width:32%; box-sizing:border-box;'><span style='color:#454545;font-weight:bold;font-size:16px;margin-right:5px;'>&#9993;</span>".$val['total']['total']."</div>
					<div style='padding:7px; display:inline-block; width:32%; box-sizing:border-box;'><span style='color:#228b22; font-weight:bold;font: size 16px;margin-right:5px;'>&#10004;</span>".$val['total']['sent']."(".gosmtp_cal_percentage($val['total']['sent'], $val['total']['total']).")"."</div>
					<div style='padding:7px; display:inline-block; width:32%; box-sizing:border-box;'><span style='color:red;font-weight:bold;font-size:16px;margin-right:5px;'>&#10006;</span>".$val['total']['failed']."(".gosmtp_cal_percentage($val['total']['failed'], $val['total']['total']).")"."</div>
				</td>
			</tr>";
			
			// Print Only 5 Rows
			if($key == 4){
				break;
			}
		}
	}else{
		$email .="<tr >
		<td colspan='3' style='text-align:center'>
			<h4>Email Records were not found this week!</h4>
		</td>
	   </tr>";
	}

	$email .='</table></td></tr></table>
		</body>
	</html>';

	if($send_email || isset($_GET['test_reports'])){
		
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$admin_email = get_option('admin_email');
		
		if(!empty($admin_email)){
			$title = 'Weekly Report';
		    
			if(wp_mail($admin_email, $title, $email, $headers)){
			
				echo '<div class="updated notice is-dismissible">
					<p>'.__('Email Sent successfully to ').' <a href="mailto:'.$admin_email.'">'.$admin_email.'</a>.</p>
				</div>
				<div class="updated notice is-dismissible">
					<p>'.__('Weekly email reports were sent from: '.$start.' to: '.$end.'').' </p>
				</div>';
			}else{
				echo '<div class="error notice is-dismissible">
					<p>'.__('Failed to send email to ').' <a href="mailto:'.$admin_email.'">'.__($admin_email).'</a>'.__('. Plesae check ').'<a href="'.admin_url('admin.php?page=gosmtp-logs').'">'. __(' Email logs ').'</a>'.__('for more info.').'</p>
				</div>';
			}
		    
		}else{
		   echo "<script>alert('Admin email has not yet been set up. Please first configure the admin email.')</script>";
		}
	}
	
	// Update Timestamp
	if($send_email){
		$gosmtp->options['weekly_reports']['timestamp'] = strtotime("next ".$gosmtp->options['weekly_reports']['weekday']);
		
		update_option( 'gosmtp_options', $gosmtp->options );
		
		return;
	}
	
	$email.= '<form style="text-align:center">
		<input type="hidden" name="page"  value="weekly_email_reports">
		<input type="submit" name="test_reports" value = "Send Test Weekly Reports" style="background:var(--blue);color:var(--white);padding:9px 10px;border:none;border-radius:3px;">
		<p>'. __('Note: Test weekly report will be sent to admin email') .'</p>
	</form></div>';
	
	echo $email;
}

?>
