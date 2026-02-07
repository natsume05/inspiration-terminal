jQuery(document).ready(function(){

	// Tabs Handler
	var tabs = jQuery('.gosmtp-wrapper').find('.nav-tab');
	var tabsPanel = jQuery('.tabs-wrapper').find('.gosmtp-tab-panel');

	function gosmtp_load_tab(event){ 

		var hash  = window.location.hash;

		// No action needed when there is know hash value 
		if(!hash){
			return;
		}

		// Scroll top if tabs is not visible
		jQuery("html, body").animate({ scrollTop: 0 }, "fast");

		// Select elements
		jEle = jQuery(".nav-tab-wrapper").find("[href='" + hash + "']"); 

		if(jEle.length < 1){
			return;
		}
		
		// Remove active tab
		tabs.removeClass('nav-tab-active');
		tabsPanel.hide();
		
		// Make tab active
		jEle.addClass('nav-tab-active');
		jQuery('.tabs-wrapper').find(hash).show();
	
		if(hash == '#gosmtp-connections-settings'){
			return;
		}

		// Modify and update current URL
		gosmtp_update_uri(hash);
	}

	// Load function when hash value change
	jQuery( window ).on( 'hashchange', gosmtp_load_tab);

	// For First load
	gosmtp_load_tab();

	tabs.click(function(e){
		if(jQuery(this).hasClass("nav-tab-active")){
			e.preventDefault();
		}
		
		// Hide edit connection form
		if(jQuery('.gosmtp-new-conn-wrap').css('display') == 'block'){
			jQuery('#gosmtp-back-trigger').click();
		}

	});

	// Auth show and hide Handler
	var smtpAuth = jQuery('input[name="smtp[smtp_auth]"]');

	smtpAuth.on('click', function(){
		var val = jQuery(this).attr('value');

		if(val == 'Yes'){
			jQuery('.smtp-authentication').show();
		}else{
			jQuery('.smtp-authentication').hide();
		}
	});

	// Mailer active effert
	jQuery('.gosmtp-mailer-input').not('.pro').click( function(){
		
		var parent = jQuery(this).closest('.gosmtp-tab-panel');
		var jEle = jQuery(this);
		
		// Set active mailer
		parent.find('.gosmtp-mailer-input').find('.mailer_label').removeClass('mail_active');
		jEle.find('.mailer_label').addClass('mail_active');

		// Taggle mailer tabs
		parent.find('tr').hide(); 
		parent.find('.always_active').closest('tr').show();

		// Show active tab
		attr_name = parent.find('.mail_active').attr('data-name');

		parent.find('.'+attr_name).closest("tr").show();

		jEle.find('[name="mailer"]').prop('checked', true);

		// For On load set
		if(attr_name =='smtp'){
			parent.find('input[name="smtp[smtp_auth]"][checked="checked"]').click();
		}

	});

	//Handle checkbox events
	// TODO: check
	jQuery('body').on('click','.gosmtp-multi-check, .gosmtp-checkbox', function(e){
		e.stopPropagation();

		$this = jQuery(this);
		var parent = $this.parent().parent().parent();
		var checkedCount = jQuery('td input[type="checkbox"]:checked').length;
		var total = jQuery('td input[type="checkbox"]').length;
		var prop = false;
		var clas = '';
    
		if($this.hasClass('gosmtp-multi-check')){
			clas = 'td input[type="checkbox"]';
			prop = $this.prop('checked') == true ? true : false;
		}else{
			prop = checkedCount == total ? true : false;
			clas = '.gosmtp-multi-check';
		}
    
		parent.find(clas).prop('checked',prop);
		
		checkedCount = jQuery('td input[type="checkbox"]:checked').length;
		if(checkedCount > 0){
			jQuery('.gosmtp-log-options').css('display','flex');
		}else{
			jQuery('.gosmtp-log-options').css('display','none');
		}
		
	});

	jQuery('body').on('click','#gosmtp-table-opt-btn',function(){
		var option = jQuery('#gosmtp-table-options').val();
		var ids = [];
    
		jQuery('#gosmtp-logs-table').find('td input[type=checkbox]:checked').each(function(){
			ids.push(jQuery(this).val());
		})

		if(ids.length == 0){
			alert('Invalid selection!');
			return;
		}

		var action = option == 'delete' ? 'gosmtp_delete_log' : '';
		
		if(action == ''){
			alert('Invalid option!');
			return;
		}

		jQuery.ajax({
			url:gosmtp_ajaxurl + 'action='+action,
			dataType : 'JSON',
			type : 'post',
			data: {
				id:ids,
				gosmtp_nonce: gosmtp_ajax_nonce
			},
			success:function(data){
				if( data.response !=undefined ){
					alert(data.response);
				}else{
					alert('Someting went wrong !');
				}
				
				window.location.reload();
			},
			error:function(){
				alert('Someting went wrong !');
			}
		});
	});
  
	// Send Test Mail
	jQuery('body').on('submit', '#smtp-test-mail', function(e){

		e.preventDefault();
		var $this = jQuery(this);
		var formData = new FormData( jQuery(this)[0] );
		formData.append('gosmtp_nonce', gosmtp_ajax_nonce);

		jQuery.ajax({
			url: gosmtp_ajaxurl + 'action=gosmtp_test_mail',
			data: formData,
			type: 'POST',
			processData: false,
			contentType: false,
			cache: false,
			beforeSend: function(){
				gosmtp_loader('show');
				jQuery('#send_mail').attr('type', 'button');
				var btnhtml = `<i class="dashicons dashicons-update-alt"></i>&nbsp;Sending&nbsp;`;
				$this.find('#send_mail').html(btnhtml);
			},
			success: function( res ){
				gosmtp_loader('hide');
				$this.find('#send_mail').html('Send Mail');
				jQuery('#send_mail').attr('type', 'submit');
				res = gosmtp_isJSON(res);
				
				if(!res){
					alert('Someting went wrong !');
					return false;
				}

				if( res.error != undefined){
					alert(res.error);
					return false;
				}
				
				alert('Mail sent successfully!');
				window.location.reload();
			},
			error: function(){
				gosmtp_loader('hide');
				alert('Mail not sent!');
				jQuery('#send_mail').attr('type','submit');
				$this.find('#send_mail').html('Send Mail');
			}
		});
	});

	jQuery('.gosmtp-mailer-input').find('.mail_active').closest('.gosmtp-mailer-input').click();
	
	// Handle reload and retry events
	jQuery('body').on('click', '.gosmtp-resend, .gosmtp-retry, .gosmtp-pupup-retry, .gosmtp-pupup-resend', function(e){
		e.stopPropagation();

		var $this = jQuery(this);
		var isDialog = $this.hasClass('gosmtp-pupup-resend') || $this.hasClass('gosmtp-pupup-retry') ? true : false;
		var mail_id = jQuery(this).attr('data-id') != undefined ? jQuery(this).attr('data-id') : '';
		var operation = jQuery(this).hasClass('gosmtp-resend') == true ? 'resend' : 'retry';
		var className = '';
    
		jQuery.ajax({
			url:gosmtp_ajaxurl + 'action=gosmtp_resend_mail',
			dataType : 'JSON',
			type : 'post',
			data: {
				id:mail_id,
				gosmtp_nonce: gosmtp_ajax_nonce,
				operation: operation
			},
			beforeSend:function(){
				gosmtp_loader('show');
				$this.addClass('gosmtp-resend-process');
			},
			success:function( res ){
				gosmtp_loader('hide');

				if(isDialog){
					className = $this.hasClass('gosmtp-pupup-retry') ? 'gosmtp-pupup-retry' : 'gosmtp-pupup-resend';
				}else{
					className = $this.hasClass('gosmtp-pupup-retry') ? 'gosmtp-pupup-retry' : 'gosmtp-pupup-resend';
				}
				
				var dialog_icon = "";

				$this.removeClass(className);
				$this.removeClass('gosmtp-resend-process');
				
				if(res.error != undefined){
					$this.html('<i class="dashicons dashicons-update-alt"></i><span>Retry</span>');
					$this.addClass('gosmtp-retry');
					dialog_icon = '<i class="failed dashicons dashicons-warning"></i>';
					alert( res.error );
				}else{
					$this.html('<i class="dashicons dashicons-image-rotate"></i><span>Resend</span>');
					dialog_icon = '<i class="sent dashicons dashicons-yes-alt"></i>';
					$this.addClass('gosmtp-resend');
					alert( res.response );
				}
				
				if(isDialog){
					jQuery('.gosmtp-dialog-header').find('.gosmtp-status-icon').html(dialog_icon);
				}

				window.location.reload();

			},
			error:function(){
				gosmtp_loader('hide');
				alert('Someting went wrong !');
			}
		});
	});
	
	// Handle delete events
	jQuery('body').on('click','.gosmtp-mail-delete',function(e){
		e.stopPropagation();

		var mail_id = jQuery(this).attr('data-id') != undefined ? jQuery(this).attr('data-id') : '';
		var parent = jQuery(this).parent().parent();
		jQuery.ajax({
			url:gosmtp_ajaxurl + 'action=gosmtp_delete_log',
			dataType : 'JSON',
			type : 'post',
			data: {
				id:mail_id,
				gosmtp_nonce: gosmtp_ajax_nonce
			},
			success:function(data){
				if( data.response !=undefined ){
					alert(data.response);
					window.location.reload();
				}else{
					alert('Someting went wrong !');
				}
			},
			error:function(){
				alert('Someting went wrong !');
			}
		});
	});

	// GoSMTP mail info popup
	jQuery('body').on('click','.gosmtp-mail-details', function(){

		var dialog = jQuery('#gosmtp-logs-dialog');
		
		var dialog_icon = dialog.find('.gosmtp-dialog-header').find('.gosmtp-status-icon');
		var mail_id = jQuery(this).attr('data-id') != undefined ? jQuery(this).attr('data-id') : '';

		jQuery.ajax({
			url : gosmtp_ajaxurl + 'action=gosmtp_get_log',
			dataType : 'JSON',
			type : 'post',
			data: {
				'gosmtp_nonce' : gosmtp_ajax_nonce,
				id: mail_id
			},
			beforeSend : function(){
				gosmtp_loader('show');
			},
			success : function( res ){				
				if(res.response.data != undefined){
					var resp = res.response.data;
					var headers = resp.headers != undefined ? resp.headers : '';
					var headers_ = '{}';
					
          if(typeof headers == 'object' && Object.keys(headers).length > 0){
						headers_ = JSON.stringify(headers, null, 3);
					}
          
					dialog.find('.gosmtp-log-headers').html('<pre>'+headers_+'</pre>');

					var attachments = resp.attachments != undefined ? resp.attachments : '';
					var attachments_count = 0;
					var attachments_ = '{}';
          
					if(typeof attachments == 'object' && Object.keys(attachments).length > 0){
						attachments_ = JSON.stringify(attachments, null, 3);
						attachments_count = attachments.length;
					}
          
					dialog.find('.gosmtp-log-attachments').html('<pre>'+attachments_+'</pre>');
					dialog.find('.gosmtp-attachment-count').text('('+attachments_count+')');

					var response = resp.response != undefined ? resp.response : '';
					if(typeof response == 'object' && Object.keys(response).length > 0){
						response = JSON.stringify(response, null, 3);
					}
					dialog.find('.gosmtp-log-response').html('<pre>'+response+'</pre>');

					var to = resp.to != undefined ? resp.to : 'NA';
					dialog.find('.gosmtp-message-tos').text(to);

					var from = resp.from != undefined ? resp.from : 'NA';
					dialog.find('.gosmtp-message-from').text(from);

					var subject = resp.subject != undefined ? resp.subject : 'NA';
					dialog.find('.gosmtp-message-subject').text(subject);

					var created = resp.created != undefined ? resp.created : 'NA';
					dialog.find('.gosmtp-message-created').text(created);

					var provider = resp.provider != undefined ? resp.provider : 'NA';
					dialog.find('.gosmtp-message-mailer').text(provider);
                    
					var source = resp.source != undefined ? resp.source : 'NA';
					dialog.find('.gosmtp-message-mailer').text(provider+' / '+source);
					
					var body = resp.body != undefined ? resp.body : 'NA';
					dialog.find('.gosmtp-message-body').html(body);

					var forward_html = '';
          
					if(resp.status != undefined){
						var status = resp.status;
						var icon = '<i class="'+(status.toLowerCase())+' dashicons '+(status == 'Sent' ? 'dashicons-yes-alt' : 'dashicons-warning')+'"></i>';
						dialog_icon.html(icon);
						var resend_retry = status == 'Sent' ? 'Resend' : 'Retry';
						var rr_html = `<button type="button" data-id="`+mail_id+`" class="gosmtp-pupup-`+resend_retry.toLowerCase()+`">
							<i class="dashicons `+( resend_retry == 'Retry' ? 'dashicons-update-alt' : 'dashicons-image-rotate' )+`"></i>
							<span>`+resend_retry+`</span>
						</button>`;
						jQuery('.gosmtp-dialog-actions').html(rr_html);
						forward_html = `<button type="button" data-id="`+mail_id+`" class="gosmtp-pupup-forward">
							<i class="dashicons dashicons-share-alt2"></i>
							<span>Forward</span>
						</button>`;
					}
          
					jQuery('.gosmtp-forward-dialog').html(forward_html);
				}
				jQuery('body').css('overflow','hidden');
				gosmtp_loader('hide');
				dialog.fadeIn();
			},
			error:function(){
				gosmtp_loader('hide');
				alert('Someting went wrong !');
			}
		});
	});
	
	// GoSMTP export files
	jQuery('body').on('submit','#gosmtp_export', function(e){
		e.preventDefault();
		
		var formData = new FormData(this);
		
		// Append the nonce
		formData.append('gosmtp_nonce', gosmtp_ajax_nonce);
		
		var format = formData.get('format');
		
		jQuery.ajax({
			url: gosmtp_ajaxurl + 'action=gosmtp_export_data',
			type: "POST",
			data: formData,
			processData: false,
			contentType: false,
			cache:false,
			xhrFields: {
				responseType: 'blob'
			},
			beforeSend : function(){
				jQuery('.dashicons-image-rotate').show();
			},
			success: function(data, status, xhr){
				
				// Response in blob type due to this we get error form headers
				if(typeof data == 'string'){
					error = xhr.getResponseHeader('x-error');
					res = gosmtp_isJSON(error);
				   
					if( res.error != undefined){
						alert(res.error);
					}
					return false;
				}

				// Create a new date object for the current date
				const currentDate = new Date();
				const day = String(currentDate.getDate()).padStart(2, '0');
				const month = String(currentDate.getMonth() + 1).padStart(2, '0');
				const year = currentDate.getFullYear();
				const formattedDate = `${year}_${month}_${day}`;
				
				// Download zip for eml formate
				if(format == 'eml'){
					format = 'zip';
				}				
				
				// Create link for download files
				var a = document.createElement('a');
				var url = window.URL.createObjectURL(data);
				a.href = url;
				a.download = 'GoSMTP_email_export_'+formattedDate+'.'+format;
				a.click();
				window.URL.revokeObjectURL(url);
			},
			complete: function(res){
				jQuery('.dashicons-image-rotate').hide(300);
			}
		});
	});

	// GoSMTP forward email
	jQuery('body').on('submit','#gosmtp-forward-form', function(e){
		e.preventDefault();
		e.stopPropagation();

		jQuery('body').css('overflow','hidden');

		var recipient_email = jQuery('.gosmtp-recipient-email').val();
		var dialog = jQuery('#gosmtp-forward-dialog');
		var id = jQuery('.forward-mail').attr('data-id');
		jQuery.ajax({
			url:gosmtp_ajaxurl + 'action=gosmtp_resend_mail',
			dataType : 'JSON',
			type : 'post',
			data: {
				id:id,
				gosmtp_nonce: gosmtp_ajax_nonce,
				recipient_email: recipient_email
			},
			beforeSend:function(){
				gosmtp_loader('show');
				jQuery(this).addClass('gosmtp-resend-process');
			},
			success:function( res ){
				gosmtp_loader('hide');
				
				if(res.error != undefined){
					alert( res.error );
				}else{
					alert( res.response );
				}

				window.location.reload();

			},
			error:function(){
				gosmtp_loader('hide');
				alert('Someting went wrong !');
			}
		
		});
	});
	
	// GoSMTP test mail popup
	jQuery('body').on('click','#gosmtp-testmail-btn', function(){
		jQuery('body').css('overflow','hidden');
		var dialog = jQuery('#gosmtp-testmail-dialog');
		dialog.fadeIn();
	});

	// GoSMTP forward email popup
	jQuery('body').on('click','.gosmtp-forward, .gosmtp-pupup-forward', function(e){
		e.stopPropagation();

		jQuery('body').css('overflow','hidden');
		var dialog = jQuery('#gosmtp-forward-dialog');
		var id = jQuery(this).attr('data-id');
		jQuery('.forward-mail').attr('data-id',id);
		dialog.fadeIn();
	});

	jQuery('.gosmtp-dialog,.gosmtp-dialog-close,.cancel-button').on('click',function(e){
		if(e.currentTarget.classList[0] == 'gosmtp-dialog-close' || e.target.classList[0] == 'gosmtp-dialog' || e.target.classList[1] == 'cancel-button'){
			jQuery(this).closest('.gosmtp-dialog').fadeOut();
			jQuery('body').css('overflow','auto');
		}
	});

	// GoSMTP accordion
	jQuery('.gosmtp-accordion-header').on('click',function(e){
		jQuery(this).parent().toggleClass("gosmtp-accordion-open")
		jQuery(this).parent().find('.gosmtp-accordion-content').slideToggle();
	});
	
	// Scrolling event on mailer click
	jQuery('body').on('click', '.mailer', function(e){
		var mailer_container = jQuery(this).closest('tr');
		jQuery(mailer_container).get(0).scrollIntoView({behavior: "smooth", inline: "nearest"});
	});
  
  	// Show or hide logger settings
	jQuery('body').on('change', '#enable_logs', function(e){
		
		if(jQuery(this).prop('checked')){
			jQuery('.gosmtp-logs-options').show();
			return;
		}
		
		jQuery('.gosmtp-logs-options').hide();
	});
	
	jQuery('#enable_logs:checked').trigger('change');
	
	// Report page handler
	gosmtp_report_handler();

	// For active radio label for format type
	jQuery('body').on('change', '.gosmtp-radio-list input[type="radio"]', function(e){
		
		var lable = jQuery(this).next('label');
		var cEle = jQuery('#custom-field');
		var cActive = jQuery('.active_radio_tab').attr('for');
    
		if(cActive == 'csv' || cActive == 'xls'){
			sessionStorage.setItem('gosmtp_export_custom_fields', cEle.prop('checked'));
		}else{
			var checked_val = sessionStorage.getItem('gosmtp_export_custom_fields');
			
			if(checked_val != 'true'){	
				cEle.prop('checked', false);
			}else{
				cEle.prop('checked', true);
			}
		}
		
		jQuery('.gosmtp-radio-list label').removeClass('active_radio_tab');
		lable.addClass('active_radio_tab');
		cEle.attr('disabled', false);
		
		if(lable.attr('for') == 'eml' ){
			cEle.prop('checked', false);
			cEle.attr('disabled', true);
		}
		
		cEle.trigger('change');

	});
	
	// Show custom fields default
	jQuery('.gosmtp-radio-list input[type="radio"]:checked').trigger('change');

	// For active radio label
	jQuery('body').on('change', '#custom-field', function(e){
		
		jQuery(this).addClass('active_radio_tab');
		
		if(jQuery(this).prop('checked')){
			jQuery('.can-hidden').slideDown(200);
			return;
		}
		
		jQuery('.can-hidden').slideUp(200);		
	});
	
	// For weekdays checkbox
	jQuery('body').on('change', '#enable_weekly_reports', function(e){
		
		if(jQuery(this).prop('checked')){
			jQuery('.form-table #gosmtp-week-list').show();
			return;
		}
		
		jQuery('.form-table #gosmtp-week-list').hide();
	});
	
	jQuery('#enable_weekly_reports:checked').trigger('change');
	
	jQuery('body').on('click', '#gosmtp-new-conn, #gosmtp-new-conn-link', function(e){

		var wrap = jQuery('.gosmtp-new-conn-wrap');
		var form = wrap.find('form');
		
		jQuery('#gosmtp-connections-settings').addClass('gosmtp-new-conn-open');

		// Reset form
		if(form.length > 0){
			
			// Reset textboxs except `.gosmtp_copy`
			form.find('input[type=text], input[type=password]').each(function(){
				if(jQuery(this).hasClass('gosmtp_copy')){
					return;
				}

				jQuery(this).val('');
			});

			form.find('input[type=text], input[type=password]').removeAttr('readonly');

			// Reset checkboxes
			form.find('input[type=checkbox]:checked,input[type=radio]:checked').removeAttr('checked');

			// Reset dropdowns
			form.find('select option:selected').removeAttr('selected');

			// Reset auth links
			form.find('[data-field=auth]').removeAttr('href').removeClass('button').text('You need to save settings with Client ID and Client Secret before you can proceed.');

		}
		
		// Reset mailer
		wrap.find('.mailer_check')[0].click();

		// Remove connection id if exists
		wrap.find('[name="conn_id"]').remove();

		// Modify and update current URL
		gosmtp_update_uri('#gosmtp-connections-settings');
		
	});

	jQuery('body').on('click', '#gosmtp-back-trigger', function(e){
		
		var parent = jQuery('#gosmtp-connections-settings');
		parent.removeClass('gosmtp-new-conn-open gosmtp-edit-conn-open');

		// Modify and update current URL
		gosmtp_update_uri('#gosmtp-connections-settings');

	});

	jQuery('body').on('click', '.gosmtp-delete-conn',function(e){

		var resp = confirm('Do you want to continue?');
		if(!resp){		
			e.preventDefault();
		}
		
	});
	
	// Toggling the test message input
	if(jQuery('.gosmtp-test-html-template').length){
		jQuery('.gosmtp-test-html-template').on('change', function(){
			if(jQuery(this).is(':checked')){
				jQuery('.gosmtp-test-message').hide();
			} else{
				jQuery('.gosmtp-test-message').show();
			}
		});
	}
});

