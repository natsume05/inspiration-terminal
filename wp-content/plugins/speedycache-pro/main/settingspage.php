<?php

namespace SpeedyCache;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class SettingsPage{
	
	static function license_tab(){
		global $speedycache;
		
		echo '<div class="speedycache-tab" id="speedycache-license">
			<h2><img src="'.SPEEDYCACHE_URL.'/assets/images/icons/license.svg" height="32" width="32"/> License</h2>
			<table class="wp-list-table fixed striped users speedycache-license-table" cellspacing="1" border="0" width="78%" cellpadding="10" align="center">
				<tbody>
					<tr>				
						<th align="left" width="25%">'.esc_html__('SpeedyCache Version', 'speedycache').'</th>
						<td>'.SPEEDYCACHE_PRO_VERSION.(defined('SPEEDYCACHE_PRO') ? ' (Pro Version)' : '').'</td>
					</tr>
					<tr>			
						<th align="left" valign="top">'.esc_html__('SpeedyCache License', 'speedycache').'</th>
						<td align="left">
							<form method="post" action="">
								<span style="color:var(--speedycache-red)">
									'.(defined('SPEEDYCACHE_PRO') && empty($speedycache->license) ? '<span style="color:var(--speedycache-red)">Unlicensed</span> &nbsp; &nbsp;' : '').'
								</span>
								<input type="hidden" name="security" value="'.wp_create_nonce('speedycache_license').'"/>
								<input type="hidden" name="action" value="speedycache_verify_license"/>
								<input type="text" name="license" value="'.(empty($speedycache->license) ? empty($_POST['license']) ? '' : \SpeedyCache\Util::sanitize_post('speedycache_license') : $speedycache->license['license']).'" size="30" placeholder="e.g. SPDFY-11111-22222-33333-44444" style="width:300px;">
								<button class="speedycache-button speedycache-btn-black" id="speedycache-license-btn" type="submit">Update License<span class="speedycache-spinner"><span></button>
							</form>';
							if(!empty($speedycache->license)){
								$expires = $speedycache->license['expires'];
								$expires = substr($expires, 0, 4).'/'.substr($expires, 4, 2).'/'.substr($expires, 6);
								
								echo '<div style="margin-top:10px;">License Status : '.(empty($speedycache->license['status_txt']) ? 'N.A.' : wp_kses_post($speedycache->license['status_txt'])).' &nbsp; &nbsp; &nbsp;';
								
								if(empty($speedycache->license['has_plid']) || $speedycache->license['expires'] <= date('Ymd')){
									echo 'License Expires : '.($speedycache->license['expires'] <= date('Ymd') ? '<span style="color:var(--speedycache-red)">'.esc_attr($expires).'</span>' : esc_attr($expires));
								}
								echo '</div>';
							}
						echo '</td>
					</tr>
					<tr>
						<th align="left">URL</th>
						<td>'.get_site_url().'</td>
					</tr>
					<tr>				
						<th align="left">Path</th>
						<td>'.ABSPATH.'</td>
					</tr>
					<tr>				
						<th align="left">Server\'s IP Address</th>
						<td>'.esc_html($_SERVER['SERVER_ADDR']).'</td>
					</tr>
					<tr>				
						<th align="left">.htaccess is writable</th>
						<td>'.(is_writable(ABSPATH.'/.htaccess') ? '<span style="color:var(--speedycache-red)">Yes</span>' : '<span style="color:green">No</span>').'</td>
					</tr>		
				</tbody>
			</table>
		</div>';
	}
	
	// Earlier we use to just log deletion, but now we will log other stuff too.
	static function logs(){
		global $speedycache;
		
		$speedycache->logs['logs'] = get_option('speedycache_delete_cache_logs', []);

		echo '<div class="speedycache-logs">
			<div class="speedycache-logs-header">SpeedyCache Logs</div>
			<div class="speedycache-logs-content">';
			if(!empty($speedycache->logs['logs']) && count($speedycache->logs['logs']) > 0){
				foreach($speedycache->logs['logs'] as $key => $log){
					echo '<div class="speedycache-logs-row">'.(isset($log['date']) ? esc_html($log['date']) : '') . (isset($log['via']) ? esc_html(\SpeedyCache\Logs::decode_via($log['via'])) : '').'</div>';
				}
			}else{
				echo '<div class="speedycache-logs-row">'. esc_html__('No logs found', 'speedycache'). '</div>';
			}

			echo '</div>
		</div>';
	}
	
