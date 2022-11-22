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
			$params['user_image'] = !empty($user['image']) ? $user['image'] : $params['icon_loggedin'] ?? 'user/user_loggedin.png';
			
			if (empty($params['display_name']) || $params['display_name'] == 'username'){
				$params['user_name'] = $user['username'];
			} else if ($params['display_name'] == 'email'){
				$params['user_name'] = $user['email'];
			} else if ($params['display_name'] == 'full'){
				$params['user_name'] = $user['first_name'].' '.$user['last_name'];
			} else if ($params['display_name'] == 'first'){
				$params['user_name'] = $user['first_name'];
			}
			
		} else {
		
			$params['loggedin'] = 0;
		}
		
		
		return $params;
	
	}
	
}
