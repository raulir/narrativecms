<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class register extends CI_Controller{
	
	function panel_action($params){
		
		$this->load->model('user/user_model');
		
		$do = $this->input->post('do');

		if ($do == 'register'){
			 
			$email = $this->input->post('email');
			$first_name = $this->input->post('first_name');
			$last_name = $this->input->post('last_name');
			$username = $this->input->post('username');
			$password = $this->input->post('password');
			$password2 = $this->input->post('password2');
			$phone = $this->input->post('phone');
				
			if ($password !== $password2){
				$error = 'password_mismatch';
			}
			
			if (empty($error)){
				
				$user = $this->user_model->create_user([
						'username' => $username,
						'email' => $email,
						'first_name' => $first_name,
						'last_name' => $last_name,
						'password' => $password,
						'phone' => $phone,
				]);
				
				if (!empty($user['error'])){
					$error = $user['error'];
				}
				
			}
			
		}
		
		if (!empty($error)){
			$params['error'] = $error;
		}

		return $params;
		
	}
	
	function panel_params($params){

		// check if logged in
		$params['loggedin'] = 0;

		return $params;
	
	}
	
}
