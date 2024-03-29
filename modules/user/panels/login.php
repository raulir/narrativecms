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
			
			$hashed_password = sha1((!empty($GLOBALS['settings']['salt']) ? $GLOBALS['settings']['salt'] : 'cms').$password);

			if ($hashed_password != $user['password']){
				$return['error'] = 'bad_password';
				return $return;
			}
		
			// if process reaches this, the login was success, put user data to session
			
			$_SESSION['user'] = $user;

			
		}
		
		if (!empty($error)){
			$params['error'] = $error;
		}

		return $params;
		
	}
	
	function panel_params($params){
		
		// check if logged in
		$params['loggedin'] = !empty($_SESSION['user']);

		return $params;
	
	}
	
}