	static function stats(){
		global $speedycache;
		
		if(!class_exists('\SpeedyCache\Util')){
			return;
		}

		// CACHE SIZE
		$cache_stats = get_option('speedycache_html_size', 0);

		if(empty($cache_stats)){
			$desktop_cache = \SpeedyCache\Util::cache_path('all');
			$cache_stats = 0;

			if(file_exists($desktop_cache)){
				$cache_stats = \SpeedyCache\Util::dir_size($desktop_cache);
			}
			
			$mobile_cache = \SpeedyCache\Util::cache_path('mobile-cache');

			if(file_exists($mobile_cache)){
				$cache_stats += \SpeedyCache\Util::dir_size($mobile_cache);
			}
			
			update_option('speedycache_html_size', $cache_stats);
		}
		
		// MINIFIED SIZE
		$assets_stats = get_option('speedycache_assets_size', 0);

		if(empty($assets_stats)){
			$assets_dir = \SpeedyCache\Util::cache_path('assets');
			$assets_stats = 0;

			if(file_exists($assets_dir)){
				$assets_stats = \SpeedyCache\Util::dir_size($assets_dir);
			}
			
			update_option('speedycache_assets_size', $assets_stats);
		}
		
		$img_count = 0;
		if(class_exists('\SpeedyCache\Image')){
			$img_count = \SpeedyCache\Image::optimized_file_count();
		}

		echo '<div class="speedycache-admin-row">
			<div class="speedycache-stats-block speedycache-is-block">
				<div class="speedycache-stats-name">Cache Stats</div>
				<div class="speedycache-stats-number">'.esc_html(size_format($cache_stats)).'</div>
				<div class="speedycache-stat-status"><span class="speedycache-stat-status-indicator" '.(!empty($speedycache->options['status'])? 'style="background-color:#0c6;"' : '').'></span><span>Enabled</span></div>
			</div>
			<div class="speedycache-stats-block speedycache-is-block">
				<div class="speedycache-stats-name">Assets Stats</div>
				<div class="speedycache-stats-number">'.esc_html(size_format($assets_stats)).'</div>
			</div>';
			if(!defined('SITEPAD')){
				echo'
				<div class="speedycache-stats-block speedycache-is-block">
					<div class="speedycache-stats-name">Object Cache Stats</div>
					<div class="speedycache-stats-number">'.esc_html($speedycache->object_memory).'</div>
					<div class="speedycache-stat-status"><span class="speedycache-stat-status-indicator" '.(!empty($speedycache->object['enable'])? 'style="background-color:#0c6;"' : '').'></span><span>Enabled</span></div>
				</div>';
			}
			echo'
			<div class="speedycache-stats-block speedycache-is-block">
				<div class="speedycache-stats-name">Image Stats</div>
				<div class="speedycache-stats-number">'.esc_html($img_count).' IMG</div>
			</div>
		</div>';
	}
	
	static function image_optm(){
		echo '<h2><img src="'.SPEEDYCACHE_URL.'/assets/images/icons/image.svg" height="32" width="32"/> Image Optimization</h2>';
		
		\SpeedyCache\Image::statics();
		\SpeedyCache\Image::settings();
		\SpeedyCache\Image::list_image_html();
	}
	
