<?php

namespace GOSMTP\mailer\gmail;

class Auth{
	
	private $client_id;
	private $client_secret;
	private $redirect_uri;
	private $state;
	private $options;
	private $accessTokenMethod = 'POST';
	
	public function __construct($client_id = '', $client_secret = '', $redirect_uri = '', $state = ''){
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->redirect_uri = $redirect_uri;
		$this->state = $state;
		$this->options = $this->getConfig();
	}
 
	private function getConfig(){
		return array(
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret,
			'redirect_uri' => $this->redirect_uri,
			'urlAuthorize' => 'https://accounts.google.com/o/oauth2/auth',
			'urlAccessToken' => 'https://www.googleapis.com/oauth2/v4/token',
			'urlResourceOwnerDetails' => '',
			'scopes' => 'https://www.googleapis.com/auth/gmail.compose',
			'access_type' => 'offline',
			'include_granted_scopes' => 'true',
			'approval_prompt' => 'force',
			'state' => $this->state,
		);
	}
	
	public function setClientId($client_id){
		$this->client_id= $client_id;
		return true;
	}

	public function setClientSecret($client_secret){
		$this->client_secret = $client_secret;
		return true;
	}

	public function add_scope($scope){
		if(is_array($scope)){
			$separator = ',';
			$this->options['scope'] = implode($separator, $scope);
			return true;
		}
		$this->options['scope'] = $scope;
		return true;
	}

	public function set_access_type($access_type){
		$this->options['access_type'] = $access_type;
		return true;
	}
	
	public function set_approval_prompt($approval_prompt){
		$this->options['approval_prompt'] = $approval_prompt;
		return true;
	}
	
	public function getAuthUrl(){
		return $this->getAuthorizationUrl();
	}
	
	public function getAuthorizationUrl($options = []){
		$base = $this->options['urlAuthorize'];

		$params = $this->getAuthorizationParameters($options);
		$query  = http_build_query($params, null, '&', \PHP_QUERY_RFC3986);

		return $this->appendQuery($base, $query);
	}

	private function getAuthorizationParameters($options){

		if(empty($options['scope'])){
			$options['scope'] = $this->options['scopes'];
		}

		$options += [
			'access_type' => $this->options['access_type'],
			'include_granted_scopes' => $this->options['include_granted_scopes'],
			'response_type'   => 'code',
			'state' => $this->getRandomState().$this->options['state'],
			'approval_prompt' => $this->options['approval_prompt'],
			
		];

		if(is_array($options['scope'])){
			$separator = ',';
			$options['scope'] = implode($separator, $options['scope']);
		}

		// Store the state as it may need to be accessed later on.
		$this->options['state'] = $options['state'];

		// Business code layer might set a different redirect_uri parameter
		// depending on the context, leave it as-is
		if(!isset($options['redirect_uri'])){
			$options['redirect_uri'] = $this->options['redirect_uri'];
		}

		$options['client_id'] = $this->options['client_id'];

		return $options;
	}
	
	protected function getRandomState($length = 32){
		// Converting bytes to hex will always double length. Hence, we can reduce
		// the amount of bytes by half to produce the correct length. 
		$state = bin2hex(random_bytes($length / 2));

		update_option('_gosmtp_last_generated_state', $state);

		return $state;
	}
	
	protected function appendQuery($url, $query){
		$query = trim($query, '?&');

		if($query){
			$glue = strstr($url, '?') === false ? '?' : '&';
			return $url . $glue . $query;
		}

		return $url;
	}
	
	public function sendTokenRequest($type, $params){
		try {
			$tokens = $this->getAccessToken($type, $params);
			return $tokens;
		} catch (\Exception $exception) {
			return new \WP_Error(423, $exception->getMessage());
		}
	}
	
	/**
	* Requests an access token using a specified grant and option set.
	*
	* @param  mixed $grant
	* @param  array $options
	* @throws \Exception
	* @return array tokens
	*/
	public function getAccessToken($grant, array $options = []){
		$params = [
			'client_id' => $this->options['client_id'],
			'client_secret' => $this->options['client_secret'],
			'redirect_uri'  => $this->options['redirect_uri'],
			'grant_type' => $grant,
		];

		$params += $options;

		$requestData = $this->getAccessTokenRequestDetails($params);


		$response = wp_remote_request($requestData['url'], $requestData['params']);

		if(is_wp_error($response)) {
			throw new \Exception(
				$response->get_error_message()
			);
			return $response;
		}

		$responseBody = wp_remote_retrieve_body($response);
	

		if(false === is_array($response)){
			throw new \Exception(
				'Invalid response received from Authorization Server. Expected JSON.'
			);
		}

		if(empty(['access_token'])){
			throw new \Exception(
				'Invalid response received from Authorization Server.'
			);
		}

		return \json_decode($responseBody, true);
	}

	/**
	* Returns a prepared request for requesting an access token.
	*
	* @param array $params Query string parameters
	* @return array $requestDetails
	*/
	protected function getAccessTokenRequestDetails($params){
		$method  = $this->accessTokenMethod;
		$url	 = $this->options['urlAccessToken'];
		$options = http_build_query($params, null, '&', \PHP_QUERY_RFC3986);

		return [
			'url' => $url,
			'params' => [
				'method' => $method,
				'body' => $options,
				'headers' => [
					'content-type' => 'application/x-www-form-urlencoded'
				]
			]
		];
	}

}