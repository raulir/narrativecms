<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class reminder extends \Controller {

	function _login_by_email(){

		$this->load->model('cms/cms_page_panel_model');
		$config = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');

		// show_username: "0" = email is login name, "1" = username
		return empty($config['show_username']);

	}

	function _resolve_user_for_login_name($login_name){

		$this->load->model('user/user_model');

		$login_name = trim((string)$login_name);
		if ($login_name === ''){
			return [];
		}

		if ($this->_login_by_email()){
			return $this->user_model->get_user_by_email($login_name);
		}

		return $this->user_model->get_user_by_username($login_name);

	}

	function _load_reminders($filename){

		if (!file_exists($filename)){
			return [];
		}

		$data = json_decode(file_get_contents($filename), true);

		return is_array($data) ? $data : [];

	}

	/** Change the token file under one lock: $fn gets the reminders and returns them changed. */
	function _update_reminders($filename, callable $fn){

		$this->load->model('user/user_model');
		$this->user_model->json_file_update($filename, $fn);

	}

	function _find_reminder_by_token($reminders, $token){

		$token = trim((string)$token);
		if ($token === ''){
			return null;
		}

		foreach ($reminders as $key => $row){
			if (!empty($row['token']) && $row['token'] === $token){
				return ['key' => $key, 'row' => $row];
			}
		}

		return null;

	}

	function panel_action($params){

		$this->load->model('user/user_model');
		$this->load->model('cms/cms_email_model');

		$filename = cms_path('tmp').'user_reminders.json';
		// Save password window (seconds). Display of the form uses the same window so UI matches.
		$ttl_seconds = 1800;

		$do = $params['cms_do'] ?? false;

		if ($do == 'remind'){

			$username = trim((string)($params['username'] ?? ''));
			if ($this->_login_by_email()){
				$username = $this->user_model->normalise_email($username);
			}

			if ($username === ''){
				$return['error'] = 'bad_username';
				return $return;
			}

			// 3 an hour per typed name, 10 per client address; counted whether or not the account exists
			$name_key = 'name:'.mb_strtolower($username);
			$ip_key = 'ip:'.$this->user_model->_client_address();
			if ($this->user_model->attempts_over('remind', $name_key, 3, 3600) || $this->user_model->attempts_over('remind', $ip_key, 10, 3600)){
				$this->user_model->auth_log('remind_limited');
				return ['error' => 'too_many'];
			}
			$this->user_model->attempt_note('remind', $name_key);
			$this->user_model->attempt_note('remind', $ip_key);

			$user = $this->_resolve_user_for_login_name($username);

			// No error if unknown user (do not leak accounts); still show generic success in UI
			if (!(empty($user) || !$user['show'])){

				$token = $this->user_model->new_token();

				$this->_update_reminders($filename, function($reminders) use ($token, $username){
					// Drop older open tokens for this login name (only the newest link stays valid)
					$login_key = mb_strtolower($username);
					foreach ($reminders as $key => $row){
						$stored = mb_strtolower(trim((string)($row['username'] ?? '')));
						if ($stored !== '' && $stored === $login_key){
							unset($reminders[$key]);
						}
					}
					// Store canonical login id used for lookup (the lowercased email, or the username)
					$reminders[$token] = [
							'token' => $token,
							'username' => $username,
							'time' => time(),
					];
					return $reminders;
				});

				$title = trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), $GLOBALS['config']['site_title_delimiter'].' ');
				$title = (!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').$title;

				$link = $this->user_model->reminder_link($token);

				$this->cms_email_model->send_mail(
						$user['email'],
						(!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').
						'Password reminder from '.$title,
						$link,
						['auto_submitted' => 1, 'send_now' => 1]
				);

			}

			return $params;

		}

		if ($do == 'save'){

			$username = trim((string)($params['username'] ?? ''));
			$token = trim((string)($params['token'] ?? ''));
			$password = $params['password'] ?? false;
			$password2 = $params['password2'] ?? false;

			$validation_error = $this->user_model->validate_new_password($password, $password2);
			if ($validation_error !== ''){
				$return['error'] = $validation_error;
				return $return;
			}

			// No, unknown (a newer reminder replaced it, or it was used) or expired token: the link is no longer valid
			if ($token === ''){
				$return['error'] = 'timeout';
				return $return;
			}

			$reminders = $this->_load_reminders($filename);
			$found = $this->_find_reminder_by_token($reminders, $token);

			if ($found === null){
				$return['error'] = 'timeout';
				return $return;
			}

			$row = $found['row'];
			$age = time() - (int)($row['time'] ?? 0);

			if ($age >= $ttl_seconds){
				$found_key = $found['key'];
				$this->_update_reminders($filename, function($reminders) use ($found_key){
					unset($reminders[$found_key]);
					return $reminders;
				});
				$return['error'] = 'timeout';
				return $return;
			}

			// Prefer login name stored with the token (what they used when requesting the link)
			$login_name = trim((string)($row['username'] ?? ''));
			if ($login_name === ''){
				$login_name = $username;
			}

			// If form login name differs from token, still allow when form resolves to same user
			$user = $this->_resolve_user_for_login_name($login_name);

			if (empty($user) || empty($user['show'])){
				// Fallback: form field (e.g. user typed email while token had old value)
				if ($username !== '' && $username !== $login_name){
					$user = $this->_resolve_user_for_login_name($username);
				}
			}

			if (empty($user) || empty($user['show'])){
				$return['error'] = 'bad_username';
				return $return;
			}

			// If both form and token login names are set, they must refer to this user
			// (case-insensitive for email)
			if ($username !== ''){
				$form_user = $this->_resolve_user_for_login_name($username);
				if (!empty($form_user['cms_page_panel_id'])
						&& (int)$form_user['cms_page_panel_id'] !== (int)$user['cms_page_panel_id']){
					$return['error'] = 'bad_username';
					return $return;
				}
			}

			$this->user_model->change_user_password($user['cms_page_panel_id'], $password);

			// This token is used; other expired ones go too
			$found_key = $found['key'];
			$this->_update_reminders($filename, function($reminders) use ($found_key, $ttl_seconds){
				unset($reminders[$found_key]);
				foreach ($reminders as $key => $r){
					if ((time() - (int)($r['time'] ?? 0)) >= $ttl_seconds){
						unset($reminders[$key]);
					}
				}
				return $reminders;
			});

			$this->user_model->send_password_updated_email($user);

			return $params;

		}

		// GET token in url — show new password form
		$token = $params['token'] ?? false;

		$params['success'] = 0;
		$params['autofill'] = '';
		$params['timeout'] = false;

		if (!empty($token)){

			$reminders = $this->_load_reminders($filename);
			$found = $this->_find_reminder_by_token($reminders, $token);
			$params['timeout'] = true;

			if ($found !== null){
				$row = $found['row'];
				$age = time() - (int)($row['time'] ?? 0);
				// Form available for same window as save (clearer than long display / short save)
				if ($age < $ttl_seconds){
					$params['success'] = 1;
					$params['token'] = $token;
					$params['timeout'] = false;
					// Always fill the login name used when the link was requested (email or username)
					$params['autofill'] = (string)($row['username'] ?? '');
				}
			}

		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('user/user_model');

		// A block saved before a field existed has it empty: the definition's default stands in
		$this->load->model('cms/cms_panel_model');
		$config = $this->cms_panel_model->get_cms_panel_config('user/reminder');
		foreach ($this->cms_panel_model->get_fields_defaults($config['item'] ?? []) as $name => $default){
			if (!isset($params[$name]) || $params[$name] === ''){
				$params[$name] = $default;
			}
		}

		$params['loggedin'] = $this->user_model->is_logged_in();

		// Mirror login: user/user_settings show_username — 0 = email is login name
		if ($this->_login_by_email()){
			$params['username_label'] = !empty($params['email_label']) ? $params['email_label'] : 'email';
			if (!empty($params['message_bad_email'])){
				$params['message_bad_username'] = $params['message_bad_email'];
			} else {
				$params['message_bad_username'] = 'Not a proper email entered';
			}
		}

		if (!isset($params['success'])){
			$params['success'] = 0;
		}
		if (!isset($params['autofill'])){
			$params['autofill'] = '';
		}
		if (!isset($params['timeout'])){
			$params['timeout'] = false;
		}
		if (!isset($params['token'])){
			$params['token'] = '';
		}

		return $params;

	}

}
