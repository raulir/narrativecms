<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class register extends \Controller {
	
	function panel_action($params){
		
		$this->load->model('user/user_model');
		
		$do = $this->input->post('do');

		if ($do == 'register'){
			
			if ($this->user_model->is_logged_in()){
				$params['success'] = 1;
				$params['redirect_url'] = $this->user_model->get_user_redirect_url();
				return $params;
			}
			
			$errors = [];
			
			$fields = $this->input->post('fields');
			
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
			
			if (!empty($fields) && is_array($fields)){
				
				foreach ($fields as $field){
					
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
				
			}
			
			if ($password[1] !== $password[2]){
				$errors[] = 'password_mismatch';
			} else {
				$data['password'] = $password[1];
			}
			
			$errors = array_merge($errors, $this->_validate_register_fields($params, $data, $password, $meta));
			
			if (empty($errors)){
				
				$data['meta'] = $meta;
				
				$user = $this->user_model->create_user($data);
				
				if (!empty($user['errors'])){
					
					$errors = array_merge($user['errors'], $errors);
					
				} else {
					
					if (!empty($user['data']['user_id']) && !empty($data['email'])){
						$this->user_model->send_email_verification($user['data']['user_id']);
						// Welcome now only if login is allowed without confirm; otherwise after verify
						if (!$this->user_model->email_confirmation_required()){
							$this->user_model->send_registration_welcome_email($user['data']['user_id']);
						}
					}
					
					// log_in_after: "1" = Yes (string select). Skip session if email confirmation blocks login.
					if ((string)($params['log_in_after'] ?? '') === '1' && !empty($user['data']['user_id'])){
						
						$session_user = $this->user_model->get_user($user['data']['user_id']);
						
						if (!empty($session_user['cms_page_panel_id']) && $this->user_model->login_allowed($session_user)){
							$this->load->model('cms/cms_access_model');
							$this->cms_access_model->refresh_user_session($session_user);
						}
						
					}
					
					if (!empty($params['mailinglists']) && in_array('form', $GLOBALS['config']['modules'])){
						
						$this->load->model('cms/cms_page_panel_model');
						$this->load->model('form/form_model');
						
						$fparams = $this->cms_page_panel_model->get_cms_page_panel_settings('form/basic');
						
						if (!empty($fparams['add_mailchimp']) && !empty($fparams['mailchimp_api_key']) && !empty($fparams['mailchimp_list_id'])){
							$this->form_model->create_mailchimp_subscriber($data, $fparams);
						}
						
						if (!empty($fparams['add_cm']) && !empty($fparams['cm_api_key']) && !empty($fparams['cm_api_url']) && !empty($fparams['cm_list_id'])){
							$this->form_model->create_cm_subscriber($data, $fparams);
						}
						
					}
					
					$params['success'] = 1;
					$params['redirect_url'] = $this->user_model->get_user_redirect_url();
					
				}
				
			}
			
		}
		
		if (!empty($errors)){
			$params['errors'] = $errors;
		}

		return $params;
		
	}
	
	function _validate_register_fields($params, $data, $password, $meta){
		
		$errors = [];
		
		if (!empty($params['show_email']) && $params['show_email'] == 2 && $data['email'] === ''){
			$errors[] = 'bademail';
		}
		
		if (!empty($params['show_fullname']) && $params['show_fullname'] == 2){
			if ($data['first_name'] === '' || $data['last_name'] === ''){
				$errors[] = 'mandatory';
			}
		}
		
		if (!empty($params['show_username']) && $params['show_username'] == 2 && $data['username'] === ''){
			$errors[] = 'mandatory';
		}
		
		if ((int)($params['show_password'] ?? 0) > 0){

			if ($password[1] === ''){
				$errors[] = 'password_mismatch';
			} else {
				$min_length = (int)($params['password_min_length'] ?? 8);
				if (strlen((string)$password[1]) < $min_length){
					$errors[] = 'password_length';
				}
			}

		}
		
		if (!empty($params['fields']) && is_array($params['fields'])){
			
			foreach ($params['fields'] as $field){
				
				if (empty($field['mandatory']) || $field['mandatory'] !== 'yes'){
					continue;
				}
				
				$name = $field['name'] ?? '';
				
				if ($name === '' || !empty($meta[$name])){
					continue;
				}
				
				$errors[] = 'mandatory';
				
			}
			
		}
		
		return $errors;
		
	}
	
	function panel_params($params){

		$this->load->model('user/user_model');
		
		$params['loggedin'] = $this->user_model->is_logged_in();
		$params['success_url'] = $this->user_model->get_user_redirect_url();
		$params['progress_message'] = $this->user_model->get_progress_message();
		$this->user_model->enqueue_progress_overlay();
		
		return $params;
	
	}
	
}