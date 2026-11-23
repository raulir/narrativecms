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

		$do = $params['cms_do'] ?? false;

		if ($do == 'create'){

			$this->load->model('user/user_model');
			$this->load->model('cms/cms_page_panel_model');
			$filename = cms_path('tmp').'user_reminders.json';

			$user_id = $params['user_id'] ?? false;
			$user = $this->user_model->get_user($user_id);

			$token = $this->user_model->new_token();

			// Login name must match user/user_settings (email vs username) so reset form works
			$config = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
			if (empty($config['show_username'])){
				$login_name = trim((string)($user['email'] ?? ''));
			} else {
				$login_name = trim((string)($user['username'] ?? ''));
			}

			// Drop older tokens for this account (email or username keys) and add this one, under the file lock
			$drop_keys = array_filter([
					mb_strtolower($login_name),
					mb_strtolower(trim((string)($user['email'] ?? ''))),
					mb_strtolower(trim((string)($user['username'] ?? ''))),
			]);
			$this->user_model->json_file_update($filename, function($reminders) use ($drop_keys, $token, $login_name){
				foreach ($reminders as $key => $row){
					$stored = mb_strtolower(trim((string)($row['username'] ?? '')));
					if ($stored !== '' && in_array($stored, $drop_keys, true)){
						unset($reminders[$key]);
					}
				}
				$reminders[$token] = [
						'token' => $token,
						'username' => $login_name,
						'time' => time(),
						'autofill' => 1,
				];
				return $reminders;
			});

			$url = $this->user_model->reminder_link($token);

			return ['link' => $url];

		}

		return $params;

	}

}
