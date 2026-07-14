<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class loginlink extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');

	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'create'){

			$this->load->model('user/user_model');
			$this->load->model('cms/cms_page_panel_model');
			$filename = $GLOBALS['config']['base_path'].'cache/user_reminders.json';

			$user_id = $this->input->post('user_id');
			$user = $this->user_model->get_user($user_id);

			$url = $this->input->post('url');

			$token = sha1(mt_rand(0, mt_getrandmax()).$user_id.time());

			if (file_exists($filename)){
				$reminders = json_decode(file_get_contents($filename), true);
			} else {
				$reminders = [];
			}
			if (!is_array($reminders)){
				$reminders = [];
			}

			// Login name must match user/user_settings (email vs username) so reset form works
			$config = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
			if (empty($config['show_username'])){
				$login_name = trim((string)($user['email'] ?? ''));
			} else {
				$login_name = trim((string)($user['username'] ?? ''));
			}

			// Drop older tokens for this account (email or username keys)
			$drop_keys = array_filter([
					strtolower($login_name),
					strtolower(trim((string)($user['email'] ?? ''))),
					strtolower(trim((string)($user['username'] ?? ''))),
			]);
			foreach ($reminders as $key => $row){
				$stored = strtolower(trim((string)($row['username'] ?? '')));
				if ($stored !== '' && in_array($stored, $drop_keys, true)){
					unset($reminders[$key]);
				}
			}

			$url = $url.$GLOBALS['config']['base_url'].'reminder/?token='.$token;

			$reminders[$token] = [
					'token' => $token,
					'username' => $login_name,
					'time' => time(),
					'autofill' => 1,
			];

			file_put_contents($filename, json_encode($reminders, JSON_PRETTY_PRINT));

			return ['link' => $url];

		}

		return $params;

	}

}
