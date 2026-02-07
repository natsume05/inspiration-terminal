<?php
/*
* SITESEO
* https://siteseo.io
* (c) SITSEO Team
*/

namespace SiteSEOPro;

if(!defined('ABSPATH')){
	die('Hacking Attempt !');
}

class VideoSitemap{
	
	private static $paged = 1;

	static function display_metabox(){
		global $post;
		
		$allowed_suggestion_tags = array(
			'button' => array(
				'class' => array(),
				'type' => array(),
			),
			'span' => array(
				'id' => array(),
				'class' => array(),
			),
			'div' => array(
				'class' => array(),
				'style' => array(),
			),
			'input' => array(
				'type' => array(),
				'class' => true,
				'placeholder' => true,
			)
		);
		
		$exclude_post = !empty(get_post_meta($post->ID, '_siteseo_exclude_post')) ? get_post_meta($post->ID, '_siteseo_exclude_post', true) : '';
		$video_title = !empty(get_post_meta($post->ID, '_siteseo_video_title', true)) ? get_post_meta($post->ID, '_siteseo_video_title', true) : '';
		$video_description = !empty(get_post_meta($post->ID, '_siteseo_video_description', true)) ? get_post_meta($post->ID, '_siteseo_video_description', true) : '';
		$video_thumbnail = !empty(get_post_meta($post->ID, '_siteseo_video_thumbnail', true)) ? get_post_meta($post->ID, '_siteseo_video_thumbnail', true) : '';
		$video_duration = !empty(get_post_meta($post->ID, '_siteseo_video_duration', true)) ? get_post_meta($post->ID, '_siteseo_video_duration', true) : '';
		$video_rating = !empty(get_post_meta($post->ID, '_siteseo_video_rating', true)) ? get_post_meta($post->ID, '_siteseo_video_rating', true) : '';
		
		echo'<div class="siteseo-metabox-option-wrap">
				<div class="siteseo-metabox-label-wrap">
					<label for="siteseo_exclude_post">'.esc_html__('Exclude this post', 'siteseo-pro').'</label>
				</div>
				<div class="siteseo-metabox-input-wrap">
					<input type="checkbox" name="siteseo_exclude_post" '.(!empty($exclude_post) ? 'checked' : '').'/>'.esc_html__('Exclude this post form video sitemap', 'siteseo-pro').'
				</div>
			</div>
			
			<div class="siteseo-metabox-option-wrap">
				<div class="siteseo-metabox-label-wrap">
					<label for="siteseo_video_title">'.esc_html__('Video Title:', 'siteseo-pro').'</label>
				</div>
				<div class="siteseo-metabox-input-wrap">
					<input type="text" id="siteseo_titles_title_meta" class="siteseo_titles_title_meta" name="siteseo_video_title" value="'.esc_attr($video_title) .'" class="widefat">
					<span class="description"></span>
					<div class="siteseo-metabox-tags">
						<button type="button" class="siteseo-metabox-tag" data-tag="%%post_title%%"><span class="dashicons dashicons-plus"></span> Post Title</button>
						<button type="button" class="siteseo-metabox-tag" data-tag="%%sitetitle%%"><span class="dashicons dashicons-plus"></span> Site Title</button>
						<button type="button" class="siteseo-metabox-tag" data-tag="%%sep%%"><span class="dashicons dashicons-plus"></span>Seperator</button>'.wp_kses(siteseo_suggestion_button_metabox(), $allowed_suggestion_tags).'
					</div>
				</div>
			</div>
			
			<div class="siteseo-metabox-option-wrap">
				<div class="siteseo-metabox-label-wrap">
					<label for="siteseo_video_description">'.esc_html__('Video Description', 'siteseo-pro').'</label>
				</div>
				<div class="siteseo-metabox-input-wrap">
					<textarea id="siteseo_titles_desc_meta" name="siteseo_video_description" class="widefat" rows="3">'.esc_textarea($video_description) .'</textarea>
					<div class="siteseo-metabox-tags">
						<button type="button" class="siteseo-metabox-tag" data-tag="%%post_excerpt%%">
						<span class="dashicons dashicons-plus"></span>Post Excerpt</button>'.wp_kses(siteseo_suggestion_button_metabox(), $allowed_suggestion_tags).'
					</div>
				</div>
			</div>
			
			<div class="siteseo-metabox-option-wrap">
				<div class="siteseo-metabox-label-wrap">
					<label for="siteseo_video_thumbnail">'.esc_html__('Thumbnail Video:', 'siteseo-pro').'</label>
				</div>
				<div class="siteseo-metabox-input-wrap">
					<input type="url" id="siteseo_video_thumbnail" name="siteseo_video_thumbnail" value="'.esc_attr($video_thumbnail).'" class="widefat">
					<button type="button" class="button siteseo-video-thumbnail-upload">'.esc_html__('Upload Thumbnail', 'siteseo-pro').'</button>
				</div>
			</div>
			
			<div class="siteseo-metabox-option-wrap">
				<div class="siteseo-metabox-label-wrap">
					<label for="siteseo_video_duration">'.esc_html__('Duration (seconds):', 'siteseo-pro').'</label>
				</div>
				<div class="siteseo-metabox-input-wrap">
					<input type="number" name="siteseo_video_duration" value="'.esc_attr($video_duration).'" min="0">
					<span class="description">'.esc_html__('Video duration in seconds (e.g., 180 for 3 minutes)', 'siteseo-pro').'</span>
				</div>
			</div>
			
			<div class="siteseo-metabox-option-wrap">
				<div class="siteseo-metabox-label-wrap">
					<label for="siteseo_video_rating">'.esc_html__('Video Rating', 'siteseo-pro').'</label>
				</div>
				<div class="siteseo-metabox-input-wrap">
					<input type="number" name="siteseo_video_rating" value="'.esc_attr($video_rating).'" >
					<span class="description">'.esc_html__('Allowed values are float numbers in the range 0.0 to 5.0.', 'siteseo-pro').'</span>
				</div>
			</div>';
	}
	
