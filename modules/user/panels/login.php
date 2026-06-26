<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class login extends \CI_Controller{
	
	function panel_action($params){
		
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$do = $this->input->post('do');

		if ($do == 'login'){
			 
			$username = $this->input->post('username');
			$password = $this->input->post('password');
			
			if (empty($username) || empty($password)){
				$return['error'] = 'missing_credential';
				return $return;
			}
			
			$config = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
			if (empty($config['show_username'])){
				$user = $this->user_model->get_user_by_email($username);
			} else {
				$user = $this->user_model->get_user_by_username($username);
			}

			if (empty($user) || !$user['show']){
				$return['error'] = 'bad_username';
				return $return;
			}
			
			if (!$this->user_model->verify_password($password, $user['password'])){
				$return['error'] = 'bad_password';
				return $return;
			}
			
			if (!$this->user_model->login_allowed($user)){
				$return['error'] = 'unverified_email';
				return $return;
			}
			
			if (strpos($user['password'], '$2y$') !== 0 && strpos($user['password'], '$argon2') !== 0){
				$this->user_model->upgrade_password_hash($user['cms_page_panel_id'], $password);
				$user = $this->user_model->get_user($user['cms_page_panel_id']);
			}
		
			$this->load->model('cms/cms_access_model');
			$this->cms_access_model->refresh_user_session($user);
			
		}
		
		if ($do == 'resend_verification'){
			
			$username = $this->input->post('username');
			$config = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
			
			if (empty($config['show_username'])){
				$user = $this->user_model->get_user_by_email($username);
			} else {
				$user = $this->user_model->get_user_by_username($username);
			}
			
			if (!empty($user['cms_page_panel_id']) && !$this->user_model->is_email_verified($user)){
				$this->user_model->send_email_verification($user['cms_page_panel_id']);
			}
			
			$return['resent'] = 1;
			return $return;
			
		}

		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		
		$params['loggedin'] = !empty($_SESSION['user']);
		$params['success_url'] = $this->user_model->get_user_redirect_url();

		return $params;
	
	}
	
}