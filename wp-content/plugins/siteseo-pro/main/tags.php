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

class Tags{

	static function woocommerce_index_tags($robots){
		global $siteseo;
		
		if(!class_exists('WooCommerce') || empty($siteseo->pro['toggle_state_woocommerce'])){
			return $robots;
		}
		
		if(!empty($siteseo->pro['woocommerce_cart_page_no_index'])){
			if(is_cart() && isset($robots['index'])){
				unset($robots['index']);
				$robots['noindex'] = true;
			}
		}
		
		if(!empty($siteseo->pro['woocommerce_checkout_page_no_index'])){
			if(is_checkout() && isset($robots['index'])){
				unset($robots['index']);
				$robots['noindex'] = true;
			}
		}
		
		if(!empty($siteseo->pro['woocommerce_customer_account_page_no_index'])){
			if(is_account_page() && isset($robots['index'])){
				unset($robots['index']);
				$robots['noindex'] = true;
			}
		}
		
		return array_filter($robots);
	}

	static function kkart_index_tags($robots){
		global $siteseo;
		
		if(!class_exists('kkart') || empty($siteseo->pro['toggle_state_kkart'])){
			return $robots;
		}
		
		if(!empty($siteseo->pro['kkart_cart_page_no_index'])){
			if(is_cart() && isset($robots['index'])){
				unset($robots['index']);
				$robots['noindex'] = true;
			}
		}
		
		if(!empty($siteseo->pro['kkart_checkout_page_no_index'])){
			if(is_checkout() && isset($robots['index'])){
				unset($robots['index']);
				$robots['noindex'] = true;
			}
		}
		
		if(!empty($siteseo->pro['kkart_customer_account_page_no_index'])){
			if(is_account_page() && isset($robots['index'])){
				unset($robots['index']);
				$robots['noindex'] = true;
			}
		}
		
		return array_filter($robots);
	}
	
	// WooCommerce SEO Tags
	static function woocommerce(){
		global $siteseo;
		
		if(!class_exists('WooCommerce') || empty($siteseo->pro['toggle_state_woocommerce'])){
			return;
		}
		
		// WooCommerce Product og price meta
		if(!empty($siteseo->pro['woocommerce_product_og_price'])){
			if(is_product() && function_exists('wc_get_product')){
				$product = wc_get_product(get_the_ID()); 
				if(!empty($product)){
					$product_price = $product->get_price();
					echo '<meta property="product:price:amount" content="' . esc_attr($product_price) . '" />' . "\n";
				}
			}
		}

		// WooCommerce og currency meta
		if(!empty($siteseo->pro['woocommerce_product_og_currency'])){
			if(function_exists('get_woocommerce_currency')){
				$currency = get_woocommerce_currency();
				echo '<meta property="product:price:currency" content="' . esc_attr($currency) . '" />' . "\n";
			}
		}

		// woocommerce generator tag
		if(!empty($siteseo->pro['woocommerce_meta_generator'])){
			remove_action('get_the_generator_html', 'wc_generator_tag', 10);
			remove_action('get_the_generator_xhtml', 'wc_generator_tag', 10);			
		}

		// Remove WooCommerce schema output
		if(!empty($siteseo->pro['woocommerce_schema_output'])){
			add_filter('woocommerce_structured_data_product', '__return_false');

			if(function_exists('WC')){
				remove_action('wp_footer', [WC()->structured_data, 'output_structured_data'], 10);
				remove_action('woocommerce_email_order_details', [WC()->structured_data, 'output_email_structured_data'], 30);
			}
		}

		// Remove wooCommerce Breadcrumbs schema output
		if(!empty($siteseo->pro['woocommerce_schema_breadcrumbs_output'])){
			add_filter('woocommerce_structured_data_breadcrumbs', '__return_false');
		}

	}

