<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class auth_google extends CI_Controller{
	
	function panel_action($params){
		
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
		
	}
	
	function panel_params($params){
				
		return $params;
	
	}
	
}