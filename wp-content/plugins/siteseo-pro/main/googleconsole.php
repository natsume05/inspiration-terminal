<?php
/*
* SITESEO
* https://siteseo.io
* (c) SiteSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

class GoogleConsole{

	static function save_tokens($tokens){
		return update_option('siteseo_google_tokens', $tokens);
	}

	static function get_tokens(){
		return get_option('siteseo_google_tokens', []);
	}

	static function generate_tokens(){

		$auth_code = isset($_GET['siteseo_auth_code']) ? sanitize_text_field(wp_unslash($_GET['siteseo_auth_code'])) : '';

		$post = [
			'code'   => $auth_code,
			'action' => 'generate_tokens'
		];

		$resp = wp_remote_post(SITESEO_API . '/search-console/token.php', [
			'body' => $post,
			'timeout' => 30
		]);

		if(is_wp_error($resp)){
			return ['error' => 'Google Search Console: ' . $resp->get_error_message()];
		}

		$body = wp_remote_retrieve_body($resp);

		if(empty($body)){
			return ['error' => 'Google Search Console: Empty response received from API'];
		}

		$data = json_decode($body, true);

		if(!empty($data['error'])){
			if(is_array($data['error'])){
				return ['error' => 'Google Search Console: '.$data['error']['code'].' : '.$data['error']['message']];
			} else {
				return ['error' => 'Google Search Console: '.$data['error'].' : '.$data['error_description']];
			}
		}

		if(empty($data['access_token'])){
			return ['error' => 'Google Search Console: Access token not found in API response'];
		}

		if(empty($data['refresh_token'])){
			return ['error' => 'Google Search Console: Refresh token not found in API response'];
		}

		$tokens = [
			'access_token'  => $data['access_token'],
			'refresh_token' => $data['refresh_token'],
			'created_time'  => time(),
		];

		self::save_tokens($tokens);

		return $tokens;
	}

	static function generate_access_token(){
		$tokens = self::get_tokens();

		if(empty($tokens['refresh_token'])){
			return ['error' => 'No refresh token available'];
		}

		$post_data = [
			'refresh_token' => $tokens['refresh_token'],
			'action' => 'generate_access_token'
		];

		$resp = wp_remote_post(SITESEO_API. '/search-console/token.php', [
			'body' => $post_data,
			'timeout' => 30
		]);

		if(is_wp_error($resp)){
			return ['error' => $resp->get_error_message()];
		}

		$body = wp_remote_retrieve_body($resp);
		$code = wp_remote_retrieve_response_code($resp);

		if($code < 200 || $code > 399){
			if(!empty($body)){
				$data = json_decode($body, true);
				if(!empty($data)){
					return ['error' => $data];
				}
			}

			return ['error' => __('Google Search Console: Error response received from API with status code ', 'siteseo-pro') . $code];
		}

		if(empty($body)){
			return ['error' => __('Google Search Console: Empty response received from API', 'siteseo-pro')];
		}

		$data = json_decode($body, true);

		if(empty($data)){
			return ['error' => __('Google Search Console: Empty response received from API', 'siteseo-pro')];
		}

		if(isset($data['error'])){
			return ['error' => $data['error']];
		}

		$tokens['access_token'] = $data['access_token'];
		
		self::save_tokens($tokens);
		return $tokens;
	}

	static function is_connected(){
		$data = get_option('siteseo_google_tokens', []);
		return !empty($data['connected']);
	}

	static function update_gsc_connection_status(){
		$data = get_option('siteseo_google_tokens', []);
		if(!is_array($data)){
			$data = [];
		}
		
		$data['connected'] = true;
		update_option('siteseo_google_tokens', $data);
	}

	static function disconnect(){
		delete_option('siteseo_google_tokens');
		delete_option('siteseo_search_console_data');
		return true;
	}

	static function get_page_title_from_url($url){
		$parsed_url = wp_parse_url($url);

		// If SPA URL contains a hash fragment, return the full URL
		if(isset($parsed_url['fragment']) && !empty($parsed_url['fragment'])){
			return $url;
		}

		$domain = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$path = isset($parsed_url['path']) ? rtrim($parsed_url['path'], '/') : '';

		// Homepage case
		if(empty($path) || $path === '/'){
			return $domain;
		}

		// Internal pages
		return $path . '/';
	}

	static function process_analytics_data($date_data, $pages_data = null, $keyword_data = null, $content_performance_data = null, $device = null, $country = null){

		$processed = [
			'metrics' => [
				'impressions' => ['current' => 0, 'change' => 0, 'trend' => 'neutral'],
				'clicks' => ['current' => 0, 'change' => 0, 'trend' => 'neutral'],
				'ctr' => ['current' => 0, 'change' => 0, 'trend' => 'neutral'],
				'position' => ['current' => 0, 'change' => 0, 'trend' => 'neutral']
			],
			'top_pages' => [],
			'top_keywords' => [],
			'content_ranking' => [],
			'top_loss_pages' => [],
			'top_winning_pages' => [],
			'top_winning_keywords' => [],
			'top_loss_keywords' => [],
			'chart_data' => [
				'impressions' => [],
				'clicks' => [],
				'dates' => [],
				'position' => [],
				'ctr' => [],
			],
			'keyword_data' => [
				'top3' => [],
				'pos4_10' => [],
				'pos11_50' => [],
				'pos50_100' => [],
				'dates' => []
			],
			'keyword_distribution' => [
				'top3' => 0,
				'pos4_10' => 0,
				'pos11_50' => 0,
				'pos50_100' => 0
			],
			'country_audience' =>[],
			'device_audience' => [],
			'total_devices_clicks' => [],
		];

		// Process main date-based data
		if(isset($date_data['rows']) && !empty($date_data['rows'])){
			$processed = self::process_date_data($date_data, $processed);
			
			// Process keyword data
			if($keyword_data && isset($keyword_data['rows']) && !empty($keyword_data['rows'])){
				$processed = self::process_keyword_data($keyword_data, $processed);
			}

			// Process pages data
			if($pages_data && isset($pages_data['rows']) && !empty($pages_data['rows'])){
				$processed = self::process_pages_data($pages_data, $processed);
			}

			// Process content performance data
			if($content_performance_data && isset($content_performance_data['rows']) && !empty($content_performance_data['rows'])){
				$processed = self::process_content_performance_data($content_performance_data, $processed);
			}
			
			if($device && isset($device['rows']) && !empty($device['rows'])){
				$processed = self::process_device_data($device, $processed);
			}
			
			if($country && isset($country['rows']) && !empty($country['rows'])){
				$processed = self::process_country_data($country, $processed);
			}
		}

		// Save
		return update_option('siteseo_search_console_data', $processed);
	}
	
	static function process_device_data($device, $processed){
		$device_data = [];
		$total_clicks = 0;

		if(isset($device['rows']) && !empty($device['rows'])){
			foreach($device['rows'] as $row){
				$device_name = $row['keys'];
				$impressions = $row['impressions'];
				$clicks = $row['clicks'];
				$ctr = $row['ctr'];
				$position = $row['position'];

				// Add to total_clicks
				$total_clicks += $clicks;

				$device_data[] = [
					'device' => $device_name,
					'impressions' => round($impressions),
					'clicks' => $clicks,
					'ctr' => round($ctr),
					'position' => $position
				];
			}

			// Sort by impressions (descending)
			usort($device_data, ['self', 'sort_by_impressions_desc']);

			// Store
			$processed['device_audience'] = $device_data;
			$processed['total_devices_clicks'] = $total_clicks; // Added total_clicks
		}

		return $processed;
	}

	static function process_country_data($country, $processed){
		$country_data = [];

		if(isset($country['rows']) && !empty($country['rows'])){
			foreach($country['rows'] as $row){
				$country_code = $row['keys'][0];
				$impressions = $row['impressions'];
				$clicks = $row['clicks'];
				$ctr = $row['ctr'];
				$position = $row['position'];

				// Convert country code to full country name
				$country_name = self::get_country_name($country_code);

				$country_data[] = [
					'country' => $country_name,
					'impressions' => round($impressions),
					'clicks' => $clicks,
					'ctr' => round($ctr),
					'position' => $position
				];
			}

			// Sort by impressions (descending)
			usort($country_data, ['self', 'sort_by_impressions_desc']);

			// Store
			$processed['country_audience'] = $country_data;
		}
		
		return $processed;
	}
	
	static function get_country_name($country_code){
		// First, let's normalize the country code
		$normalized_code = strtoupper(trim($country_code));
		
		$country_names = [
			// Standard ISO codes
			'US' => __('United States', 'siteseo-pro'), 
			'GB' => __('United Kingdom', 'siteseo-pro'), 
			'UK' => __('United Kingdom', 'siteseo-pro'), 
			'CA' => __('Canada', 'siteseo-pro'), 
			'AU' => __('Australia', 'siteseo-pro'), 
			'DE' => __('Germany', 'siteseo-pro'), 
			'FR' => __('France', 'siteseo-pro'), 
			'IT' => __('Italy', 'siteseo-pro'), 
			'ES' => __('Spain', 'siteseo-pro'), 
			'JP' => __('Japan', 'siteseo-pro'), 
			'IN' => __('India', 'siteseo-pro'), 
			'BR' => __('Brazil', 'siteseo-pro'), 
			'MX' => __('Mexico', 'siteseo-pro'), 
			'NL' => __('Netherlands', 'siteseo-pro'), 
			'SE' => __('Sweden', 'siteseo-pro'), 
			'NO' => __('Norway', 'siteseo-pro'), 
			'DK' => __('Denmark', 'siteseo-pro'),
			'FI' => __('Finland', 'siteseo-pro'), 
			'RU' => __('Russia', 'siteseo-pro'), 
			'CN' => __('China', 'siteseo-pro'), 
			'KR' => __('South Korea', 'siteseo-pro'),
			'USA' => __('United States', 'siteseo-pro'), 
			'UNITED STATES' => __('United States', 'siteseo-pro'),
			'UNITED KINGDOM' => __('United Kingdom', 'siteseo-pro'),
			'AUS' => __('Australia', 'siteseo-pro'), 
			'AUSTRALIA' => __('Australia', 'siteseo-pro'),
			'GER' => __('Germany', 'siteseo-pro'),
			'GERMANY' => __('Germany', 'siteseo-pro'),
			'FRA' => __('France', 'siteseo-pro'), 
			'FRANCE' => __('France', 'siteseo-pro'),
			'ITA' => __('Italy', 'siteseo-pro'), 
			'ITALY' => __('Italy', 'siteseo-pro'),
			'SPA' => __('Spain', 'siteseo-pro'), 
			'SPAIN' => __('Spain', 'siteseo-pro'),
			'JPN' => __('Japan', 'siteseo-pro'), 
			'JAPAN' => __('Japan', 'siteseo-pro'),
			'IND' => __('India', 'siteseo-pro'), 
			'INDIA' => __('India', 'siteseo-pro'),
			'BRA' => __('Brazil', 'siteseo-pro'), 
			'BRAZIL' => __('Brazil', 'siteseo-pro'),
			'MEX' => __('Mexico', 'siteseo-pro'), 
			'MEXICO' => __('Mexico', 'siteseo-pro'),
			'NLD' => __('Netherlands', 'siteseo-pro'), 
			'NETHERLANDS' => __('Netherlands', 'siteseo-pro'),
			'SWE' => __('Sweden', 'siteseo-pro'),
			'SWEDEN' => __('Sweden', 'siteseo-pro'),
			'NOR' => __('Norway', 'siteseo-pro'), 
			'NORWAY' => __('Norway', 'siteseo-pro'),
			'DNK' => __('Denmark', 'siteseo-pro'), 
			'DENMARK' => __('Denmark', 'siteseo-pro'),
			'FIN' => __('Finland', 'siteseo-pro'), 
			'FINLAND' => __('Finland', 'siteseo-pro'),
			'RUS' => __('Russia', 'siteseo-pro'),
			'RUSSIA' => __('Russia', 'siteseo-pro'),
			'CHN' => __('China', 'siteseo-pro'), 
			'CHINA' => __('China', 'siteseo-pro'),
			'KOR' => __('South Korea', 'siteseo-pro'),
			'SOUTH KOREA' => __('South Korea', 'siteseo-pro'),
			'AF' => __('Afghanistan', 'siteseo-pro'),
			'AL' => __('Albania', 'siteseo-pro'), 
			'DZ' => __('Algeria', 'siteseo-pro'),
			'AR' => __('Argentina', 'siteseo-pro'),
			'AT' => __('Austria', 'siteseo-pro'), 
			'BH' => __('Bahrain', 'siteseo-pro'),
			'BD' => __('Bangladesh', 'siteseo-pro'),
			'BE' => __('Belgium', 'siteseo-pro'), 
			'BG' => __('Bulgaria', 'siteseo-pro'),
			'CL' => __('Chile', 'siteseo-pro'), 
			'CO' => __('Colombia', 'siteseo-pro'), 
			'CR' => __('Costa Rica', 'siteseo-pro'),
			'HR' => __('Croatia', 'siteseo-pro'),
			'CZ' => __('Czech Republic', 'siteseo-pro'),
			'EG' => __('Egypt', 'siteseo-pro'),
			'GR' => __('Greece', 'siteseo-pro'), 
			'HK' => __('Hong Kong', 'siteseo-pro'),
			'HU' => __('Hungary', 'siteseo-pro'),
			'ID' => __('Indonesia', 'siteseo-pro'),
			'IE' => __('Ireland', 'siteseo-pro'),
			'IL' => __('Israel', 'siteseo-pro'),
			'JO' => __('Jordan', 'siteseo-pro'),
			'KW' => __('Kuwait', 'siteseo-pro'), 
			'LB' => __('Lebanon', 'siteseo-pro'),
			'MY' => __('Malaysia', 'siteseo-pro'),
			'MA' => __('Morocco', 'siteseo-pro'), 
			'NZ' => __('New Zealand', 'siteseo-pro'),
			'NG' => __('Nigeria', 'siteseo-pro'), 
			'OM' => __('Oman', 'siteseo-pro'), 
			'PK' => __('Pakistan', 'siteseo-pro'),
			'PE' => __('Peru', 'siteseo-pro'),
			'PH' => __('Philippines', 'siteseo-pro'),
			'PL' => __('Poland', 'siteseo-pro'),
			'PT' => __('Portugal', 'siteseo-pro'),
			'QA' => __('Qatar', 'siteseo-pro'),
			'RO' => __('Romania', 'siteseo-pro'),
			'SA' => __('Saudi Arabia', 'siteseo-pro'), 
			'SG' => __('Singapore', 'siteseo-pro'),
			'ZA' => __('South Africa', 'siteseo-pro'),
			'LK' => __('Sri Lanka', 'siteseo-pro'),
			'CH' => __('Switzerland', 'siteseo-pro'),
			'TW' => __('Taiwan', 'siteseo-pro'),
			'TH' => __('Thailand', 'siteseo-pro'),
			'TR' => __('Turkey', 'siteseo-pro'),
			'UA' => __('Ukraine', 'siteseo-pro'),
			'AE' => __('United Arab Emirates', 'siteseo-pro'), 
			'VE' => __('Venezuela', 'siteseo-pro'),
			'VN' => __('Vietnam', 'siteseo-pro'),		
		];
		
		// match
		if(isset($country_names[$normalized_code])){
			return $country_names[$normalized_code];
		}
		
		// If no match found ,return the original code
		foreach($country_names as $code => $name){
			if(strpos($normalized_code, $code) !== false || strpos($name, $normalized_code) !== false){
				return $name;
			}
		}
		
		if(strlen($normalized_code) == 2){
			return $normalized_code . ' (Unknown)';
		}
		
		return $country_code;
	}

	static function process_date_data($date_data, $processed){
		$daily_data = [];
		$monthly_data = [];
		$total_impressions = 0;
		$total_clicks = 0;
		$total_position = 0;
		$total_days = 0;

		foreach($date_data['rows'] as $row){
			if(!isset($row['keys'][0])) continue;
			
			$date = $row['keys'][0];
			$day = gmdate('Y-m-d', strtotime($date));
			$month = gmdate('Y-m', strtotime($date));

			// Initialize daily data
			if(!isset($daily_data[$day])){
				$daily_data[$day] = [
					'impressions' => 0,
					'clicks' => 0,
					'position_total' => 0,
					'rows_count' => 0
				];
			}

			// Initialize monthly data
			if(!isset($monthly_data[$month])){
				$monthly_data[$month] = [
					'impressions' => 0,
					'clicks' => 0,
					'position_total' => 0,
					'rows_count' => 0
				];
			}

			$impressions = isset($row['impressions']) ? $row['impressions'] : 0;
			$clicks = isset($row['clicks']) ? $row['clicks'] : 0;
			$position = isset($row['position']) ? $row['position'] : 0;

			// Update daily data
			$daily_data[$day]['impressions'] += $impressions;
			$daily_data[$day]['clicks'] += $clicks;
			$daily_data[$day]['position_total'] += $position;
			$daily_data[$day]['rows_count']++;

			// Update monthly data
			$monthly_data[$month]['impressions'] += $impressions;
			$monthly_data[$month]['clicks'] += $clicks;
			$monthly_data[$month]['position_total'] += $position;
			$monthly_data[$month]['rows_count']++;
		}

		// Prepare chart data
		ksort($daily_data);
		foreach($daily_data as $day => $d){
			$total_impressions += $d['impressions'];
			$total_clicks += $d['clicks'];

			$day_position = ($d['rows_count'] > 0) ? $d['position_total'] / $d['rows_count'] : 0;
			$total_position += $day_position;
			$total_days++;

			$avg_position = ($d['rows_count'] > 0) ? round($d['position_total'] / $d['rows_count'], 2) : 0;
			$ctr = $d['impressions'] > 0 ? round(($d['clicks'] / $d['impressions']) * 100, 2) : 0;

			$processed['chart_data']['dates'][] = $day;
			$processed['chart_data']['impressions'][] = $d['impressions'];
			$processed['chart_data']['clicks'][] = $d['clicks'];
			$processed['chart_data']['ctr'][] = $ctr;
			$processed['chart_data']['position'][] = $avg_position;
		}

		// Calculate metrics
		$processed['metrics']['impressions']['current'] = number_format($total_impressions);
		$processed['metrics']['clicks']['current'] = number_format($total_clicks);
		$processed['metrics']['ctr']['current'] = $total_impressions > 0 ? round(($total_clicks / $total_impressions) * 100, 2) . '%' : '0%';
		$processed['metrics']['position']['current'] = $total_days > 0 ? round($total_position / $total_days, 1) : 0;

		// Calculate trends using monthly data
		$current_month = gmdate('Y-m');
		$previous_month = gmdate('Y-m', strtotime('-1 month'));
		$two_months_ago = gmdate('Y-m', strtotime('-2 month'));

		if (empty($monthly_data[$current_month]['impressions']) && isset($monthly_data[$previous_month])) {
			// If current month has no data, use previous month as "current"
			$current = $monthly_data[$previous_month];
			$previous = isset($monthly_data[$two_months_ago]) ? $monthly_data[$two_months_ago] : ['impressions' => 0, 'clicks' => 0,'position_total' => 0, 'rows_count' => 1];
		} elseif(isset($monthly_data[$current_month]) && isset($monthly_data[$previous_month])) {
			$current = $monthly_data[$current_month];
			$previous = $monthly_data[$previous_month];
		} else {
			$current = ['impressions' => 0, 'clicks' => 0, 'position_total' => 0, 'rows_count' => 1];
			$previous = ['impressions' => 0, 'clicks' => 0, 'position_total' => 0, 'rows_count' => 1];
		}

		// Impression trend
		$current_impressions = $current['impressions'];
		$previous_impressions = $previous['impressions'];
		$impression_change = $current_impressions - $previous_impressions;
		$processed['metrics']['impressions']['change'] = number_format(abs($impression_change));
		$processed['metrics']['impressions']['trend'] = $impression_change >= 0 ? 'positive' : 'negative';

		// Click trend
		$current_clicks = $current['clicks'];
		$previous_clicks = $previous['clicks'];
		$click_change = $current_clicks - $previous_clicks;
		$processed['metrics']['clicks']['change'] = number_format(abs($click_change));
		$processed['metrics']['clicks']['trend'] = $click_change >= 0 ? 'positive' : 'negative';

		// Position trend (lower is better)
		$current_position = ($current['rows_count'] > 0) ? $current['position_total'] / $current['rows_count'] : 0;
		$previous_position = ($previous['rows_count'] > 0) ? $previous['position_total'] / $previous['rows_count'] : 0;
		$position_change = $current_position - $previous_position;
		$processed['metrics']['position']['change'] = round(abs($position_change), 1);
		$processed['metrics']['position']['trend'] = $position_change <= 0 ? 'positive' : 'negative';

		// CTR trend
		$current_ctr = $current_impressions > 0 ? ($current_clicks / $current_impressions) * 100 : 0;
		$previous_ctr = $previous_impressions > 0 ? ($previous_clicks / $previous_impressions) * 100 : 0;
		$ctr_change = $current_ctr - $previous_ctr;
		$processed['metrics']['ctr']['change'] = round(abs($ctr_change), 2) . '%';
		$processed['metrics']['ctr']['trend'] = $ctr_change >= 0 ? 'positive' : 'negative';

		return $processed;
	}

	static function process_keyword_data($keyword_data, $processed){
		$top_keywords = [];
		$top_winning_keywords = [];
		$top_loss_keywords = [];

		$total_keywords_count = 0;
		$keyword_daily_data = [];
		$unique_keywords = [];
		$keyword_aggregated = [];
		
		// Calculate average position for all keywords
		$total_position = 0;
		$keyword_count = 0;
		
		foreach($keyword_data['rows'] as $row){
			if(!isset($row['keys'][0])) continue;
			
			$keyword = $row['keys'][0];
			$date = isset($row['keys'][1]) ? $row['keys'][1] : null;
			
			// Skip if no date
			if(!$date){
				continue;
			}
			
			$day = gmdate('Y-m-d', strtotime($date));
			$impressions = isset($row['impressions']) ? $row['impressions'] : 0;
			$clicks = isset($row['clicks']) ? $row['clicks'] : 0;
			$position = isset($row['position']) ? round($row['position'], 1) : 0;
			$ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
			
			// Calculate average position
			if($position > 0){
				$total_position += $position;
				$keyword_count++;
			}
			
			// Track unique keywords with impressions
			if($impressions > 0){
				$unique_keywords[$keyword] = true;
			}

			// Initialize daily data if not exists
			if(!isset($keyword_daily_data[$day])){
				$keyword_daily_data[$day] = [
					'top3' => 0,
					'pos4_10' => 0,
					'pos11_50' => 0,
					'pos50_100' => 0,
					'keywords_tracked' => []
				];
			}

			// Only count each keyword once per day
			if(!in_array($keyword, $keyword_daily_data[$day]['keywords_tracked'])){
				if($impressions > 0 && $position > 0){
					// Categorize by position for daily distribution
					if($position <= 3){
						$keyword_daily_data[$day]['top3']++;
					} elseif($position <= 10){
						$keyword_daily_data[$day]['pos4_10']++;
					} elseif($position <= 50){
						$keyword_daily_data[$day]['pos11_50']++;
					} else{
						$keyword_daily_data[$day]['pos50_100']++;
					}
					
					$keyword_daily_data[$day]['keywords_tracked'][] = $keyword;
				}
			}

			if(!isset($keyword_aggregated[$keyword])){
				$keyword_aggregated[$keyword] = [
					'impressions' => 0,
					'clicks' => 0,
					'position' => 0,
					'position_count' => 0
				];
			}

			$keyword_aggregated[$keyword]['impressions'] += $impressions;
			$keyword_aggregated[$keyword]['clicks'] += $clicks;
			if($position > 0){
				$keyword_aggregated[$keyword]['position'] += $position;
				$keyword_aggregated[$keyword]['position_count']++;
			}
		}
		
		// Count total unique keywords
		$total_keywords_count = count($unique_keywords);

		// Store
		$processed['metrics']['total_keywords'] = [
			'current' => number_format($total_keywords_count),
			'change' => '+0',
			'trend' => 'neutral'
		];

		// Initialize keyword_data 
		$processed['keyword_data']['dates'] = [];
		$processed['keyword_data']['top3'] = [];
		$processed['keyword_data']['pos4_10'] = [];
		$processed['keyword_data']['pos11_50'] = [];
		$processed['keyword_data']['pos50_100'] = [];

		foreach($processed['chart_data']['dates'] as $chart_date){
			$day_label = gmdate('M j', strtotime($chart_date));
			$processed['keyword_data']['dates'][] = $day_label;
			
			if(isset($keyword_daily_data[$chart_date])){
				$data = $keyword_daily_data[$chart_date];
				
				// Use actual counts
				$processed['keyword_data']['top3'][] = $data['top3'];
				$processed['keyword_data']['pos4_10'][] = $data['pos4_10'];
				$processed['keyword_data']['pos11_50'][] = $data['pos11_50'];
				$processed['keyword_data']['pos50_100'][] = $data['pos50_100'];
			} else{
				// No data for this date
				$processed['keyword_data']['top3'][] = 0;
				$processed['keyword_data']['pos4_10'][] = 0;
				$processed['keyword_data']['pos11_50'][] = 0;
				$processed['keyword_data']['pos50_100'][] = 0;
			}
		}
		
		// Calculate overall distribution based on aggregated keywords
		$position_distribution = [
			'top3' => 0,
			'pos4_10' => 0,
			'pos11_50' => 0,
			'pos50_100' => 0
		];
		
		foreach($keyword_aggregated as $keyword => $data){
			if($data['impressions'] == 0) continue;
			
			// Calculate average position for this keyword
			$avg_position = $data['position_count'] > 0 ? $data['position'] / $data['position_count'] : 0;
			
			// Calculate CTR
			$ctr = $data['impressions'] > 0 ? round(($data['clicks'] / $data['impressions']) * 100, 2) : 0;
			
			// Categorize by position
			if($avg_position > 0 && $avg_position <= 3){
				$position_distribution['top3']++;
			} elseif($avg_position > 3 && $avg_position <= 10){
				$position_distribution['pos4_10']++;
			} elseif($avg_position > 10 && $avg_position <= 50){
				$position_distribution['pos11_50']++;
			} elseif($avg_position > 50){
				$position_distribution['pos50_100']++;
			}
			
			// Calculate points for ranking
			$points = self::calculate_keyword_points($data['clicks'], $data['impressions'], $avg_position, $ctr);
			$trend = $points > 50 ? 'winning' : 'loss';
			
			$keyword_data_item = [
				'keyword' => $keyword,
				'clicks' => number_format($data['clicks']),
				'impressions' => number_format($data['impressions']),
				'position' => round($avg_position, 1),
				'ctr' => $ctr . '%',
				'points' => $points,
				'trend' => $trend
			];
			
			$top_keywords[] = $keyword_data_item;
			
			// Categorize for winning/loss based on trend
			if($trend === 'winning'){
				$top_winning_keywords[] = $keyword_data_item;
			} else{
				$top_loss_keywords[] = $keyword_data_item;
			}
		}

		// Calculate distribution percentages for the bar chart
		$total_keywords_dist = array_sum($position_distribution);
		if($total_keywords_dist > 0){
			$processed['keyword_distribution']['top3'] = round(($position_distribution['top3'] / $total_keywords_dist) * 100, 1);
			$processed['keyword_distribution']['pos4_10'] = round(($position_distribution['pos4_10'] / $total_keywords_dist) * 100, 1);
			$processed['keyword_distribution']['pos11_50'] = round(($position_distribution['pos11_50'] / $total_keywords_dist) * 100, 1);
			$processed['keyword_distribution']['pos50_100'] = round(($position_distribution['pos50_100'] / $total_keywords_dist) * 100, 1);
		} else{
			$processed['keyword_distribution']['top3'] = 0;
			$processed['keyword_distribution']['pos4_10'] = 0;
			$processed['keyword_distribution']['pos11_50'] = 0;
			$processed['keyword_distribution']['pos50_100'] = 0;
		}
		
		// Sort
		usort($top_keywords, ['self', 'sort_by_impressions_desc']);
		
		usort($top_winning_keywords, ['self', 'sort_by_points_desc']);
		
		usort($top_loss_keywords, ['self', 'sort_by_points_asc']);
		
		$processed['top_keywords'] = array_slice($top_keywords, 0, 10);
		$processed['top_winning_keywords'] = array_slice($top_winning_keywords, 0, 10);
		$processed['top_loss_keywords'] = array_slice($top_loss_keywords, 0, 10);
		
		return $processed;
	}

	static function process_pages_data($pages_data, $processed){
		$top_pages = [];
		$top_loss_pages = [];
		$top_winning_pages = [];

		foreach($pages_data['rows'] as $row){
			if(!isset($row['keys'][0])) continue;
			
			$page_url = $row['keys'][0];
			$impressions = isset($row['impressions']) ? $row['impressions'] : 0;
			$clicks = isset($row['clicks']) ? $row['clicks'] : 0;
			$position = isset($row['position']) ? round($row['position'], 1) : 0;
			$ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
			
			// Get clean page title
			$page_title = self::get_page_title_from_url($page_url);
			
			// Calculate actual score based on performance
			$truseo_score = self::calculate_page_score($clicks, $impressions, $position, $ctr);
			
			// Calculate actual difference based on performance metrics
			$diff_value = self::calculate_performance_diff($clicks, $impressions, $position);
			$diff_display = $diff_value > 0 ? '+'.$diff_value : $diff_value;
			
			$page_data = [
				'title' => $page_title,
				'url' => $page_url,
				'clicks' => number_format($clicks),
				'impressions' => number_format($impressions),
				'position' => $position,
				'ctr' => $ctr . '%',
				'indexed' => true,
				'truseo_score' => round($truseo_score),
				'diff' => $diff_display,
				'diff_value' => $diff_value
			];
			
			$top_pages[] = $page_data;
			
			// Categorize for top loss/winning
			if($diff_value < 0){
				$top_loss_pages[] = $page_data;
			} elseif($diff_value > 0){
				$top_winning_pages[] = $page_data;
			}
		}

		// Sort
		usort($top_pages, ['self', 'sort_by_clicks_desc']);

		usort($top_loss_pages, ['self', 'sort_by_diff_asc']);

		usort($top_winning_pages, ['self', 'sort_by_diff_desc']);

		$processed['top_pages'] = $top_pages;
		$processed['top_loss_pages'] = $top_loss_pages;
		$processed['top_winning_pages'] = $top_winning_pages;

		return $processed;
	}

	static function process_content_performance_data($content_performance_data, $processed){
		$content_ranking = [];
		$unique_titles = [];

		foreach($content_performance_data['rows'] as $row){
			if(!isset($row['keys'][0])) continue;
		
			$page_url = $row['keys'][0];
			$last_update = isset($row['keys'][1]) ? $row['keys'][1] : '';
			$impressions = isset($row['impressions']) ? $row['impressions'] : 0;
			$clicks = isset($row['clicks']) ? $row['clicks'] : 0;
			$position = isset($row['position']) ? round($row['position'], 1) : 0;

			// page title
			$page_title = self::get_page_title_from_url($page_url);

			$title_lower = strtolower(trim($page_title));
			if(in_array($title_lower, $unique_titles)){
				continue;
			}
		
			// Add to uniques
			$unique_titles[] = $title_lower;
		
			// Calculate actual performance metrics
			$performance_data = self::calculate_content_performance($clicks, $impressions, $position);
		
			$content_data = [
				'title' => $page_title,
				'url' => $page_url,
				'indexed' => $performance_data['indexed'],
				'last_update' => $last_update,
				'loss' => number_format($performance_data['loss']),
				'drop_percent' => $performance_data['drop_percent'] . '%',
				'performance_score' => $performance_data['performance_score'] . '/100',
				'clicks' => number_format($clicks),
				'impressions' => number_format($impressions),
				'position' => $position
			];

			$content_ranking[] = $content_data;
		}
	
		// Sort
		usort($content_ranking, ['self', 'sort_by_performance_score_desc']);

		$content_ranking = array_slice($content_ranking, 0, 30); // LIMIT to 30 Content Analysis
		$processed['content_ranking'] = $content_ranking;
		return $processed;
	}

	static function sort_by_performance_score_desc($a, $b){
		if($a['performance_score'] == $b['performance_score']) return 0;
		return ($a['performance_score'] < $b['performance_score']) ? 1 : -1;
	}

	// Sort by impressions DESC
	static function sort_by_impressions_desc($a, $b){
		if($a['impressions'] == $b['impressions']) return 0;
		return ($a['impressions'] < $b['impressions']) ? 1 : -1;
	}
	
	static function sort_by_points_desc($a, $b){
		if($a['points'] == $b['points']) return 0;
		return ($a['points'] < $b['points']) ? 1 : -1;
	}

	static function sort_by_points_asc($a, $b){
		if($a['points'] == $b['points']) return 0;
		return ($a['points'] > $b['points']) ? 1 : -1;
	}

	// Sort by clicks DESC
	static function sort_by_clicks_desc($a, $b){
		if($a['clicks'] == $b['clicks']) return 0;
		return ($a['clicks'] < $b['clicks']) ? 1 : -1;
	}
	
	static function sort_by_diff_asc($a, $b){
		return $a['diff_value'] - $b['diff_value'];
	}

	static function sort_by_diff_desc($a, $b){
		return $b['diff_value'] - $a['diff_value'];
	}

	// Sort by position ASC (lower is better)
	static function sort_by_position_asc($a, $b){
		if($a['position'] == $b['position']) return 0;
		return ($a['position'] > $b['position']) ? 1 : -1;
	}

	static function calculate_keyword_points($clicks, $impressions, $position, $ctr){
		$points = 0;

		if($position > 0 && $position <= 3){
			$points += 40;
		} elseif ($position > 3 && $position <= 10){
			$points += 30;
		} elseif ($position > 10 && $position <= 50){
			$points += 15;
		} else {
			$points += 5;
		}
		
		// CTR factor
		if($ctr > 10){
			$points += 30;
		} elseif($ctr > 5){
			$points += 20;
		} elseif($ctr > 2){
			$points += 10;
		} else{
			$points += 5;
		}
		
		// Click factor
		if($clicks > 1000){
			$points += 30;
		} elseif($clicks > 100){
			$points += 20;
		} elseif($clicks > 10){
			$points += 10;
		}
		
		return min(100, $points);
	}

	static function calculate_page_score($clicks, $impressions, $position, $ctr){
		$score = 0;
		
		// Position score
		if($position > 0 && $position <= 3){
			$score += 40;
		} elseif($position > 3 && $position <= 10){
			$score += 30;
		} elseif($position > 10 && $position <= 50){
			$score += 20;
		} else{
			$score += 10;
		}
		
		// CTR score
		$score += min(30, $ctr * 3);
		
		// Click volume score
		if($clicks > 1000) $score += 30;
		elseif($clicks > 100) $score += 20;
		elseif($clicks > 10) $score += 10;
		
		return min(100, $score);
	}

	static function calculate_performance_diff($clicks, $impressions, $position){
		$score = ($clicks * 0.4) + (($impressions / 100) * 0.3) - (($position) * 0.2);
		return max(-10, min(10, round($score)));
	}

	static function calculate_content_performance($clicks, $impressions, $position){
		$ctr = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
		
		// Calculate performance score
		$performance_score = self::calculate_page_score($clicks, $impressions, $position, $ctr);
		
		return [
			'indexed' => $impressions > 0 ? 'Yes' : 'No',// If impressions > 0, consider indexed
			'loss' => max(0, $impressions - $clicks),// Real loss = impressions - clicks
			'drop_percent' => $ctr > 0 ? round((100 - $ctr), 1) : 0,// Drop % based on CTR
			'performance_score' => round($performance_score)
		];
	}


	static function get_site_url(){
		$saved_site_url = get_option('siteseo_google_tokens');
		return !empty($saved_site_url['site_url']) ? $saved_site_url['site_url'] : '';
	}
	
	static function get_all_analytics(){
		$end_date = gmdate('Y-m-d');
		$start_date = gmdate('Y-m-d', strtotime('-3 months'));
		$site_url = self::get_site_url();
		$base_endpoint = '/webmasters/v3/sites/' . urlencode($site_url) . '/searchAnalytics/query';

		$requests = [
			'date_data' => [
				'endpoint' => $base_endpoint,
				'params' => [
					'startDate' => $start_date,
					'endDate' => $end_date,
					'dimensions' => ['date'],
					'rowLimit' => 1000,
				]
			],
			'pages_data' => [
				'endpoint' => $base_endpoint,
				'params' => [
					'startDate' => $start_date,
					'endDate' => $end_date,
					'dimensions' => ['page'],
					'rowLimit' => 1000,
				]
			],
			'keyword_data' => [
				'endpoint' => $base_endpoint,
				'params' => [
					'startDate' => $start_date,
					'endDate' => $end_date,
					'dimensions' => ['query', 'date'],
					'rowLimit' => 1000,
				]
			],
			'content_performance_data' => [
				'endpoint' => $base_endpoint,
				'params' => [
					'startDate' => $start_date,
					'endDate' => $end_date,
					'dimensions' => ['page', 'date'],
					'rowLimit' => 1000,
				]
			],
			'device_audience' => [
				'endpoint' => $base_endpoint,
				'params' => [
					'startDate' => $start_date,
					'endDate' => $end_date,
					'dimensions' => ['device'],
					'rowLimit' => 1000,
				]
			],
			'country_audience' => [
				'endpoint' => $base_endpoint,
				'params' => [
					'startDate' => $start_date,
					'endDate' => $end_date,
					'dimensions' => ['country'],
					'rowLimit' => 5,
				]
			]
		];

		$results = self::api_batch_request($requests);
		
		if(isset($results['error'])){
			return new \WP_Error('batch_error', $results['error']);
		}

		$date_data = isset($results['date_data']) ? $results['date_data'] : [];
		$pages_data = isset($results['pages_data']) ? $results['pages_data'] : [];
		$keyword_data = isset($results['keyword_data']) ? $results['keyword_data'] : [];
		$content_performance_data = isset($results['content_performance_data']) ? $results['content_performance_data'] : [];
		$device_audience = isset($results['device_audience']) ? $results['device_audience'] : [];
		$country_audience = isset($results['country_audience']) ? $results['country_audience'] : [];
		
		if(isset($date_data['error']) && isset($date_data['error']['message'])){
			return new \WP_Error('date_error', $date_data['error']['message']);
		}

		$processed_data = self::process_analytics_data($date_data, $pages_data, $keyword_data, $content_performance_data, $device_audience, $country_audience);

		return $processed_data;
	}
	
	static function api_batch_request($requests){
		$tokens = self::get_tokens();

		// Check token validity
		if(empty($tokens['created_time']) || (time() - intval($tokens['created_time'])) >= 3600){
			$tokens = self::generate_access_token();
			if(isset($tokens['error'])){
				return $tokens;
			}
		}

		$boundary = 'batch_' . wp_generate_password(20, false);
		$body = '';

		foreach($requests as $key => $req){
			$body .= "--" . $boundary . "\r\n";
			$body .= "Content-Type: application/http\r\n";
			$body .= "Content-ID: " . $key . "\r\n\r\n";
			$body .= "POST /" . $req['endpoint'] . " HTTP/1.1\r\n";
			$body .= "Content-Type: application/json\r\n";
			$body .= "Accept: application/json\r\n\r\n";
			$body .= json_encode($req['params']) . "\r\n";
		}
		$body .= "--" . $boundary . "--";

		$response = wp_remote_post('https://www.googleapis.com/batch/webmasters/v3', [
			'headers' => [
				'Authorization' => 'Bearer ' . $tokens['access_token'],
				'Content-Type' => 'multipart/mixed; boundary=' . $boundary
			],
			'body' => $body,
			'timeout' => 30
		]);

		if(is_wp_error($response)){
			return ['error' => $response->get_error_message()];
		}

		$status_code = wp_remote_retrieve_response_code($response);
		if($status_code < 200 || $status_code > 399){
			return ['error' => __('The batch request responded with status code ', 'siteseo-pro') . $status_code];
		}

		$response_body = wp_remote_retrieve_body($response);
		// Parse multipart response
		$results = [];
		
		// Extract the boundary from the response Content-Type header
		$content_type = wp_remote_retrieve_header($response, 'content-type');
		$response_boundary = '';
		if(preg_match('/boundary=([^;]+)/i', $content_type, $boundary_matches)){
			$response_boundary = trim($boundary_matches[1]);
		}
		
		// If no boundary found in response, try using the request boundary as fallback
		if(empty($response_boundary)){
			$response_boundary = $boundary;
		}
		
		// TODO:: Improve the response error handling for the batch responses.
		
		// Split by response boundary
		$parts = explode('--' . $response_boundary, $response_body);
		
		foreach($parts as $part){
			if(empty(trim($part)) || trim($part) === '--'){
				continue;
			}

			// Extract Content-ID (Google responds with "response-<original_id>")
			if(preg_match('/Content-ID:\s*<?response-([^>\s]+)>?/i', $part, $id_matches)){
				$content_id = trim($id_matches[1]);
			} elseif(preg_match('/Content-ID:\s*<?([^>\s]+)>?/i', $part, $id_matches)){
				// Fallback: try without "response-" prefix
				$content_id = trim($id_matches[1]);
			} else {
				continue;
			}
			
			// Extract JSON body - find the first { and match to the last }
			$json_start = strpos($part, '{');
			if($json_start !== false){
				$json_str = substr($part, $json_start);
				
				// Find the last closing brace to get complete JSON
				$last_brace = strrpos($json_str, '}');
				if($last_brace !== false){
					$json_str = substr($json_str, 0, $last_brace + 1);
				}
				
				$data = json_decode($json_str, true);
				if($data !== null){
					$results[$content_id] = $data;
				}
			}
		}

		return $results;
	}
}
