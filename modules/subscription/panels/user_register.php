<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Extends user/register — honour pricing checkout intent for redirects.
 */
class user_register extends \Controller {

	function panel_action($params){

		if (!empty($params['success']) || !empty($params['redirect_url'])){
			$this->load->model('subscription/subscription_model');
			$resume = $this->subscription_model->get_post_auth_redirect_url();
			if (!empty($resume)){
				$params['redirect_url'] = $resume;
				$params['success_url'] = $resume;
			}
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