	static function save_video_sitemap($post_id, $post){
		
		if(!isset($_POST['siteseo_metabox_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['siteseo_metabox_nonce']), 'siteseo_metabox_nonce')){
			return $post_id;
		}

		//Post type object
		$post_type = get_post_type_object($post->post_type);

		//Check permission
		if(!current_user_can($post_type->cap->edit_post, $post_id)){
			return $post_id;
		}
		
		if(isset($_POST['siteseo_exclude_post'])){
			update_post_meta($post_id, '_siteseo_exclude_post', sanitize_text_field($_POST['siteseo_exclude_post']));
		} else{
			delete_post_meta($post_id, '_siteseo_exclude_post');
		}
		
		if(isset($_POST['siteseo_video_title'])){
			update_post_meta($post_id, '_siteseo_video_title', sanitize_text_field($_POST['siteseo_video_title']));
		} else{
			delete_post_meta($post_id, '_siteseo_video_title');
		}
		
		if(isset($_POST['siteseo_video_description'])){
			update_post_meta($post_id, '_siteseo_video_description', sanitize_text_field($_POST['siteseo_video_description']));
		} else{
			delete_post_meta($post_id, '_siteseo_video_description');
		}
		
		if(isset($_POST['siteseo_video_thumbnail'])){
			update_post_meta($post_id, '_siteseo_video_thumbnail', sanitize_text_field($_POST['siteseo_video_thumbnail']));
		} else{
			delete_post_meta($post_id, '_siteseo_video_thumbnail');
		}
		
		if(isset($_POST['siteseo_video_duration'])){
			update_post_meta($post_id, '_siteseo_video_duration', sanitize_text_field($_POST['siteseo_video_duration']));
		} else{
			delete_post_meta($post_id, '_siteseo_video_duration');
		}
		
		if(isset($_POST['siteseo_video_rating'])){
			update_post_meta($post_id, '_siteseo_video_rating', sanitize_text_field($_POST['siteseo_video_rating']));
		} else{
			delete_post_meta($post_id, '_siteseo_video_rating');
		}		
	}

	static function render_sitemap(){
		global $siteseo;
		
		if(empty($siteseo->pro['toggle_state_video_sitemap']) || empty($siteseo->pro['enable_video_sitemap'])){
			return;
		}
		
		$selected_post_types = isset($siteseo->pro['video_sitemap_posts']) ? $siteseo->pro['video_sitemap_posts'] : [];
		
		header('Content-Type: application/xml; charset=utf-8');
		
		$offset = (1000*(self::$paged - 1));

		if(get_option('permalink_structure')){
			$xsl_url = home_url('/sitemaps.xsl');
		} else{
			$xsl_url = home_url('/?sitemaps-stylesheet=sitemap');
		}

    echo'<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="'.esc_url($xsl_url).'" ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';

		if(!empty($selected_post_types)){
    
			$args = [
				'post_type' => $selected_post_types,
				'post_status' => 'publish',
				'numberposts' => 1000,
				'offset' => $offset,
				'meta_query' => [
					[
						'key' => '_siteseo_robots_index',
						'compare' => 'NOT EXISTS'
					],
					[
						'key' => '_siteseo_video_disabled',
						'compare' => 'NOT EXISTS'
					]
				]
			];

			$posts = get_posts($args);

			foreach($posts as $post){
				
				$exclude_post = !empty(get_post_meta($post->ID, '_siteseo_exclude_post', true)) ? get_post_meta($post->ID, '_siteseo_exclude_post', true) : '';
				
				if(!empty($exclude_post)){
					continue;
				}
				
				$video_urls = [];
				
				$post_content = $post->post_content;
				
				preg_match_all('#(https?://)?(www\.)?(youtube|youtu|youtube-nocookie)\.(com|be)/(watch\?v=|embed/|v/|.+\?v=)?([^&"\'\s]+)#i', $post_content, $youtube_matches);
				if(!empty($youtube_matches[6])){
					foreach($youtube_matches[6] as $video_id){
						$video_urls[] = 'https://www.youtube.com/watch?v=' . $video_id;
					}
				}
				
				preg_match_all('#(https?://)?(www\.)?vimeo\.com/([0-9]+)#i', $post_content, $vimeo_matches);
				if(!empty($vimeo_matches[3])){
					foreach($vimeo_matches[3] as $video_id){
						$video_urls[] = 'https://vimeo.com/' . $video_id;
					}
				}
				
				//(mp4, webm, etc.)
				preg_match_all('#https?://[^\s\'"]+\.(mp4|webm|ogg|mov|avi|wmv|flv)#i', $post_content, $direct_matches);
				if(!empty($direct_matches[0])){
					$video_urls = array_merge($video_urls, $direct_matches[0]);
				}
				
				$video_urls = array_unique($video_urls);
				
				if(empty($video_urls)){
					continue;
				}
				
				$post_title = get_the_title($post->ID);
				$post_excerpt = get_the_excerpt($post->ID);
				$post_thumbnail = get_the_post_thumbnail_url($post->ID, 'medium');
				
				
				$video_thumbnail = !empty(get_post_meta($post->ID, '_siteseo_video_thumbnail', true)) ? get_post_meta($post->ID, '_siteseo_video_thumbnail', true) : ($post_thumbnail ? $post_thumbnail : '');
				
				$get_video_title = !empty(get_post_meta($post->ID, '_siteseo_video_title', true)) ? get_post_meta($post->ID, '_siteseo_video_title', true) : $post_title;
				
				$video_title =  \SiteSEO\TitlesMetas::replace_variables($get_video_title);
				
				$get_video_description = !empty(get_post_meta($post->ID, '_siteseo_video_description', true)) ? get_post_meta($post->ID, '_siteseo_video_description', true) : (!empty($post_excerpt) ? $post_excerpt : $post_title);
				
				$video_description = \SiteSEO\TitlesMetas::replace_variables($get_video_description);
				
				$video_duration = !empty(get_post_meta($post->ID, '_siteseo_video_duration', true)) ? get_post_meta($post->ID, '_siteseo_video_duration', true) : '';
				
				$video_rating = !empty(get_post_meta($post->ID, '_siteseo_video_rating', true)) ? get_post_meta($post->ID, '_siteseo_video_rating', true) : '';
				
				echo "\t".'<url>
					<loc>'.esc_url(urldecode(get_permalink($post->ID))).'</loc>
					<lastmod>'.esc_html(get_the_modified_date('c', $post->ID)).'</lastmod>';
				
				foreach($video_urls as $video_url){
					
					if(empty($video_url)) {
						continue;
					}
					
					echo'<video:video>';
					
					if(!empty($video_thumbnail)){
						echo'<video:thumbnail_loc>'.esc_url($video_thumbnail).'</video:thumbnail_loc>';
					}

					echo'<video:title>'.esc_xml($video_title).'</video:title>';
					
					echo'<video:description>'.esc_xml($video_description).'</video:description>';
					
					if(strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false){
						echo'<video:player_loc allow_embed="yes" autoplay="ap=1">'.esc_url($video_url).'</video:player_loc>';
					} else{
						echo'<video:content_loc>'.esc_url($video_url).'</video:content_loc>';
					}
					
					if(!empty($video_duration)){
						echo'<video:duration>'.esc_xml($video_duration).'</video:duration>';
					}
					
					if(!empty($video_rating)){
						echo'<video:rating>'.esc_xml($video_rating).'</video:rating>';
					}
					
					echo'</video:video>';
				}
				
				echo'</url>';
			}
		}
		
		echo '</urlset>';
		exit;    
	}
	
	static function render_video_xsl(){
		$video_thumbnail_txt = __('Video', 'siteseo-pro');
		$video_title_txt = __('Video Title', 'siteseo-pro');
		$video_description_txt = __('Description', 'siteseo-pro');
		$video_duration_txt = __('Duration', 'siteseo-pro');
		$video_url_txt = __('Video URL', 'siteseo-pro');

		return '<!-- Video Sitemap -->
		<thead>
			<tr>
				<th>'.esc_xml($video_thumbnail_txt).'</th>
				<th>'.esc_xml($video_title_txt).'</th>
				<th>'.esc_xml($video_description_txt).'</th>
				<th>'.esc_xml($video_duration_txt).'</th>
				<th>'.esc_xml($video_url_txt).'</th>
			</tr>
		</thead>
		<tbody>
			<xsl:for-each select="sitemap:urlset/sitemap:url/video:video">
				<tr>
					<td>
						<xsl:if test="video:thumbnail_loc">
						<img class="siteseo-video-thumbnail" src="{video:thumbnail_loc}" alt="Video Thumbnail"/>
						</xsl:if>
					</td>
					<td>
						<div class="siteseo-video-title"><xsl:value-of select="video:title"/></div>
						<div class="siteseo-video-meta">
							<xsl:if test="video:publication_date">
								Published: <xsl:value-of select="video:publication_date"/>
							</xsl:if>
							<xsl:if test="video:duration">
								| Duration: <xsl:value-of select="video:duration"/> sec
							</xsl:if>
						</div>
					</td>
					<td>
						<div class="siteseo-video-description">
							<xsl:value-of select="video:description"/>
						</div>
					</td>
					<td>
						<xsl:if test="video:duration">
							<xsl:value-of select="video:duration"/> sec
						</xsl:if>
					</td>
					<td>
						<div class="siteseo-video-url">
							<xsl:choose>
								<xsl:when test="video:player_loc">
									<a href="{video:player_loc}" target="_blank">Watch Video</a>
								</xsl:when>
								<xsl:when test="video:content_loc">
									<a href="{video:content_loc}" target="_blank">Video File</a>
								</xsl:when>
								<xsl:otherwise>
									No URL available
								</xsl:otherwise>
							</xsl:choose>
						</div>
					</td>
				</tr>
			</xsl:for-each>
		</tbody>';
	}
}
