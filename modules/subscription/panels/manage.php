<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * My subscription — thin panel; actions live in subscription_model.
 */
class manage extends \Controller {

	function panel_action($params){

		$do = $params['cms_do'] ?? '';
		$actions = ['sync_subscription', 'set_auto_renew', 'change_plan', 'update_payment_method'];
		if (!in_array($do, $actions, true)){
			return $params;
		}

		$this->load->model('user/user_model');
		$this->load->model('subscription/subscription_model');

		if (!$this->user_model->is_logged_in()){
			return ['ok' => 0, 'error' => 'Login required'];
		}

		$user = $this->user_model->get_current();
		$user_id = (int)($user['cms_page_panel_id'] ?? $user['user_id'] ?? 0);

		if ($do === 'sync_subscription'){
			$result = $this->subscription_model->action_sync_subscription($user_id);
		} else if ($do === 'set_auto_renew'){
			$raw = $params['auto_renew'] ?? false;
			$auto_renew = ($raw === '1' || $raw === 1 || $raw === 'on' || $raw === true);
			$result = $this->subscription_model->action_set_auto_renew($user_id, $auto_renew);
		} else if ($do === 'update_payment_method'){
			$result = $this->subscription_model->action_update_payment_method(
					$user_id,
					(string)($params['return_url'] ?? ''),
					$this->subscription_model->app_web_root($params['app_root_url'] ?? '')
			);
		} else {
			$result = $this->subscription_model->action_change_plan(
					$user_id,
					(int)($params['product_id'] ?? 0),
					(int)($params['currency_id'] ?? 0)
			);
		}

		// Only what manage.js reads (definition ajax.output); error comes back as cms_error
		$reply = [];
		foreach (['ok', 'changed', 'redirect', 'auto_renew_on', 'status_period_line', 'error'] as $key){
			if (array_key_exists($key, $result)){
				$reply[$key] = $result[$key];
			}
		}
		return $reply;

	}

	function panel_params($params){

		$this->load->model('subscription/subscription_model');
		// Purchase and the app's checkout watch live in pricing.js (manage.js calls them)
		$GLOBALS['_panel_js'][] = 'modules/subscription/js/pricing.js';
		$cid = (int)($params['currency_id'] ?? 0);
		if ($cid > 0){
			$params['active_currency_override'] = $cid;
		}
		return $this->subscription_model->prepare_manage_panel($params);

	}

}
