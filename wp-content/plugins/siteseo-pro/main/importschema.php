<?php
/*
* SITESEO
* https://siteseo.io
* (c) SiteSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class ImportSchema{

	/**
	 * Extract schema from a remote URL
	 * 
	 * @param string $schema_url The URL to fetch schema from
	 * @return array|\WP_Error Array of schemas or WP_Error on failure
	 */
	static function extract_schema_from_url($schema_url){
		// Validate URL format

		if(!wp_http_validate_url($schema_url)){
			return new \WP_Error('invalid_url', __('Invalid URL provided', 'siteseo-pro'));
		}

		$response = wp_remote_get($schema_url, [
			'timeout' => 30,
			'user-agent' => 'SiteSEO-PRO-Schema-Importer/1.0'
		]);
		
		if(is_wp_error($response)){
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		if($response_code !== 200){
			return new \WP_Error('http_error', sprintf(__('Remote server returned error code: %d', 'siteseo-pro'), $response_code));
		}

		$html_body = wp_remote_retrieve_body($response);

		if(empty($html_body)){
			return new \WP_Error('empty_response', __('Remote server returned empty response', 'siteseo-pro'));
		}

		return self::extract_schemas_from_html($html_body);
	}

	/**
	 * Extract schemas from HTML content using WP_HTML_Processor for safer parsing
	 * 
	 * @param string $html_body The HTML content to parse
	 * @return array Array of extracted schemas
	 */
	static function extract_schemas_from_html($html_body) {
		$schemas = [];
		
		// We do not want to handle HTML which is too large to handle
		if(!is_string($html_body) || strlen($html_body) > 10000000){
			return new \WP_Error('size_exceeded', __('The size of the HTML is over the allowed limit', 'siteseo-pro'));
		}

		preg_match_all('#<script[^>]+?type=[\'"]application/ld\+json[\'"][^>]*?>(.*?)</script>#is', $html_body, $matches);
		if(empty($matches[1])){
			return new \WP_Error('schema_not_found', __('No schema found in the content', 'siteseo-pro'));
		}

		foreach($matches[1] as $script_content){
			$decoded = json_decode(trim($script_content), true);
			
			if(json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)){
				continue;
			}

			$decoded = self::sanitize_schema_data($decoded);
			$schemas = array_merge($schemas, self::process_decoded_schema($decoded));
		}

		return $schemas;
	}

	/**
	 * Extract schema from JSON string
	 * 
	 * @param string $schema_json The JSON string to parse
	 * @return array|\WP_Error Array of schemas or WP_Error on failure
	 */
	static function extract_schema_from_json($schema_json){
		$decoded = json_decode($schema_json, true);

		if(empty($decoded)){
			return new \WP_Error('schema_not_found', __('Schema not found', 'siteseo-pro'));
		}

		$decoded = self::sanitize_schema_data($decoded);

		return self::process_decoded_schema($decoded);
	}

	/**
	 * Process a decoded schema array and extract individual schema items
	 * 
	 * @param array $decoded The decoded schema data
	 * @return array Array of processed schema items
	 */
	private static function process_decoded_schema($decoded){
		$schemas = [];
		$context = !empty($decoded['@context']) ? $decoded['@context'] : 'https://schema.org/';
		
		// Handle both @graph arrays and single schema objects
		$items = [];
		if(isset($decoded['@graph']) && is_array($decoded['@graph'])){
			$items = $decoded['@graph'];
		} else if(isset($decoded['@type'])){
			$items = [$decoded];
		}
		
		foreach($items as $item){
			if(!isset($item['@type'])) continue;
			
			$final_schema = [
				'@context' => $context,
				'@graph'   => [$item]
			];
			
			$schemas[] = [
				'type'    => $item['@type'],
				'json_ld' => json_encode($final_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
				'schema'  => $final_schema
			];
		}

		return $schemas;
	}

	/**
	 * Sanitize schema data to prevent XSS and other security issues
	 * 
	 * @param array $decoded The schema data to sanitize
	 * @return array Sanitized schema data
	 */
	static function sanitize_schema_data($decoded){
		if(!is_array($decoded)){
			return [];
		}

		array_walk_recursive($decoded, function(&$value, $key){
			$target_keys = ['articleBody', 'text', 'description', 'headline'];
			if(is_string($value) && in_array($key, $target_keys, true)){
				$value = wp_kses_post($value);
			}else if(is_string($value)){
				$value = sanitize_text_field($value);
			}
		});

		return $decoded;
	}
}