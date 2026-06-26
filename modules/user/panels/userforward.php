<?php 

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class userforward extends \CI_Controller{
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		$user = $this->user_model->get_current();

		if (!empty($user['user_id'])){
			_position_link_redirect($this->user_model->get_user_redirect_url());
		}

		return $params;
	
	}
	
}