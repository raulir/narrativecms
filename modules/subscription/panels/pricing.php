<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Pricing table UI. Catalogue build and intent live in subscription_model.
 */
class pricing extends \Controller {

	function panel_action($params){

		$do = $params['cms_do'] ?? '';
		if (!in_array($do, ['browser_discard', 'paid_status', 'browser_open', 'set_checkout_intent'], true)){
			return $params;
		}

		$this->load->model('subscription/subscription_model');

		if ($do === 'browser_discard'){
			$this->subscription_model->browser_enter_discard((string)($params['token'] ?? ''));
			return ['ok' => 1];
		}

		if ($do === 'set_checkout_intent'){
			$ok = $this->subscription_model->set_checkout_intent(
					(int)($params['product_id'] ?? 0),
					(int)($params['currency_id'] ?? 0),
					trim((string)($params['resume_url'] ?? ''))
			);
			return ['ok' => $ok ? 1 : 0, 'login_url' => $this->subscription_model->get_login_url()];
		}

		$this->load->model('user/user_model');
		$user = $this->user_model->is_logged_in() ? $this->user_model->get_current() : [];
		$user_id = (int)($user['cms_page_panel_id'] ?? $user['user_id'] ?? 0);

		// The plans page asks this every few seconds while a checkout it started is open
		if ($do === 'paid_status'){
			if ($user_id < 1){
				return ['ok' => 0, 'paid' => 0];
			}
			return ['ok' => 1, 'paid' => $this->subscription_model->user_has_paid_subscription($user_id) ? 1 : 0];
		}

		// browser_open
		$url = $this->subscription_model->browser_enter_create($user_id, (string)($params['page_path'] ?? ''));
		return ['ok' => $url !== '' ? 1 : 0, 'url' => $url];

	}

	function panel_params($params){

		$this->load->model('subscription/subscription_model');
		return $this->subscription_model->prepare_pricing_panel($params);

	}

}