	static function bloat_tab(){
		global $speedycache;
		
		echo '<h2><img src="'.SPEEDYCACHE_URL.'/assets/images/icons/broom.svg" height="32" width="32"/> Bloat Remover</h2>
		<form method="post">
		<input type="hidden" name="action" value="speedycache_save_bloat_settings"/>';
		wp_nonce_field('speedycache_ajax_nonce');

		if(!defined('SPEEDYCACHE_PRO')){
			return;
		}
		
		$bloat_options = array(
			'disable_xmlrpc' => array(
				'id' => 'speedycache_disable_xmlrpc',
				'title' => __('Disable XML RPC', 'speedycache'),
				'description' => __('XML-RPC can cause performance and security issues'),
			),
			'remove_gfonts' => array(
				'id' => 'speedycache_remove_gfonts',
				'title' => __('Disable Google Fonts', 'speedycache'),
				'description' => __('Use users system fonts to prevent loading of fonts from server', 'speedycache'),
			),
			'disable_jmigrate' => array(
				'id' => 'speedycache_disable_jmigrate',
				'title' => __('Disable jQuery Migrate', 'speedycache'),
				'description' => __('Disable jQuery Migrate for better speed.', 'speedycache'),
				'docs' => 'https://speedycache.com/docs/bloat-remover/how-to-remove-jquery-migrate-in-wordpress/',
			),
			'disable_dashicons' => array(
				'id' => 'speedycache_disable_dashicons',
				'title' => __('Disable DashIcons', 'speedycache'),
				'description' => __('DashIcons are used on ' . (defined('SITEPAD') ? 'Sitepad' : 'WordPress') . ' admin and might not be used on Front End.', 'speedycache'),
			),
			'disable_gutenberg' => array(
				'id' => 'speedycache_disable_gutenberg',
				'title' => __('Disable Gutenberg', 'speedycache'),
				'description' => __('Decouple Gutenberg if you use another page builder.', 'speedycache'),
			),
			'disable_block_css' => array(
				'id' => 'speedycache_disable_block_css',
				'title' => __('Disable Block Editor CSS', 'speedycache'),
				'description' => __('Some themes might not use Block Editor CSS on the front.', 'speedycache'),
			),
			'disable_oembeds' => array(
				'id' => 'speedycache_disable_oembeds',
				'title' => __('Disable OEmbeds', 'speedycache'),
				'description' => __('OEmbeds increases load on site if a lot of embeds are being used.', 'speedycache'),
			),
			'update_heartbeat' => array(
				'id' => 'speedycache_update_heartbeat',
				'title' => __('Update Heartbeat', 'speedycache'),
				'description' => __('Change how frequently heartbeat is checked.', 'speedycache'),
				'settings' => 'speedycache_update_heartbeat',
			),
			'limit_post_revision' => array(
				'id' => 'speedycache_limit_post_revision',
				'title' => __('Limit Post Revision', 'speedycache'),
				'description' => __('Change how many post revision you want to keep.', 'speedycache'),
				'settings' => 'speedycache_limit_post_revision',
			),
			'disable_cart_fragment' => array(
				'id' => 'speedycache_disable_cart_fragment',
				'title' => __('Disable Cart Fragments', 'speedycache'),
				'description' => __('Disable WooCommerce cart fragments for better performance.', 'speedycache'),
			),
			'disable_woo_assets' => array(
				'id' => 'speedycache_disable_woo_assets',
				'title' => __('Disable WooCommerce Assets', 'speedycache'),
				'description' => __('Disables WooCommerce assets to reduce unwanted asset loading.', 'speedycache'),
				'docs' => 'https://speedycache.com/docs/bloat-remover/how-to-remove-woocommerce-assets/',
			),
			'disable_rss' => array(
				'id' => 'speedycache_disable_rss',
				'title' => __('Disable RSS feeds', 'speedycache'),
				'description' => __('Disable RSS feeds to reduce request which use server resources.', 'speedycache'),
			),
		);
		
		foreach($bloat_options as $bloat_key => $bloat_option){
			echo '<div class="speedycache-option-wrap">
			<label for="'.esc_attr($bloat_option['id']).'" class="speedycache-custom-checkbox">
				<input type="checkbox" id="'.esc_attr($bloat_option['id']).'" name="'.esc_attr($bloat_key).'" '. (!empty($speedycache->bloat[$bloat_key]) ? ' checked' : '').'/>
				<div class="speedycache-input-slider"></div>
			</label>
			<div class="speedycache-option-info">
				<span class="speedycache-option-name"><span>'.esc_html($bloat_option['title']). '</span>';
				
				// Docs Link here
				if(isset($bloat_option['docs'])){
					echo '<a href="'.esc_url($bloat_option['docs']).'" target="_blank"><span class="dashicons dashicons-info" style="font-size:14px"></span></a>';
				}
				
				// Setting if any
				if(isset($bloat_option['settings'])){
				echo '<span class="speedycache-modal-settings-link" setting-id="'.esc_attr($bloat_option['settings']).'" style="display:'. (!empty($speedycache->bloat[$bloat_key]) ? 'inline-block' : 'none').';">- '.esc_html__('Settings', 'speedycache').'</span>';
				}
				echo '</span>
				<span class="speedycache-option-desc">'. esc_html($bloat_option['description']).'</span> 
			</div>
		</div>';
		}
		
		// Bloat modals
		echo '<div modal-id="speedycache_limit_post_revision" class="speedycache-modal">
			<div class="speedycache-modal-wrap">
				<div class="speedycache-modal-header">
					<div>'.esc_html__('Limit Post Revision', 'speedycache').'</div>
					<div title="Close Modal" class="speedycache-close-modal">
						<span class="dashicons dashicons-no"></span>
					</div>
				</div>
				<div class="speedycache-modal-content">
					<label for="speedycache_post_revision_count">'.esc_html__('Select Post Revision Count', 'speedycache').'</label>
					<select id="speedycache_post_revision_count" name="post_revision_count" value="'.(!empty($speedycache->bloat['post_revision_count']) ? esc_attr($speedycache->bloat['post_revision_count']) : '').'">';
						$post_revision_opts = array(
							'disable' => esc_html__('Disable', 'speedycache'),
							'1' => '1',
							'2' => '2',
							'3' => '3',
							'4' => '4',
							'5' => '5',
							'10' => '10',
							'20' => '20',
						);
						
						foreach($post_revision_opts as $value => $post_revision_opt){
							$selected = '';
							
							if(!empty($speedycache->bloat['post_revision_count']) && $speedycache->bloat['post_revision_count'] == $value){
								$selected = 'selected';
							} elseif(empty($speedycache->bloat['post_revision_count']) && $value == '10'){
								$selected = 'selected';
							}

							echo '<option value="'.esc_attr($value).'" '.esc_attr($selected).'>'.esc_html($post_revision_opt).'</option>';
						}
					echo '</select>
	
				</div>
				<div class="speedycache-modal-footer">
					<button type="button" action="close">
						<span>'.esc_html__('Submit', 'speedycache').'</span>
					</button>
				</div>
			</div>
		</div>
		
		<!-- Modal Settings for heartbeat of WordPress -->
		<div modal-id="speedycache_update_heartbeat" class="speedycache-modal">
			<div class="speedycache-modal-wrap">
				<div class="speedycache-modal-header">
					<div>'.esc_html__('Update HeartBeat', 'speedycache').'</div>
					<div title="Close Modal" class="speedycache-close-modal">
						<span class="dashicons dashicons-no"></span>
					</div>
				</div>
				<div class="speedycache-modal-content">';
					$heartbeat_modes = array(
						'15' => esc_html__('15 Seconds(Default)', 'speedycache'),
						'30' => esc_html__('30 seconds', 'speedycache'),
						'45' => esc_html__('45 Seconds', 'speedycache'),
						'60' => esc_html__('60 seconds', 'speedycache'),
						'120' => esc_html__('2 Minutes', 'speedycache'),
					);
					
					$disable_heartbeat = array(
						'dont' => esc_html__('Do not Disable', 'speedycache'),
						'disable' => esc_html__('Disable', 'speedycache'),
						'editor' => esc_html__('Allow on Editor only', 'speedycache'),
					);

					echo '<table>
					
					<tr>
					<td style="text-align:left;">
					<label for="speedycache_heartbeat_backend">'.esc_html__('Heartbeat Frequency', 'speedycache').'</label></td>
					<td><select id="speedycache_heartbeat_frequency" name="heartbeat_frequency" value="'.(!empty($speedycache->bloat['heartbeat_frequency']) ? esc_attr($speedycache->bloat['heartbeat_frequency']) : '').'">';
						foreach($heartbeat_modes as $value => $heartbeat_mode){
							$selected = '';
							
							if(!empty($speedycache->bloat['heartbeat_frequency']) && $speedycache->bloat['heartbeat_frequency'] == $value){
								$selected = 'selected';
							} elseif(empty($speedycache->bloat['heartbeat_frequency']) && $value == '120'){
								$selected = 'selected';
							}

							echo '<option value="'.esc_attr($value).'" '.esc_attr($selected).'>'.esc_html($heartbeat_mode).'</option>';
						}
					echo '</select></td></tr>';

					echo '<tr><td style="text-align:left;"><label for="speedycache_disable_heartbeat">'.esc_html__('Disable Heartbeat', 'speedycache').'</label></td>
					<td><select id="speedycache_disable_heartbeat" name="disable_heartbeat" value="'.(!empty($speedycache->bloat['disable_heartbeat']) ? esc_attr($speedycache->bloat['disable_heartbeat']) : '').'">';
						foreach($disable_heartbeat as $value => $disable_mode){
							$selected = '';
							
							if(!empty($speedycache->bloat['disable_heartbeat']) && $speedycache->bloat['disable_heartbeat'] == $value){
								$selected = 'selected';
							} elseif(empty($speedycache->bloat['disable_heartbeat']) && $value == 'dont'){
								$selected = 'selected';
							}

							echo '<option value="'.esc_attr($value).'" '.esc_attr($selected).'>'.esc_html($disable_mode).'</option>';
						}
					echo '</select></td></tr></table>';

				echo '</div>
				<div class="speedycache-modal-footer">
					<button type="button" action="close">
						<span>'.esc_html__('Submit', 'speedycache').'</span>
					</button>
				</div>
			</div>
		</div>';
		\SpeedyCache\Settings::save_btn();
		echo '</form>';
	}
	