function gosmtp_isJSON(str) {
	try {
		var obj = JSON.parse(str);
		return obj;
	} catch (e) {
		return false;
	}
}

function gosmtp_copy_url(id){

	var copyText = jQuery("#" +id);
	var copyMessage = jQuery("." +id);

	// Select the text field
	copyText.select();
	
	// Show Message after Coppied
	copyMessage.slideDown(500);
	
	// Copy the text inside the text field
	navigator.clipboard.writeText(copyText.val());
	
	// Hide Message after 3 second
	setTimeout(function(){
		copyMessage.slideUp(500);
	}, 3000);

}

function gosmtp_loader(option = ''){
	var config = option == 'show' ? 'flex' : 'none';
	jQuery('.gosmtp-loader').css('display', config)
}

// Insert data id to checkbox and find active filter from url.
function gosmtp_report_handler(){
		
	// Date filter for email report
	jQuery('#gosmtp-date-option-filter').change(function(){
		
		var dEle = jQuery('.gosmtp-report-date-container, #gosmtp-filter-date');
		
		if(jQuery(this).val() == 'custom_date'){
			dEle.show(300);
			return;
		}
		
		dEle.hide(300);	
	});

	// Multi select Toggele event
	jQuery('.gosmtp-fiter-container .multiselect, .gosmtp-fiter-container .dropdown').click(function(e){
		
		var target = jQuery(e.target);
		var container = jQuery(this).closest('.gosmtp-fiter-container');
		
		if(target.hasClass( 'multiselect' ) || target.hasClass( 'dropdown' )){
			var cEle = jQuery('.gosmtp-fiter-container').not(container);

			// Slide Up all dropdowns
			cEle.css("z-index", "");
			cEle.find('ul').slideUp();
			cEle.find('.dropdown').removeClass('dashicons-arrow-up-alt2');
			cEle.find('.dropdown').addClass('dashicons-arrow-down-alt2');
			
			container.css("z-index", "1000");
			container.find('ul').slideToggle();
			container.find('.dropdown').toggleClass('dashicons-arrow-down-alt2');
			container.find('.dropdown').toggleClass('dashicons-arrow-up-alt2');
		}
		
	});

	// Multi select Checkbox click event
	jQuery('.multiselect-options li input[type=checkbox]').click(function(){

		var jEle = jQuery(this);
		var val = jEle.val();
		var oEle = jEle.closest('ul.multiselect-options');
		
		// All selected value container
		var container = [];

		// Select all if select all checked
		if(val == 'all' && jEle.prop('checked')){
			oEle.find('li input[type=checkbox]').prop('checked', true);
		}else if(val == 'all'){
			oEle.find('li input[type=checkbox]').prop('checked', false);
		}
				
		// Make Select box checked when all checkbox checked accept Select all checkbox
		if(oEle.find('li input[type=checkbox]:not(input[value=all]):checked').length < oEle.find('.multiselect-checkbox').length-1){
			oEle.find('li input[value=all]').prop('checked', false);
		}else{
			oEle.find('li input[value=all]').prop('checked', true);
		}
		
		// Insert all the checked button to the array
		oEle.find('li input[type=checkbox]:checked:not(input[value=all])').each(function(){
			container.push(jQuery(this).val());
		})

		// Empty all element before Insert element
		jEle.closest('.gosmtp-fiter-container').find('.multiselect').html('');

		// Insert value when there are empty array
		if(container.length == 0){
			jEle.closest('.gosmtp-fiter-container').find('.multiselect').text('Select Filter');
		}

		// Empty all element 
		for(i=0; i <= container.length-1; i++){
			jEle.closest('.gosmtp-fiter-container').find('.multiselect').append('<div class="gosmtp-container-val"><span >'+container[i].replace(/[._-]/g,' ')+'</span><span class="filter-close dashicons dashicons-no-alt" data-id='+oEle.find('li input[value='+container[i]+']').attr('data-id')+' ></span><div>');
		}
	});

	// Element close button event
	jQuery('body').on('click', '.filter-close',function(){
		
		var id = parseInt((jQuery(this).attr('data-id')));
		jQuery(this).closest('.gosmtp-fiter-container').find(' li input[type=checkbox]').get(id).click();
	});

	jQuery('.gosmtp-fiter-container').each(function(){
		var all_checkbox = jQuery(this).find('.multiselect-options li input[type=checkbox]');
		for(i=0; i<all_checkbox.length; i++){
			jQuery(all_checkbox[i]).attr('data-id', i);
		}
		var checked = jQuery(this).find(' .multiselect-options li input[type=checkbox]:checked');
		jQuery(this).find(checked).click();

		var checkbox = jQuery('.multiselect-options li input[type=checkbox]:not(input[value=all])');
		
		let searchParams = new URLSearchParams(window.location.search);
		
		for(j=0; j < checkbox.length; j++){
			if(searchParams.has('multiselect['+j+']')){
				var val = searchParams.get('multiselect['+j+']');
				jQuery('.multiselect-options li input[value='+val+']').click();
			}
		}
	});
}

// Only update current URL without refresh
function gosmtp_update_uri(uri = ''){
	
	var urlObj = new URL(window.location.href);
	
	urlObj.search = '';
	urlObj.hash = '';
	
	var url = urlObj.toString()+'?page=gosmtp'+uri;
	
	// Update browser's session history stack.
	history.pushState({urlPath:url}, '', url);
}