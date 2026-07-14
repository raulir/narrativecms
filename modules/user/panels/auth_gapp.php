<?php 

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class auth_gapp extends \Controller{
	
	function panel_params($params){
		
		$this->load->model('user/user_google_model');
		
		if (empty($params['result']['profile'])) {
			$params['error'] = 'missing_profile';
			return $params;
		}
		
		$result = $this->user_google_model->login_from_app_profile($params['result']['profile']);
		
		if (!empty($result['error'])) {
			$params['error'] = $result['error'];
			return $params;
		}
		
		$_SESSION['user'] = $result['user'];
		$params['login_success'] = 1;

		return $params;
	
	}
	
}