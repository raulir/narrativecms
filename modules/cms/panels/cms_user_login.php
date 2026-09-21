<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_user_login extends \Controller {

	function panel_action($params){

		$do = $this->input->post('do');
		if ($do == 'cms_user_login'){

			$this->_ensure_session_dir_for_login();

			$username = $this->input->post('username');
			$password = $this->input->post('password');

			$this->load->model('cms/cms_user_model');

			$cms_user_data = $this->cms_user_model->get_cms_user_login_data($username, $password);

			if (!empty($cms_user_data)){

				$_SESSION['cms_user'] = $cms_user_data;
				cms_session_mark_cms_password_checked();

				header('Location: '.$GLOBALS['config']['base_url'].'admin/', true, 302);
				exit();
					
			}

		}

		return $params;

	}

	function panel_params($params){

		return [];

	}

	function _ensure_session_dir_for_login(){

		if (session_id()){
			return;
		}

		$path = function_exists('cms_path') ? rtrim(str_replace('\\', '/', cms_path('session')), '/') : '';
		if ($path !== '' && !is_dir($path) && $this->_session_dir_is_allowed($path)){
			@mkdir($path, 0700, true);
		}

		if (function_exists('cms_session_boot')){
			cms_session_boot();
		}

	}

	function _session_dir_is_allowed($path){

		$path = rtrim(str_replace('\\', '/', (string)$path), '/');
		$project = rtrim(str_replace('\\', '/', (string)($GLOBALS['config']['base_path'] ?? '')), '/');
		$tmp = function_exists('cms_path') ? rtrim(str_replace('\\', '/', cms_path('tmp')), '/') : '';

		if ($project !== '' && ($path === $project || strpos($path, $project.'/') === 0)){
			return true;
		}
		if ($tmp !== '' && ($path === $tmp || strpos($path, $tmp.'/') === 0)){
			return true;
		}

		return false;

	}

}
