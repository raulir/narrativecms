<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class password_change extends \Controller {

	function panel_action($params){

		$do = $params['cms_do'] ?? false;

		if ($do == 'save'){

			$this->load->model('user/user_model');

			$user = $this->user_model->get_current();
			if ($user === false || empty($user['cms_page_panel_id'])){
				$return['error'] = 'bad_save';
				return $return;
			}

			$password = $params['password'] ?? false;
			$password2 = $params['password2'] ?? false;

			// An account that has a password proves it first (an account made with Google has none)
			if ((string)($user['password'] ?? '') !== ''
					&& !$this->user_model->verify_password((string)($params['password_current'] ?? ''), $user['password'])){
				$this->user_model->auth_log('password_change_fail');
				$return['error'] = 'bad_current';
				return $return;
			}

			$validation_error = $this->user_model->validate_new_password($password, $password2);
			if ($validation_error !== ''){
				$return['error'] = $validation_error;
				return $return;
			}

			$user_id = (int)$user['cms_page_panel_id'];
			$this->user_model->change_user_password($user_id, $password);
			// This session takes the new password; the user's other sessions end (user_model::get_current)
			$this->load->model('cms/cms_access_model');
			$this->cms_access_model->refresh_user_session($this->user_model->get_user($user_id));
			$this->user_model->send_password_updated_email($user);

			return $params;

		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('user/user_model');

		// Settings saved before a field existed have it empty: the definition's default stands in
		$this->load->model('cms/cms_panel_model');
		$config = $this->cms_panel_model->get_cms_panel_config('user/password_change');
		foreach ($this->cms_panel_model->get_fields_defaults($config['settings'] ?? []) as $name => $default){
			if (!isset($params[$name]) || $params[$name] === ''){
				$params[$name] = $default;
			}
		}

		$current = $this->user_model->get_current();
		$params['loggedin'] = $current !== false;
		// The current-password row only for an account that has a password
		$params['has_password'] = $current !== false && (string)($current['password'] ?? '') !== '';

		return $params;

	}

}
