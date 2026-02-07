jQuery(document).ready(function($) {
	$('.speedycache-test-notice .speedycache-custom-dismiss').on('click', function(e) {
		e.preventDefault();
		$('.speedycache-test-notice').slideUp();
			$.ajax({
				url: speedycache_pro_ajax.url,
				type: 'POST',
				data: {
					action: 'speedycache_dismiss_test_notice',
					security: speedycache_pro_ajax.nonce
				}
			});
		});	

	$('.speedycache-copy-test-settings').on('click', function(e){
		e.preventDefault();
		$.ajax({
			method : 'GET',
			url : speedycache_pro_ajax.url + '?action=speedycache_copy_test_settings&security='+speedycache_pro_ajax.nonce,
			success: function(res){
				if(res.success){
					alert('The settings has been successfully saved!');
					location.reload(true);
					return;
				}
				if(res.data){
					alert(res.data);
				}
			}
		});
	});
});

function speedycache_pro_get_db_optm(){
	if(speedycache_pro_ajax.db_load){
		return;
	}

	speedycache_pro_ajax.db_load = true;
	
	jQuery.ajax({
		method : 'GET',
		url : speedycache_pro_ajax.url + '?action=speedycache_pro_get_db_optm&security='+speedycache_pro_ajax.nonce,
		beforeSend: function(){
			jQuery('.speedycache-db-number').text('(Loading...)');
		},
		success: function(res){
			if(res.success && res.data){
				for(let i in res.data){
					jQuery(`[speedycache-db-name=${i}] .speedycache-db-number`).text(`(${res.data[i]})`);
				}

				return;
			}

			if(res.data){
				alert(res.data);
			}
		}
	});
}