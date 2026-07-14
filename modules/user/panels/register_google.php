<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class register_google extends \Controller {

	function panel_params($params){

		$this->load->model('user/user_google_model');
		$this->user_google_model->set_web_auth_intent('register');

		return $params;

	}

}
