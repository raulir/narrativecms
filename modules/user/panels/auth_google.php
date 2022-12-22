<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once('vendor/autoload.php');

class auth_google extends CI_Controller{
	
	function panel_action($params){
		
		Firebase\JWT\JWT::$leeway = 60;
		
		// $id_token = $this->input->post('g_csrf_token');
		$id_token = $this->input->post('credential');
		
		$client = new Google\Client(['client_id' => $params['google_client_id']]);  // Specify the CLIENT_ID of the app that accesses the backend
		$payload = $client->verifyIdToken($id_token);

		if (!($payload && $payload['email_verified'])) {
			$params['error'] = 'google_error';
			return $params;
		}

		$this->load->model('user/user_model');
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_image_model');
		
		// check if user exists
		
		$user = $this->user_model->get_user_by_email($payload['email']);
		
		if (!(empty($user) || !$user['show'])){
			
			if (empty($user['image'])){
				$image = $this->cms_image_model->scrape_image($payload['picture'], 'user', 'user');
				$this->cms_page_panel_model->update_cms_page_panel($user['cms_page_panel_id'], [
						'image' => $image,
				]);
				$user['image'] = $image;
			}
			
			$_SESSION['user'] = $user;

		} else {
			
			// create a new user
			
			$image = $this->cms_image_model->scrape_image($payload['picture'], 'profile', 'profile');

			$data = [
					'email' => $payload['email'],
					'image' => $image,
					'username' => $payload['email'],
					'first_name' => $payload['given_name'],
					'last_name' => $payload['family_name'],
					'phone' => '',
					'meta' => ['google' => 1],
					'password' => '',
			];
			
			$user = $this->user_model->create_user($data);
			
			$_SESSION['user'] = $user;

		}

		return $params;
		
	}
	
	function panel_params($params){
				
		return $params;
	
	}
	
}
