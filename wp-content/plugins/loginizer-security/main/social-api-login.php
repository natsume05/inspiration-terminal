<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT');
}

if(!defined('LOGINIZER_DIR')){
	die('Loginizer plugin not activated');
}

if(!file_exists(LOGINIZER_DIR . '/lib/hybridauth/autoload.php') || !file_exists(LOGINIZER_DIR . '/main/social-base.php')){
	wp_die('Please update Loginizer plugin to use this feature');
}

include_once LOGINIZER_DIR . '/lib/hybridauth/autoload.php';
include_once LOGINIZER_DIR . '/main/social-base.php';

use Hybridauth\Exception\Exception;
use Hybridauth\Hybridauth;
use Hybridauth\HttpClient;
use Hybridauth\Storage\Transient;

class LoginizerPro_Social_API extends Loginizer_Social_Base{
	public static $auth_api = 'https://auth.loginizer.com/';
	private static $fb_api_base = 'https://graph.facebook.com/v8.0/';
	
	// Login using the Softaculous API
	static function api_auth(){
		global $loginizer, $lz_error;

		try {

			if(!is_ssl()){
				throw new Exception('Your website is not on HTTPS');
			}

			if(empty($loginizer['license']) || empty($loginizer['license']['license']) || empty($loginizer['license']['active'])){
				throw new Exception('License is inactive');
			}
			
			if(!loginizer_can_login()){
				if(!empty($lz_error) && !empty($lz_error['ip_blocked'])){
					throw new Exception($lz_error['ip_blocked']);
				}
				
				throw new Exception(__('Your IP is blocked', 'loginizer-security'));
			}

			self::$storage = new Transient();

			// Setting which nonce to use
			$lz_social_nonce = '';
			$nonce_name = 'loginizer_social_check';
			if(empty($_GET['state']) && !empty($_GET['social_security'])){
				$lz_social_nonce = sanitize_text_field(wp_unslash($_GET['social_security']));
			} elseif(!empty($_GET['state']) && !empty($_GET['nonce'])){
				$lz_social_nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
				$nonce_name = 'loginizer_social_api';
			} elseif(!empty($_POST['nonce'])){
				$lz_social_nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
				$nonce_name = 'loginizer_social_api';
			}

			// Security verification
			if(!wp_verify_nonce($lz_social_nonce, $nonce_name)){
				self::$error['security-check'] = __('Security check failed when trying to login', 'loginizer-security');
				self::$storage->clear();
				self::trigger_error();
				return;
			}

			if(!empty($_GET['error'])){
				self::$ref = self::$storage->get('ref');
				$error = sanitize_text_field(wp_unslash($_GET['error']));
				self::$error['login-error'] = esc_html($error);
				self::$storage->clear();
				self::trigger_error();
			}

			// Making sure if the social provider is enabled or not.
			$provider = sanitize_text_field(wp_unslash($_REQUEST['lz_social_provider']));
			$providers = get_option('loginizer_provider_settings', []);
			if(empty($providers) || empty($providers[$provider]) || empty($providers[$provider]['enabled']) && !empty($providers[$provider]['loginizer_social_key'])){
				self::$error['no-provider'] = esc_html__('Social Provider is either empty or not enabled', 'loginizer-security');
				self::$storage->clear();
				self::trigger_error();
			}

			// Ref is the base URL from where the user is attemting to login.
			if(!empty($_REQUEST['ref']) && wp_http_validate_url(sanitize_url(wp_unslash($_REQUEST['ref'])))){
				self::$storage->set('ref', rawurlencode(sanitize_url(wp_unslash($_REQUEST['ref']))));
			}

			if(isset($_REQUEST['interim-login'])){
				self::$storage->set('interim_login', 'lz');
			}

			// Step 1: Get URL to redirect and do the redirect.
			if(empty($_GET['state'])){
				// Setting the provider to the user session.
				if(!empty($_GET['lz_social_provider'])){
					$provider = lz_optget('lz_social_provider');
					self::$storage->set('provider', $provider);
				}
				
				$res = wp_remote_post(self::$auth_api . '?action=get', [
					'timeout' => 8,
					'body' => [
						'url' => site_url(),
						'license' => $loginizer['license']['license'],
						'callback' => wp_login_url()
					]
				]);

				if(empty($res)){
					throw new Exception(__('Auth token response came empty', 'loginizer-security'));
				}

				if(is_wp_error($res)){
					// Retry in case the request times out.
					$res = wp_remote_post(self::$auth_api . '?action=get', [
						'timeout' => 10,
						'body' => [
							'url' => site_url(),
							'license' => $loginizer['license']['license'],
							'callback' => wp_login_url()
						]
					]);
					
					if(empty($res)){
						throw new Exception(__('Auth token response came empty', 'loginizer-security'));
					}
					
					if(is_wp_error($res)){
						throw new Exception($res->get_error_message());
					}
				}

				$res_code = wp_remote_retrieve_response_code($res);
		
				if($res_code != 200){
					$request_body = wp_remote_retrieve_body($res);

					if(!empty($request_body)){
						$request_body = self::decode_json($request_body, 'Error Corrupted');

						if(!empty($request_body['error'])){
							throw new Exception(esc_html($request_body['error']) . ': '. esc_html($res_code));
						}			
					}

					throw new Exception(__('Unexpected HTTP response ', 'loginizer-security') . esc_html($res_code));
				}

				$request_body = wp_remote_retrieve_body($res);

				if(empty($request_body)){
					throw new Exception(__('Empty response body received', 'loginizer-security'));
				}

				$lo_key = json_decode($request_body, true);

				if(empty($lo_key)){
					throw new Exception(__('Response does not contain required key', 'loginizer-security'));
				}

				$lo_key = sanitize_text_field(wp_unslash($lo_key));

				$query_params = [
					'lo_key' => $lo_key,
					'nonce' => wp_create_nonce('loginizer_social_api'),
					'provider' => $provider,
					'action' => 'verification',
				];

				$api = self::$auth_api .'?'. http_build_query($query_params);

				wp_redirect(sanitize_url($api));
				die();
			}

			// Step 2: Getting the access token
			if($_GET['state'] == 'finish'){
				if(empty($_POST['access_token'])){
					throw new Exception(__('Empty access_token was returned!', 'loginizer-security'));
				}

				$access_token = sanitize_text_field($_POST['access_token']);
				$provider = self::$storage->get('provider');

				if(empty($provider) && !empty($_GET['lz_social_provider'])){
					$provider = sanitize_text_field(wp_unslash($_GET['lz_social_provider']));
				}
				
				if(self::$storage->get('ref')){
					self::$ref = self::$storage->get('ref');
				}

				// Verifying user details.
				if(self::api_auth_validate($access_token, $provider)){
					//self::$storage->clear();
					self::close_tab();
					return;
				}

				//self::$storage->clear();
				if(!empty(self::$error)){
					self::trigger_error();
				}

				self::close_tab();
				return;
			}

		} catch(\Exception $e){
			self::$error['login_error'] = __('Something went wrong: ', 'loginizer-security') . $e->getMessage();

			if(!empty(self::$storage)){
				self::$storage->clear();
			}

			self::trigger_error();
			//wp_safe_redirect(wp_login_url());
			return;
		}
	}

