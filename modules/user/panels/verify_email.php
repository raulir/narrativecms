<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class verify_email extends \CI_Controller{
	
	function panel_action($params){
		
		$token = $this->input->get('token');
		
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
		
		$this->user_model->refresh_session_for_user_id($result['user_id']);
		
		$header_page_id = $this->user_model->get_logged_in_header_page_id();
		
		$params['verified'] = 1;
		$params['_no_cache'] = 1;
		
		if ($header_page_id){
			$params['_swap_header_page_id'] = $header_page_id;
		}
		
		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		$params['success_url'] = $this->user_model->get_user_redirect_url();
		
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