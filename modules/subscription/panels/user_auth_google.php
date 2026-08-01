<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Extends user/auth_google — honour pricing checkout intent for success_url.
 */
class user_auth_google extends \Controller {

	function panel_params($params){

		$this->load->model('subscription/subscription_model');
		$resume = $this->subscription_model->get_post_auth_redirect_url();
		if (!empty($resume)){
			$params['success_url'] = $resume;
		}

		return $params;

	}

}
