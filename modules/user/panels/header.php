<?php 

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class header extends \CI_Controller{
	
	function panel_params($params){
		
		$this->load->model('user/user_model');

		// check if logged in
		
		$user = $this->user_model->get_current();
		
		if (!empty($user['user_id'])){
			
			$params['loggedin'] = 1;
			$params['user_image'] = !empty($user['image']) ? $user['image'] : $params['icon_loggedin'];
			$params['user_name'] = $user['email'];
			
		} else {
		
			$params['loggedin'] = 0;
		}
		
		
		return $params;
	
	}
	
}
