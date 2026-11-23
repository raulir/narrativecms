<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class login extends \Controller{
	
	function panel_action($params){
		
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$do = $params['cms_do'] ?? false;

		if ($do == 'login'){
			 
			$username = trim((string)($params['username'] ?? ''));
			$password = (string)($params['password'] ?? '');

			if ($username === '' || $password === ''){
				$return['error'] = 'missing_credential';
				return $return;
			}

			// Only from our own pages: another site can't sign a visitor in to an account of its choosing
			if (!$this->user_model->request_from_own_site()){
				return ['error' => 'bad_origin'];
			}

			$config = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
			if (empty($config['show_username'])){
				$username = $this->user_model->normalise_email($username);
			}

			// 10 failures for one login name or 20 from one address in 15 minutes: no more tries for now
			$name_key = 'name:'.mb_strtolower($username);
			$ip_key = 'ip:'.$this->user_model->_client_address();
			if ($this->user_model->attempts_over('login_fail', $name_key, 10, 900) || $this->user_model->attempts_over('login_fail', $ip_key, 20, 900)){
				$this->user_model->auth_log('login_limited');
				return ['error' => 'too_many'];
			}

			if (empty($config['show_username'])){
				$user = $this->user_model->get_user_by_email($username);
			} else {
				$user = $this->user_model->get_user_by_username($username);
			}

			// An unknown user and a wrong password answer the same, so nobody learns which accounts exist
			if (empty($user) || !$user['show'] || !$this->user_model->verify_password($password, $user['password'])){
				$this->user_model->attempt_note('login_fail', $name_key, 900);
				$this->user_model->attempt_note('login_fail', $ip_key, 900);
				$this->user_model->auth_log('login_fail');
				$return['error'] = 'bad_password';
				return $return;
			}
			
			if (!$this->user_model->login_allowed($user)){
				$return['error'] = 'unverified_email';
				return $return;
			}
			
			if (strpos($user['password'], '$2y$') !== 0 && strpos($user['password'], '$argon2') !== 0){
				$this->user_model->upgrade_password_hash($user['cms_page_panel_id'], $password);
				$user = $this->user_model->get_user($user['cms_page_panel_id']);
			}
		
			$this->load->model('cms/cms_access_model');
			$this->cms_access_model->refresh_user_session($user);
			cms_session_regenerate();

			if (!empty($GLOBALS['config']['inapp'])){
				$params['login_success'] = 1;
				$params['email'] = $user['email'] ?? $username;
				$params['session_id'] = session_id();
			}

		}
		
		if ($do == 'resend_verification'){
			
			$username = trim((string)($params['username'] ?? ''));
			$config = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
			if (empty($config['show_username'])){
				$username = $this->user_model->normalise_email($username);
			}

			// A mail each time: 3 an hour per login name, 10 per client address
			$name_key = 'name:'.mb_strtolower($username);
			$ip_key = 'ip:'.$this->user_model->_client_address();
			if ($this->user_model->attempts_over('resend', $name_key, 3, 3600) || $this->user_model->attempts_over('resend', $ip_key, 10, 3600)){
				$this->user_model->auth_log('resend_limited');
				return ['error' => 'too_many'];
			}
			$this->user_model->attempt_note('resend', $name_key);
			$this->user_model->attempt_note('resend', $ip_key);

			if ($username === ''){
				$user = [];
			} else if (empty($config['show_username'])){
				$user = $this->user_model->get_user_by_email($username);
			} else {
				$user = $this->user_model->get_user_by_username($username);
			}
			
			if (!empty($user['cms_page_panel_id']) && !$this->user_model->is_email_verified($user)){
				$this->user_model->send_email_verification($user['cms_page_panel_id']);
			}
			
			$return['resent'] = 1;
			return $return;
			
		}

		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('user/user_model');
		
		$params['loggedin'] = $this->user_model->is_logged_in();
		$params['success_url'] = $this->user_model->get_user_redirect_url();

		// Web: alternatives from CMS (link to /login-google/ GSI).
		// Inapp: same repeater shown as .login_app_google in login.tpl.php — do not clear either path.
		// Post-login resume for checkout: subscription extends this panel (user_login).

		return $params;
	
	}
	
}