<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class register extends CI_Controller{
	
	function panel_action($params){
		
		$this->load->model('user/user_model');
		
		$do = $this->input->post('do');

		if ($do == 'register'){
			
			$errors = [];
			
			$fields = $this->input->post('fields');
			
//			_print_r($fields);
			
			$data = [
					'email' => '',
					'image' => '',
					'username' => '',
					'first_name' => '',
					'last_name' => '',
					'phone' => '',
					'meta' => [],
					'password' => '',
			];
			
			$meta = [];
			$password = [
					1 => '',
					2 => '',
			];
			
			foreach($fields as $field){
				
				if ($field['id'] == 'email'){
					$data['email'] = trim($field['value']);
				} else if ($field['id'] == 'image'){
					$data['image'] = trim($field['value']);
				} else if ($field['id'] == 'username'){
					$data['username'] = trim($field['value']);
				} else if ($field['id'] == 'first_name'){
					$data['first_name'] = trim($field['value']);
				} else if ($field['id'] == 'last_name'){
					$data['last_name'] = trim($field['value']);
				} else if ($field['id'] == 'password'){
					$password[1] = $field['value'];
				} else if ($field['id'] == 'password2'){
					$password[2] = $field['value'];
				} else if ($field['id'] == 'phone'){
					$data['phone'] = trim($field['value']);
				} else {
					if (preg_match('/^[a-z][a-z0-9]*$/', $field['id'])){
						$meta[$field['id']] = $field['value'];
					}
				}
				
			}
			
			// password match
			if ($password[1] !== $password[2]){
				$errors []= 'password_mismatch';
			} else {
				$data['password'] = $password[1];
			}
			
			if (empty($errors)){
				
				$user = $this->user_model->create_user($data);
				
				if (!empty($user['errors'])){

					$errors = array_merge($user['errors'], $errors);
				
				} else {
				
					if (!empty($mailinglists) && in_array('form', $GLOBALS['config']['modules'])){
						
						$this->load->model('form/form_model');
						
						$fparams = $this->cms_page_panel_model->get_cms_page_panel_settings('form/basic');
						
						$subscription_data = [
								'email' => $email,
								'name' => $first_name.' '.$last_name,
						];
						
						if (!empty($fparams['add_mailchimp']) && !empty($fparams['mailchimp_api_key']) && !empty($fparams['mailchimp_list_id'])){
							$result = $this->form_model->create_mailchimp_subscriber($data, $fparams);
						}
							
						if (!empty($fparams['add_cm']) && !empty($fparams['cm_api_key']) && !empty($fparams['cm_api_url']) && !empty($fparams['cm_list_id'])){
							$result = $this->form_model->create_cm_subscriber($data, $fparams);
						}
	
					}
				
				}
			
			}
			
		}
		
		if (!empty($errors)){
			$params['errors'] = $errors;
		}

		return $params;
		
	}
	
	function panel_params($params){

		// check if logged in
		$params['loggedin'] = !empty($_SESSION['user']);
		
		return $params;
	
	}
	
}
