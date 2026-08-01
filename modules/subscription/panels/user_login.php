<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Extends user/login — honour pricing checkout intent for success_url.
 * After login AJAX: return success_url so already-subscribed users go to /start/, not payment.
 */
class user_login extends \Controller {

	function panel_action($params){

		// Failed login returns early with error — leave alone
		if (!empty($params['error'])){
			return $params;
		}

		$do = $this->input->post('do');
		if ($do !== 'login'){
			return $params;
		}

		$this->load->model('user/user_model');
		if (!$this->user_model->is_logged_in()){
			return $params;
		}

		$this->load->model('subscription/subscription_model');
		$redirect = $this->subscription_model->get_post_auth_redirect_url();
		if ($redirect !== null && $redirect !== ''){
			$params['success_url'] = $redirect;
		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('subscription/subscription_model');
		$resume = $this->subscription_model->get_post_auth_redirect_url();
		if (!empty($resume)){
			$params['success_url'] = $resume;
		}

		return $params;

	}

}
