<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class logout extends \Controller{
	
	function panel_action($params){
		
		$do = $params['cms_do'] ?? false;

		// The user, its access keys, and a new session id (the old id is worth nothing after logout)
		if ($do == 'logout' && isset($_SESSION['user'])){
			$this->load->model('cms/cms_access_model');
			$this->cms_access_model->_clear_user_session();
			cms_session_regenerate();
		}

		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		$params['success_url'] = $this->user_model->get_logout_redirect_url();
		
		return $params;
		
	}

}