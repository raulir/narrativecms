<?php

namespace user;

defined('BASEPATH') OR exit('No direct script access allowed');

class user_google_model extends \Model {

	/**
	 * Website GSI journey: set before showing login_google / register_google.
	 * Cleared in auth_google after handling the credential.
	 */
	function set_web_auth_intent($mode) {

		$mode = ($mode === 'register') ? 'register' : 'login';
		$_SESSION['google_auth_intent'] = $mode;

	}

	function get_web_auth_intent() {

		$mode = $_SESSION['google_auth_intent'] ?? 'login';

		return ($mode === 'register') ? 'register' : 'login';

	}

	function clear_web_auth_intent() {

		unset($_SESSION['google_auth_intent']);

	}
	
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
	
	/**
	 * Website GSI login: existing active users only.
	 */
	function login_from_web_payload($payload) {
		
		return $this->_resolve_web_user([
				'email' => $payload['email'] ?? '',
				'google_id' => $payload['sub'] ?? '',
				'given_name' => $payload['given_name'] ?? '',
				'family_name' => $payload['family_name'] ?? '',
				'name' => $payload['name'] ?? '',
				'picture' => $payload['picture'] ?? '',
		], 'login');
		
	}
	
	/**
	 * Website GSI register: create new user only; existing email → emailexists (no session).
	 */
	function register_from_web_payload($payload) {
		
		return $this->_resolve_web_user([
				'email' => $payload['email'] ?? '',
				'google_id' => $payload['sub'] ?? '',
				'given_name' => $payload['given_name'] ?? '',
				'family_name' => $payload['family_name'] ?? '',
				'name' => $payload['name'] ?? '',
				'picture' => $payload['picture'] ?? '',
		], 'register');
		
	}
	
	/**
	 * Native app: may create account on first Google sign-in.
	 */
	function login_from_app_profile($profile) {
		
		if (empty($profile['email'])) {
			return ['error' => 'missing_email'];
		}
		
		return $this->_resolve_web_user([
				'email' => $profile['email'],
				'google_id' => $profile['id'] ?? '',
				'given_name' => $profile['givenName'] ?? '',
				'family_name' => $profile['familyName'] ?? '',
				'name' => $profile['name'] ?? '',
				'picture' => $profile['imageUrl'] ?? '',
		], 'app');
		
	}
	
	/**
	 * @param array $data profile fields
	 * @param string $mode login|register|app
	 */
	function _resolve_web_user($data, $mode = 'login') {
		
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_image_model');
		$this->load->model('cms/cms_page_panel_model');
		
		if (empty($data['email'])) {
			return ['error' => 'google_error'];
		}
		
		$user = $this->user_model->get_user_by_email($data['email']);
		
		if (!empty($user) && !empty($user['show'])) {
			
			if ($mode === 'register') {
				return ['error' => 'emailexists'];
			}
			
			return ['user' => $this->_ensure_user_image($user, $data['picture'] ?? '')];
			
		}
		
		if (!empty($user) && empty($user['show'])) {
			return ['error' => 'user_hidden'];
		}
		
		// No user row yet
		if ($mode === 'login') {
			return ['error' => 'not_registered'];
		}
		
		// register or app: create
		return $this->_create_user_from_google_data($data);
		
	}
	
	/**
	 * Display name from Google given_name. Collisions are fine when email is login name.
	 * If username is login name, create_user still enforces uniqueness/length.
	 */
	function _username_from_google_data($data) {

		$username = trim((string)($data['given_name'] ?? ''));

		if ($username === '') {
			$email = (string)($data['email'] ?? '');
			$at = strpos($email, '@');
			$username = $at !== false ? substr($email, 0, $at) : $email;
		}

		return $username;

	}

	function _create_user_from_google_data($data) {
		
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_image_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$image = '';
		if (!empty($data['picture'])) {
			$image = $this->cms_image_model->scrape_image($data['picture'], 'user', 'user');
		}
		
		$create_data = [
				'email' => $data['email'],
				'username' => $this->_username_from_google_data($data),
				'first_name' => $data['given_name'] ?? '',
				'last_name' => $data['family_name'] ?? '',
				'phone' => '',
				'meta' => [
						'google' => 1,
						'google_id' => $data['google_id'] ?? '',
				],
				'password' => '',
				'email_verified' => 'yes',
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

		// Google email is already verified — welcome immediately
		$this->user_model->send_registration_welcome_email($user_id);
		
		return ['user' => $user];
		
	}
	
	function _ensure_user_image($user, $picture_url) {
		
		if (!empty($user['image']) || empty($picture_url)) {
			return $user;
		}
		
		$this->load->model('cms/cms_image_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$image = $this->cms_image_model->scrape_image($picture_url, 'user', 'user');
		if ($image === '' || $image === false) {
			return $user;
		}
		
		$this->cms_page_panel_model->update_cms_page_panel($user['cms_page_panel_id'], [
				'image' => $image,
		]);
		$user['image'] = $image;
		
		return $user;
		
	}
	
}
