<?php
/*
* SITESEO
* https://siteseo.io
* (c) SiteSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

class AI{

	static function modal(){
		global $siteseo, $ai_languages;
		
		$tones = ['Formal', 'Informal', 'Creative', 'Persuasive', 'Casual', 'Confidence', 'Friendly', 'Inspirational', 'Motivational', 'Nostalgic', 'Playful', 'Professional', 'Scientific', 'Straightforward', 'Witty'];
		
		$audience = ['Bloggers', 'General Audience', 'Marketers', 'Developers', 'Writers', 'Seniors', 'Musicians', 'Healthcare', 'Educators', 'Students', 'Photographers', 'Foodies', 'Artists', 'Video Creators', 'Travelers', 'Professionals'];
		
		$ai_languages = [
			'en' => 'English (US)',
			'ar' => 'Arabic',
			'bg' => 'Bulgarian',
			'ca' =>	'Catalan',
			'zh-CN' => 'Chinese (Simplified)',
			'zh-TW' => 'Chinese (Traditional)',
			'hr' => 'Croatian',
			'cs' => 'Czech',
			'da' => 'Danish',
			'nl' => 'Dutch',
			'en-GB' => 'English (UK)',
			'fil' => 'Filipino',
			'fi' => 'Finnish',
			'fr' => 'French',
			'fr-CA' => 'French (Canadian)',
			'de' => 'German',
			'de-AT' => 'German (Austria)',
			'de-CH' => 'German (Switzerland)',
			'el' => 'Greek',
			'iw' => 'Hebrew',
			'hi' => 'Hindi',
			'hu' => 'Hungarain',
			'id' => 'Indonesian',
			'it' => 'Italian',
			'ja' => 'Japanese',
			'ko' => 'Korean',
			'lv' => 'Latvian',
			'lt' => 'Lithuanian',
			'no' => 'Norwegian',
			'fa' => 'Persian',
			'pl' => 'Polish',
			'pt' => 'Portuguese',
			'pt-BR' => 'Portuguese (Brazil)',
			'pt-PT' => 'Portuguese (Portugal)',
			'ro' => 'Romanian',
			'ru' => 'Russian',
			'sr' => 'Serbian',
			'sk' => 'Slovak',
			'sl' => 'Spanish',
			'es-419' => 'Spanish (Latin America)',
			'sv' => 'Swedish',
			'th' => 'Thai',
			'tr' => 'Turkish',
			'uk' => 'Ukrainian',
			'vi' => 'Vietnamese',
		];
		
		$selected_lang = 'en';
		
		$ai_tokens = get_option('siteseo_ai_tokens');
		
		echo'<div class="siteseo-ai-modal-overlay" id="siteseo-ai">
		<div class="siteseo-ai-modal">
			<div class="siteseo-ai-modal-header">
				<div class="siteseo-ai-header-content">
					<span class="siteseo-ai-modal-title">'.esc_html__('SiteSEO AI', 'siteseo-pro').'</span>
				</div>
				<button class="siteseo-ai-modal-close" id="siteseo-ai-close-modal" aria-label="Close modal">
					<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
			
			<div class="siteseo-ai-modal-body">
				<div class="siteseo-ai-modal-left">
				<form class="siteseo-ai-generate">
					<div class="siteseo-ai-form-group">
						<label class="siteseo-ai-label">'.esc_html__('Focus Keyword', 'siteseo-pro').'</label>
						<div class="siteseo-ai-input-container">
							<input type="text" class="siteseo-ai-input" placeholder="Main keyword..."/>
							<span class="siteseo-ai-note">'.esc_html__('Please enter a keyword with at least 4 characters.', 'siteseo-pro').'</span>
						</div>
					</div>

					<div class="siteseo-ai-form-group"><label class="siteseo-ai-label">'.esc_html__('Post Brief', 'siteseo-pro').'</label>
						<div class="siteseo-ai-input-container">
							<textarea class="siteseo-ai-input" rows="3" placeholder="Brief about your post..."></textarea>
							<span class="siteseo-ai-note">'.esc_html__('Please enter a post brief with more than 9 characters.', 'siteseo-pro').'</span>
						</div>
					</div>

					<div class="siteseo-ai-form-row">
						<div class="siteseo-ai-form-group"><label class="siteseo-ai-label">'.esc_html__('Tone', 'siteseo-pro').'</label>
							<div class="siteseo-ai-input-container">
								<select class="siteseo-ai-input">';
									foreach($tones as $tone){
										echo'<option>'.esc_html($tone).'</option>';
									}
									
								echo'</select>
							</div>
						</div>

						<div class="siteseo-ai-form-group"><label class="siteseo-ai-label">'.esc_html__('Audience', 'siteseo-pro').'</label>
							<div class="siteseo-ai-input-container">
							  <select class="siteseo-ai-input">';
								foreach($audience as $audien){
									echo'<option>'.esc_html($audien).'</option>';
								}
							  echo'</select>
							</div>
						</div>
					</div>

					<div class="siteseo-ai-form-row">
						<div class="siteseo-ai-form-group"><label class="siteseo-ai-label">'.esc_html__('Language', 'siteseo-pro').'</label>
							<div class="siteseo-ai-input-container">
								<select class="siteseo-ai-input" id="siteseo-ai-language-select">';
									foreach($ai_languages as $code => $label){
										$selected = ($code === $selected_lang) ? ' selected' : '';
										echo'<option value="'.esc_attr($code).'" '.esc_html($selected).'>'.esc_html($label) . '</option>';
									}
							  echo'</select>
							</div>
						</div>

						<div class="siteseo-ai-form-group"><label class="siteseo-ai-label">'.esc_html__('Generate', 'siteseo-pro').'</label>
							<div class="siteseo-ai-input-container">
								<label><input type="checkbox" name="generate_title"/>'.esc_html__('Title', 'siteseo-pro').'</label>
								<label><input type="checkbox" name="generate_desc"/>'.esc_html__('Description','siteseo-pro').'</label>
							</div>
						</div>
					</div>

					<div class="siteseo-ai-form-row">
						<label class="siteseo-ai-label">'.esc_html__('Outputs', 'siteseo-pro').'</label>
						<div class="siteseo-ai-input-container">
							<input type="number" min="1" max="5" value="2"/>
						</div>
					</div>
					<button class="siteseo-ai-generate-button"><span class="siteseo-ai-spinner"></span>'.esc_html__('Generate', 'siteseo-pro').'</button>
				</form>
				</div>
			  
				<div class="siteseo-ai-modal-right">
					<div class="siteseo-ai-output-heading">
						<svg class="siteseo-ai-output-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span>'.esc_html__('AI Suggestions', 'siteseo-pro').'</span>
						<div class="siteseo-ai-token-count" style="margin-left:auto;">';
							if(is_array($ai_tokens) && (!empty($ai_tokens['remaining_tokens']) || $ai_tokens['remaining_tokens'] > 0)){
								echo'<span class="siteseo-ai-token-badge">Tokens Remaining '.esc_html(number_format((int)$ai_tokens['remaining_tokens'])).
								(!empty($ai_tokens['remaining_tokens']) && $ai_tokens['remaining_tokens'] < 900 ? 
									'<br/> <a href="'.esc_url(SITESEO_PRO_AI_BUY.'&softwp_lic='.$siteseo->license['license']).'" target="_blank" class="siteseo-ai-buy-tokens">'.esc_html__('Buy AI Tokens', 'siteseo-pro').'</a>' : 
									''
								).'</span>
								<span class="dashicons dashicons-image-rotate siteseo-ai-refresh-tokens" id="siteseo-ai-refresh-tokens" title="Refresh tokens"></span>';
							}
						echo '</div>
					</div>
					<div class="siteseo-ai-output-box">
						<span class="siteseo-generate-animation">'.esc_html__('Generating.....', 'siteseo-pro').'</span>
						<span class="siteseo-ai-error-msg"></span>
						<div class="siteseo-ai-output-results">
							<div class="siteseo-ai-tabs" style="display:none;">
								<button class="siteseo-ai-tab-btn" data-tab="siteseo-titles-tab" style="display:none;">Titles (<span class="siteseo-ai-tab-stat">0</span>)</button>
								<button class="siteseo-ai-tab-btn" data-tab="siteseo-descriptions-tab" style="display:none;">Descriptions (<span class="siteseo-ai-tab-stat">0</span>)</button>
							</div>
							<div class="siteseo-ai-tab-content" id="siteseo-titles-tab">
								<div class="siteseo-ai-items-list">
									<div class="siteseo-ai-outputs-titles"></div>
								</div>
							</div>
							<div class="siteseo-ai-tab-content" id="siteseo-descriptions-tab">
								<div class="siteseo-ai-items-list">
									<div class="siteseo-ai-outputs-desc"></div>
								</div>
							</div>
						</div>
						<div class="siteseo-ai-snackbar"></div>
						<div class="siteseo-ai-placeholder">
								<svg class="siteseo-ai-placeholder-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M8 14C8 14 9.5 16 12 16C14.5 16 16 14 16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M9 9H9.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M15 9H15.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<p>'.esc_html__('Your generated SEO titles and description will appear here', 'siteseo-pro').'</p>
						</div>
					</div>
					<p class="description">'.sprintf(esc_html__('Got feedback on this AI implementation or SiteSEO? Let us know by submitting a  %1$sticket through our system%2$s', 'siteseo-pro'), '<a href="https://softaculous.deskuss.com/open.php?topicId=22" target="_blank">', '</a>').'</p>
				</div>
			</div>
		</div></div>';
	}

}
