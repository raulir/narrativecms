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
		
		$params['verified'] = 1;
		
		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		$params['success_url'] = $this->user_model->get_user_redirect_url();
		
		if (!empty($params['error'])) {
			$message_key = 'message_'.$params['error'];
			if (!empty($params[$message_key])) {
				$params['error_message'] = $params[$message_key];
			}
		}
		
		return $params;
	
	}
	
}