	static function kkart(){
		global $siteseo;	
		if(!class_exists('KKART') || empty($siteseo->pro['toggle_state_kkart'])){
			return;
		}
		
		// KKART Product og price meta
		if(!empty($siteseo->pro['kkart_product_og_price'])){
			if(is_product() && function_exists('kkart_get_product')){
				$product = kkart_get_product(get_the_ID()); 
				if(!empty($product)){
					$product_price = $product->get_price();
					echo '<meta property="product:price:amount" content="' . esc_attr($product_price) . '" />' . "\n";
				}
			}
		}

		// KKART og currency meta
		if(!empty($siteseo->pro['kkart_product_og_currency'])){
			if(function_exists('get_kkart_currency')){
				$currency = get_kkart_currency();
				echo '<meta property="product:price:currency" content="' . esc_attr($currency) . '" />' . "\n";
			}
		}

		// KKART generator tag
		if(!empty($siteseo->pro['kkart_meta_generator'])){
			remove_action('get_the_generator_html', 'kkart_generator_tag', 10);
			remove_action('get_the_generator_xhtml', 'kkart_generator_tag', 10);			
		}

		// Remove KKART schema output
		if(!empty($siteseo->pro['kkart_schema_output'])){
			add_filter('kkart_structured_data_product', '__return_false');

			if(function_exists('KKART')){
				remove_action('wp_footer', [KKART()->structured_data, 'output_structured_data'], 10);
				remove_action('kkart_email_order_details', [KKART()->structured_data, 'output_email_structured_data'], 30);
			}
		}

		// Remove KKART Breadcrumbs schema output
		if(!empty($siteseo->pro['kkart_schema_breadcrumbs_output'])){
			add_filter('kkart_structured_data_breadcrumbs', '__return_false');
		}

	}

	// Easy Digital Downloads SEO tags
	static function easy_digital_downloads(){
		global $siteseo;
		
		if(!class_exists('Easy_Digital_Downloads') || empty($siteseo->pro['toggle_state_easy_digital'])){
			return;
		}

		// OG price meta
		if(!empty($siteseo->pro['edd_product_og_price'])){
			if(function_exists('get_the_ID') && function_exists('edd_get_download_price')){
				$product_id = get_the_ID();
				$price = edd_get_download_price($product_id);
				echo '<meta property="product:price:amount" content="' . esc_attr($price) . '" />' . "\n";
			}
		}

		// OG currency meta
		if(!empty($siteseo->pro['edd_product_og_currency'])){
			if(function_exists('edd_get_currency')){
				$currency = edd_get_currency();
				echo '<meta property="product:price:currency" content="' . esc_attr($currency) . '" />' . "\n";
			}
		}

		// Remove header
		if(!empty($siteseo->pro['edd_meta_generator'])){
			remove_action('wp_head', 'edd_version_in_header');
		}

	}

	// Dublin Core SEO tags
	static function dublin_core(){
		global $siteseo;

		if(empty($siteseo->pro['dublin_core_enable']) || empty($siteseo->pro['toggle_state_dublin_core'])){
			return;
		}

		if(function_exists('siteseo_titles_the_title')){
			$title = siteseo_titles_the_title();
			echo '<meta name="dc.title" content="' . esc_attr($title) . '" />' . "\n";
		}

		$description = get_option('blogdescription');
		if(!empty($description)){
			echo '<meta name="dc.description" content="' . esc_attr($description) . '" />' . "\n";
		}

		$source = htmlspecialchars(urldecode(user_trailingslashit(get_home_url())));
		if(!empty($source)){
			echo '<meta name="dc.source" content="' . esc_attr($source) . '" />' . "\n";
			echo '<meta name="dc.relation" content="' . esc_attr($source) . '" />' . "\n";
		}

		$lang = get_locale();
		if(!empty($lang)){
			echo '<meta name="dc.language" content="' . esc_attr($lang) . '" />' . "\n";
		}

		$subject = get_bloginfo('description');
		if(!empty($subject)){
			echo '<meta name="dc.subject" content="' . esc_attr($subject) . '" />' . "\n";
		}
	}
	
