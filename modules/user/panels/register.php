<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class register extends \Controller {

	/** Longest value kept per form field (characters). Extra fields not listed here: 500. */
	const FIELD_CAPS = ['email' => 254, 'username' => 100, 'first_name' => 100, 'last_name' => 100, 'phone' => 50];

	function panel_action($params){

		$this->load->model('user/user_model');

		$do = $params['cms_do'] ?? false;

		if ($do == 'register'){

			if ($this->user_model->is_logged_in()){
				$params['success'] = 1;
				$params['redirect_url'] = $this->user_model->get_user_redirect_url();
				return $params;
			}

			// Only from our own pages: another site can't make an account and sign the visitor in to it
			if (!$this->user_model->request_from_own_site()){
				return ['errors' => ['bad_origin']];
			}

			// 5 new accounts an hour from one address
			$ip_key = 'ip:'.$this->user_model->_client_address();
			if ($this->user_model->attempts_over('register', $ip_key, 5, 3600)){
				$this->user_model->auth_log('register_limited');
				return ['errors' => ['too_many']];
			}

			// The form's rules come only from the stored block that shows it, never from the post
			$rules = $this->_register_rules($params['cms_page_panel_id'] ?? 0);
			if ($rules === null){
				error_log_user('user/register: refused a post without a shown user/register block');
				return ['errors' => ['bad_form']];
			}

			$errors = [];

			// The posted form (register.js); "fields" is the block's Extra fields repeater
			$fields = $params['register_fields'] ?? false;

			$data = [
				'email' => '',
				'username' => '',
				'first_name' => '',
				'last_name' => '',
				'phone' => '',
				'meta' => [],
				'password' => '',
			];

			$meta = [];
			$password = [
				1 => '',
				2 => '',
			];

			$extra_names = [];
			foreach ((array)($rules['fields'] ?? []) as $extra){
				$name = is_array($extra) ? trim((string)($extra['name'] ?? '')) : '';
				if (preg_match('/^[a-z][a-z0-9_]*$/', $name)){
					$extra_names[$name] = 1;
				}
			}

			if (!empty($fields) && is_array($fields)){

				foreach ($fields as $field){

					if (!is_array($field)){
						continue;
					}
					$id = (string)($field['id'] ?? '');
					$raw = is_scalar($field['value'] ?? null) ? (string)$field['value'] : '';

					if ($id === 'password' || $id === 'password2'){
						$password[$id === 'password' ? 1 : 2] = mb_substr($raw, 0, 200);
						continue;
					}

					if (!isset(self::FIELD_CAPS[$id]) && !isset($extra_names[$id])){
						continue;
					}
					$value = mb_substr(trim(cms_utf8_string(strip_tags($raw))), 0, self::FIELD_CAPS[$id] ?? 500);

					if (isset(self::FIELD_CAPS[$id])){
						$data[$id] = $value;
					} else {
						$meta[$id] = $value;
					}

				}

			}

			$data['email'] = $this->user_model->normalise_email($data['email']);
			if ($data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false){
				$errors[] = 'bademail';
			}

			$errors = array_merge($errors, $this->_validate_register_fields($rules, $data, $password, $meta));

			if ($password[1] !== '' && empty($errors)){
				$data['password'] = $password[1];
			}

			if (empty($errors)){

				$data['meta'] = $meta;

				$user = $this->user_model->create_user($data);

				if (!empty($user['errors'])){

					$errors = array_merge($user['errors'], $errors);

				} else {

					$this->user_model->attempt_note('register', $ip_key);

					if (!empty($user['data']['user_id']) && !empty($data['email'])){
						$this->user_model->send_email_verification($user['data']['user_id']);
						// Welcome now only if login is allowed without confirm; otherwise after verify
						if (!$this->user_model->email_confirmation_required()){
							$this->user_model->send_registration_welcome_email($user['data']['user_id']);
						}
					}

					// log_in_after: "1" = Yes (string select). Skip session if email confirmation blocks login.
					if ((string)($rules['log_in_after'] ?? '') === '1' && !empty($user['data']['user_id'])){

						$session_user = $this->user_model->get_user($user['data']['user_id']);

						if (!empty($session_user['cms_page_panel_id']) && $this->user_model->login_allowed($session_user)){
							$this->load->model('cms/cms_access_model');
							$this->cms_access_model->refresh_user_session($session_user);
							cms_session_regenerate();
						}

					}

					if (!empty($rules['mailinglists']) && in_array('form', $GLOBALS['config']['modules'])){

						$this->load->model('cms/cms_page_panel_model');
						$this->load->model('form/form_model');

						$fparams = $this->cms_page_panel_model->get_cms_page_panel_settings('form/basic');

						if (!empty($fparams['add_mailchimp']) && !empty($fparams['mailchimp_api_key']) && !empty($fparams['mailchimp_list_id'])){
							$this->form_model->create_mailchimp_subscriber($data, $fparams);
						}

						if (!empty($fparams['add_cm']) && !empty($fparams['cm_api_key']) && !empty($fparams['cm_api_url']) && !empty($fparams['cm_list_id'])){
							$this->form_model->create_cm_subscriber($data, $fparams);
						}

					}

					$params['success'] = 1;
					$params['redirect_url'] = $this->user_model->get_user_redirect_url();
					// Checkout resume: subscription extends panel_action (user_register).

				}

			}

		}

		if (!empty($errors)){
			$params['errors'] = array_values(array_unique($errors));
		}

		return $params;

	}

	/**
	 * The register rules: the definition defaults under the stored values of the shown user/register block
	 * with this id. Null when the id is not such a block.
	 */
	function _register_rules($cms_page_panel_id){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id < 1){
			return null;
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		if (!is_array($block) || ($block['panel_name'] ?? '') !== 'user/register' || empty($block['show'])){
			return null;
		}

		$config = $this->cms_panel_model->get_cms_panel_config('user/register');
		$rules = $this->cms_panel_model->get_fields_defaults($config['item'] ?? []);
		foreach ($block as $name => $value){
			if ($value !== '' && $value !== null){
				$rules[$name] = $value;
			}
		}

		return $rules;

	}

	function _validate_register_fields($rules, $data, $password, $meta){

		$errors = [];

		// An account is never made without its login name
		$username_is_login = !empty($this->user_model->get_user_settings()['show_username']);
		if ($data['email'] === '' && !$username_is_login){
			$errors[] = 'bademail';
		}
		if ($data['username'] === '' && $username_is_login){
			$errors[] = 'mandatory';
		}

		if ((int)($rules['show_email'] ?? 0) === 2 && $data['email'] === ''){
			$errors[] = 'bademail';
		}

		if ((int)($rules['show_fullname'] ?? 0) === 2){
			if ($data['first_name'] === '' || $data['last_name'] === ''){
				$errors[] = 'mandatory';
			}
		}

		if ((int)($rules['show_username'] ?? 0) === 2 && $data['username'] === ''){
			$errors[] = 'mandatory';
		}

		if ((int)($rules['show_password'] ?? 0) > 0){

			// The one password rule (user_model::password_error), with the block's minimum when it asks for more
			$rule = $this->user_model->password_error($password[1], $password[2], (int)($rules['password_min_length'] ?? 8));
			if ($rule === 'passwords_mismatch'){
				$errors[] = 'password_mismatch';
			} else if ($rule === 'password_short'){
				$errors[] = $password[1] === '' ? 'password_mismatch' : 'password_length';
			}

		}

		if (!empty($rules['fields']) && is_array($rules['fields'])){

			foreach ($rules['fields'] as $field){

				if (!is_array($field) || empty($field['mandatory']) || $field['mandatory'] !== 'yes'){
					continue;
				}

				$name = $field['name'] ?? '';

				if ($name === '' || !empty($meta[$name])){
					continue;
				}

				$errors[] = 'mandatory';

			}

		}

		return $errors;

	}

	function panel_params($params){

		$this->load->model('user/user_model');

		$params['loggedin'] = $this->user_model->is_logged_in();
		$params['success_url'] = $this->user_model->get_user_redirect_url();
		$params['progress_message'] = $this->user_model->get_progress_message();
		$this->user_model->enqueue_progress_overlay();
		// Checkout resume: subscription extends panel_params (user_register).

		return $params;

	}

}
