<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class register_google extends \Controller {

	function panel_params($params){

		$this->load->model('user/user_google_model');
		$this->load->model('user/user_model');
		$this->user_google_model->set_web_auth_intent('register');

		$params['progress_message'] = $this->user_model->get_progress_message();
		$this->user_model->enqueue_progress_overlay(true);

		return $params;

	}

}