	// local business feature
	static function local_business(){
		global $siteseo;
		
		if(empty($siteseo->pro['toggle_state_local_buz'])){
			return;
		}
		
		$business_name = isset($siteseo->pro['business_type']) ? $siteseo->pro['business_type'] : '';
		$street = isset($siteseo->pro['street_address']) ? $siteseo->pro['street_address'] : '';
		$city = isset($siteseo->pro['city']) ? $siteseo->pro['city'] : '';
		$state = isset($siteseo->pro['state']) ? $siteseo->pro['state'] : '';
		$place_id = isset($siteseo->pro['postal_code']) ? $siteseo->pro['postal_code'] : '';
		$country = isset($siteseo->pro['country']) ? $siteseo->pro['country'] : '';
		$phone = isset($siteseo->pro['telephone']) ? $siteseo->pro['telephone'] : '';
		$price_range = isset($siteseo->pro['price_range']) ? $siteseo->pro['price_range'] : '';
		$latitude = isset($siteseo->pro['latitude']) ? $siteseo->pro['latitude'] : '';
		$longitude = isset($siteseo->pro['longitude']) ? $siteseo->pro['longitude'] : '';

		$output = '<div class="siteseo-local-business" itemscope itemtype="http://schema.org/LocalBusiness">
			<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
			<p><span itemprop="BusinessName">'.esc_attr($business_name).'</span><br>
			<span itemprop="streetAddress">'.esc_attr($street).'</span><br>
			<span itemprop="postalCode">'.esc_attr($place_id).'</span>&nbsp;&nbsp;&nbsp;
			<span itemprop="addressLocality">'.esc_attr($city).'</span><br>
			<span itemprop="addressRegion">'.esc_attr($state).'</span><br />
			<span itemprop="addressCountry">'.esc_attr($country).'</span></p>
			</div>
			<p>Phone: <span itemprop="telephone">'.esc_attr($phone).'</span></p>';

		if(!empty($latitude) && !empty($longitude) && !empty($place_id)){
			$output .= '<a href="https://www.google.com/maps/search/?api=1' . esc_attr($place_id). '&query=' .$latitude. ',' .$longitude. '" title="' . __('View this local business on Google Maps (new window)', 'siteseo-pro') . '" target="_blank">' . __('View on Google Maps', 'siteseo-pro') . '</a><br><br>';
			$output .= '<div itemprop="geo" itemscope itemtype="http://schema.org/GeoCoordinates">
					<meta itemprop="latitude" content="' . esc_attr($latitude) . '" />
					<meta itemprop="longitude" content="' . esc_attr($longitude) . '" />
				</div>';
		}
		
		// Opening hours 
		if(isset($siteseo->pro['opening_hours']) && is_array($siteseo->pro['opening_hours'])){
			$output .= '<div class="siteseo-local-business">
				<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';

			$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
			
			foreach($days as $key => $day){
				$hours = $siteseo->pro['opening_hours'][$key];
				
				if(!empty($hours['closed'])){
					$output .= '<tr>
					<td style="text-align: center;font-weight: bold;">' . esc_html($day) . '</td>
					<td style="text-align: center;">Closed</td>
					</tr>';
				} else{
					$morning = '';
					$afternoon = '';
					
					if(!empty($hours['open_morning'])){
						$morning = esc_html("{$hours['open_morning_start_hour']}:{$hours['open_morning_start_min']} - {$hours['open_morning_end_hour']}:{$hours['open_morning_end_min']}");
					}
					
					if(!empty($hours['open_afternoon'])){
						$afternoon = esc_html("{$hours['open_afternoon_start_hour']}:{$hours['open_afternoon_start_min']} - {$hours['open_afternoon_end_hour']}:{$hours['open_afternoon_end_min']}");
					}
					
					$output .= '<tr>
							<td style="text-align: center;font-weight: bold;">' . $day . '</td>
							<td style="text-align:center;">'.$morning .'</td>
							<td style="text-align:center;">'. $afternoon .'</td>
						</tr>';
					
				}
			}
			
			$output .= '</table></div>';
		
		}

		return $output;
		
	}
	