	// Verifying the user data
	static function api_auth_validate($access_token, $provider){
		global $loginizer;

		if(empty($provider)){
			throw new Exception(__('Empty Provider.', 'loginizer-security'));
		}
		
		switch($provider){
			case 'Google':
				$access_url = 'https://www.googleapis.com/oauth2/v3/userinfo/';
				break;

			case 'LinkedInOpenID':
				$access_url = 'https://api.linkedin.com/v2/userinfo';
				break;

			case 'GitHub':
				$access_url = 'https://api.github.com/user/emails';
				break;
				
			case 'Facebook':
				$access_url = 'https://graph.facebook.com/v8.0/me?fields=email,first_name,last_name,id';
				break;
				
			case 'MicrosoftGraph':
				$access_url = 'https://graph.microsoft.com/v1.0/me';
				break;
				
			case 'Twitter':
				$access_url = 'https://api.twitter.com/2/users/me?user.fields=id,name,username,confirmed_email,profile_image_url';
				break;
		}
		
		if(empty($access_url)){
			throw new Exception(__('Invalid Provider.', 'loginizer-security'));
		}

		$res = wp_remote_get($access_url, [
			'headers' => array(
				'Authorization' => 'Bearer '.$access_token,
			),
		]);

		if(empty($res)){
			throw new Exception(__('Auth token response came empty', 'loginizer-security'));
		}
		
		if(is_wp_error($res)){
			throw new Exception($res->get_error_message());
		}
		
		// Checking the response code
		$res_code = wp_remote_retrieve_response_code($res);
		
		if($res_code != 200){
			// Retriving any error if any
			$request_body = wp_remote_retrieve_body($res);
			
			if(!empty($request_body)){
				$request_body = self::decode_json($request_body, 'Error Corrupted');
				
				if(!empty($request_body['error'])){
					throw new Exception(esc_html($request_body['error']) . ': '. esc_html($res_code));
				}			
			}

			throw new Exception(__('Unexpected HTTP response from ', 'loginizer-security') . esc_html(ucfirst($provider)) . ': '. esc_html($res_code));
		}

		$user_info = wp_remote_retrieve_body($res);

		if(empty($user_info)){
			throw new Exception(__('Empty response body received', 'loginizer-security'));
		}

		$user_info = self::decode_json($user_info, __('Verification Failed', 'loginizer-security'));
	
		// GitHub share email on seperate end point than other user data.
		// And in that email data it share secondary email as well, so primary email is located at 0 index.
		if($provider == 'GitHub'){
			if(empty($user_info[0]) || empty($user_info[0]['email'])){
				throw new Exception(ucfirst($provider) . ' ' . __('response missing required field: email.', 'loginizer-security'));
			}

			$user_info = $user_info[0];
		}
		
		// Every Provider has different index to share same data, so we need to parse it to use it in a standard way.
		$user_data = self::parse_data($user_info, $provider);

		if(empty($user_data)){
			throw new Exception(__('No user info provided by the provider.', 'loginizer-security'));
		}
		
		if(empty($user_data['email'])){
			throw new Exception(ucfirst($provider) . ' ' . __('response missing required field: email.', 'loginizer-security'));
		}
		
		if(empty($user_data['email_verified'])){
			throw new Exception(__('Email you are trying to login is not verified.', 'loginizer-security'));
		}
		
		if(empty(email_exists($user_data['email']))){

			if(!empty($loginizer['social_settings']['general']['register_new'])){
				// GitHub need seperate request to get User data as we only request email in the first Request
				// So we only request user data when needed, thats while user registration.
				if($provider == 'GitHub'){
					$user_data = self::get_github_user($user_data['email'], $access_token);
				}

				self::register_account($user_data);
				echo 'Register Acount';
				return;
			}

			throw new Exception(__('No user found with this email.', 'loginizer-security'));
		}

		$user = get_user_by('email', $user_data['email']);
		
		if(empty($user)){
			throw new Exception(__('No user found with this account.', 'loginizer-security'));
		}
		
		// Authenticating
		$authenticated = loginizer_wp_authenticate($user, $user->user_login, $user->user_pass);
		if(is_wp_error($authenticated)){
			return;
		}

		return self::login_user($user);

	}
	
