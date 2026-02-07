
jQuery(document).ready(function() {
	jQuery('#captcha_wc_block_checkout').on('change', function(){
		let jEle = jQuery(this),
		other_checkout = jQuery('#captcha_wc_checkout');
		
		if(other_checkout.is(':checked')){
			other_checkout.prop('checked', false);
			alert('A Checkout could be either classic or block based, can\'t be both');
		}
	});
	
	jQuery('#captcha_wc_checkout').on('change', function(){
		let jEle = jQuery(this),
		other_checkout = jQuery('#captcha_wc_block_checkout');

		if(other_checkout.is(':checked')){
			other_checkout.prop('checked', false);
			alert('A Checkout could be either classic or block based, can\'t be both');
		}
	});
});