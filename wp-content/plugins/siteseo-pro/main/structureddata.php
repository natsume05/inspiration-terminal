<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

class StructuredData{
	
	static function display_metabox(){
		global $post;
		
		if(is_front_page() || is_home()){
			$post_id = get_option('page_on_front');

			if(!$post_id && is_home()){
				$post_id = get_option('page_for_posts');
			}
			
		} else{
			$post_id = $post ? $post->ID : 0;
		}
		
		$schema_type = '';
		$custom_schema = '';
		if(!empty($post_id)){
			$schema_type = !empty(get_post_meta($post_id, '_siteseo_structured_data_type', true)) ? get_post_meta($post_id, '_siteseo_structured_data_type', true) : '';
			$schema_properties = !empty(get_post_meta($post_id, '_siteseo_structured_data_properties', true)) ? get_post_meta($post_id, '_siteseo_structured_data_properties', true) : '';
			$custom_schema = !empty(get_post_meta($post_id, '_siteseo_structured_data_custom', true)) ? get_post_meta($post_id, '_siteseo_structured_data_custom', true) : '';
		}
		
		$schema_types['Article'] = 'Article';
		$schema_types['NewsArticle'] = 'News Article';
		$schema_types['Blogposting'] = 'Blog post';
		$schema_types['Product'] = 'Product';
		$schema_types['Recipe'] = 'Recipe';
		$schema_types['Restaurant'] = 'Restaurant';
		$schema_types['Course'] = 'Course';
		$schema_types['LocalBusiness'] = 'Local Business';
		$schema_types['Person'] = 'Person';
		$schema_types['Organization'] = 'Organization';
		$schema_types['Book'] = 'Book Recording';
		$schema_types['MusicRecording'] = 'Music Album';
		$schema_types['SoftwareApplication'] = 'Software Application';
		$schema_types['VideoObject'] = 'Video';
		$schema_types['Event'] = 'Event';
		$schema_types['JobPosting'] = 'Job Posting';
		$schema_types['CustomSchema'] = 'Custom Schema';
		
		
		$custom_schema_placeholder = json_encode([
			'type' => 'object',
			'properties' => [
				'placeholder' => ['type' => 'string'],
				'description' => ['type' => 'integer']
			]
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		
		
		/* Show global schema notice if aplicable for current content */
		$global_schema_names = self::check_global_schema_is_applicable();
		
		// Output the notice if any applicable schemas
		if(!empty($global_schema_names)){
			echo'<div class="siteseo-metabox-option-wrap">
				<div class="siteseo-notice is-warning">'.wp_kses_post(implode(', ', array_unique($global_schema_names))). ' global schemas are in use for this item. 
				<a href="'.esc_url(admin_url('admin.php?page=siteseo-pro-page')).'">Click here to manage them</a></div>
			</div>';
		}
		
		echo'<div class="siteseo-metabox-option-wrap">
			<div class="siteseo-metabox-label-wrap">
				<label for="siteseo_structured_data_type">'.esc_html__('Select Schema Types','siteseo-pro').'</label>
			</div>
			<div class="siteseo-metabox-input-wrap">
				<select name="siteseo_structured_data_type" class="siteseo_structured_data_type" id="siteseo_structured_data_type">
					<option value="">'.esc_html__('None', 'siteseo-pro').'</option>';
					foreach($schema_types as $type => $label){
						echo '<option value="'.esc_attr($type).'" '.selected($schema_type, $type, false).'>'.esc_html($label).'</option>';
					}
				echo'</select>
				</input>
			</div>
		</div>';
		
		$schema_template = self::get_schema_properties();
		
		echo'<div class="siteseo-schema-flex-container">
		<div class="siteseo-metabox-schema" style="'.(empty($schema_type) || $schema_type === 'CustomSchema' ? 'display:none;' : '').'">
					<div class="siteseo-schema-properties">';

					if(!empty($schema_type) && isset($schema_template[$schema_type]) && $schema_type !== 'CustomSchema'){
						
						$is_textarea = ['description', 'instructions', 'reviewBody', 'questions', 'step', 'ingredients', 'recipeInstructions', 'courseDescription', 'bookDescription', 'softwareRequirements', 'menu', 'name'];
						
						$is_date_type = ['datePublished', 'dateModified', 'uploadDate', 'startDate', 'endDate', 'foundingDate', 'releaseDate'];
						
						foreach($schema_template[$schema_type] as $property => $default){
							echo '<p><h4 for="siteseo_schema_property_'.esc_attr($property).'">'.esc_html(ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $property))).':</h4>';

							if(in_array($property, $is_textarea)){
								echo '<textarea name="schema_properties['.esc_attr($property).']" id="siteseo_schema_property_' .esc_attr($property).'" rows="3" class="widefat">'.esc_textarea(isset($schema_properties[$property]) ? $schema_properties[$property] : '').'</textarea>';
							} else if(in_array($property, $is_date_type)){
								echo '<input type="datetime-local" name="schema_properties['.esc_attr($property).']" id="siteseo_schema_property_'.esc_attr($property).'" value="' .esc_attr(isset($schema_properties[$property]) ? $schema_properties[$property] : '').'" class="widefat">';
							} else {
								echo '<input type="text" name="schema_properties['.esc_attr($property).']" id="siteseo_schema_property_'.esc_attr($property).'" value="' .esc_attr(isset($schema_properties[$property]) ? $schema_properties[$property] : '').'" class="widefat">';
							}

							echo '</p>';
						}
					}
		
				echo '</div>
			</div>
			
		<div class="siteseo_custom_schema_container" style="'.((empty($schema_type) || $schema_type !== 'CustomSchema') ? 'display:none;' : '').'">
			<h4>'.esc_html__('Custom Schema', 'siteseo-pro').'</h4>
			<span class="siteseo-json-error"></span>
				<p>
					<textarea name="siteseo_structured_data_custom" placeholder="'.esc_attr($custom_schema_placeholder).'" rows="10" class="siteseo_structured_data_custom widefat code">'.
					(!empty($custom_schema) ? esc_textarea(json_encode($custom_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : '').'</textarea>
				</p>
				<p class="description">'.
				// translators: %1$s and %2$s are the opening and closing <a> tags around "Read here".
				sprintf(esc_html__('Create your custom schema as per guidelines. %1$sRead here%2$s.', 'siteseo-pro'),
				'<a href="https://schema.org/docs/schemas.html" target="_blank" rel="noopener noreferrer">',
				'</a>').'</p>
		</div>
		
		<div class="siteseo-metabox-option-wrap">
			<div class="siteseo-schema-preview" style="'.(empty($schema_type) ? 'display:none;' : '').'">
				<div class="siteseo-schema-preview-header"><h4>'.esc_html__('JSON-LD Preview', 'siteseo-pro').'</h4>
					<a class="button" id="siteseo_validate_schema">'.esc_html__('Google Validation', 'siteseo-pro').'</a>
				</div>
				
				<pre id="siteseo_schema_preview" class="siteseo_schema_preview">';
				if($schema_type === 'CustomSchema' && !empty($custom_schema)){
					echo '<div id="siteseo_highlighter">'.esc_textarea(json_encode($custom_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'</div>';
				} elseif (!empty($schema_type) && !empty($schema_properties)){
					$schema_data = array(
						'@context' => 'https://schema.org',
						'@type' => $schema_type
					);

					foreach($schema_properties as $key => $value){
						if(!empty($value)){
							$schema_data[$key] = $value;
						}
					}
					
					echo'<div id="siteseo_highlighter" class="siteseo_highlighter">'.esc_html(json_encode($schema_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)).'</div>';
				} else{
					echo'<div>'.esc_html__('No schema has been selected.', 'siteseo-pro').'</div>';
				}
				echo'</pre>
			</div>
		</div></div>';		
	}
	
	static function check_global_schema_is_applicable(){
		$auto_global_schema = get_option('siteseo_auto_schema');
		$applicable_types = [];
		
		if(empty($auto_global_schema['schemas'])){
			return;
		}
		
		$queried_obj = get_queried_object();
		
		foreach($auto_global_schema['schemas'] as $schema){
			$show = false;

			/** -------------------------
			** Check display_on rules
			**-------------------------**/
			if(!empty($schema['display_on'])){
				foreach($schema['display_on'] as $display){
					switch($display){
						case 'entire_website':
							$show = true;
							break;

						case 'all_singulars':
							$show = in_array(get_post_type(), ['post', 'page', 'product']);
							break;

						case 'front_page':
							if(function_exists('is_front_page') && is_front_page()) $show = true;
							break;

						case 'all_posts':
							$show = get_post_type() === 'post';
							break;

						case 'all_pages':
						case 'all_page':
							$show = get_post_type() === 'page';
							break;

						case 'all_product':
							$show = get_post_type() === 'product';
							break;

						case 'all_product_categories':
							if(get_post_type() === 'product' && has_term('', 'product_cat', $queried_obj)) $show = true;
							break;

						case 'all_product_tags':
							if(get_post_type() === 'product' && has_term('', 'product_tag', $queried_obj)) $show = true;
							break;

						case 'all_category':
						case 'all_taxonomy':
							if(in_array(get_post_type(), ['category', 'post_tag', 'custom_taxonomy'])) $show = true;
							break;

						case 'all_custom_layouts':
							$show = get_post_type() === 'custom_layout';
							break;

						case 'specific_targets':
							if(!empty($schema['specific_targets']) && is_array($schema['specific_targets'])){
								$show = in_array(get_queried_object_id(), $schema['specific_targets']);
							}
							break;
					}

					if($show) break; // stop checking other display_on rules
				}
			}

			if(!$show) continue; // skip if display_on does not match

			/* -------------------------
			* Check display_not_on rules
			* -------------------------*/
			$excluded = false;
			if(!empty($schema['display_not_on'])){
				foreach($schema['display_not_on'] as $not){
					// Skip 'none'
					if($not === 'none') continue;

					// Handle string rules
					if(is_string($not)){
						switch($not){
							case 'entire_website':
								$excluded = true;
								break;

							case 'all_posts':
								if(get_post_type() === 'post') $excluded = true;
								break;

							case 'all_pages':
							case 'all_page':
								if(get_post_type() === 'page') $excluded = true;
								break;

							case 'all_product':
								if(get_post_type() === 'product') $excluded = true;
								break;

							case 'all_category':
							case 'all_taxonomy':
								if(in_array(get_post_type(), ['category', 'post_tag', 'custom_taxonomy'])) $excluded = true;
								break;

							case 'all_product_categories':
								if(get_post_type() === 'product' && has_term('', 'product_cat', $queried_obj)) $excluded = true;
								break;

							case 'all_product_tags':
								if(get_post_type() === 'product' && has_term('', 'product_tag', $queried_obj)) $excluded = true;
								break;
						}
					}
					
					// Handle (specific targets)
					if(is_array($not) && isset($not['type']) && $not['type'] === 'specific_targets'){
						if(!empty($not['targets'])){
							$queried_name = is_object($queried_obj) && isset($queried_obj->name) ? $queried_obj->name : '';
							$targets = (array) $not['targets'];
								
							foreach($targets as $target){
								if((string)$target === (string)get_queried_object_id() || (string)$target === $queried_name){
									$excluded = true;
									break 2; // exit both loops
								}
							}
						}
					}

					if($excluded) break;
				}
			}	

			if(!$excluded){
				$applicable_types[] = '<strong>' . esc_html($schema['type']) . '</strong>';
			}
		}
		
		return $applicable_types;
	}
	
	static function get_schema_properties(){
		
		return [
			'Article'=> [
				'headline' => '',
				'author' => '',
				'datePublished' => '',
				'dateModified' => '',
				'publisher' => '',
				'description' => '',
			],
			'Blogposting' => [
				'headline' => '',
				'author' => '',
				'datePublished' => '',
				'dateModified' => '',
				'publisher' => '',
				'description' => '',
			],
			'Course' => [
				'name' => '',
				'Description' => '',
				'provider' => '',
				'availableLanguage' => '',
				'coursePrerequisites' =>'',
				'courseCode' => '',
				'hasCourseInstance' => '',
				'timeRequired' => '',
				'educationalCredentialAwarded' => '',
			],
			'MusicRecording' => [
				'name' => '',
				'byArtist' => '',
				'duration' => '',
				'recordingOf' => '',
				'inAlbum' => '',
				'datePublished' => '',
				'releasedEvent' => '',
				'abstract' => '',
			],
			'Book' => [
				'name' => '',
				'author' => '',
				'bookEdition' => '',
				'isbn' => '',
				'publisher' => '',
				'datePublished' => '',
				'abstract' => '',
				'inLanguage' => '',
			],
			'Restaurant' => [
				'name' => '',
				'address' => '',
				'hasMenu' => '',
				'telephone' => '',
				'priceRange' => '',
				'openingHours' => '',
			],
			'SoftwareApplication' => [
				'name' => '',
				'applicationCategory' => '',
				'applicationSubCategory' => '',
				'availableOnDevice' => '',
				'operatingSystem' => '',
				'softwareVersion' => '',
				'softwareRequirements' => '',
				'downloadUrl' => '',
			],
			'VideoObject' => [
				'name' => '',
				'description' => '',
				'thumbnailUrl' => '',
				'uploadDate' => '',
				'embedUrl' => '',
				'publisher' =>'',
				'creator' => '',
			],
			'Event' => [
				'name' => '',
				'startDate' => '',
				'endDate' => '',
				'location' => '',
				'description' => '',
				'offers' => '',
				'organizer' => '',
				'performer' => '',
			],
			'Recipe' => [
				'name' => '',
				'author' => '',
				'description' => '',
				'cookTime' => '',
				'cookingMethod' => '',
				'prepTime' => '',
				'totalTime' => '',
				'recipeYield' => '',
				'recipeCategory' => '',
				'recipeCuisine' => '',
				'recipeInstructions' => '',
			],
			'Person' =>[
				'name' => '',
				'jobTitle' => '',
				'email' => '',
				'telephone' => '',
				'address' => '',
			],
			'Organization' => [
				'name' => '',
				'url' =>'',
				'description' => '',
				'email' => '',
				'founder' => '',
				'foundingDate' => '',
				'numberOfEmployees' => '',
				'location' => '',
			],
			'JobPosting' =>[
				'name' => '',
				'industry' => '',
				'title' => '',
				'totalJobOpenings' => '',
				'skills' => '',
				'jobBenefits' => '',
				'jobLocationType' => '',
			],
			'NewsArticle' =>[
				'headline' => '',
				'author' => '',
				'datePublished' => '',
				'dateModified' => '',
				'publisher' => '',
				'description' => '',
			],
			'Product' =>[
				'name' => '',
				'description' => '',
				'brand' => '',
				'category' => '',
				'releaseDate' => '',
				'size' => '',	
			],

			'LocalBusiness' =>[
				'legalName' => '',
				'founder' => '',
				'address' => '',
				'email' => '',
				'numberOfEmployees' => '',
				'telephone' => '',
				'taxID' => '',
				'vatID' => '',
			],
		]; 
	}
	
	static function save_metabox($post_id, $post){
		
		// Security Check
		if(!isset($_POST['siteseo_metabox_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['siteseo_metabox_nonce']), 'siteseo_metabox_nonce')){
			return $post_id;
		}

		//Post type object
		$post_type = get_post_type_object($post->post_type);

		//Check permission
		if(!current_user_can($post_type->cap->edit_post, $post_id)){
			return $post_id;
		}
		
		if(isset($_POST['siteseo_structured_data_type'])){
			update_post_meta($post_id, '_siteseo_structured_data_type', sanitize_text_field($_POST['siteseo_structured_data_type']));
		} else{
			delete_post_meta($post_id, '_siteseo_structured_data_type');
		}
		
		if(isset($_POST['siteseo_structured_data_custom'])){
			$decode_schema = json_decode(wp_unslash($_POST['siteseo_structured_data_custom']), true);
			if(json_last_error() === JSON_ERROR_NONE){
				update_post_meta($post_id, '_siteseo_structured_data_custom', $decode_schema);
			}
		} else{
			delete_post_meta($post_id, '_siteseo_structured_data_custom');
		}
		
		if(isset($_POST['schema_properties']) && is_array($_POST['schema_properties'])){
			
			$properties = array();
			$text_area_fields = array('description', 'instructions', 'reviewBody', 'questions', 'step', 'ingredients','recipeInstructions', 'courseDescription', 'bookDescription', 'softwareRequirements', 'menu');
			
			foreach($_POST['schema_properties'] as $key => $value){
				if(in_array($key, $text_area_fields)){
					$properties[$key] = sanitize_textarea_field($value);
				} else{
					$properties[$key] = sanitize_text_field($value);
				}
			}
			
			update_post_meta($post_id, '_siteseo_structured_data_properties', $properties);
		} else{
			delete_post_meta($post_id, '_siteseo_structured_data_properties');
		}
	}
	
	static function render(){
		global $siteseo, $post;
			
		if(empty($siteseo->pro['toggle_state_stru_data'])){
			return; // disable
		}
		
		self::inject_global_schema(); // call auto global schema
		
		if(empty($siteseo->pro['enable_structured_data'])){
			return;
		}
		
		if(!is_singular()){
			return;
		}

		$schema_type = !empty(get_post_meta($post->ID , '_siteseo_structured_data_type', true)) ? get_post_meta($post->ID, '_siteseo_structured_data_type', true) : '';
		
		if($schema_type === 'CustomSchema'){
			$custom_schema = !empty(get_post_meta($post->ID , '_siteseo_structured_data_custom', true)) ? get_post_meta($post->ID, '_siteseo_structured_data_custom', true) : '';
			if(!empty($custom_schema)){
				echo'<script type="application/ld+json">'.json_encode($custom_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'</script>' . "\n";
			}

		} else {
			
			$schema_properties = !empty(get_post_meta($post->ID, '_siteseo_structured_data_properties', true)) ? get_post_meta($post->ID, '_siteseo_structured_data_properties', true) : '';
			
			if(!empty($schema_type) && is_array($schema_properties)){
				$schema_data = array(
					'@context' => 'https://schema.org',
					'@type' => $schema_type
				);
				
				foreach($schema_properties as $key => $value){
					if(!empty($value)){
						$schema_data[$key] = $value;
					}
				}
				
				echo'<script type="application/ld+json">'.json_encode($schema_data, JSON_UNESCAPED_SLASHES).'</script>' . "\n";
				
			}
		}
	}
	
	static function inject_global_schema(){
		global $siteseo;

		$options = get_option('siteseo_auto_schema');
		
		$post_id = get_queried_object_id();

		if(empty($options['schemas'])){
			return;
		}

		$graph = []; 

		foreach($options['schemas'] as $schema){
			$display = true;
			
			// If no WooCommerce then not render product schema
			if($schema['type'] === 'Product' &&  (!class_exists('WooCommerce') && !class_exists('KKART'))){
				continue;
			}
			
			if(!empty($schema['display_on'])){
				$display = false;
				foreach($schema['display_on'] as $rule){
					if(is_array($rule) && isset($rule['type']) && $rule['type'] === 'specific_targets'){
						$targets = explode(',', $rule['targets']);
						if(self::check_specific_targets($targets)){
							$display = true;
							break;
						}
					} elseif(self::check_display_rule_list([$rule])){
						$display = true;
						break;
					}
				}
			}
			
			if($display && !empty($schema['display_not_on'])){
				foreach($schema['display_not_on'] as $rule){
					if(is_array($rule) && isset($rule['type']) && $rule['type'] === 'specific_targets'){
						$targets = explode(',', $rule['targets']);
						if(self::check_specific_targets($targets)){
							$display = false;
							break;
						}
					} elseif(self::check_display_rule_list([$rule])){
						$display = false;
						break;
					}
				}
			}
			
			if($display){
				$schema_json = self::generate_schema_json($schema);
				if(!empty($schema_json)){
					$graph[] = $schema_json;
				}
			}
		}

		if(!empty($graph)){
			$output = [
				'@context' => 'https://schema.org',
				'@graph' => $graph
			];
			
			echo '<script type="application/ld+json">'. wp_json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .'</script>' . "\n";
		}
	}
	
	static function check_display_rule_list($rules){
		
		if(empty($rules)){
			return false;
		}

		global $post;
		
		foreach($rules as $rule){

			if(is_string($rule)){

				switch($rule){
					case 'entire_website':
						return true;
						
					case 'all_singulars':
						if(is_singular()){
							return true;
						}
						break;

					case 'front_page':
						if(is_front_page()){
							return true;
						}
						break;

					case 'all_posts':
						if(is_singular('post')){
							return true;
						}
						break;

					case 'all_pages':
						if(is_singular('page')){
							return true;
						}
						break;

					case 'all_custom_layouts':
						if(function_exists('is_custom_layout') && is_custom_layout()){
							return true;
						}
						break;

					case 'all_product':
						if(function_exists('is_product') && is_product()){
							return true;
						}
						break;

					case 'all_product_categories':
						if(function_exists('is_product_category') && is_product_category()){
							return true;
						}
						break;

					case 'all_product_tags':
						if(function_exists('is_product_tag') && is_product_tag()){
							return true;
						}
						break;
				}
			}

			if(is_array($rule) && isset($rule['type']) && $rule['type'] === 'specific_targets' && !empty($rule['targets'])){
				$targets = (array) $rule['targets'];

				foreach($targets as $target_slug){
					if(self::check_target_match($target_slug)){
						return true;
					}
				}
			}
		}

		return false;
	}

	static function check_specific_targets($targets){
		if(empty($targets)){
			return false;
		}

		$post_id = get_queried_object_id();
		global $post;

		foreach($targets as $target){
			$target = trim($target);
			
			// Check by post ID
			if(is_numeric($target) && (int)$target === $post_id){
				return true;
			}
			
			// Check by slug for singular pages
			if(is_singular() && $post && $post->post_name === $target){
				return true;
			}
			
			// Check for special pages
			if($target === 'home' && is_front_page()){
				return true;
			}
			
			if($target === 'blog' && is_home()){
				return true;
			}
			
			// Check for archive pages
			if(is_archive()){
				$queried_object = get_queried_object();
				
				if(is_category() && $queried_object instanceof \WP_Term && $queried_object->slug === $target){
					return true;
				}
				
				if(is_tag() && $queried_object instanceof \WP_Term && $queried_object->slug === $target){
					return true;
				}
				
				if(is_tax() && $queried_object instanceof \WP_Term && $queried_object->slug === $target){
					return true;
				}
				
				if(is_post_type_archive() && $queried_object instanceof \WP_Post_Type && $queried_object->name === $target){
					return true;
				}
			}
		}

		return false;
	}

	static function check_target_match($slug){
		global $post;

		if(is_singular()){
			if($post && $post->post_name === $slug){
				return true;
			}
		}

		if(is_archive()){
			if(get_query_var('category_name') === $slug || get_query_var('tag') === $slug){
				return true;
			}
		}

		return false;
	}

	static function generate_schema_json($schema){
		if(empty($schema['type'])){
			return null;
		}
		
		$post_id = get_queried_object_id();

		// Special case handling
		if($schema['type'] === 'BreadcrumbList'){
			return [
				'@type' => 'BreadcrumbList',
				'@id' => get_permalink($post_id) . '#breadcrumblist',
				'itemListElement' => self::get_breadcrumb_items()
			];
		}

		if($schema['type'] === 'SearchAction'){
			return self::get_search_action();
		}

		// Normal schema generation
		$json = [
			'@type' => $schema['type'],
			'name' => $schema['name'],
		];

		// Process properties if they exist
		if(!empty($schema['properties'])){
			$processed_properties = self::process_properties_recursive($schema['properties'], $schema['type']);
			
			// Merge processed properties with basic schema structure
			$json = array_merge($json, $processed_properties);
		}

		// Add @id if URL exists in the processed properties
		if(!empty($json['url'])){
			$type_fragment = strtolower($schema['type']);
			$json['@id'] = $json['url'] . '#' . $type_fragment;
		}

		return $json;
	}
		
	static function process_properties_recursive($properties, $properties_def = []){
		$result = [];
		
		foreach($properties as $prop_key => $prop_value){
			$prop_key = trim($prop_key);
			
			if(is_array($prop_value)){
				// This is a nested array - process recursively
				$nested_result = self::process_properties_recursive($prop_value, $properties_def);
				
				if(!empty($nested_result)){
					// Use the property key as the nested object key
					$result[$prop_key] = $nested_result;
				}
				
			} else{
				// This is a regular property value
				$property_name = isset($properties_def[$prop_key]) ? $properties_def[$prop_key] : $prop_key;
				
				$actual_value = self::replace_suggestion_variables($prop_value);

				if(!empty($property_name) && !empty($actual_value)){
					$result[$property_name] = $actual_value;
				}
			}
		}
		
		return $result;
	}

	static function get_search_action(){
		return [
			'@type' => 'SearchAction',
			'@id' => get_home_url() . '#searchaction',
			'target' => get_home_url() . '/?s={search_term_string}',
			'query-input' => 'required name=search_term_string'
		];
	}

	static function get_breadcrumb_items(){
		global $post;
		
		$post_id = get_queried_object_id();
		$items = [];

		// Home
		$items[] = [
			'@type' => 'ListItem',
			'position' => 1,
			'item' => [
				'@id' => get_home_url(),
				'name' => 'Home'
			]
		];

		// Category
		$categories = get_the_category($post_id);
		if($categories){
			$cat = $categories[0];
			$items[] = [
				'@type' => 'ListItem',
				'position' => 2,
				'item' => [
					'@id' => get_category_link($cat->term_id),
					'name' => $cat->name
				]
			];
		}

		// Current post/page
		$items[] = [
			'@type' => 'ListItem',
			'position' => 3,
			'item' => [
				'@id' => get_permalink($post_id),
				'name' => get_the_title($post_id)
			]
		];

		return $items;
	}

	static function auto_schema(){
		
		return [
			'Article' => [
				'URL' => '%%post_url%%',
				'Headline' => '%%post_title%%',
				'Description' => '%%post_excerpt%%',
				'Date Published' => '%%post_date%%',
				'Date Modified' => '%%post_modified_date%%',
				'Keywords' => '%%keywords%%',
				'Word count' => '%%post_word_count%%',
				'Image' => '%%post_thumbnail_url%%',
				'Main entity of page' => '',
				'Author' => [
					'@type' => 'Person',
					'name' => '%%post_author%%',
					'URL' => '%%post_url%%',
				],
			],
			'WebSite' => [
				'Name' => '%%sitetitle%%',
				'Description' => '%%site_description%%',
				'URL' => '%%site_url%%',
				'Potential Action' => '',
				'Publisher' => '',
				'In Language' => '%%site_language%%',
			],
			'WebPage' => [
				'Name' => '%%current_page_title%%',
				'URL' => '%%current_page_url%%',
				'Breadcrumb List' => '',
				'Description' => '%%post_excerpt%%',
				'Publisher' => '%%post_author%%',
				'In Language' => '%%site_language%%',
			],
			'BreadcrumbList' => [],
			'SearchAction' => [],
			'Person' => [
				'Name' => '%%author_display_name%%',
				'URL' => '%%author_url%%',
				'Given name' => '%%author_first_name%%',
				'Family name' => '%%author_last_name%%',
				'Brand' => '%%sitetitle%%',
				'Main entity of page' => '',
				'Description' => '%%site_description%%',
				'Image' => '%%author_avatar%%',
				'Same as' => '',
			],
			'Product' => [
				'Name' => '%%post_title%%',
				'Description' => '%%product_desc%%',
				'Brand' => [
					'Name' => '%%sitetitle%%',
					'URL' => '%%post_url%%',
				],
				'Image' => [
					'URL' => '%%product_img_url%%',
					'Image Width' => '%%product_img_width%%',
					'Image Height'=> '%%product_img_height%%',
				],
				'AggregateRating' => [
					'Type' => 'AggregateRating',
					'Rating Value'=> '%%product_rating_value%%',
					'Review Count'=> '%%product_review_count%%',
				],
				'Offers' => [
					'Type' => 'Offer',
					'Price' => '%%product_price%%',
					'Price Currency' => '%%product_currency%%',
					'Availability' => '%%product_stock_status%%',
				],
			],
		];
	}

	static function replace_suggestion_variables($content){
		global $post;

		// Remove wrapping %% if present
		return preg_replace_callback('/%%(.+?)%%/', function($matches){
			return self::load_suggestion_data($matches[1]);
		}, $content);
	}
	
	static function load_suggestion_data($variable){
		global $post;

		if(empty($variable)){
			return '';
		}

		switch($variable){
			case 'site_url':
				return site_url();
				
			case 'sitetitle':
				return get_bloginfo('name');
				
			case 'site_description':
				return get_bloginfo('description');

			case 'site_language':
				return get_locale();

			case 'post_id':
			case 'post_title':
			case 'post_url':
			case 'post_date':
			case 'post_excerpt':
			case 'post_slug':
			case 'post_thumbnail_url':
			case 'post_category':
			case 'post_tags':
			case 'post_author':
			case 'post_word_count':
			case 'post_modified_date':
			case 'post_date':
				if(empty($post)){
					return '';
				}

				return self::load_post_suggestion_data($variable);
				
			case 'author_id':
			case 'author_first_name':
			case 'author_last_name':
			case 'author_display_name':
			case 'author_website':
			case 'author_bio':
			case 'author_avatar':
			case 'author_desc':
			case 'author_title':
			case 'author_email':
			case 'author_nickname':
			case 'author_url':
				return self::load_author_suggestion_data($variable);
				
			case 'user_id':
			case 'user_email':
			case 'user_first_name':
			case 'user_last_name':
			case 'user_post_url':
			case 'user_desc':
				return self::load_user_suggestion_data($variable);
				
			case 'current_page_title':
				return get_the_title();
				
			case 'current_page_url':
				return get_permalink();
		}
		
		// WooCommerce
		if(function_exists('wc_get_product') && is_singular('product') && strpos($variable, 'product_') === 0){
			$product = wc_get_product($post->ID);
			if(!empty($product)){
				
				switch($variable){
					case 'product_img_url':
					case 'product_img_width':
					case 'product_img_height':
						return self::woo_img_suggestion_data($variable, $product);
						
					case 'product_desc':
						return $product->get_short_description();
						
					case 'product_full_desc':
						return $product->get_description();
						
					case 'product_price': 
						return $product->get_price();
						
					case 'product_currency':
						return get_woocommerce_currency();
						
					case 'product_rating_value':
						return $product->get_average_rating();
						
					case 'product_review_count':
						return $product->get_review_count();
						
					case 'product_stock_status':
						return $product->is_in_stock() ? 'In Stock' : 'Out of Stock';
						
				}
			}
		}
		

		return '';
	}
	
	static function load_post_suggestion_data($variable){
		global $post;

		if(empty($variable) || empty($post)){
			return '';
		}

		$post_id = isset($post->ID) ? $post->ID : 0;

		switch($variable){
			case 'post_id':
				return $post_id;
				
			case 'post_title':
				return get_the_title($post_id);
				
			case 'post_url':
				return get_permalink($post_id);
				
			case 'post_date':
				$post_time = get_post_time('U', true, $post_id);
				return gmdate('c', $post_time);

			case 'post_modified_date':
				$post_modified_time = get_post_modified_time('U', true, $post_id);
				return gmdate('c', $post_modified_time);

			case 'post_excerpt':
				$post_excerpt = get_the_excerpt($post_id);
				if(strlen($post_excerpt) > 160){
					$post_excerpt = substr($post_excerpt, 0, 160);
					$post_excerpt = substr($post_excerpt, 0, strrpos($post_excerpt, ' ')) . '...';
				}

				return $post_excerpt;
				
			case 'post_slug':
				return basename(get_permalink($post_id));
				
			case 'post_thumbnail_url':
				return get_the_post_thumbnail_url($post, 'full');
				
			case 'post_category':
				$post_category = get_the_category($post_id);
				return !empty($post_category) ? $post_category[0]->name : '';
				
			case 'post_tags':
				$post_tags = wp_get_post_tags($post_id, ['fields' => 'names']);
				return !empty($post_tags) ? implode(', ', $post_tags) : '';
				
			case 'post_author':
				return get_the_author();
				
			case 'post_word_count':
				$post_content = wp_strip_all_tags($post->post_content);
				return str_word_count($post_content);
				
			case 'keywords':
				return get_post_meta($post_id, '_siteseo_analysis_target_kw', true);
		}
		
		return '';
	}
	
	static function load_author_suggestion_data($variable){
		global $post;

		$author_id = isset($post->post_author) ? $post->post_author : get_current_user_id();
		$post_id = isset($post->ID) ? $post->ID : 0;
		
		switch($variable){
			case 'author_id':
				return $author_id;
				
			case 'author_first_name':
				return get_the_author_meta('first_name', $author_id);
				
			case 'author_last_name':
				return get_the_author_meta('last_name', $author_id);
				
			case 'author_display_name':
				return get_the_author_meta('display_name', $author_id);
				
			case 'author_website':
				return get_the_author_meta('url', $author_id);
				
			case 'author_bio':
			case 'author_desc':
				return get_the_author_meta('description', $author_id);
				
			case 'author_avatar':
				return get_avatar_url($author_id);
				
			case 'author_title':
				return get_the_author_meta('title', $author_id);
				
			case 'author_email':
				return get_the_author_meta('user_email', $author_id);
				
			case 'author_nickname':
				return get_the_author_meta('nickname', $author_id);
				
			case 'author_url':
				return get_author_posts_url($post_id);
		}
		
		return '';
	}
	
	static function load_user_suggestion_data($variable){		
		$user_id = get_current_user_id();
		$user_data = get_userdata($user_id);

		switch($variable){
			case 'user_id':
				return $user_id;
				
			case 'user_email':
				return isset($user_data->user_email) ? $user_data->user_email : '';
				
			case 'user_first_name':
				return isset($user_data->first_name) ? $user_data->first_name : '';
				
			case 'user_last_name':
				return isset($user_data->last_name) ? $user_data->last_name : '';
				
			case 'user_post_url':
				return get_author_posts_url($user_id);
				
			case 'user_desc':
				return get_user_meta($user_id, 'description', true);
		}
		
		return '';
	}
	
	static function woo_img_suggestion_data($variable, $product){
		
		$image_id = $product->get_image_id(); // Gets the featured image Id
		switch($variable){
			case 'product_img_url':
				return $image_id ? wp_get_attachment_url($image_id) : '';
			
			case 'product_img_height':
			case 'product_img_width':
				$image_size = $image_id ? wp_get_attachment_metadata($image_id) : null;
				
				if($variable == 'product_img_height'){
					return isset($image_size['height']) ? $image_size['height'] : '';
				}
				
				return $image_width = isset($image_size['width']) ? $image_size['width'] : '';			
		}

		return '';
	}

	static function suggestion_variables(){
		
		$tags = [
			'%%author_title%%' => 'Author title',
			'%%author_desc%%' => 'Autor desc',
			'%%author_email%%' => 'Author email',
			'%%author_first_name%%' => 'Author first name',
			'%%author_last_name%%' => 'Author last name',
			'%%author_id%%' => 'Author id',
			'%%author_nickname%%' => 'Author Nickname',
			'%%author_website_url%%' => 'Author website url',
			'%%category%%' => 'Category',
			'%%_category_title_%%' => 'Category title',
			'%%_category_description_%%' => 'Category description',
			'%%tag_title%%' => 'Tag title',
			'%%tag_description' => 'Tag description',
			'%%sitetitle%%' => 'Site title',
			'%%siteurl%%' => 'Site url',
			'%%post_title%%' => 'Post title',
			'%%post_id%%' => 'Post Id',
			'%%post_url%%' => 'Post url',
			'%%post_slug%%' => 'Post Slug',
			'%%post_word_count%%' => 'Post word count',
			'%%post_tags%%' => 'Post tags',
			'%%post_category%%' => 'Post category',
			'%%post_thumbnail_url%%' => 'Post thumbnail',
			'%%post_date%%' => 'Post date',
			'%%post_modified_date%%' => 'post modified date',
			'%%post_author%%' => 'Post Author',
			'%%current_page_title%%' => 'Current Page title',
			'%%current_page_url%%' => 'Current Page url',
			'%%user_email%%' => 'User mail',
			'%%user_description%%' => 'User desc',
			'%%user_first_name%%' => 'User first name',
			'%%user_last_name%%' => 'User last name',
			'%%user_post_url%%' => 'User post url',
			'%%site_language%%' => 'Wordpress site language',
			'%%author_url%%' => 'Author URL',
			'%%author_avatar%%' => 'Author Avatar',
		];
		
		
		if(class_exists('WooCommerce') || class_exists('KKART')){
			$tags = array_merge($tags, [
				'%%product_desc%%' => 'Product Short Description',
				'%%product_full_desc%%' => 'Product Full Description',
				'%%product_img_url%%' => 'Product Image',
				'%%product_img_width%%' => 'Product Image Width',
				'%%product_img_height%%' => 'Product Image Height',
				'%%product_rating_value%%' => 'Product Rating Value',
				'%%product_review_count%%' => 'Product Review Count',
				'%%product_price%%' => 'Product Price',
				'%%product_currency%%' => 'Product Currency',
				'%%product_stock_status%%' => 'Product Stock Status',
			]);
		}

		return $tags;
	}
	
	static function suggestion_schema_button(){
		
		$suggest_variable = self::suggestion_variables();
		
		echo'<div class="siteseo-suggestions-wrapper">
			<div class="siteseo-suggetion" style="margin-left:80px;margin-top:5px">
				<div class="siteseo-search-box-container">
					<input type="text" class="siteseo-search-box" placeholder="Search a tag...">
				</div>
				<div class="siteseo-suggestions-container">';
				foreach($suggest_variable as $key =>$value){
					echo'<div class="section">'.esc_html($value).'
						<div class="item">
							<div class="tag">'.esc_html($key).'</div>
						</div>
					</div>';
				}
			echo '</div>
			</div>
		</div>';
	}
	
	static function process_nested_properties($properties_data){
		$result = [];
    
		foreach($properties_data as $key => $value){
			if(is_array($value)){
				// multi-dimensional array
				$result[$key] = self::process_nested_properties($value);
				continue;
			}
			
			// This is a regular property - sanitize and format the key
			$special_cases = [
				'URL' => 'url',
				'ID' => 'id',
				'Image URL' => 'imageUrl',
				'Price Currency' => 'priceCurrency',
				'Published date' => 'datePublished',
				'Modified date' => 'dateModified',
				'Is part of' => 'isPartOf',
				'Image' => 'image',
				'Brand' => 'brand',
				'Type' => '@type',
				'AggregateRating' => 'aggregateRating',
				'Rating Value' => 'ratingValue',
				'Word count' => 'wordCount',
				'In Language' => 'inLanguage',
				'Breadcrumb List' => 'breadcrumbList',
			];
			
			if(isset($special_cases[$key])){
				$formatted_key = $special_cases[$key];
			} else{
				$formatted_key = lcfirst(str_replace(' ', '', ucwords($key)));
			}

			$result[$formatted_key] = sanitize_text_field(wp_unslash($value));
		}
		
		return $result;
	}
}
