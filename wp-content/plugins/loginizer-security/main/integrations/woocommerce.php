<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

function loginizer_cap_register_block(){

	// Check if the function exists
	if(!function_exists('woocommerce_store_api_register_endpoint_data')){
		return;
	}
	
	woocommerce_store_api_register_endpoint_data(
		[
			'endpoint' => 'checkout',
			'namespace' => 'loginizer-security-captcha',
			'data_callback'   => function() {
				// Return empty array for GET requests
				return [];
			},
			'schema_callback' => function() {
				return [
					'token' => [
						'description' => __('Captcha security token', 'loginizer-security'),
						'type' => 'string',
						'readonly' => false,
					],
				];
			},
		]
	);
}

add_action('woocommerce_init', 'loginizer_cap_register_block');

// Verify captcha for block based checkout
function loginizer_pro_cap_woo_checkout_verify($order, $request){
	
	// Extract extension data
	$extensions = $request->get_param('extensions');

	if(empty($extensions['loginizer-security-captcha']['token'])){
		throw new \WC_REST_Exception('captcha_missing',	__('Captcha verification failed. Please refresh the page and try again.', 'loginizer-security'), 400);
	}

	$token = sanitize_text_field(wp_unslash($extensions['loginizer-security-captcha']['token']));

	// Verify Captcha, $token is used just for this case
	if(!loginizer_cap_verify($token)){
		throw new \WC_REST_Exception('captcha_failed', __('Captcha verification failed.', 'your-plugin'), 400);
	}

	return $order;
}

add_action('woocommerce_store_api_checkout_update_order_from_request', 'loginizer_pro_cap_woo_checkout_verify', 10, 2);


// For block based woocommerce checkout to render before pay button.
function loginizer_pro_cap_woo_block_render($block_content){
	$captcha = loginizer_cap_form(true, '', 'woo_block');

	return $captcha.$block_content;
}

// For block based woocommerce checkout to render before payment block
function loginizer_pro_cap_woo_block_render_before_payment($block_content){
	$captcha = loginizer_cap_form(true, '', 'woo_block');

	return $captcha.$block_content;
}