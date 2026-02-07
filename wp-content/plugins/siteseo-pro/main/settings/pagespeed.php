<?php

namespace SiteSEOPro\Settings;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT');
}

class PageSpeed{

	static function analysis(){
		$active_tab = isset($_POST['tab']) ? 'desktop' : 'mobile';
		$page_speed = get_option('siteseo_pro_page_speed');

		echo '<div class="siteseo-flex siteseo-justify-center siteseo-metabox-subtabs" style="margin:10px 0">
				<input type="radio" name="ps_device_type" id="siteseo-ps-mobile-tab" value="mobile" checked/>
				<label for="siteseo-ps-mobile-tab" class="siteseo-ps-device-toggle">
					<span style="font-size:30px;display:flex;align-items:center; justify-content:center;"  class="dashicons dashicons-smartphone"></span>
					<span style="padding:10px;font-weight:500;font-size:1.05em;padding-left:1px;">Mobile</span>
				</label>
				
				<input type="radio" name="ps_device_type" id="siteseo-ps-desktop-tab" value="desktop"/>
				<label for="siteseo-ps-desktop-tab" class="siteseo-ps-device-toggle">
					<span style="font-size:30px;display:flex; align-items:center; justify-content:center;" class="dashicons dashicons-desktop"></span>
					<span style="padding:10px;font-weight:500;font-size:1.05em;padding-left:5px;">Desktop</span>
				</label>
		</div>';
		echo'<div id="siteseo-ps-mobile" class="siteseo-flex siteseo-direction-column tab-content">';

		if(!empty($page_speed['mobile'])){
			
			self::score_graph($page_speed, 'mobile');

			echo '<div style="margin-top:1%;" class="siteseo-metabox-subtabs">
				<div class="siteseo-metabox-tab-label siteseo-metabox-tab-label-active" data-tab="siteseopro_audits_tab">' . esc_html__('Audits', 'siteseo-pro') . '</div>
				<div class="siteseo-metabox-tab-label" data-tab="siteseopro_opportunities_tab">' . esc_html__('Opportunities', 'siteseo-pro') . '</div>
				<div class="siteseo-metabox-tab-label" data-tab="siteseopro_diagnostics_tab">' . esc_html__('Diagnostics', 'siteseo-pro') . '</div>
			</div>';
			
			self::audit_tabs($page_speed, 'mobile');
		}

	echo'</div>';
	echo'<div id="siteseo-ps-desktop" class="siteseo-direction-column tab-content">';

		if(!empty($page_speed['desktop'])){
			self::score_graph($page_speed, 'desktop');

			echo '<div style="margin-top:1%;" class="siteseo-metabox-subtabs">
				<div class="siteseo-metabox-tab-label siteseo-metabox-tab-label-active" data-tab="siteseopro_audits_tab">' . esc_html__('Audits', 'siteseo-pro') . '</div>
				<div class="siteseo-metabox-tab-label" data-tab="siteseopro_opportunities_tab">' . esc_html__('Opportunities', 'siteseo-pro') . '</div>
				<div class="siteseo-metabox-tab-label" data-tab="siteseopro_diagnostics_tab">' . esc_html__('Diagnostics', 'siteseo-pro') . '</div>
			</div>';
				
			self::audit_tabs($page_speed, 'desktop');
		}
		echo'</div>';

	}
	
	static function score_graph(&$page_speed, $device){
		// Score show for mobile
		$score = isset($page_speed[$device]['score']) ? $page_speed[$device]['score'] : null;
		$pagespeed_score = $score * 100;

		$pagespeed_color = self::set_status_color($pagespeed_score);
		
		echo '<div class="siteseopro-flex-wrapper">
			  <div class="siteseo-single-chart">
				<svg viewBox="0 0 36 36" class="siteseo-circular-chart orange">
					<path class="siteseo-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
					<path class="siteseo-circle" style="stroke:' . esc_attr($pagespeed_color) . '"
					stroke-dasharray="' . esc_attr($pagespeed_score) . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
					<text x="18" y="20.35" class="siteseo-percentage">' . esc_html($pagespeed_score) . '%</text>
				</svg>
			</div>
		</div>';

		echo '<div class="siteseo-pro-details">
				<span style="color:#008000" class="dashicons dashicons-yes"></span>Good&nbsp&nbsp
				<span style="color:#ffA500" class="dashicons dashicons-warning"></span>Warning&nbsp&nbsp
				<span style="color:#ff0000" class="dashicons dashicons-no"></span>Errors
			  </div>';

		if(!empty($page_speed['mobile']['fetchTime'])){
			$fetch_time = $page_speed['mobile']['fetchTime'];
			$fetch_date = date_i18n(get_option('date_format'), strtotime($fetch_time));
			$fetch_time = gmdate('H:i', strtotime($fetch_time));

			echo '<div class="siteseo-pro-fechtime"><span>' . esc_html__('Captured at ', 'siteseo-pro') . esc_html($fetch_date) . ', ' . esc_html($fetch_time) . ' UTC</span></div>';
		}
	}
	
