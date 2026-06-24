<?php defined('BASEPATH') OR exit('No direct script access allowed');

class user_google_model extends Model {
	
	function verify_web_credential($credential, $google_client_id) {
		
		if (empty($credential) || empty($google_client_id)) {
			return false;
		}
		
		require_once($GLOBALS['config']['base_path'].'vendor/autoload.php');
		
		Firebase\JWT\JWT::$leeway = 60;
		
		$client = new Google\Client(['client_id' => $google_client_id]);
		$payload = $client->verifyIdToken($credential);
		
		if (!($payload && !empty($payload['email_verified']))) {
			return false;
		}
		
		return $payload;
		
	}
	
	function login_from_web_payload($payload) {
		
		return $this->_login_or_create_user([
				'email' => $payload['email'],
				'google_id' => $payload['sub'] ?? '',
				'given_name' => $payload['given_name'] ?? '',
				'family_name' => $payload['family_name'] ?? '',
				'name' => $payload['name'] ?? '',
				'picture' => $payload['picture'] ?? '',
		]);
		
	}
	
	function login_from_app_profile($profile) {
		
		if (empty($profile['email'])) {
			return ['error' => 'missing_email'];
		}
		
		return $this->_login_or_create_user([
				'email' => $profile['email'],
				'google_id' => $profile['id'] ?? '',
				'given_name' => $profile['givenName'] ?? '',
				'family_name' => $profile['familyName'] ?? '',
				'name' => $profile['name'] ?? '',
				'picture' => $profile['imageUrl'] ?? '',
		]);
		
	}
	
	function _login_or_create_user($data) {
		
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_image_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$user = $this->user_model->get_user_by_email($data['email']);
		
		if (!empty($user) && !empty($user['show'])) {
			
			return ['user' => $this->_ensure_user_image($user, $data['picture'])];
			
		}
		
		if (!empty($user) && empty($user['show'])) {
			return ['error' => 'user_hidden'];
		}
		
		$image = '';
		if (!empty($data['picture'])) {
			$image = $this->cms_image_model->scrape_image($data['picture'], 'user', 'user');
		}
		
		$create_data = [
				'email' => $data['email'],
				'username' => $data['email'],
				'first_name' => $data['given_name'],
				'last_name' => $data['family_name'],
				'phone' => '',
				'meta' => [
						'google' => 1,
						'google_id' => $data['google_id'],
				],
				'password' => '',
		];
		
		$result = $this->user_model->create_user($create_data);
		
		if (!empty($result['errors'])) {
			return ['error' => reset($result['errors'])];
		}
		
		$user_id = $result['data']['user_id'] ?? $result['data']['cms_page_panel_id'] ?? 0;
		
		if (empty($user_id)) {
			return ['error' => 'create_failed'];
		}
		
		$user = $this->user_model->get_user($user_id);
		
		if (empty($user)) {
			return ['error' => 'create_failed'];
		}
		
		if (!empty($image)) {
			$this->cms_page_panel_model->update_cms_page_panel($user['cms_page_panel_id'], [
					'image' => $image,
			]);
			$user['image'] = $image;
		}
		
		return ['user' => $user];
		
	}
	
	function _ensure_user_image($user, $picture_url) {
		
		if (!empty($user['image']) || empty($picture_url)) {
			return $user;
		}
		
		$this->load->model('cms/cms_image_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$image = $this->cms_image_model->scrape_image($picture_url, 'user', 'user');
		$this->cms_page_panel_model->update_cms_page_panel($user['cms_page_panel_id'], [
				'image' => $image,
		]);
		$user['image'] = $image;
		
		return $user;
		
	}
	
}