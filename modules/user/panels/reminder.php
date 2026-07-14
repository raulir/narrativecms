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

	function _save_reminders($filename, $reminders){

		file_put_contents($filename, json_encode($reminders, JSON_PRETTY_PRINT));

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

		$filename = $GLOBALS['config']['base_path'].'cache/user_reminders.json';
		// Save password window (seconds). Display of the form uses the same window so UI matches.
		$ttl_seconds = 1800;

		$do = $this->input->post('do');

		if ($do == 'remind'){

			$username = trim((string)$this->input->post('username'));
			$url = $this->input->post('url');

			if ($username === ''){
				$return['error'] = 'bad_username';
				return $return;
			}

			$user = $this->_resolve_user_for_login_name($username);

			// No error if unknown user (do not leak accounts); still show generic success in UI
			if (!(empty($user) || !$user['show'])){

				$token = sha1(mt_rand(0, mt_getrandmax()).$username.time());
				$reminders = $this->_load_reminders($filename);

				// Drop older open tokens for this login name (only the newest link stays valid)
				$login_key = strtolower($username);
				foreach ($reminders as $key => $row){
					$stored = strtolower(trim((string)($row['username'] ?? '')));
					if ($stored !== '' && $stored === $login_key){
						unset($reminders[$key]);
					}
				}

				// Store canonical login id used for lookup (email or username string as typed)
				$reminders[$token] = [
						'token' => $token,
						'username' => $username,
						'time' => time(),
				];

				$this->_save_reminders($filename, $reminders);

				$title = trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), $GLOBALS['config']['site_title_delimiter'].' ');
				$title = (!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').$title;

				$base = preg_replace('/[?#].*$/', '', (string)$url);
				if ($base === ''){
					$base = $GLOBALS['config']['base_url'].'reminder/';
				}
				$link = rtrim($base, '?&').'?token='.$token;

				$this->cms_email_model->send_mail(
						$user['email'],
						(!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').
						'Password reminder from '.$title,
						$link,
						['auto_submitted' => 1]
				);

			}

			return $params;

		}

		if ($do == 'save'){

			$username = trim((string)$this->input->post('username'));
			$token = trim((string)$this->input->post('token'));
			$password = $this->input->post('password');
			$password2 = $this->input->post('password2');

			$validation_error = $this->user_model->validate_new_password($password, $password2);
			if ($validation_error !== ''){
				$return['error'] = $validation_error;
				return $return;
			}

			if ($token === ''){
				$return['error'] = 'bad_save';
				return $return;
			}

			$reminders = $this->_load_reminders($filename);
			$found = $this->_find_reminder_by_token($reminders, $token);

			if ($found === null){
				$return['error'] = 'bad_save';
				return $return;
			}

			$row = $found['row'];
			$age = time() - (int)($row['time'] ?? 0);

			if ($age >= $ttl_seconds){
				unset($reminders[$found['key']]);
				$this->_save_reminders($filename, $reminders);
				$return['error'] = 'bad_save';
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

			unset($reminders[$found['key']]);

			// Drop other expired tokens
			foreach ($reminders as $key => $r){
				if ((time() - (int)($r['time'] ?? 0)) >= $ttl_seconds){
					unset($reminders[$key]);
				}
			}

			$this->_save_reminders($filename, $reminders);

			$this->user_model->send_password_updated_email($user);

			return $params;

		}

		// GET token in url — show new password form
		$token = $this->input->get('token');

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