	static function db_tab(){
		global $wpdb, $speedycache;
		
		echo '<h2><img src="'.SPEEDYCACHE_URL.'/assets/images/icons/db.svg" height="32" width="32"/> Database Optimizer</h2>';
		
		// TODO: Show notice which mentions about the bloat settings as we will slowly migrate the html code of bloat to the Pro version, to prevent the GPL plugin providers from making users fool.
		if(!defined('SPEEDYCACHE_PRO')){
			return;
		}
		
		$statics = array('all_warnings' => 0, 'post_revisions' => 0, 'trashed_contents' => 0, 'trashed_spam_comments' => 0, 'trackback_pingback' => 0, 'transient_options' => 0, 'expired_transient' => 0);
		
		// These stats have been moved to ajax, to make them lazy load as on website with big dbs,
		// it was preventing the site from loading SpeedyCache settings page.
		$statics['all_warnings'] = $statics['all_warnings'] + $statics['transient_options'] + $statics['trackback_pingback']+ $statics['trashed_spam_comments']+ $statics['trashed_contents']+ $statics['post_revisions'];

		echo '<div speedycache-db-name="all_warnings" class="speedycache-db-row">
			<div>
				<div class="speedycache-db-info db">
					<div>'.esc_html__('Clean everything', 'speedycache').' <span class="speedycache-db-number">('.esc_html($statics['all_warnings']).')</span></div>
					<div>'.esc_html__('Run the all options', 'speedycache').'</div>
				</div>
			</div>
			<button class="speedycache-button speedycache-btn-black speedycache-db-optm-btn">'.esc_html__('Run optimization', 'speedycache').'<span class="speedycache-spinner"></span></button>
		</div>

		<div speedycache-db-name="post_revisions" class="speedycache-db-row">
			<div>
				<div class="speedycache-db-info db">
					<div>'.esc_html__('Post Revisions', 'speedycache').' <span class="speedycache-db-number">('.esc_html($statics['post_revisions']).')</span></div>
					<div>'.esc_html__('Clean the all post revisions', 'speedycache').'</div>
				</div>
			</div>
			<button class="speedycache-button speedycache-btn-black speedycache-db-optm-btn">'.esc_html__('Run optimization', 'speedycache').'<span class="speedycache-spinner"></span></button>
		</div>

		<div speedycache-db-name="trashed_contents" class="speedycache-db-row">
			<div>
				<div class="speedycache-db-info db">
					<div>'.esc_html__('Trashed Contents', 'speedycache').'<span class="speedycache-db-number">('.esc_html($statics['trashed_contents']).')</span></div>
					<div>'.esc_html__('Clean the all trashed posts & pages', 'speedycache').'</div>
				</div>
			</div>
			<button class="speedycache-button speedycache-btn-black speedycache-db-optm-btn">'.esc_html__('Run optimization', 'speedycache').'<span class="speedycache-spinner"></span></button>
		</div>

		<div speedycache-db-name="trashed_spam_comments" class="speedycache-db-row">
			<div>
				<div class="speedycache-db-info db">
					<div>'.esc_html__('Trashed & Spam Comments', 'speedycache').' <span class="speedycache-db-number">('.esc_html($statics['trashed_spam_comments']).')</span></div>
					<div>'.esc_html__('Clean the all comments from trash & spam', 'speedycache').'</div>
				</div>
			</div>
			<button class="speedycache-button speedycache-btn-black speedycache-db-optm-btn">'.esc_html__('Run optimization', 'speedycache').'<span class="speedycache-spinner"></span></button>
		</div>

		<div speedycache-db-name="trackback_pingback" class="speedycache-db-row">
			<div>
				<div class="speedycache-db-info db">
					<div>'.esc_html__('Trackbacks and Pingbacks', 'speedycache').' <span class="speedycache-db-number">('.esc_html($statics['trackback_pingback']).')</span></div>
					<div>'.esc_html__('Clean the all trackbacks and pingbacks', 'speedycache').'</div>
				</div>
			</div>
			<button class="speedycache-button speedycache-btn-black speedycache-db-optm-btn">'.esc_html__('Run optimization', 'speedycache').'<span class="speedycache-spinner"></span></button>
		</div>

		<div speedycache-db-name="transient_options" class="speedycache-db-row">
			<div>
				<div class="speedycache-db-info db">
					<div>'.esc_html__('Transient Options', 'speedycache').' <span class="speedycache-db-number">('.esc_html($statics['transient_options']).')</span></div>
					<div>'.esc_html__('Clean the all transient options', 'speedycache').'</div>
				</div>
			</div>
			<button class="speedycache-button speedycache-btn-black speedycache-db-optm-btn">'.esc_html__('Run optimization', 'speedycache').'<span class="speedycache-spinner"></span></button>
		</div>
		<div speedycache-db-name="expired_transient" class="speedycache-db-row">
			<div>
				<div class="speedycache-db-info db">
					<div>'.esc_html__('Expired Transients', 'speedycache').' <span class="speedycache-db-number">('.esc_html($statics['expired_transient']).')</span></div>
					<div>'.esc_html__('Clean the expired transients', 'speedycache').'</div>
				</div>
			</div>
			<button class="speedycache-button speedycache-btn-black speedycache-db-optm-btn">'.esc_html__('Run optimization', 'speedycache').'<span class="speedycache-spinner"></span></button>
		</div>';

		// DB Automatic Optimization via Cron
		echo'<div class="speedycache-cron-container">
			<h3>'.esc_html__('Automatic Optimization', 'speedycache').'</h3>
			<p>'.esc_html__('Sets how frequently the database is automatically optimized.', 'speedycache').'</p>
			<form method="post">
				<input type="hidden" name="action" value="speedycache_save_db_settings"/>';
				wp_nonce_field('speedycache_ajax_nonce');
				
				echo '<select name="db_purge_interval">
					<option value="" '.(!empty($speedycache->options['db_purge_interval']) ? selected($speedycache->options['db_purge_interval'], '', false) : ' selected').'>'.esc_html__('Never', 'speedycache').'</option><option value="daily" '.(!empty($speedycache->options['db_purge_interval']) ? selected($speedycache->options['db_purge_interval'], 'daily', false) : ' selected').'>'.esc_html__('Daily', 'speedycache').'</option>
					<option value="weekly" '.(!empty($speedycache->options['db_purge_interval']) ? selected($speedycache->options['db_purge_interval'], 'weekly', false) : '').'>'.esc_html__('Weekly', 'speedycache').'</option>
					<option value="fortnight" '.(!empty($speedycache->options['db_purge_interval']) ? selected($speedycache->options['db_purge_interval'], 'fortnight', false) : '').'>'.esc_html__('Fortnight', 'speedycache').'</option>
					<option value="monthly" '.(!empty($speedycache->options['db_purge_interval']) ? selected($speedycache->options['db_purge_interval'], 'monthly', false) : '').'>'.esc_html__('Monthly', 'speedycache').'</option>
				</select>
				<div>
					<div class="speedycache-cron-checkbox">
						<div class="speedycache-cron-inside-checkbox">
							<div class="speedycache-checkbox">
								<label>
									<input type="checkbox" name="db_post_revisions" value="1" '.(!empty($speedycache->options['db_post_revisions']) ? checked($speedycache->options['db_post_revisions'], true, false) : '').'/>'.esc_html__('Post Revisions', 'speedycache').'
								</label>
							</div>
							<div class="speedycache-checkbox">
								<label>
									<input type="checkbox" name="db_trashed_contents" value="1" '.(!empty($speedycache->options['db_trashed_contents']) ? checked($speedycache->options['db_trashed_contents'], true, false) : '').'/>'.esc_html__('Trashed Contents', 'speedycache').'
								</label>
							</div>
							<div class="speedycache-checkbox">
								<label>
									<input type="checkbox" name="db_trashed_spam_comments" value="1" '.(!empty($speedycache->options['db_trashed_spam_comments']) ? checked($speedycache->options['db_trashed_spam_comments'], true, false) : '').'/>'.esc_html__('Trashed & Spam Comments', 'speedycache').'
								</label>
							</div>
							<div class="speedycache-checkbox">
								<label>
									<input type="checkbox" name="db_trackbacks_pingback" value="1" '.(!empty($speedycache->options['db_trackbacks_pingback']) ? checked($speedycache->options['db_trackbacks_pingback'], true, false) : '').'/>'.esc_html__('Trackbacks and Pingbacks', 'speedycache').'
								</label>
							</div>
							<div class="speedycache-checkbox">
								<label>
									<input type="checkbox" name="db_transient_options" value="1" '.(!empty($speedycache->options['db_transient_options']) ? checked($speedycache->options['db_transient_options'], true, false) : '').'/>'.esc_html__('Transient Options', 'speedycache').'
								</label>
							</div>
							<div class="speedycache-checkbox">
								<label>
									<input type="checkbox" name="db_expired_transient" value="1" '.(!empty($speedycache->options['db_expired_transient']) ? checked($speedycache->options['db_expired_transient'], true, false) : '').'/>'.esc_html__('Expired Transient', 'speedycache').'
								</label>
							</div>
						</div>
					</div>
				</div>';
				\SpeedyCache\Settings::save_btn();
			echo '</form>
		</div>';
		
	}
	
