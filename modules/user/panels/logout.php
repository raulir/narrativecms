<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class logout extends Controller{
	
	function panel_action($params){
		
		$do = $this->input->post('do');

		if ($do == 'logout' && isset($_SESSION['user'])){
			unset($_SESSION['user']);
		}

		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		$params['success_url'] = $this->user_model->get_logout_redirect_url();
		
		return $params;
		
	}

}