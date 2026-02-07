/* https://wordpress.stackexchange.com/questions/342148/list-of-js-events-in-the-woocommerce-frontend/352171#352171 */
jQuery(document).ready(function() {
	jQuery(document.body).on('update_checkout updated_checkout applied_coupon_in_checkout removed_coupon_in_checkout', function() {
		if(jQuery('#lz-turnstile-div').is(':empty') && turnstile) {
			turnstile.remove('#lz-turnstile-div');
			turnstile.render('#lz-turnstile-div');
		}
		
		if(jQuery('.g-recaptcha').length && grecaptcha){
			jQuery('.g-recaptcha').each(function(){
				if(jQuery(this).is(':empty')){
					let container = jQuery(this),
					siteKey = container.data('sitekey');

					if(siteKey){
						grecaptcha.render(container[0], {'sitekey': siteKey});
					}
				}		
			})
		}
	});
});

/* Woo Checkout Block */
document.addEventListener('DOMContentLoaded', function () {

	if(wp && wp.data){
		var unsubscribe = wp.data.subscribe( function () {
			var turnstile_item = jQuery('#lz-turnstile-div'),
			math_input = jQuery('#loginizer_cap_math'),
			grecaptcha_item = jQuery('.lz-recaptcha'),
			hcaptcha_item = jQuery('.h-captcha');

			// making sure we load it only when v3 is enabled
			if(typeof lz_cap_div_class != 'undefined' && lz_cap_ver == '3'){
				var grecaptcha_v3_input = jQuery('.lz-v3-input');
			}
			
      // This only for reCaptcha v2 invisible, should not work with the checkbox one
			if(typeof lz_cap_ver != 'undefined' && lz_cap_ver == '2' && lz_cap_invisible == '1'){
				var grecaptcha_v2_input = jQuery('#g-recaptcha-response');
			}

			// turnstile
			if(typeof turnstile != 'undefined' && turnstile_item && turnstile_item.length){
				turnstile.render(turnstile_item[0], {
					sitekey: turnstile_item.data('sitekey'),
					callback: function(data){
						wp.data
							.dispatch('wc/store/checkout')
							.setExtensionData('loginizer-security-captcha', {
								token: data,
							});
					},
				});

				unsubscribe();
			}
			
			// Google captcha, v2
			if(typeof grecaptcha != 'undefined' && grecaptcha_item &&  grecaptcha_item.length && typeof grecaptcha_v3_input == 'undefined'){
				grecaptcha.ready(() => {
					grecaptcha.render(grecaptcha_item[0], {
						sitekey: grecaptcha_item.data('sitekey'),
						callback: function(data){
							wp.data
								.dispatch('wc/store/checkout')
								.setExtensionData('loginizer-security-captcha', {
									token: data,
								});
						},
					});
				});

				unsubscribe();
			}
			
			// hcaptcha
			if(typeof hcaptcha != 'undefined' && hcaptcha_item && hcaptcha_item.length){
				hcaptcha.render(hcaptcha_item[0], {
					sitekey: hcaptcha_item.data('sitekey'),
					callback: function(data){
						wp.data
							.dispatch('wc/store/checkout')
							.setExtensionData('loginizer-security-captcha', {
								token: data,
							});
					},
				});

				unsubscribe();
			}
			
			// math captcha
			if(math_input && math_input.length && !math_input.data('listener_added')){
				math_input.on('change', function(e){
					let data = e.currentTarget.value;
					
					wp.data
						.dispatch('wc/store/checkout')
						.setExtensionData('loginizer-security-captcha', {
							token: data,
						});
				});
				
				// flag to make sure we dont add event listener again
				math_input.data('listener_added', true);
				unsubscribe();
			}
			
			// recaptcha v3
			if(grecaptcha_v3_input && grecaptcha_v3_input.length && !grecaptcha_v3_input.data('listener_added')){
				// this is to make sure, if the value is set before the event listener_added
				// then it could be added directly to the extension.
				if(grecaptcha_v3_input.val()){
					wp.data
						.dispatch('wc/store/checkout')
						.setExtensionData('loginizer-security-captcha', {
							token: grecaptcha_v3_input.val(),
						});
				}

				grecaptcha_v3_input.on('change', function(e){
					let data = e.currentTarget.value;
					
					wp.data
						.dispatch('wc/store/checkout')
						.setExtensionData('loginizer-security-captcha', {
							token: data,
						});
				});
				
				// flag to make sure we dont add event listener again
				grecaptcha_v3_input.data('listener_added', true);
				unsubscribe();
			}
			
			// recaptcha v2 invisible
			if(grecaptcha_v2_input && grecaptcha_v2_input.length && !grecaptcha_v2_input.data('listener_added')){
				// this is to make sure, if the value is set before the event listener_added
				// then it could be added directly to the extension.
				if(grecaptcha_v2_input.val()){
					wp.data
						.dispatch('wc/store/checkout')
						.setExtensionData('loginizer-security-captcha', {
							token: grecaptcha_v2_input.val(),
						});
				}

				grecaptcha_v2_input.on('change', function(e){
					let data = e.currentTarget.value;
					
					wp.data
						.dispatch('wc/store/checkout')
						.setExtensionData('loginizer-security-captcha', {
							token: data,
						});
				});
				
				// flag to make sure we dont add event listener again
				grecaptcha_v2_input.data('listener_added', true);
				unsubscribe();
			}
			
			
		}, 'wc/store/cart');
	}
} );