	// structured data schema
	static function structured_data(){
		global $siteseo;
		
		if(empty($siteseo->pro['toggle_state_stru_data']) || empty($siteseo->pro['enable_structured_data'])){
			return;
		}
		
		if(!is_front_page() || !is_home()){
			return;
		}
		
		// Data load 
		$organization_logo = !empty($siteseo->pro['structured_data_image_url']) ? $siteseo->pro['structured_data_image_url'] : '';
		$org_email_id = !empty($siteseo->pro['org_email']) ? $siteseo->pro['org_email'] : '';
		$org_phone = !empty($siteseo->pro['org_phone_no']) ? $siteseo->pro['org_phone_no'] : '';
		$org_name = !empty($siteseo->pro['org_name']) ? $siteseo->pro['org_name'] : '';
		$org_legal_name = !empty($siteseo->pro['org_legal']) ? $siteseo->pro['org_legal'] : '';
		$org_establish_date = !empty($siteseo->pro['establish_date']) ? $siteseo->pro['establish_date'] : '';
		$org_no_emp = !empty($siteseo->pro['number_emp']) ? $siteseo->pro['number_emp'] : '';
		$org_vat_id = !empty($siteseo->pro['vat_id']) ? $siteseo->pro['vat_id'] : '';
		$org_tax_id = !empty($siteseo->pro['tax_id']) ? $siteseo->pro['tax_id'] : '';
		$org_iso_code = !empty($siteseo->pro['iso_code']) ? $siteseo->pro['iso_code'] : '';
		$org_lei_code = !empty($siteseo->pro['let_code']) ? $siteseo->pro['let_code'] : '';
		$org_dnus_code = !empty($siteseo->pro['dnus_number']) ? $siteseo->pro['dnus_number'] : '';
		$org_naics = !empty($siteseo->pro['naics_code']) ? $siteseo->pro['naics_code'] : '';
	
		// JSON-LD
		$json_ld = [
			'@context' => 'https://schema.org',
			'@type' => 'Organization',
			'logo' => esc_url($organization_logo)
		];
	
		// Not empty
		if(!empty($org_email_id)) $json_ld['email'] = esc_attr($org_email_id);
		if(!empty($org_legal_name)) $json_ld['legalName'] = esc_attr($org_legal_name);
		if(!empty($org_establish_date)) $json_ld['foundingDate'] = esc_attr($org_establish_date);
		if(!empty($org_no_emp)) $json_ld['numberOfEmployees'] = esc_attr($org_no_emp);
		if(!empty($org_vat_id)) $json_ld['VatID'] = esc_attr($org_vat_id);
		if(!empty($org_tax_id)) $json_ld['taxID'] = esc_attr($org_tax_id);
		if(!empty($org_iso_code)) $json_ld['iso6523Code'] = esc_attr($org_iso_code);
		if(!empty($org_lei_code)) $json_ld['leiCode'] = esc_attr($org_lei_code);
		if(!empty($org_dnus_code)) $json_ld['duns'] = esc_attr($org_dnus_code);
		if(!empty($org_naics)) $json_ld['naics'] = esc_attr($org_naics);

		if(!empty($org_email_id) && !empty($org_phone)){
			$json_ld['contactPoint'] = [
				'@type' => 'ContactPoint',
				'contactType' => 'Customer Support',
				'email' => $org_email_id,
				'telephone' => $org_phone
			];
		}
	
		// JSON-LD script
		echo '<script type="application/ld+json" class="siteseo-schema">';
		echo json_encode($json_ld, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		echo '</script>';
	}

	static function load_data_local_business($attributes){
		
		$data = \SiteSEOPro\Tags::local_business();
		
		if(is_front_page() && !empty($attributes['displayOnHomepage'])){
			return '<div class="local-business-block">' . $data . '</div>';
		}

		if(!is_front_page() && empty($attributes['displayOnHomepage'])){
			return '<div class="local-business-block">' . $data . '</div>';
		}

		return '';
	}
}