	static function decode_json($string, $error_state){
		$json = json_decode($string, true);
		
		if(JSON_ERROR_NONE != json_last_error()){
			throw new Exception('Invalid JSON response: ' . json_last_error_msg());
		}

		return $json;
	}
	
	/* 
	 * Formats the json data in the Array format and indexes we need
	 * Array Format we need to maintain is:
	 * [
	 *	'first_name' => '',
	 *	'last_name' => '',
	 *	'email' => '',
	 *	'email_verified' => '',
	 *	'photoURL' => '',
	 * ]
	 */
	protected static function parse_data($data, $provider){
		
		switch($provider){
			case 'Facebook':
				return self::parse_facebook($data);
				
			case 'Google':
				return self::parse_google($data);
				
			case 'GitHub':
				return self::parse_github($data);
				
			case 'LinkedInOpenID':
				return self::parse_google($data); // LinkedIn has same structure as Google
				
			case 'MicrosoftGraph':
				return self::parse_microsoft($data);
			
			case 'Twitter':
				return self::parse_twitter($data);
		}
		
		return [];
	}
	
	protected static function parse_google($data){
		$user_profile = [
			'email' => !empty($data['email']) ? sanitize_email(wp_unslash($data['email'])) : '',
			'first_name' => !empty($data['given_name']) ? sanitize_text_field(wp_unslash($data['given_name'])) : '',
			'last_name' => !empty($data['family_name']) ? sanitize_text_field(wp_unslash($data['family_name'])) : '',
			'photoURL' => !empty($data['picture']) ? sanitize_url(strtok($data['picture'], '?')) : '',
			'email_verified' => !empty($data['email_verified']) ? sanitize_text_field(wp_unslash($data['email_verified'])) : '',
		];
		
		return $user_profile;
	}
	
