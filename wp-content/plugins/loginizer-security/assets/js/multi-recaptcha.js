
// WooCommerce Registration form
jQuery(document).ready(function(){
	show_lz_multi_cap();
});

function captcha_v2_invisible_process(el){
	
	grecaptcha.ready(function() {
		var cap_div_id = grecaptcha.render(el,{
			'sitekey': lz_cap_sitekey, 'size': 'invisible',
			'callback' : function (recaptchaToken) {
				jQuery('#g-recaptcha-response').trigger('change');
			},
			'expired-callback' : function(){grecaptcha.reset(cap_div_id);}
		});
		
		grecaptcha.execute(cap_div_id);
	});
	
}

function show_lz_multi_cap(){
	
	if(typeof grecaptcha == 'undefined'){
		setTimeout(function(){show_lz_multi_cap()}, 1000);
		return;
	}
	
	// reCAPTCHA v2 tick box
	jQuery('.lz-recaptcha').each(function(index, el) {
		
		// Get the sitekey
		var lz_cap_key = jQuery(this).attr('data-sitekey');
		//alert(lz_cap_key);
		
		// Render it
		try{			
			grecaptcha.render(el, {'sitekey' : lz_cap_key});
		}catch(e){}
		
	});

	
	// reCAPTCHA v2 invisible
	if(typeof lz_cap_invisible !== 'undefined' && lz_cap_invisible == 1 && typeof lz_cap_ver !== 'undefined' && lz_cap_ver == 2){
		
		for (var i = 0; i < document.forms.length; ++i) {
			var form = document.forms[i];
			var cap_div = form.querySelector("."+lz_cap_div_class);
			
			if (null === cap_div) continue;
			cap_div.innerHTML = '';
			
			captcha_v2_invisible_process(cap_div);
		}
	}
	
	// reCAPTCHA v3
	if(typeof lz_cap_ver !== 'undefined' && lz_cap_ver == 3){
		grecaptcha.ready(function() {
			grecaptcha.execute(lz_cap_sitekey, {action: lz_cap_page_type}).then(function(token) {
				// pass the token to the backend script for verification
				jQuery(".lz-v3-input").each(function() {
					jQuery(this).val(token).trigger('change');
				});
			});
		});
	}
	
}