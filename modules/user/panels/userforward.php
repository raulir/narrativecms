<?php 

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class userforward extends \CI_Controller{
	
	function panel_params($params){
		
		$this->load->model('user/user_model');

		// check if logged in
		$user = $this->user_model->get_current();

		if (!empty($user['user_id']) && !empty($params['status'])){

			header('Location: '.$GLOBALS['config']['base_url'].$params['link']['url'], true, 302);
			exit();

		} elseif (empty($user['user_id']) && empty($params['status'])) {
		
			header('Location: '.$GLOBALS['config']['base_url'].$params['link']['url'], true, 302);
			exit();
		
		}

		return $params;
	
	}
	
}