	protected static function parse_facebook($data){
		$user_profile = [
			'email' => !empty($data['email']) ? sanitize_email(wp_unslash($data['email'])) : '',
			'first_name' => !empty($data['first_name']) ? sanitize_text_field(wp_unslash($data['first_name'])) : '',
			'last_name' => !empty($data['last_name']) ? sanitize_text_field(wp_unslash($data['last_name'])) : '',
			'email_verified' => isset($data['email']), // Facebook only gives email if it is verified
		];

		$user_profile['photoURL'] = self::$fb_api_base . $data['id'];
        $user_profile['photoURL'] .= '/picture?width=150&height=150';
		$user_profile['photoURL'] = sanitize_url(wp_unslash($user_profile['photoURL']));

		return $user_profile;
	}
	
	protected static function parse_github($data){
		$user_profile = [
			'email' => !empty($data['email']) ? sanitize_email(wp_unslash($data['email'])) : '',
			'email_verified' => !empty($data['verified']) ? sanitize_text_field(wp_unslash($data['verified'])) : '',
		];
		
		return $user_profile;
	}
	
	protected static function parse_microsoft($data){
		
		$email = !empty($data['mail']) ? sanitize_email(wp_unslash($data['mail'])) : '';

		if(empty($email)){
			$possible_email = $data['userPrincipalName'];
			if(strpos($possible_email, '@') !== false){
				$email = $possible_email;
			}
		}

		$user_profile = [
			'email' => $email,
			'first_name' => !empty($data['givenName']) ? sanitize_text_field(wp_unslash($data['givenName'])) : '',
			'last_name' => !empty($data['surname']) ? sanitize_text_field(wp_unslash($data['surname'])) : '',
			'email_verified' => !empty($email),
		];

		return $user_profile;
	}
	
	protected static function parse_twitter($data){
		
		$data = $data['data'];

		$email = !empty($data['confirmed_email']) ? sanitize_email(wp_unslash($data['confirmed_email'])) : '';

		$user_profile = [
			'email' => $email,
			'first_name' => !empty($data['username']) ? sanitize_text_field(wp_unslash($data['username'])) : '',
			'last_name' => '',
			'email_verified' => !empty($email),
			'photoURL' => !empty($data['profile_image_url']) ? sanitize_url(wp_unslash($data['profile_image_url'])) : '',
		];

		return $user_profile;
	}
	
	protected static function get_github_user($email, $access_token){
		$user_api = 'https://api.github.com/user';
		
		$res = wp_remote_get($user_api, [
			'headers' => array(
				'Authorization' => 'Bearer '.$access_token,
			),
		]);

		if(empty($res)){
			throw new Exception(__('GitHub user data response empty', 'loginizer-security'));
		}
		
		if(is_wp_error($res)){
			throw new Exception($res->get_error_message());
		}
		
		$res_code = wp_remote_retrieve_response_code($res);
		
		if($res_code != 200){
			$request_body = wp_remote_retrieve_body($res);
			
			if(!empty($request_body)){
				$request_body = self::decode_json($request_body, 'Error message malformed');
				
				if(!empty($request_body['error'])){
					throw new Exception(esc_html($request_body['error']) . ': '. esc_html($res_code));
				}			
			}

			throw new Exception(__('Unexpected HTTP response from ', 'loginizer-security') . esc_html('GitHub User API') . ': '. esc_html($res_code));
		}

		$user_info = wp_remote_retrieve_body($res);

		if(empty($user_info)){
			throw new Exception(__('Empty response body received', 'loginizer-security'));
		}

		$user_info = self::decode_json($user_info, __('User data malformed', 'loginizer-security'));

		$user_data = [
			'email' => $email,
			'photoURL' => !empty($user_info['avatar_url']) ? sanitize_url($user_info['avatar_url']) : '',
			'first_name' => !empty($user_info['login']) ? sanitize_text_field($user_info['login']) : '',
			'last_name' => '',
		];

		return $user_data;
		
	}
}

LoginizerPro_Social_API::api_auth();