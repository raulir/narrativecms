<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class verify_email extends \Controller{
	
	function panel_action($params){
		
		$token = $params['token'] ?? false;
		
		if (empty($token)){
			$params['error'] = 'missing_token';
			return $params;
		}
		
		$this->load->model('user/user_model');
		$result = $this->user_model->verify_email_token($token);
		
		if (!empty($result['error'])){
			$params['error'] = $result['error'];
			return $params;
		}
		
		$params['verified'] = 1;
		$params['_no_cache'] = 1;

		// Signs in only in the browser that registered or asked for the mail (user_model::verify_mark),
		// with a new session id; elsewhere the email is confirmed and Continue goes to the login page
		if (empty($result['same_browser'])){
			$params['signed_in'] = 0;
			return $params;
		}

		$this->user_model->refresh_session_for_user_id($result['user_id']);
		cms_session_regenerate();
		unset($_SESSION['user_verify_mark']);
		$params['signed_in'] = 1;

		$header_page_id = $this->user_model->get_logged_in_header_page_id();

		if ($header_page_id){
			$params['_swap_header_page_id'] = $header_page_id;
		}

		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		// Confirmed in another browser: not signed in here, so Continue leads to the login page
		$params['success_url'] = (isset($params['signed_in']) && empty($params['signed_in']) && !$this->user_model->is_logged_in())
				? $this->user_model->get_login_redirect_url()
				: $this->user_model->get_user_redirect_url();
		
		if (empty($params['message_success'])){
			$params['message_success'] = 'Your email has been confirmed.';
		}
		
		if (empty($params['continue_text'])){
			$params['continue_text'] = 'Continue';
		}
		
		if (!empty($params['error'])) {
			$message_key = 'message_'.$params['error'];
			if (!empty($params[$message_key])) {
				$params['error_message'] = $params[$message_key];
			}
		}
		
		return $params;
	
	}
	
}