	static function audit_tabs(&$page_speed, $device){
		echo '<div class="siteseo-audit-tabs siteseopro_audits_tab siteseo-metabox-tab" style="display:' . ($device == 'mobile' ? 'block' : 'none') . ';">';

		foreach($page_speed[$device] as $key => $audit){
			if(isset($audit['title'])){
				$get_icons_audit = self::set_dash_icons($audit['score']);
				$title_icon_audit = $get_icons_audit['title'];
				$description_icon_audit = $get_icons_audit['description'];

				echo '<div class="audit-item">';
				echo '<div class="siteseo-audit-title">';
				echo $title_icon_audit . esc_html($audit['title']) . '<span style="position: absolute; right: 0;" class="toggle-icon dashicons dashicons-arrow-up-alt2"></span></div>';
				if(isset($audit['description'])){
					echo '<div class="description"><hr>';
					echo $description_icon_audit . wp_kses_post(self::description_handle_with_url($audit['description'])) . '</div>';
				}
				echo '</div>';
			}
		}
		echo '</div>';

		echo '<div class="siteseo-audit-tabs siteseopro_opportunities_tab siteseo-metabox-tab" style="display:none;">';
		foreach($page_speed[$device]['opportunities'] as $opportunity){
			if(isset($opportunity['title'])){
				$icons_opportunity = self::set_dash_icons($opportunity['score']);
				$title_icon_opportunity = $icons_opportunity['title'];
				$description_icon_opportunity = $icons_opportunity['description'];
			
				echo '<div class="audit-item">';
				echo '<div class="siteseo-audit-title">';
				echo $title_icon_opportunity . esc_html($opportunity['title']) . '<span style="position: absolute; right: 0;" class="toggle-icon dashicons dashicons-arrow-up-alt2"></span></div>';
				if (isset($opportunity['description'])) {
					echo '<div class="description"><hr>';
					echo $description_icon_opportunity . wp_kses_post(self::description_handle_with_url($opportunity['description'])) . '</div>';
					
				}
				echo '</div>';
			}
		}
		echo '</div>';

		echo '<div class="siteseo-audit-tabs siteseopro_diagnostics_tab siteseo-metabox-tab" style="display:none;">';
		if(empty($page_speed['desktop']['diagnostics'])){
			echo '<table style="margin-left:30%;"  class="siteseo-notice-table"><tr><td class="siteseo-notice is-success"><p>'.esc_html__('No Diagnostics Available', 'siteseo-pro').'</p></td></tr></table>';
		}else{
			foreach($page_speed['desktop']['diagnostics'] as $diagnostic){
				if(isset($diagnostic['title'])){
					$icons_diagnostic = self::set_dash_icons($diagnostic['score']);
					$title_icon_diagnostic = $icons_diagnostic['title'];
					$description_icon_diagnostic = $icons_diagnostic['description'];

					echo '<div class="audit-item">';
					echo '<div class="siteseo-audit-title">';
					echo $title_icon_diagnostic . esc_html($diagnostic['title']) . '<span style="position:absolute; right:0;" class="toggle-icon dashicons dashicons-arrow-up-alt2"></span></div>';
					if(isset($diagnostic['description'])){
						echo '<div class="description"><hr>';
						echo $description_icon_diagnostic . wp_kses_post(self::description_handle_with_url($diagnostic['description'])) . '</div>';
					}
					echo '</div>';
				}
			}
		}
		echo '</div>';
	}
 
	static function set_status_color($score){

		if($score >= 0 && $score < 49){
			$status_color = '#ff0000;';
		} elseif($score >= 50 && $score < 90){
			$status_color = '#ffA500';
		} elseif($score >= 90 && $score <= 100){
			$status_color = '#008000';
		} else{
			$status_color = 'grey';
		}

		return $status_color;
	}

	static function set_dash_icons($title_score){

		$dashicons_title = '';
		$dashicons_desc = '';

		if($title_score > 0.90){
			$title_icon = '<span style="color:#008000" class="dashicons dashicons-yes"></span>';
			$desc_icon = '<span style="color:#008000" class="dashicons dashicons-thumbs-up"></span>';
		} else if($title_score >= 0.50 && $title_score <= 0.89){
			$title_icon = '<span style="color:#ffA500" class="dashicons dashicons-warning"></span>';
			$desc_icon = '<span style="color:#ffA500" class="dashicons dashicons-thumbs-down"></span>';
		} else{
			$title_icon = '<span style="color:#ff0000" class="dashicons dashicons-no"></span>';
			$desc_icon = '<span style="color:#ff0000" class="dashicons dashicons-thumbs-down"></span>';
		}

		return ['title' => $title_icon, 'description' => $desc_icon];
	}

	static function description_handle_with_url($description){
		preg_match('/\((https?.*)\)/', trim($description), $matches);

		if(empty($matches[0]) || empty($matches[1])){
			return $description;
		}

		$url = $matches[1];
		$description = str_replace($matches[0], '', $description);

		$description = preg_replace_callback('/\[(.*?)\]/', function($matches) use ($url){
			return '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($matches[1]) . '</a>';
		}, $description);

		return $description;
	}
}