<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class auth_google extends CI_Controller{
	
	function panel_action($params){
		
		// web google auth disabled
		$params['error'] = 'google_error';
		return $params;
		
		/*
		$id_token = $this->input->post('credential');
		
		$this->load->model('user/user_google_model');
		
		$payload = $this->user_google_model->verify_web_credential($id_token, $params['google_client_id']);

		if (empty($payload)) {
			$params['error'] = 'google_error';
			return $params;
		}
		
		$result = $this->user_google_model->login_from_web_payload($payload);
		
		if (!empty($result['error'])) {
			$params['error'] = $result['error'];
			return $params;
		}
		
		$this->load->model('cms/cms_access_model');
		$this->cms_access_model->refresh_user_session($result['user']);

		return $params;
		*/
		
	}
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		
		$params['loggedin'] = $this->user_model->is_logged_in();
		$params['success_url'] = $this->user_model->get_user_redirect_url();
		
		if (!empty($params['error'])) {
			$message_key = 'message_'.$params['error'];
			if (!empty($params[$message_key])) {
				$params['error_message'] = $params[$message_key];
			} else {
				$params['error_message'] = !empty($params['message_google_error']) ? $params['message_google_error'] : 'Google sign-in failed';
			}
		}
				
		return $params;
	
	}
	
}