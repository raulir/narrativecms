<?php 

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class auth_gapp extends \Controller{

	/**
	 * Signs in only from the app's own ajax post (shell.js app_post → ajax_api/get_panel) on an in-app
	 * backend, from one of our origins (user_model::request_from_own_site). A page render, with or
	 * without ?credential=, never signs in.
	 */
	function panel_action($params){

		if (!(get_instance() instanceof \Ajax_api) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
				|| empty($GLOBALS['config']['inapp']) || !isset($params['credential'])){
			return $params;
		}

		$this->load->model('user/user_model');
		if (!$this->user_model->request_from_own_site()){
			$params['error'] = 'bad_origin';
			return $params;
		}

		return $this->complete_login($params);

	}

	private function complete_login($params){

		$this->load->model('user/user_google_model');
		$this->load->model('cms/cms_page_panel_model');

		$credential = (string)($params['credential'] ?? '');
		$clientId = $this->user_google_model->web_client_id();
		error_log('GAPP credential_len='.strlen($credential).' client_len='.strlen($clientId));
		$payload = $this->user_google_model->verify_web_credential($credential, $clientId);

		if (empty($payload) || empty($payload['email'])) {
			$reason = $this->user_google_model->verify_error ?? '';
			if ($reason === '' && empty($payload['email'])) {
				$reason = 'no_email';
			}
			error_log('GAPP verify_fail='.$reason);
			$params['error'] = 'google_error'.($reason !== '' ? ':'.$reason : '');
			return $params;
		}

		$this->load->model('user/user_model');
		$existing = $this->user_model->get_user_by_email($payload['email']);
		$agree = (string)($params['agree'] ?? '');

		if (empty($existing) && $agree !== '1'){
			$params['needs_agreement'] = 1;
			$params['confirm'] = $this->confirm_text();
			return $params;
		}

		$result = $this->user_google_model->login_from_verified_app_payload($payload);

		if (!empty($result['error'])) {
			$params['error'] = $result['error'];
			return $params;
		}

		$this->load->model('cms/cms_access_model');
		$this->cms_access_model->refresh_user_session($result['user']);
		cms_session_regenerate();
		$params['login_success'] = 1;
		$params['email'] = $_SESSION['user']['email'] ?? ($result['user']['email'] ?? '');
		$params['session_id'] = session_id();

		return $params;

	}

	private function confirm_text(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('music/user_confirm');
		if (!is_array($settings)){
			$settings = [];
		}

		$link_url = 'privacy-policy/';
		if (!empty($settings['link']['url'])){
			$link_url = ltrim((string)$settings['link']['url'], '/');
		}

		return [
			'heading' => $this->confirm_value($settings, 'heading', 'Create account'),
			'text' => $this->confirm_value($settings, 'text', 'Creating an account stores your name, email, and practice progress, as described in the privacy policy.'),
			'link_text' => $this->confirm_value($settings, 'link_text', 'Full privacy policy'),
			'link_url' => $link_url,
			'agree' => $this->confirm_value($settings, 'agree', 'I agree'),
			'cancel' => $this->confirm_value($settings, 'cancel', 'Cancel'),
		];

	}

	private function confirm_value($settings, $name, $default){

		$value = trim((string)($settings[$name] ?? ''));

		return $value !== '' ? $value : $default;

	}

}