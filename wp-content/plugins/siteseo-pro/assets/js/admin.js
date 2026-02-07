jQuery(document).ready(function($){
	
	// robots.txt
	$("#siteseo_googlebots, #siteseo_bingbots, #siteseo_yandex_bots, #siteseo_semrushbot, #siteseo_rss_feeds, #siteseo_gptbots, #siteseo_link_sitemap, #siteseo_wp_rule, #siteseo_majesticsbots, #siteseo_ahrefsbot, #siteseo_mangools, #siteseo_google_ads_bots, #siteseo_google_img_bot").on("click", function(){
		let currentVal = $("#siteseo_robots_file_content").val();
		let tagVal = $(this).attr("data-tag");
		$("#siteseo_robots_file_content").val(currentVal + "\n" + tagVal);
	});
	
	// htaccess
	$('#siteseo_block_dir, #siteseo_wp_config, #siteseo_error_300').on('click', function(){
		let currentVal = $("#siteseo_htaccess_file").val();
		let tagVal = $(this).attr("data-tag");
		$("#siteseo_htaccess_file").val(currentVal + "\n" + tagVal);
	});
	
	$('#siteseopro-pagespeed-results .siteseo-metabox-tab-label').click(function(){
		$('.siteseo-metabox-tab-label').removeClass('siteseo-metabox-tab-label-active');
		$('.siteseo-metabox-tab').hide();

		$(this).addClass('siteseo-metabox-tab-label-active');

		var activeTab = $(this).data('tab');
		$('.' + activeTab).show();
	});
	
	$('input[name="ps_device_type"]').on('change', function(){
		jEle = jQuery(this),
		val = jEle.val();
		
		if(val == 'mobile'){
			jQuery('#siteseo-ps-mobile').css('display', 'flex');
			jQuery('#siteseo-ps-mobile').find('.siteseo-metabox-tab-label:first-child').trigger('click');
			jQuery('#siteseo-ps-desktop').hide();
		} else {
			jQuery('#siteseo-ps-mobile').hide();
			jQuery('#siteseo-ps-desktop').css('display', 'flex');
			jQuery('#siteseo-ps-desktop').find('.siteseo-metabox-tab-label:first-child').trigger('click');
		}
		
	});

	$('#siteseopro-pagespeed-btn').on('click', function(){
		$('#siteseopro-pagespeed-results').empty();
	let spinner = $(this).next(),
		input = $(this).closest('div').find('input');

	spinner.addClass('is-active'),

		siteseo_pagespeed_request(input.val(), true);
		siteseo_pagespeed_request(input.val(), false);
	});

	$('#siteseopro-clear-Page-speed-insights').on('click', function(){
		$.ajax({
			url: siteseo_pro.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_pro_pagespeed_insights_remove_results',
				nonce: siteseo_pro.nonce
			},
			success: function(response){
				$('#siteseopro-pagespeed-results').empty();
			}
		});

	});

	$('.siteseo-audit-title').next('.description').hide();

	$('.siteseo-audit-title').on('click', function(e){
		var description = $(this).next('.description');
		var icon = $(this).find(".toggle-icon");

		if(description.is(':visible')){
			description.hide();
			icon.addClass('class', 'toggle-icon dashicons dashicons-arrow-up-alt2');
		} else {
			description.show();
			icon.addClass('class', 'toggle-icon dashicons dashicons-arrow-down-alt2');
		}
	});
	
	// updating htaccess
	$('#siteseo_htaccess_btn').on('click', function(){
		event.preventDefault();
		
		let spinner = $(event.target).next('.spinner');

		if(spinner.length){
			spinner.addClass('is-active');
		}

		let htaccess_code = $('#siteseo_htaccess_file').val(),
		htaccess_enable = $('#siteseo_htaccess_enable').is(':checked') ? 1 : 0;

		$.ajax({
		
			url : siteseo_pro.ajax_url,
			method: 'POST',
			data: {
				action: 'siteseo_pro_update_htaccess',
				htaccess_code: htaccess_code,
				htaccess_enable: htaccess_enable,
				_ajax_nonce : siteseo_pro.nonce
			},
			success: function(res){
				if(spinner.length){
					spinner.removeClass('is-active');
				}
				
				if(res.success){
					alert(res.data);
					return;
				}
				
				if(res.data){
					alert(res.data)
					return;
				}

				alert('Something went wrong, updating the file');
			}
		});
	});
	
	// Csv download
	$('#siteseo-export-csv').on('click', function(event){
		event.preventDefault();
		
		$.ajax({
			method: 'POST',
			url: siteseo_pro.ajax_url,
			data: {
				action: 'siteseo_pro_export_redirect_csv',
				_ajax_nonce: siteseo_pro.nonce
			},
			
			beforeSend: function(){
				$('#siteseo-export-csv').prop('disabled', true);
			},
			xhrFields:{
				responseType: 'blob'
			},
			success: function(response, status, xhr){
				
		var filename = 'siteseo-redirect-data-' + new Date().toISOString().slice(0,10) + '.csv';
				var disposition = xhr.getResponseHeader('Content-Disposition');
				if(disposition){
					var match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
					if(match && match[1]){
						filename = match[1].replace(/['"]/g, '');
					}
				}
				
			  var blob = new Blob([response], { type: 'text/csv' });
				var url = window.URL.createObjectURL(blob);
				var a = document.createElement('a');
				a.href = url;
				a.download = filename;
				document.body.appendChild(a);
				a.click();
				window.URL.revokeObjectURL(url);
				document.body.removeChild(a);
			},
			error: function(){
				alert('Error connecting to the server');
			},
			complete: function(){
				$('#siteseo-export-csv').prop('disabled', false);
			}
		});
	});
	
	// Clear all redirect logs
	$('#siteseo_redirect_all_logs').on('click', function(){
		event.preventDefault();
		
		if(!confirm('Are you sure you want to clear all logs?')){
			return;
		}

		let spinner = $(event.target).next('.spinner');

		if(spinner.length){
			spinner.addClass('is-active');
		}

		$.ajax({
			method: 'POST',
			url: siteseo_pro.ajax_url,
			data: {
				action: 'siteseo_pro_clear_all_logs',
				_ajax_nonce: siteseo_pro.nonce
			},
			success: function(res){
				if(spinner.length){
					spinner.removeClass('is-active');
				}

				if(res.success){
					alert(res.data);
					window.location.reload();
					return;
				}
				alert('Unable to clear logs.');
			},
			error: function(){
				alert('Error clearing logs.');
				if(spinner.length){
					spinner.removeClass('is-active');
				}
			}
		});
	});
	
	// update robots file
	$('#siteseo-update-robots').on('click', function(){
		event.preventDefault();
	
		let spinner = $(event.target).next('.spinner');

		if(spinner.length){
			spinner.addClass('is-active');
		}

		$.ajax({
			method : 'POST',
			url : siteseo_pro.ajax_url,
			data : {
				action : 'siteseo_pro_update_robots',
				robots : $('#siteseo_robots_file_content').val(),
				_ajax_nonce : siteseo_pro.nonce
			},
			success: function(res){
				
				if(spinner.length){
					spinner.removeClass('is-active');
				}

				if(res.success){
					alert(res.data);
					window.location.reload();
					return;
				}

				if(res.data){
					alert(res.data);
					return;
				}
				
				alert('Unable to create the robots.txt file');
			}
		});
	});
	
	$('#select-all-logs').on('click', function(){
		$('.log-selector').prop('checked', this.checked);
	});
	
	// Delete specific recoder 
	$('#siteseo-remove-selected-log').on('click', function(){
		var selectedIds = [];
		
		$('.log-selector:checked').each(function(){
			selectedIds.push($(this).val());
		});
		
		if(selectedIds.length === 0){
			alert('Please select at least one log to delete');
			return;
		}
		
		if(!confirm('Are you sure you want to delete the selected logs?')){
			return;
		}
		
		$.ajax({
			type : 'POST',
			url: siteseo_pro.ajax_url,
			data:{
				action: 'siteseo_pro_remove_selected_logs',
				ids: selectedIds,
				_ajax_nonce: siteseo_pro.nonce
			},
			success: function(response){
				if(response.success){
					
					$('.log-selector:checked').closest('tr').remove();
					alert('Selected logs deleted successfully');
				}else{
					alert('Error: ' + response.data);
				}
			},
			error: function(){
				alert('Failed to delete logs. Please try again.');
			}
		});
	});
	
	// Delete robots txt file
	$('#siteseopro-delete-robots-txt').on('click', function(e){
	e.preventDefault();
		$.ajax({
	  type: 'POST',
			url: siteseo_pro.ajax_url,
			data: {
		action: 'siteseo_pro_delete_robots_txt',
			  _ajax_nonce: siteseo_pro.nonce
			},
			success: function(response){
				
		if(response.success){
					window.location.reload();
				} else{
					alert(response.data);
				}
			},
			error: function(xhr, status, error){
				alert('An error occurred: ' + error);
			}
		});
	});

	// handel ajax toggle
	$('.siteseo-toggleSw').on('click', function(){
		const $toggle = $(this);
		const toggleKey = $toggle.data('toggle-key');
		const action = $toggle.data('action');

		saveToggle($toggle, toggleKey, action);
	});

	function saveToggle($toggle, toggleKey, action){
		const $container = $toggle.closest('.siteseo-toggleCnt');
		const $stateText = $container.find(`.toggle_state_${toggleKey}`);
		const $input = $(`#${toggleKey}`);

		$container.addClass('loading');
		$toggle.toggleClass('active');

		const newValue = $toggle.hasClass('active') ? '1' : '0';
		$input.val(newValue);
		$stateText.text($toggle.hasClass('active') ? 'Click to disable this feature' : 'Click to enable this feature');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: action,
				toggle_value: newValue,
				nonce: $toggle.data('nonce')
			},
			success: function(response){
				if(response.success){
					// Show the custom toast message
					ToastMsg('Your settings have been saved.');
				if(response.data.reload){
				  location.reload();
				}
				} else{
					console.error('Failed to save toggle state');
					toggleError($toggle, $input, $stateText);
					ToastMsg(response.data.message || 'Failed to save toggle state', 'error');
				}
			},
			error: function() {
				console.error('Ajax request failed');
				toggleError($toggle, $input, $stateText);
				ToastMsg('Unable to save settings', 'error');
			},
			complete: function() {
				$container.removeClass('loading');
			}
		});
	}
	
	//toast
	function ToastMsg(message, type = 'success') {

		const toast = $('<div>')
			.addClass('siteseo-toast')
			.addClass(type) 
			.html(`<span class="dashicons dashicons-yes"></span> ${message}`);

		$('body').append(toast); 

		// 3 seconds
		toast.fadeIn(300).delay(3000).fadeOut(300, function () {
			toast.remove();
		});
	}

	// error handler
	function toggleError($toggle, $input, $stateText) {
		$toggle.toggleClass('active');
		$input.val($toggle.hasClass('active') ? '1' : '0');
		$stateText.text($toggle.hasClass('active') ? 'Disable' : 'Enable');
	}

	// media uploader for image logo 
	$('#siteseopro_structured_data_upload_img').click(function(e) {
		var mediaUploader;
		e.preventDefault();
		
		if (mediaUploader) {
			mediaUploader.open();
			return;
		}

		
		mediaUploader = wp.media.frames.file_frame = wp.media({
			title: 'Media',
			button: {
				text: 'Select'
			},
			multiple: false
		});

		
		mediaUploader.on('select', function() {
			var attachment = mediaUploader.state().get('selection').first().toJSON();
			$('#structured_data_image_url').val(attachment.url);
		});
		
		mediaUploader.open();

	});

	// Icon to refresh AI tokens
	$('#siteseo-sidebar-refersh-tokens').on('click', function(e){
		e.preventDefault();
		let token_container = $(this).closest(".siteseo-ai-token-count");
		$('.siteseo-sidebar-ai-tokens').fadeOut(200).fadeIn(200);
		
		$.ajax({
			url: siteseo_pro.ajax_url,
			method: "POST",
			data: {
				action: "siteseo_pro_refresh_tokens",
				nonce: siteseo_pro.nonce,
			},
			success: function(res){
				if(!res.success){
					if(res.data){
						alert(res.data);
						return;
					}
					alert("Something went wrong fetching token data");
					return;
				}
				update_dashbord_ai_tokens(res.data);
			},
			error: function(){
				alert("Error refreshing tokens");
			}
		});
	});
	
	function update_dashbord_ai_tokens(remaining_tokens){
		let formatted_tokens = remaining_tokens < 0 ? 0 : parseInt(remaining_tokens).toLocaleString('en'),
		token_badge = $('.siteseo-ai-token-badge');
		
		// Update the tokens count text
		$('.siteseo-sidebar-ai-tokens').text('Tokens Remaining ' + formatted_tokens);
		
		// If you want to add a badge (though it's not in your original HTML)
		if(token_badge.length === 0){
			$('.siteseo-ai-token-count').prepend(
				'<span class="siteseo-ai-token-badge">' + formatted_tokens + '</span>'
			);
		} else{
			token_badge.text(formatted_tokens);
		}
	}
	
	/** global schema modal **/
	$('#siteseo-auto-schema-modal').dialog({
		autoOpen: false,
		modal: true,
		width: '40%',
		minWidth: 500,
		maxWidth: 600,
		closeOnEscape: false,
		dialogClass: 'siteseo-modal',
		draggable: false,
		resizable: false,
		position: { my: "center", at: "center", of: window },
		buttons: {
			"submit": {
				text: "Save",
				class: "save-schema-btn",
				click: function(){
					save_schema();
				}
			}
		}, 
		open: function(){
			// add spinner in footer
			let $btn = $(this).parent().find('.save-schema-btn');
			if(!$btn.next('.spinner').length){
				$btn.after('<span class="spinner" style="margin-left:130px; position:absolute;"></span>');
			}
		}	 
	});
	
	/** Add events **/
	$('#siteseo-auto-schema-modal').on('dialogopen', function() {
		// Add schema rule event
		$('.siteseo-add-schema-rule').off('click').on('click', function(e){
			e.preventDefault();
			let $row = $(this).closest('tr'),
			$container = $row.find('.siteseo-schema-rule-container'),
			$first = $container.find('select').first();

			let $clone = $first.clone();
			let $wrapper = $('<div class="siteseo-schema-rule" style="margin-top:5px; display:flex; align-items:center; gap:5px;"></div>');
			$wrapper.append($clone);
			$wrapper.append('<span title="Delete Rule" class="dashicons dashicons-trash siteseo-remove-schema-rule"></span>');
			$container.append($wrapper);

			// delete rule event as new elements are added
			bind_remove_rule();
		});

		// Delete rule event
		bind_remove_rule();

		// Specific targets change event
		bind_specific_targets_change();
	});
	
	$('#siteseo-auto-schema').on('click', function(e){
		e.preventDefault();

		// Open modal
		$('#siteseo-auto-schema-modal').dialog('open');

		// Clear all inputs and selects
		$('input[name="schema_id"]').val('');
		$('input[name="schema_name"]').val('');
		$('select[name="schema_type"]').val('None').trigger('change');

		// Clear dynamically added rules
		$('.siteseo-schema-rule-container').each(function(){
			let $first = $(this).find('select').first().clone();
			$(this).empty().append($first);
		});

		$('.siteseo-specific-input').remove();

		// Clear schema properties container
		$('#siteseo-schema-properties-show').empty();

		// Set modal title
		$('#siteseo-auto-schema-modal').dialog('option', 'title', 'Add Schema');
	});

	$('#siteseo-auto-schema-import-btn').on('click', function(e){
		e.preventDefault();
		// Open modal
		$('#siteseo-auto-schema-modal-import').dialog('open');
	});

	// Schema import modal
	$('#siteseo-auto-schema-modal-import').dialog({
		autoOpen: false,
		modal: true,
		width: '40%',
		minWidth: 400,
		maxWidth: 600,
		closeOnEscape: false,
		dialogClass: 'siteseo-modal',
		draggable: false,
		resizable: false,
		position: { my: "center", at: "center", of: window },
		buttons: {
			"submit": {
				text: 'Import',
				class: "schema-import-btn",
				click: function(){
					import_schema();
				}
			}
		},
		open: function(){
			var $btn = $(this).parent().find('.schema-import-btn');
			if(!$btn.next('.spinner').length){
				$btn.after('<span class="spinner" style="margin-left:150px; position:absolute;"></span>');
			}
		}	 
	});

	// Schema URl fetch 
	function import_schema(){

		var spinner = $('.schema-import-btn').next('.spinner');
		if(spinner.length){
			spinner.addClass('is-active');
		}

		$.ajax({
			url: siteseo_pro.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_import_schema',
				type: $('#siteseo-select-control-select').val(),
				url: $('#siteseo-text-control-url').val(),
				html: $('#siteseo-textarea-control-html').val(),
				json: $('#siteseo-textarea-control-json').val(),
				is_manual : $('#siteseo-schama-import-manual').is(':checked') ? true : '',
				nonce: siteseo_pro.nonce
			},
			success: function(response){
				if(spinner.length){
					spinner.removeClass('is-active');
				}

				if(response.success){
					render_schema_list(response.data.schemas);
				}else{
					alert(response.data);
				}
			}
		});
	}

	// Schema Show in UI
	function render_schema_list(schemas){
		// Reset previous data
		$('#siteseo-schemas-list').html('');
		schemas.forEach(function(schema, index){
			if(typeof highlightJson === 'function'){
				var highlight = highlightJson(schema.json_ld);
			}

			var item = `
				<div class="siteseo-schema">
					<div class="siteseo-schema-import-code-header">
						<div>
							<h4 class="siteseo-schema-section-title">JSON-LD Code</h4>
							<h4 class="siteseo-schema-section-title">#${index+1}: <strong>${schema.type}</strong></h4>
						</div>
						<div class="siteseo-si-action-btns">
							<div class="siteseo-schema-btn">
								<button class="siteseo-copy-schema-btn" data-target="schema_${index}"><span class="dashicons dashicons-media-code"></span> Copy</button>
								<button class="siteseo-schema-test-btn" type="button"><span class="dashicons dashicons-google"></span> Test with Google</button>
							</div>
							<textarea class="siteseo-schema-preview" id="schema_${index}" style="display:none;">${schema.json_ld}</textarea>
					</div>
					</div>
					<pre class="siteseo-code-output"><code class="siteseo-language-javascript">${highlight}</code></pre>
				</div>`;

			$('#siteseo-schemas-list').append(item);
		});

		$('#siteseo-imported-schema-results').show();
		// For Copy
		$('.siteseo-copy-schema-btn').off().on('click', function(){
			let id = $(this).data('target'),
			text = $('#' + id).val().trim();
			navigator.clipboard.writeText(text);

			$(this).html('<span class="dashicons dashicons-media-code"></span> Copied!').prop('disabled', true);
			setTimeout(() => {
				$(this).html('<span class="dashicons dashicons-media-code"></span> Copy').prop('disabled', false);
			}, 2000);
		});

		// Google rich results test
		$(document).on('click', '.siteseo-schema-test-btn', function(e){
			e.preventDefault();

			// Fetch Schema
			let $wrapper = $(this).closest('.siteseo-schema'),
			$textarea = $wrapper.find('.siteseo-schema-preview'),
			schemaContent = $textarea.val().trim(),
			$form = $('<form>', {
				'method': 'POST',
				'action': 'https://search.google.com/test/rich-results',
				'target': '_blank'
			}),
			$input = $('<input>', {
				'type': 'hidden',
				'name': 'code_snippet',
				'value': schemaContent
			});

			$form.append($input);
			$('body').append($form);

			$form.submit();
			$form.remove();
		});
	}

	// Action hide and show
	$('#siteseo-select-control-select').on('change', function(){
		var type = $(this).val();
		if(type === 'url'){
			$('.siteseo-schema-input-url').show();
			$('.siteseo-schema-input-html').hide();
			$('.siteseo-schema-input-customcode').hide();
			$('#siteseo-imported-schema-results').hide();
		}else if(type === 'html'){
			$('.siteseo-schema-input-html').show();
			$('.siteseo-schema-input-url').hide();
			$('.siteseo-schema-input-customcode').hide();
			$('#siteseo-imported-schema-results').hide();
		}else if(type === 'json'){
			$('.siteseo-schema-input-customcode').show();
			$('.siteseo-schema-input-html').hide();
			$('.siteseo-schema-input-url').hide();
			$('#siteseo-imported-schema-results').hide();
		}
	});

	/**loads global schema **/
	$('#siteseo_schema_type').change(function(e){
		let schema_type = $(this).val();
		let container = $('#siteseo-schema-properties-show');

		container.empty(); // Clear previous properties every time

		if(schema_type && siteseo_pro.schema[schema_type]){

			if(schema_type === 'BreadcrumbList' || schema_type === 'SearchAction'){
				$('#siteseo-schema-properties-container').hide();
			} else {
				load_schema_properties(schema_type);
				$('#siteseo-schema-properties-container').show();
			}

		} else{
			$('#siteseo-schema-properties-container').hide();
		}
	});
	
	function load_schema_properties(schema_type, existing_properties){
		let properties = existing_properties && Object.keys(existing_properties).length ? existing_properties : siteseo_pro.schema[schema_type];

		let container = $('#siteseo-schema-properties-show');
		container.empty();

		function process_properties(propObj, parent_key = '', depth = 0) {
			$.each(propObj, function(label, variable) {
				let full_key = parent_key ? `${parent_key}[${label}]` : label;

				if(typeof variable === 'object' && !Array.isArray(variable)){
					// nested object (sub-field group)
					let group_row = `
						<tr class="schema-property-row">
							<th scope="row" colspan="2">
								<strong>${label}</strong>
							</th>
						</tr>`;
					container.append(group_row);

					// Process nested properties
					process_properties(variable, full_key, depth + 1);
				} else {
					// This is a regular property
					let value = variable;

					// Try to get existing value from nested structure
					if(existing_properties){
						let keys = full_key.split('[').map(k => k.replace(']', ''));
						let current = existing_properties;

						for(let i = 0; i < keys.length; i++){
							if(current && current[keys[i]] !== undefined){
								current = current[keys[i]];
							} else {
								current = '';
								break;
							}
						}

						if(current !== undefined && current !== ''){
							value = current;
						} else if(existing_properties[label] !== undefined){
							// Fallback to flat structure
							value = existing_properties[label];
						}
					}

					let row = `
						<tr class="schema-property-row">
							<th scope="row">${label}</th>
							<td class="wrap-tags">
								<input class="siteseo_add_properties" value="${value}" type="text" name="schema_properties${parent_key ? '[' + parent_key + ']' : ''}[${label}]">
								<p class="description">Type # to view variables suggestions</p>
							</td>
						</tr>`;
					
					container.append(row);
				}
			});
		}

		// Start processing from the root properties
		process_properties(properties);
		
		// Keyup event
		bind_property_keyup();
	}
	
	function bind_property_keyup(){
		$('.siteseo_add_properties').off('keyup').on('keyup', function(e){
			e.preventDefault();
			e.stopPropagation();

			let $this = $(this),
			wrapper = $this.closest('.wrap-tags');
			val = $this.val(),
			suggestion_box = $('.siteseo-suggestions-wrapper .siteseo-suggetion').first().clone(true);
			
			wrapper.find('.siteseo-suggetion').remove();
			if(val.includes('#')){
				$this.after(suggestion_box);
				suggestion_box.show();
			} else{
				wrapper.find('.siteseo-suggetion').hide();
			}

		});
	}
	
	function save_schema(){
		let spinner = $('.save-schema-btn').next('.spinner');
		
		if(spinner.length){
			spinner.addClass('is-active');
		}
		
		$.ajax({
			url: siteseo_pro.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_pro_save_schema',
				schema_data: $('#siteseo-schema-form').find(':input').serialize(),
				nonce: siteseo_pro.nonce
			},
			success: function(response){
				
				if(spinner.length){
					spinner.removeClass('is-active');
				}

				if(response.success){
					$('#siteseo-auto-schema-modal').dialog("close");
					
					let schema = response.data; // {id, name, type}
					$('p:contains("No schemas added yet.")').remove();

					// Ensure table exists
					let $table = $('.siteseo-history-table');
					if(!$table.length){
						let table_html = `
							<h3>Manage Schema</h3>
							<table class="wp-list-table widefat fixed striped siteseo-history-table">
								<thead>
									<tr>
										<th>Schema Name</th>
										<th>Schema Type</th>
										<th>Actions</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>`;
						$('#siteseo-auto-schema-import-btn').after(table_html);
						
						$table = $('.siteseo-history-table');
					}

					// Add row data
					let $tbody = $table.find('tbody'),
					$existing_row = $tbody.find(`tr[data-id="${schema.id}"]`);

					let row_html = `
							<tr data-id="${schema.id}">
							<td>${schema.name}</td>
							<td>${schema.type}</td>
							<td>
								<span class="siteseo-action siteseo-edit-schema">
									<span class="dashicons dashicons-edit"></span>
									<label>Edit</label>
								</span>
								<span class="siteseo-action siteseo-delete-schema">
									<span class="dashicons dashicons-trash"></span>
									<label>Delete</label>
								</span>
								<span class="spinner"></span>
							</td>
						</tr>`;

					if($existing_row.length){
						$existing_row.replaceWith(row_html);
					} else{
						$tbody.append(row_html);
					}
					
				} else{
					alert('Error saving schema: ' + response.data);
				}
			},
			error: function(xhr, status, error){
				alert('AJAX error: ' + error);
			}
		});
	}
	
	// delete schema ajax
	$('#tab_auto_schema').on('click', '.siteseo-delete-schema', function(e){
		e.preventDefault();
		if(!confirm('Are you sure you want to delete this schema?')) return;
		
		let id = $(this).closest('tr').data('id'),
		spinner = $(this).next();
		
		if(spinner.length){
		  spinner.addClass('is-active');
		}
		
		$.ajax({
			url: siteseo_pro.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_pro_delete_schema',
				id: id,
				nonce: siteseo_pro.nonce
			},
			success: function(response){
				
				if(spinner.length){
				  spinner.removeClass('is-active');
				}
				
				if(response.success){
					$('tr[data-id="' + id + '"]').remove();
					if ($('#siteseo-schema-list tr').length === 0){
						$('#siteseo-schema-list').html('<tr><td colspan="3">No schemas found.</td></tr>');
					}
				}
			}
		});
	});
	
	// Edit schema -
	$('#tab_auto_schema').on('click', '.siteseo-edit-schema', function(e){
		e.preventDefault();
		
		let id = $(this).closest('tr').data('id'),
		spinner = $('#siteseo-auto-schema-modal .siteseo-modal-loads-data .spinner'),
		loading = $('#siteseo-auto-schema-modal .siteseo-modal-loads-data').show();
		loader_text = $('.siteseo-modal-loads-data .siteseo-loading-text');
		
		spinner.addClass('is-active');
		loader_text.text('Loading schema data, please wait...');
		
		$('#siteseo-auto-schema-modal').dialog("option", "title", "Edit Schema");
		$('#siteseo-auto-schema-modal').dialog("open");
		$('#siteseo-schema-form').hide();
		
		$.ajax({
			url: siteseo_pro.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_pro_get_schema',
				id: id,
				nonce: siteseo_pro.nonce
			},
			success: function(response){
				spinner.removeClass('is-active');
				loading.hide();
		
				if(response.success){
					
					$('#siteseo-schema-form').show();
					let schema = response.data;
					
					// Fill the form with schema data
					$('input[name="schema_id"]').val(schema.id);
					$('input[name="schema_name"]').val(schema.name);
					$('select[name="schema_type"]').val(schema.type).trigger('change');

					// Set display rules
					if(schema.display_on && schema.display_on.length){
						let $first = $('select[name="display_on[]"]').first();
						
						// Clear existing rules first
						let $container = $first.closest('.siteseo-schema-rule-container');
						$container.empty().append($first);
						
						// Add all rules
						for(let i = 0; i < schema.display_on.length; i++){
							let rule = schema.display_on[i];
							let value, targets;
							
							// Check if it's a specific target object or string
							if(typeof rule === 'object' && rule.type === 'specific_targets'){
								value = 'specific_targets';
								targets = rule.targets;
							} else{
								value = rule;
								targets = '';
							}
							
							let $select;
							if(i === 0){
								$select = $first;
								$select.val(value);
							} else{
								$select = $first.clone().val(value);
								let $wrapper = $('<div class="siteseo-schema-rule" style="margin-top:5px; display:flex; align-items:center; gap:5px;"></div>');
								$wrapper.append($select);
								$wrapper.append('<span title="Delete Rule" class="dashicons dashicons-trash siteseo-remove-schema-rule"></span>');
								$container.append($wrapper);
							}
							
							// Add specific targets input if needed
							if(value === 'specific_targets'){
								let $input = $('<input type="text" name="specific_targets[]" class="siteseo-specific-input" placeholder="Enter specific IDs or URLs" style="margin-top:5px; width:100%;" value="' + targets + '">');
								$select.after($input);
							}
						}
					}

					if(schema.display_not_on && schema.display_not_on.length){
						let $first = $('select[name="display_not_on[]"]').first();
						
						// Clear existing rules first
						let $container = $first.closest('.siteseo-schema-rule-container');
						$container.empty().append($first);
						
						// Add all rules
						for(let i = 0; i < schema.display_not_on.length; i++){
							let rule = schema.display_not_on[i];
							let value, targets;
							
							// Check if it's a specific target object or string
							if(typeof rule === 'object' && rule.type === 'specific_targets'){
								value = 'specific_targets';
								targets = rule.targets;
							} else{
								value = rule;
								targets = '';
							}
							
							let $select;
							if(i === 0){
								$select = $first;
								$select.val(value);
							} else{
								$select = $first.clone().val(value);
								let $wrapper = $('<div class="siteseo-schema-rule" style="margin-top:5px; display:flex; align-items:center; gap:5px;"></div>');
								$wrapper.append($select);
								$wrapper.append('<span title="Delete Rule" class="dashicons dashicons-trash siteseo-remove-schema-rule"></span>');
								$container.append($wrapper);
							}
							
							// Add specific targets input if needed
							if(value === 'specific_targets'){
								let $input = $('<input type="text" name="specific_targets_not[]" class="siteseo-specific-input" placeholder="Enter specific IDs or URLs" style="margin-top:5px; width:100%;" value="' + targets + '">');
								$select.after($input);
							}
						}
					}
					
					// Load properties
					load_schema_properties(schema.type, schema.properties);
				}
			}
		});
	});
	
	function bind_remove_rule(){
		$('#siteseo-auto-schema-modal').off('click', '.siteseo-remove-schema-rule').on('click', '.siteseo-remove-schema-rule', function(e){
			e.preventDefault();
			$(this).closest('.siteseo-schema-rule').remove();
		});
	}
	
	// Show input when "specific_targets" selected
	function bind_specific_targets_change(){
		
		$('#siteseo-auto-schema-modal').off('change', 'select[name="display_on[]"], select[name="display_not_on[]"]').on('change', 'select[name="display_on[]"], select[name="display_not_on[]"]', function(){
			let $select = $(this),
			value = $select.val(),
			is_display_on = $select.attr('name') === 'display_on[]',
			input_name = is_display_on ? 'specific_targets[]' : 'specific_targets_not[]';
			
			$select.siblings('.siteseo-specific-input').remove();

			if(value === 'specific_targets'){
				let $input = $('<input type="text" name="' + input_name + '" class="siteseo-specific-input" placeholder="Enter specific IDs or URLs" style="margin-top:5px; width:100%;">');
				$select.after($input);
			}
		});
	}

	// Disconnect search console
	$('.siteseo-statistics-disconnect').on('click', function(){
		if(confirm('Are you sure you want to disconnect from Google Search Console?')){
			$.ajax({
				url: siteseo_pro.ajax_url,
				type: 'POST',
				data: {
					action: 'siteseo_pro_disconnect_google',
					nonce: siteseo_pro.nonce
				},

				success: function (response){
					location.reload();
				},

				error: function (xhr, status, error){
					alert('Disconnection failed: ' + error);
				}
			});
		}
	});

	$('#siteseo-site-connection-dialog').dialog({
		autoOpen: true,
		modal: true,
		width: 550,
		dialogClass: 'siteseo-modal',
		closeOnEscape: false,
		open: function (event, ui){},
		close: function (event, ui){
			let clear_url = window.location.origin + window.location.pathname + '?page=siteseo-search-statistics';
			window.location.href = clear_url;
		}
	});

	// Handle suggested site selection
	$('input[name="suggested_site"]').on('change', function(){
		$('#siteseo-site-url').val($(this).val());
	});

	// Cancel button handler
	$('#siteseo-cancel-connection').on('click', function(){
		$('#siteseo-site-connection-dialog').dialog('close');
		let clear_url = window.location.origin + window.location.pathname + '?page=siteseo-search-statistics';
		window.location.href = clear_url;
	});

	$('#siteseo-show-existing-properties').on('click', function(){
		$('.siteseo-option-primary').slideUp(300);
		$(this).hide();
		$('#siteseo-existing-properties-section').slideDown(300);
	});

	$('#siteseo-back-to-main').on('click', function(){
		$('#siteseo-existing-properties-section').slideUp(300);
		$('.siteseo-option-primary').slideDown(300);
		$('#siteseo-show-existing-properties').show();
	});

	$('#siteseo-create-gsc-property').on('click', function(){
		let site_url = $('#siteseo-new-domain-url').val(),
		spinner = $(this).next();

		let log_container = $('#siteseo-gsc-logs');
		if(!log_container.length){
			log_container = $('<div id="siteseo-gsc-logs" style="margin-top:10px; max-height:150px; overflow-y:auto; background:#f9f9f9; padding:10px; border:1px solid #ddd; font-size:12px; line-height:1.5;"></div>');
			$('#siteseo-site-connection-dialog').append(log_container);
		}

		log_container.empty(); // Clear previous logs
		spinner.addClass('is-active');

		let log_status = (msg) => {
			log_container.append('<div>' + msg + '</div>');
			log_container.scrollTop(log_container[0].scrollHeight);
		};
	
		perform_gsc_setup(log_status, spinner, true);
		
		return false;

	});
	
	// Refreshes the Search console stats
	$('#siteseo-refresh-search-stats').on('click', update_search_console_data);
	
	// Reloads the data for search console, if 12 hours has passed since last refresh
	if(siteseo_pro.reload_search_console_stats){
		let query_string = window.location.search,
		url_params = new URLSearchParams(query_string);
		if(url_params.get('page') == 'siteseo-search-statistics'){
			siteseo_pro.reload_search_console_stats = false;
			update_search_console_data_cron();
		}
	}

	function update_search_console_data_cron(){
		jEle = $('#siteseo-refresh-search-stats');
		
		if(jEle.attr('disabled') == true){
			return;
		}

		jEle.attr('disabled', true).addClass('siteseo-spin-animation'); // adding the loading state
		jEleWrapper = $('.siteseo-statistics-sites').closest('.siteseo-statistics-wrapper');

		$.ajax({
			url: siteseo_pro.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_pro_refresh_search_stats',
				nonce: siteseo_pro.nonce,
				cron: true
			},
			beforeSend: function(){
				jEleWrapper.prepend('<em>Updating the analytics</em>');
			},
			success: function(response){
				if(response.success){
					jEleWrapper.find('em').text('Refresh the page to see updated data');
					return;
				}
				
				if(response.data){
					console.error(response.data);
					return;
				}
				
				console.error('Something went wrong updating the analytics, though the cron');
			},
			error: function(jqXHR, status, error_thrown){
				console.error('AJAX Error: ' + error_thrown);
			},
			complete: function(){
				jEle.attr('disabled', false).removeClass('siteseo-spin-animation');
			}
		});
	}
	
	function update_search_console_data(){
		jEle = $(this);
		
		if(jEle.attr('disabled') == true){
			return;
		}

		jEle.attr('disabled', true).addClass('siteseo-spin-animation'); // adding the loading state

		$.ajax({
			url: siteseo_pro.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_pro_refresh_search_stats',
				nonce: siteseo_pro.nonce,
			},
			success: function(response){
				if(response.success){
					alert(response.data + ' The page will reload');
					window.location.reload();
					return;
				}
				
				if(response.data){
					alert(response.data);
					return;
				}
				
				alert('Something went wrong updating the analytics');
			},
			error: function(jqXHR, status, error_thrown){
				alert('AJAX Error: ' + error_thrown);
			},
			complete: function(){
				jEle.attr('disabled', false).removeClass('siteseo-spin-animation');
			}
		});
	}
	
	// Common setup function
	function perform_gsc_setup(log_status, spinner, redirect_on_success){
		log_status('Starting process...');

		// Step 1: Create Property
		log_status('Creating property...');
		perform_gsc_request('create_property')
			.then(function (response){
				log_status('Property created successfully.');

				log_status('Verifying property...');
				// Step 2: Requesting Google to verify ownership
				return perform_gsc_request('verify');
			})
			.then(function(){
				log_status('Property verified successfully!');

				// Step 3: Submit sitemap
				log_status('Submitting sitemap...');
				return perform_gsc_request('submit_sitemap');
			})
			.then(function(){
				log_status('Sitemap submitted successfully.');

				// Step 4: Fetch analytics
				log_status('Fetching analytics data...');
				return perform_gsc_request('fetch_analytics');
			})
			.then(function(){
				log_status('Connected successfully.....');

        $('.siteseo-btn-gsc').text('Connected');

				if(spinner){
					spinner.removeClass('is-active');
				}

				if(redirect_on_success){
					let clear_url = window.location.origin + window.location.pathname + '?page=siteseo-search-statistics';
					window.location.href = clear_url;
				}
			})
			.catch(function(error){
				let error_msg = error.message || 'An error occurred';
				log_status('<span style="color:red;">Error: ' + error_msg + '</span>');
				
				$('.siteseo-btn-gsc').prop('disabled', false).css('opacity', '1').text('Connect');
				
				if(spinner){
					spinner.removeClass('is-active');
					if (spinner.remove) spinner.remove();
				}
				
				if(redirect_on_success){
					alert(error_msg);
				}
			});
	}

	function perform_gsc_request(step, data){
		return new Promise(function (resolve, reject){
			$.ajax({
				url: siteseo_pro.ajax_url,
				type: 'POST',
				data: $.extend({
					action: 'siteseo_pro_create_gsc_property',
					step: step,
					nonce: siteseo_pro.nonce
				}, data),
				success: function (response){
					if(response.success){
						resolve(response);
					} else {
						reject(new Error(response.data.message || 'Unknown error in step ' + step));
					}
				},
				error: function (xhr, status, error){
					reject(new Error('Network error in step ' + step));
				}
			});
		});
	}

	$(document).on('click', '#siteseo-connect-existing', function(){
		let site_url = $('#siteseo-site-url').val(),
		$button = $(this),
		spinner = $(this).next()
		original_text = $button.text();

		let log_container = $('#siteseo-gsc-logs');
		if(!log_container.length){
			log_container = $('<div id="siteseo-gsc-logs" style="margin-top:10px; max-height:150px; overflow-y:auto; background:#f9f9f9; padding:10px; border:1px solid #ddd; font-size:12px; line-height:1.5;"></div>');
			$('#siteseo-site-connection-dialog').append(log_container);
		}

		log_container.empty(); // Clear previous logs

		let log_status = (msg) => {
			log_container.append('<div>' + msg + '</div>');
			log_container.scrollTop(log_container[0].scrollHeight);
		};

		spinner.addClass('is-active');
		
		$button.text('Connecting...');

		log_status('Fetching data...');

		perform_gsc_request('submit_sitemap', {'site_url':site_url})
			.then(function(){
				log_status('Sitemap submitted successfully.');

				log_status('Fetching analytics data...');
				return perform_gsc_request('fetch_analytics', {'site_url':site_url});
			})
			.then(function(){
				log_status('Analytics data fetched successfully.');

				if(spinner){
					spinner.removeClass('is-active');
				}

				let clear_url = window.location.origin + window.location.pathname + '?page=siteseo-search-statistics';
				window.location.href = clear_url;
			})
			.catch(function(error){
				let error_msg = error.message || 'An error occurred';
				log_status('<span style="color:red;">Error: ' + error_msg + '</span>');
				
				$('.siteseo-btn-gsc').prop('disabled', false).css('opacity', '1').text('Connect');
				
				if(spinner){
					spinner.removeClass('is-active');
					if (spinner.remove) spinner.remove();
				}
				
				alert(error_msg);
			});
	});
	
	//Onbording 
	if(typeof siteseo_pro !== 'undefined' && siteseo_pro.auth_code){
		connect_gsc_onboarding(siteseo_pro.auth_code);
	}

	function connect_gsc_onboarding(){
		let log_container = $('#siteseo-onboarding-logs');
		let connect_btn = $('.siteseo-btn-gsc');
		
		// Disable the connect button
		connect_btn.prop('disabled', true).css('opacity', '0.6').text('Connecting…');;
		
		if(!log_container.length){
			log_container = $('<div id="siteseo-onboarding-logs" class="siteseo-gsc-list" style="margin-top:20px; max-height:200px; overflow-y:auto; color:#fff;"></div>');
			$('.siteseo-gsc-section').append(log_container);
		}

		log_container.empty().show();
		let spinner = $('<span class="spinner is-active" style="float:none; margin-right:10px;"></span>');
		log_container.prepend(spinner);
		let log_status = (msg, type = 'info') => {
			let color = type === 'error' ? '#ff6b6b' : (type === 'success' ? '#51cf66' : '#fff');
			log_container.append('<div style="color:' + color + '; margin-bottom:5px;">' + msg + '</div>');
			log_container.scrollTop(log_container[0].scrollHeight);
		};

		perform_gsc_setup(log_status, spinner, false);
	}

});

async function siteseo_pagespeed_request(url, is_mobile = false){
	jQuery.ajax({
		url: siteseo_pro.ajax_url,
		type: 'POST',
		data: {
			action: 'siteseo_pro_get_pagespeed_insights',
			is_mobile : is_mobile,
			test_url : url,
			nonce: siteseo_pro.nonce
		},
		success: function(response){
			if(!response.success){
				alert(response.data ?? 'Something went wrong');
				return;
			}

			if(siteseo_pro.pagespeed_response){
				//spinner.removeClass('is-active');
				location.reload(true);
				return;
			}

			siteseo_pro['pagespeed_response'] = true;
		}
	});	

	
}