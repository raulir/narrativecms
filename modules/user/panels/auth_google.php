<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class auth_google extends \Controller {
	
	function panel_action($params){
		
		$id_token = $this->input->post('credential');
		
		$this->load->model('user/user_google_model');
		
		// Capture before clear so template can show journey-specific CTAs
		$params['auth_intent'] = $this->user_google_model->get_web_auth_intent();
		
		$payload = $this->user_google_model->verify_web_credential($id_token, $params['google_client_id'] ?? '');

		if (empty($payload)) {
			$params['error'] = 'google_error';
			$this->user_google_model->clear_web_auth_intent();
			return $params;
		}
		
		if ($params['auth_intent'] === 'register') {
			$result = $this->user_google_model->register_from_web_payload($payload);
		} else {
			$result = $this->user_google_model->login_from_web_payload($payload);
		}
		
		$this->user_google_model->clear_web_auth_intent();
		
		if (!empty($result['error'])) {
			$params['error'] = $result['error'];
			return $params;
		}
		
		$this->load->model('cms/cms_access_model');
		$this->cms_access_model->refresh_user_session($result['user']);

		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		$this->load->model('user/user_google_model');
		
		// GET without credential: still clear stuck intent; show result UI if error from action
		if (empty($params['auth_intent'])) {
			$params['auth_intent'] = $this->user_google_model->get_web_auth_intent();
		}
		
		$params['loggedin'] = $this->user_model->is_logged_in();
		$params['success_url'] = $this->user_model->get_user_redirect_url();
		
		if (!empty($params['error'])) {
			$message_key = 'message_'.$params['error'];
			$params['error_message'] = $params[$message_key] ?? $params['message_google_error'];
		}
				
		return $params;
	
	}
	
}