	static function object_tab(){
		global $speedycache;
	
		echo '<h2><img src="'.SPEEDYCACHE_URL.'/assets/images/icons/object.svg" height="32" width="32"/> '.esc_html__('Object Cache', 'speedycache').'</h2>';

		echo '<div class="speedycache-block">
			<div class="speedycache-admin-row">
				<div class="speedycache-is-block speedycache-object-stat">
					<div style="display:flex; justify-content:space-between;">
						<div><strong>Caching Status:</strong>  '.(!empty($speedycache->object['enable']) ? '<span style="color:green;">Enabled</span>' : '<span style="color:red;">Disabled</span>').'</div>
						<div><strong>Memory Usage:</strong> <span>'.esc_html__($speedycache->object_memory).'</span></div>
					</div>
					<div class="speedycache-drop-in"><strong>Drop In:</strong> '.(defined('SPEEDYCACHE_OBJECT_CACHE') ? '<span style="color:green;">Valid</span>' : '<span style="color:red;">Not Valid</span>').'</div>
					<div><strong>phpRedis Status:</strong> '.(empty(phpversion('redis')) ? '<span style="color:red">' . esc_html__('phpRedis Not Found', 'speedycache') : (version_compare(phpversion('redis'), '3.1.1') > 0 ? '<span style="color:green">'. esc_html__('Available', 'speedycache') . '('.esc_html(phpversion('redis')).')' : '<span style="color:red">' . esc_html__('You are using older version of PHPRedis'))).'</span></div>';
					if(!empty($speedycache->object['hashed_prefix'])){
						echo'<div><strong>Hashed Prefix:</strong> <span>'.esc_html($speedycache->object['hashed_prefix']).'</span></div>';
					}
					echo'<button class="speedycache-button speedycache-btn-black speedycache-flush-db">Flush DB<span class="speedycache-spinner"></span></button>
				</div>
			</div>
			<div class="speedycache-object-charts"></div>
		</div>
		
		<div class="speedycache-block">
			<form method="POST">';
			wp_nonce_field('speedycache_ajax_nonce');
			echo '<input type="hidden" value="speedycache_save_object_settings" name="action">

			<div class="speedycache-block-title">
				<h2>'.esc_html__('Settings', 'speedycache').'</h2>
			</div>
			<table class="wp-list-table speedycache-table" style="width:100%;">
				<tr>
					<th><label for="speedycache_enable_object">'.esc_html__('Enable', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_enable_object" class="speedycache-custom-checkbox">
							<input type="checkbox" id="speedycache_enable_object" name="enable_object" '.((isset($speedycache->object['enable']) && $speedycache->object['enable']) ? ' checked="true"' : '').'/>
							<div class="speedycache-input-slider"></div>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('Enables Object caching, if you have full page caching then it might show some conflicts.', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr style="display:none;">
					<th><label for="speedycache_object_driver">'.esc_html__('Driver', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_object_driver">
							<select name="driver" id="speedycache_object_driver">
								<option value="Redis" selected>Redis</option>
							</select>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('Choose which Object Cache Driver you want to use.', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_object_host">'.esc_html__('Host', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_object_host">
							<input type="text" name="host" id="speedycache_object_host" value="'.(!empty($speedycache->object['host']) ? esc_attr($speedycache->object['host']) : 'localhost').'"/>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('Your Redis host name or IP address.', 'speedycache').'</div>
					</td>
				</tr>

				<tr>
					<th><label for="speedycache_object_port">'.esc_html__('Port', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_object_port">
							<input type="text" name="port" id="speedycache_object_port" value="'.(!empty($speedycache->object['port']) ? esc_attr($speedycache->object['port']) : '6379').'"/>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('Your Redis host port number', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_object_username">'.esc_html__('Username', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_object_username">
							<input type="password" id="speedycache_object_username" name="username" value="'. ((!empty($speedycache->object['username'])) ? esc_html($speedycache->object['username']) : '').'" />
						</label>
						<div class="speedycache-option-desc">'.esc_html__('Username of your Redis acccount.', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_object_password">'.esc_html__('Password', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_object_password">
							<input type="password" id="speedycache_object_password" name="password" value="'.((!empty($speedycache->object['password'])) ? esc_html($speedycache->object['password']) : '').'" />
						</label>
						<div class="speedycache-option-desc">'.esc_html__('Password for your Redis Account.', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_object_ttl">'.esc_html__('Object Time to live', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_object_ttl">
							<input type="text" name="ttl" id="speedycache_object_ttl" value="'.(!empty($speedycache->object['ttl']) ? esc_attr($speedycache->object['ttl']) : '360').'"/>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('How long you want the cached Object to persist', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_object_db_id">'.esc_html__('Redis DB ID', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_object_db_id">
							<input type="text" name="db-id" id="speedycache_object_db_id" value="'. (!empty($speedycache->object['db-id']) ? esc_attr($speedycache->object['db-id']) : '0').'" style="width:45px;"/>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('Set the database number, make sure to keep it different for every website you use it on', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_persistant_object">'.esc_html__('Persistent Connection', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_persistent_object" class="speedycache-custom-checkbox">
							<input type="checkbox" id="speedycache_persistent_object" name="persistent" '. ((!empty($speedycache->object['persistent'])) ? ' checked="true"' : '').'/>
							<div class="speedycache-input-slider"></div>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('This will Keep Alive the connection to redis.', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_object_admin">'.esc_html__('Cache WP_Admin', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_object_admin" class="speedycache-custom-checkbox">
							<input type="checkbox" id="speedycache_object_admin" name="admin" '.((!empty($speedycache->object['admin'])) ? ' checked="true"' : '').'/>
							<div class="speedycache-input-slider"></div>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('This will cache the admin pages too.', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_async_flush">'.esc_html__('Asynchronous Flushing', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_async_flush" class="speedycache-custom-checkbox">
							<input type="checkbox" id="speedycache_async_flush" name="async_flush" '.((!empty($speedycache->object['async_flush'])) ? ' checked="true"' : '').'/>
							<div class="speedycache-input-slider"></div>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('Deletes asynchronously, without blocking', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_serialization_method">'.esc_html__('Serialization Method', 'speedycache').'</label></th>
					<td>';
						$serialization_methods = ['SERIALIZER_PHP', 'SERIALIZER_IGBINARY'];

						echo '<label for="speedycache_serialization_method">
						
							<select id="speedycache_serialization_method" name="serialization" value="'. (!empty($speedycache->object['serialization']) ? esc_attr($speedycache->object['serialization']) : 'php').'">
								<option value="none">None</option>';

								foreach($serialization_methods as $method){
									$selected = '';

									if(empty($speedycache->object['serialization']) && $method === 'SERIALIZER_PHP'){
										$selected = 'selected';
									}else if(!empty($speedycache->object['serialization']) && $speedycache->object['serialization'] === $method){
										$selected = 'selected';
									}

									if(defined('Redis::'.$method)){
										echo '<option value="'.esc_attr($method).'" '.esc_attr($selected).'>'.esc_html($method).'</option>';
									}
								}
							echo '</select>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('If you don\'t see IG_BINARY option then the phpredis is not built with IG_BINARY, IG_BINARY can save upto 50% space', 'speedycache').'</div>
					</td>
				</tr>

				<tr>
					<th><label for="speedycache_compression_method">'.esc_html__('Compression Method', 'speedycache').'</label>
					</th>
					<td>';
						$serialization_methods = ['None', 'COMPRESSION_ZSTD', 'COMPRESSION_LZ4', 'COMPRESSION_LZF'];

						echo '<label for="speedycache_compression_method">
						
							<select id="speedycache_compression_method" name="compress" value="'. (!empty($speedycache->object['compress']) ? esc_attr($speedycache->object['compress']) : 'php').'">
								<option value="none">None</option>';

								foreach($serialization_methods as $method){
									$selected = '';

									if(empty($speedycache->object['compress']) && $method === 'None'){
										$selected = 'selected';
									}else if(!empty($speedycache->object['compress']) && $speedycache->object['compress'] === $method){
										$selected = 'selected';
									}

									if(defined('Redis::'.$method)){
										echo '<option value="'.esc_attr($method).'" '.esc_attr($selected).'>'.esc_html($method).'</option>';
									}
								}
							echo '</select>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('If you dont see any option then your phpredis is not built with compression options', 'speedycache').'</div>
					</td>
				</tr>
				
				<tr>
					<th><label for="speedycache_non_cache_group">'.esc_html__('Do not cache groups', 'speedycache').'</label></th>
					<td>
						<label for="speedycache_non_cache_group">
							<textarea id="speedycache_non_cache_group" name="non_cache_group" rows="5" cols="30">';
								if(empty($speedycache->object['non_cache_group'])){
									$speedycache->object['non_cache_group'] = ['plugins', 'comment', 'counts', 'wc_session_id'];
								}

								foreach($speedycache->object['non_cache_group'] as $group){
									echo esc_html($group) . "\n";
								}
							echo '</textarea>
						</label>
						<div class="speedycache-option-desc">'.esc_html__('These are the groups which should not be cached, One Per Line', 'speedycache').'</div>
					</td>
				</tr>
				
			</table>';

			\SpeedyCache\Settings::save_btn();
			echo '</form>
		</div>';
	}
}
