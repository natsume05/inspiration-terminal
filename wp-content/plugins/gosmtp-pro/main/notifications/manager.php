<?php

namespace GOSMTP\Notifications;

class Manager{

	public $options = [];
	public $service = '';

	public function __construct(){

		//Load options
		$this->load_options();
	}

	public function load_options(){

		$options = get_option('gosmtp_options', []);
		$this->options = $options;
	}

	public function get_active_service(){

		if(!isset($this->options['notifications']['notification_service'])){
			return;
		}

		return $this->options['notifications']['notification_service'];
	}

	public function get_option($key, $service = '', $default = ''){

		$options = $this->options;

		if(!empty($service) && $service == $this->get_active_service()){
			$options = $this->options['notifications'];
		}

		return isset($options[$key]) ? $options[$key] : $default;
	}

	public function save_options($options){
		if(!method_exists($this, 'load_services_field')){
			return $options;
		}

		$fields = $this->load_services_field();

		foreach($fields as $key => $field){

			$val = '';

			if(!empty($_REQUEST[$this->service]) && isset($_REQUEST[$this->service][$key])){
				$val = sanitize_text_field($_REQUEST[$this->service][$key]);
			}
			$options[$key] = $val;
		}
		
		return $options;
	}
}
?>