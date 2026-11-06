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

			$validation_error = $this->user_model->validate_new_password($password, $password2);
			if ($validation_error !== ''){
				$return['error'] = $validation_error;
				return $return;
			}

			$user_id = (int)$user['cms_page_panel_id'];
			$this->user_model->change_user_password($user_id, $password);
			$this->user_model->send_password_updated_email($user);

			return $params;

		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('user/user_model');

		$params['loggedin'] = $this->user_model->is_logged_in();

		return $params;

	}

}
