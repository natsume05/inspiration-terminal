jQuery(document).ready(function($){

	// video thumbnail upload
	$(document).on('click', '.siteseo-video-thumbnail-upload', function(e){
    e.preventDefault();
    var button = $(this);
    var frame = wp.media({
            title: 'Select or Upload Video Thumbnail',
            button: {
              text: 'Use this image'
            },
            multiple: false
      });
       
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            button.prev('input').val(attachment.url);
        });
        
        frame.open();
	});
	
	$(document).on('click', '#siteseo_validate_schema', function(e){
		e.preventDefault();

		// Fetch Schema
		var schemaContentWrap = $('.siteseo_schema_preview #siteseo_raw_schema');
		if(!schemaContentWrap.length){
			schemaContentWrap = $('.siteseo_schema_preview');
		}

		let schemaContent = '';
		
		// This is to ensure we dont end up having 2 values of the schema.
		if(schemaContentWrap.length > 1){
			schemaContent = schemaContentWrap.eq(0).text();
		} else {
			schemaContent = schemaContentWrap.text();
		}

		let $form = $('<form>', {
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
		$("body").append($form);

		$form.submit();
		$form.remove();
	});
	
  // Custom schema json invalid
	$(document.body).on('blur', '.siteseo_structured_data_custom', function(e){
		e.preventDefault();
		let json_str = $(this).val().trim(),
		error_box = $('.siteseo-json-error');

		if(json_str === ''){
			error_box.hide(); // empty allowed
			return;
		}

		try {
			JSON.parse(json_str);
      error_box.hide();
		} catch(e){
			error_box.text("⚠ Invalid JSON: " + e.message);
			error_box.show();
		}
	});
	
	$(document).on('change', '.siteseo_structured_data_type', function(e){
		e.preventDefault();
		
		let schemaType = $(this).val(),
		propertiesContainer = $('.siteseo-metabox-schema'),
		customSchemaContainer = $('.siteseo_custom_schema_container'),
		propertiesDiv = $('.siteseo-schema-properties');
		preview = $('.siteseo-schema-preview');

		$('.siteseo_structured_data_type').val(schemaType);
		
		propertiesDiv.empty();
		
		if(schemaType === ''){
			propertiesContainer.hide();
			customSchemaContainer.hide();
			preview.hide();
			$('.siteseo_schema_preview').html('');
			$('#siteseo_raw_schema').text('');
			return;
		}
	   
		if(schemaType === 'CustomSchema'){
			propertiesContainer.hide();
			preview.show();
			customSchemaContainer.show();
			updateCustomSchemaPreview();
			return;
		} else{
			propertiesContainer.show();
			customSchemaContainer.hide();
			preview.show();
		}
		
		// schemas load
		let properties = structuredDataMetabox.propertyTemplates[schemaType] || {};
		// Create form fields for each property
		$.each(properties, function(property, defaultValue){
			var label = property.replace(/([a-z])([A-Z])/g, '$1 $2');
			label = label.charAt(0).toUpperCase() + label.slice(1);

			let field = '',
			is_textarea_fields = ['description', 'instructions', 'reviewBody', 'questions', 'step', 'ingredients', 'recipeInstructions', 'courseDescription', 'bookDescription', 'softwareRequirements', 'menu'],
			is_date_type_fields = ['datePublished', 'dateModified', 'uploadDate', 'startDate', 'endDate', 'foundingDate', 'releaseDate'];
			
			if(is_textarea_fields.includes(property)){
				field = $('<textarea/>').attr({ name: 'schema_properties[' + property + ']', id: 'siteseo_schema_property_' + property, rows: 3, class: 'widefat'}).val(defaultValue);
			} else if (is_date_type_fields.includes(property)){
				field = $('<input/>').attr({ type: 'datetime-local', name: 'schema_properties[' + property + ']', id: 'siteseo_schema_property_' + property,
				class: 'widefat'}).val(defaultValue);
			} else {
				field = $('<input/>').attr({ type: 'text', name: 'schema_properties[' + property + ']', id: 'siteseo_schema_property_' + property,
				class: 'widefat'}).val(defaultValue);
			}
			
			$('<p/>')
				.append($('<label/>').attr('for', 'siteseo_schema_property_' + property).text(label + ':'))
				.append(field)
				.appendTo(propertiesDiv);
			});
			
			// preview update
			updatePreview();
			$(document).on('input', '.siteseo-schema-properties input, #schema_properties textarea', updatePreview);
	});
	
	// preview function
	function updatePreview(){
		var schemaType = $('.siteseo_structured_data_type').val();
		if(schemaType === 'CustomSchema'){
			updateCustomSchemaPreview();
			return;
		}

		var schemaData = {
			'@context': 'https://schema.org',
			'@type': schemaType
		};
	   
		$('.siteseo-schema-properties input, .siteseo-schema-properties textarea').each(function(){
			var propertyName = $(this).attr('name').match(/\[(.*?)\]/)[1];
			var propertyValue = $(this).val();

			if(propertyValue !== ''){
			schemaData[propertyName] = propertyValue;
			}
		});
	   
		var jsonString = JSON.stringify(schemaData, null, 2);
		$('#siteseo_raw_schema').text(jsonString);
	   
	   // Make sure highlighter element exists
		if($('.siteseo_schema_preview .siteseo_highlighter').length === 0){
		   $('.siteseo_schema_preview').html('<div id="siteseo_highlighter" class="siteseo_highlighter"></div><div id="siteseo_raw_schema" style="display:none;"></div>');
		}
	   
		$('.siteseo_schema_preview .siteseo_highlighter').html(highlightJson(jsonString));
	}
	
	// Custom schema preview
	function updateCustomSchemaPreview(){
		var customSchema = $('.siteseo_structured_data_custom').val() || '';
		$('#siteseo_raw_schema').text(customSchema);
	   
		// highlighter element exists
		if($('.siteseo_schema_preview .siteseo_highlighter').length === 0){
			$('.siteseo_schema_preview').html('<div id="siteseo_highlighter" class="siteseo_highlighter"></div><div id="siteseo_raw_schema" style="display:none;"></div>');
		}
	   
		try{
			
			if(customSchema.trim()){
				var jsonObj = JSON.parse(customSchema);
				$('.siteseo_schema_preview .siteseo_highlighter').html(highlightJson(jsonObj));
			} else{
				$('.siteseo_schema_preview .siteseo_highlighter').html('');
			}
		} catch(e){
			
			$('.siteseo_schema_preview .siteseo_highlighter').text(customSchema);
		}
	}
   
	// as per schema change update preview
	$(document).on('input', '.siteseo_structured_data_custom', updateCustomSchemaPreview);
   
	// Initial preview update
	if($('.siteseo_structured_data_type').val() !== ''){
		if($('.siteseo_structured_data_type').val() === 'CustomSchema'){
			updateCustomSchemaPreview();
		} else{
			updatePreview();
			$(document).on('input', '.siteseo-schema-properties input, .siteseo-schema-properties textarea', updatePreview);
		}
	}
	
	/*** Refresh tokens ***/
	$(document).on('click', '.siteseo-ai-refresh-tokens', function(e){
		e.preventDefault();
		e.stopPropagation();
		let token_container = $(this).closest(".siteseo-ai-token-count");
		token_container.addClass("siteseo-loading");
 
		$('.siteseo-ai-token-badge').fadeOut(200).fadeIn(200);
	
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
                        snackbar(res.data);
                        return;
                    }
                    snackbar("Something went wrong fetching token data");
                    return;
                }
                update_tokens(res.data);
            },
            error: function(){
                snackbar("Error refreshing tokens");
            },
            complete: function(){
                token_container.removeClass("siteseo-loading");
            }
        });
    });
	
	function snackbar(msg){
		if(!msg){
			msg = 'Something went wrong!';
		}

		snack_bar = $('.siteseo-ai-snackbar');
		snack_bar.text(msg);
		snack_bar.show();
		setTimeout(function(){ snack_bar.hide() }, 3500);
	}
  	
	/** update tokens **/
	function update_tokens(remaining_tokens){
		let formatted_tokens = remaining_tokens < 0 ? 0 : parseInt(remaining_tokens).toLocaleString('en'),
		token_badge = $('.siteseo-ai-token-badge');
		
		if(token_badge.length === 0){
    
			$('.siteseo-ai-token-count').prepend(
				'<span class="siteseo-ai-token-badge">Tokens Remaining ' + formatted_tokens + '</span>' +
				'<span class="dashicons dashicons-image-rotate siteseo-ai-refresh-tokens" id="siteseo-ai-refresh-tokens" title="Refresh tokens"></span>' + '<hr />'
			);
		} else{
			let show_buy_link = remaining_tokens < 900 ? '<br/><a href="'+siteseo_pro.buy_link+'" target="_blank" class="siteseo-ai-buy-tokens">Buy AI Tokens</a>' : '';
			token_badge.html('Tokens Remaining ' + formatted_tokens + show_buy_link);
		}
	}
	
	// Open modal
	$(document).on('click', '.siteseo-ai-modal-open', function(e){
		e.preventDefault();
		let context = $(this).data('context') || 0,
		$modal = $('.siteseo-ai-modal-overlay');
		
		if(typeof siteseo_universal !== 'undefined'){
			$modal.data('context', context).show();
		} else{
			
			if(window.parent && window.parent !== window){
				let $parentModal = window.parent.jQuery('.siteseo-ai-modal-overlay');

				if($parentModal.length){
					$parentModal.data('context', context).show();
					add_input_fields();
					return;
				}
			}
			      
			$modal.data('context', context).show();
		}
		
		// Update input fileds on tigger ai output
		add_input_fields();
	});
	
	// Close modal
	$('#siteseo-ai-close-modal').on('click', function(){
		$('.siteseo-ai-modal-overlay').hide();
	});

	// Close when clicking outside
	$(document).on('click', '.siteseo-ai-modal-overlay', function(e){
		if($(e.target).is('.siteseo-ai-modal-overlay')){
			let $modal = $('.siteseo-ai-modal-overlay');
			if($modal.is(':visible')){
				$modal.hide();
			}
		}
	});

	/** AI **/
	$('.siteseo-ai-generate').on('submit', function(e){
		e.preventDefault();
		
		let context = $('#siteseo-ai').data('context'),
		generate_title = $('input[name="generate_title"]').is(':checked'),
		generate_desc = $('input[name="generate_desc"]').is(':checked');
		
		$('.siteseo-ai-placeholder').remove();
		$('.siteseo-ai-error').remove();

		// Handling Universal metabox
		let universal_metabox = document.querySelector('#siteseo-iframe-universal-metabox');
		if(typeof siteseo_universal !== 'undefined' && context.length === 0 && universal_metabox && universal_metabox.contentWindow){
			let universal_metabox_doc = universal_metabox.contentWindow.document,
			$universal_metabox_visible = $(universal_metabox_doc).find('.siteseo-ai-modal-open:visible');
			
			if($universal_metabox_visible.length){
				context = $universal_metabox_visible.data('context');
			}
		}

		let focus_keyword = $('.siteseo-ai-modal-body .siteseo-ai-input[type="text"]').eq(0).val(),
		post_brief = $('.siteseo-ai-modal-body textarea').val(),
		tone = $('.siteseo-ai-modal-body select').eq(0).val(),
		audience = $('.siteseo-ai-modal-body select').eq(1).val(),
		language = $('#siteseo-ai-language-select').val(),
		num_titles = $('.siteseo-ai-modal-body input[type="number"]').val(),
		spinner = $('.siteseo-ai-spinner');
		
		if(!focus_keyword){
			$('.siteseo-ai-error-msg').html('<div class="siteseo-ai-error">Please enter the focus keyword.</div>');
			return;
		}
		
		if(focus_keyword.length < 4){
			$('.siteseo-ai-error-msg').html('<div class="siteseo-ai-error">Focus keyword should be at least 4 characters long.</div>');
			return;
		}
		
		if(!post_brief){
			$('.siteseo-ai-error-msg').html('<div class="siteseo-ai-error">Please enter the post summary.</div>');
			return;
		}
		
		if(post_brief.length < 9){
			$('.siteseo-ai-error-msg').html('<div class="siteseo-ai-error">Post summary should be at least 9 characters long.</div>');
			return;
		}
		
		if(!generate_title && !generate_desc){
			$('.siteseo-ai-error-msg').html('<div class="siteseo-ai-error">Please select at least one option (title or description) to proceed..</div>');
			return;
		}
		
		$('.siteseo-ai-error').remove();
		
		spinner.addClass('siteseo-ai-spinner-active');
		
		let $button = $(this);
    
		$('.siteseo-generate-animation').addClass('siteseo-ai-shimmer');

		let prompt = (generate_title || generate_desc) ? build_prompt(focus_keyword, post_brief, tone, audience, language, num_titles, context, generate_title, generate_desc) : null;
		
		if(!prompt){
			return;
		}

		$.ajax({
			url: siteseo_pro.ajax_url,
			type: 'POST',
			data: {
				action: 'siteseo_pro_ai_generate',
				prompt: prompt,
				nonce: siteseo_pro.nonce
			}
		})
		.then(function(response){
			if(!response.success){
				throw response.data;
			}

			if(response.data && response.data.remaining_tokens){
				update_tokens(response.data.remaining_tokens);
			}

			return response.data.ai;
		})
		.then(function(response){
			spinner.removeClass('siteseo-ai-spinner-active');
			$button.prop('disabled', false);

			if(!response.titles && generate_title && !generate_desc){
				throw 'Unable to generate title';
			} else if(!response.descriptions && generate_desc && !generate_title){
				throw 'Unable to generate description';
			} else if(!response.titles && !response.descriptions && generate_desc && generate_title){
				throw 'Unable to generate title and description';
			}

			process_ai_response(response, generate_title, generate_desc);
		})
		.catch(function(error) {
			spinner.removeClass('siteseo-ai-spinner-active');
			$button.prop('disabled', false);
			handle_error($button, error);
		});
	});
	
	function build_prompt(focus_keyword, post_brief, tone, audience, lang, num, context, is_title = false, is_desc = false){
		// Return early if nothing is requested.
		if(!is_title && !is_desc){
			return '';
		}

		// Use a simple map for platform context for easier maintenance.
		const platform_map = {
			og: 'OpenGraph (Facebook)',
			twitter: 'X (Twitter)',
		};
		const platform_context = platform_map[context] || 'web page';

		// Dynamically build the parts of the prompt.
		const generate_parts = [];
		const length_instructions = [];
		const format_object = {};

		if(is_title){
			generate_parts.push('title');
			length_instructions.push('each title must be between 50 and 60 characters');
			format_object.titles = ['string', 'string']; // Example structure
		}

		if(is_desc){
			generate_parts.push('description');
			length_instructions.push('each description must be between 150 and 160 characters');
			format_object.descriptions = ['string', 'string']; // Example structure
		}

		const generate = generate_parts.join(' and ');
		const content_length = `The length of ${length_instructions.join(', and ')}.`;
		const format = JSON.stringify(format_object);

		// Building the final prompt.
		return `Generate exactly ${num} SEO ${platform_context} ${generate} suggestions for a blog post with the following details:
- Focus Keyword: ${focus_keyword}
- Post Brief: ${post_brief}
- Tone: ${tone}
- Target Audience: ${audience}
- Language: ${lang}

It should be optimized for ${platform_context}, engaging, and ${content_length}
Return the response in json format like ${format} and do not include any extra text or explanation — only the raw JSON.`;
	}

	function process_ai_response(response, generate_title, generate_desc){
		let titles = [];
		let descriptions = [];

		let title_target, desc_target,
		copy_icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>',
		use_icon_svg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
		
		// Process if title res valid
		if(typeof response.titles != 'undefined' && response.titles.length > 0){
			titles = response.titles;
		}

		// Process desc res valid
		if(typeof response.descriptions != 'undefined' && response.descriptions.length > 0){
			descriptions = response.descriptions;
		}
    
		let title_html = '',
		desc_html = '',
		error_msg = '';

		// Show tabs
		if(titles.length > 0){
			// Count existing titles plus new ones
			let existingTitlesCount = $('.siteseo-ai-outputs-titles .siteseo-ai-item').length,
			title_tab = $('.siteseo-ai-tab-btn[data-tab="siteseo-titles-tab"]');
			title_tab.show().addClass('active');
			title_tab.find('.siteseo-ai-tab-stat').text(existingTitlesCount + titles.length);

			titles.forEach(function(title){
				if(title.trim()){
					title_html += `
					<div class="siteseo-ai-item">
						<div class="siteseo-ai-item-content">${title.trim()}</div>
						<div class="siteseo-ai-item-actions">
							<button class="siteseo-ai-copy-btn" data-text="${title.trim()}">
								${copy_icon_svg} Copy
							</button>
							<button class="siteseo-ai-use-btn" id="siteseo-ai-titles" data-text="${title.trim()}">
								${use_icon_svg} Use This
							</button>
						</div>
					</div>`;
				}
			});

		} else {
			if(generate_title){
				let error_msg = titles < 1 ? 'No titles were generated' : '';
				title_html += `<div class="siteseo-ai-error">${error_msg}</div>`;
			}
		}
		
		if(descriptions.length > 0){
			// Count existing descriptions plus new ones
			let existingDescCount = $('.siteseo-ai-outputs-desc .siteseo-ai-item').length;
			desc_tab = $('.siteseo-ai-tab-btn[data-tab="siteseo-descriptions-tab"]');
			
			desc_tab.show().addClass('active');
			desc_tab.find('.siteseo-ai-tab-stat').text(existingDescCount + descriptions.length);
			
			descriptions.forEach(function(desc){
				if(desc.trim()){
					desc_html += `
					<div class="siteseo-ai-item">
						<div class="siteseo-ai-item-content">${desc.trim()}</div>
						<div class="siteseo-ai-item-actions">
							<button class="siteseo-ai-copy-btn" data-text="${desc.trim()}">
								${copy_icon_svg} Copy
							</button>
							<button class="siteseo-ai-use-btn" id="siteseo-ai-desc" data-text="${desc.trim()}">
								${use_icon_svg} Use This
							</button>
						</div>
					</div>`;
				}
			});
		} else {
			if(generate_desc){
				let error_msg = descriptions < 1 ? 'No descriptions were generated' : '';
				desc_html += `<div class="siteseo-ai-error">${error_msg}</div>`;
			}
		}
		
		$('.siteseo-ai-tabs').show(); // Show the tabs
		$('.siteseo-generate-animation').removeClass('siteseo-ai-shimmer');
		
		if(title_html){
			$('.siteseo-ai-outputs-titles').prepend(title_html);
		}
		
		if(desc_html){
			$('.siteseo-ai-outputs-desc').prepend(desc_html);
		}
		
		add_input_fields();

		if(generate_title || generate_desc){
			$('.siteseo-ai-tab-btn').off('click').on('click', function(){
				$('.siteseo-ai-tab-btn').removeClass('active');
				$(this).addClass('active');
				
				let tab_to_show = $(this).data('tab');
				$('.siteseo-ai-tab-content').removeClass('active');
				$('#' + tab_to_show).addClass('active');
			});
			
			// activate tab
			if(generate_title && generate_desc){
				$('.siteseo-ai-tab-btn[data-tab="siteseo-titles-tab"]').click();
			} else if(generate_title){
				$('.siteseo-ai-tab-btn[data-tab="siteseo-titles-tab"]').click();
			} else if (generate_desc){
				$('.siteseo-ai-tab-btn[data-tab="siteseo-descriptions-tab"]').click();
			}
		}

		//copy and use buttons reset
		$('.siteseo-ai-copy-btn').off('click').on('click', function(){
			if($(this).hasClass('siteseo-ai-copy-btn')){
				let text = $(this).data('text');
				navigator.clipboard.writeText(text).then(function(){
					let $btn = $(this);
					$btn.html(`${use_icon_svg} Copied!`);
					setTimeout(function(){
						$btn.html(`${copy_icon_svg} Copy`);
					}, 2000);
				}.bind(this));
			} 
		});
		
		$('.siteseo-ai-use-btn').on('click', function(){
			let text = $(this).data('text'),
				target = $(this).data('target'),
				context = $(this).closest('.siteseo-ai-modal-overlay').data('context');

			// For universal metabox
			let universal_metabox = document.querySelector('#siteseo-iframe-universal-metabox');
			if(universal_metabox && universal_metabox.contentWindow){
				let universal_metabox_doc = universal_metabox.contentWindow.document;
				let universal_jQuery = universal_metabox.contentWindow.jQuery;

				let actual_target;
				switch(context){
					case 'og':
						actual_target = 'siteseo_social_fb_title_meta';
						if($(this).attr('id') === 'siteseo-ai-desc') {
							actual_target = 'siteseo_social_fb_desc_meta';
						}
						break;
					case 'twitter':
						actual_target = 'siteseo_social_twitter_title_meta';
						if($(this).attr('id') === 'siteseo-ai-desc') {
							actual_target = 'siteseo_social_twitter_desc_meta';
						}
						break;
					default:
						actual_target = 'siteseo_titles_title_meta';
						if($(this).attr('id') === 'siteseo-ai-desc') {
							actual_target = 'siteseo_titles_desc_meta';
						}
				}

				let $universal_metabox_input = universal_jQuery(universal_metabox_doc).find('#' + actual_target);
				if($universal_metabox_input.length){
					$universal_metabox_input.val(text);
					$universal_metabox_input.trigger('input');
				}
			} else {
				// For regular metabox
				let $input = $('#' + target);
				if($input.length){
					$input.val(text).trigger('input');
				}
			}
				
			let $btn = $(this);
			$btn.html(`${use_icon_svg} Applied!`);
			setTimeout(function(){
				$btn.html(`${use_icon_svg} Use This`);
			}, 2000);
		});
	}
	
	function add_input_fields(){
		
		let $modal = $('.siteseo-ai-modal-open:visible');
		let	context = $modal.data('context');
		
		if(!context){
			let $modal = jQuery('.siteseo-ai-modal-open:visible');
			context = $modal.data('context');
		}
		
		let title_target, desc_target;
		switch(context){
			case 'og':
				title_target = 'siteseo_social_fb_title_meta';
				desc_target = 'siteseo_social_fb_desc_meta';
				break;
			case 'twitter':
				title_target = 'siteseo_social_twitter_title_meta';
				desc_target = 'siteseo_social_twitter_desc_meta';
				break;
			default:
				title_target = 'siteseo_titles_title_meta';
				desc_target = 'siteseo_titles_desc_meta';
		}
		
		// Update all title buttons
		$('.siteseo-ai-use-btn[id="siteseo-ai-titles"]').each(function(){
			$(this).data('target', title_target);
		});
		
		// Update all description buttons
		$('.siteseo-ai-use-btn[id="siteseo-ai-desc"]').each(function(){
			$(this).data('target', desc_target);
		});
	}
	
	function handle_error($button, error){
		$button.prop('disabled', false);
		$('.siteseo-ai-error-msg').html('<div class="siteseo-ai-error">Error: ' + error + '</div>');
		$('.siteseo-generate-animation').removeClass('siteseo-ai-shimmer');
	}

	// Tab switch
	$('.siteseo-tab').on('click', function(){
		$('.siteseo-tab').removeClass('active');
		$(this).addClass('active');
		
		let tab = $(this).data('tab');
		$('.siteseo-ai-tab-content').removeClass('active');
		$('.siteseo-ai-tab-content[data-tab="' + tab + '"]').addClass('active');
	});
	
});
