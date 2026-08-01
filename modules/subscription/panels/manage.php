<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * My subscription — thin panel; actions live in subscription_model.
 */
class manage extends \Controller {

	function panel_action($params){

		$do = $this->input->post('do') ?? ($params['do'] ?? '');
		$actions = ['sync_subscription', 'set_auto_renew', 'change_plan', 'update_payment_method'];
		if (!in_array($do, $actions, true)){
			return $params;
		}

		$this->load->model('user/user_model');
		$this->load->model('subscription/subscription_model');

		if (!$this->user_model->is_logged_in()){
			print(json_encode(['ok' => 0, 'error' => 'Login required'], JSON_UNESCAPED_UNICODE));
			exit();
		}

		$user = $this->user_model->get_current();
		$user_id = (int)($user['cms_page_panel_id'] ?? $user['user_id'] ?? 0);

		if ($do === 'sync_subscription'){
			$result = $this->subscription_model->action_sync_subscription($user_id);
		} else if ($do === 'set_auto_renew'){
			$raw = $this->input->post('auto_renew');
			$auto_renew = ($raw === '1' || $raw === 1 || $raw === 'on' || $raw === true);
			$result = $this->subscription_model->action_set_auto_renew($user_id, $auto_renew);
		} else if ($do === 'update_payment_method'){
			$result = $this->subscription_model->action_update_payment_method(
					$user_id,
					(string)$this->input->post('return_url')
			);
		} else {
			$result = $this->subscription_model->action_change_plan(
					$user_id,
					(int)$this->input->post('product_id'),
					(int)$this->input->post('currency_id')
			);
		}

		print(json_encode($result, JSON_UNESCAPED_UNICODE));
		exit();

	}

	function panel_params($params){

		$this->load->model('subscription/subscription_model');
		$cid = (int)$this->input->get('currency_id');
		if ($cid > 0){
			$params['active_currency_override'] = $cid;
		}
		return $this->subscription_model->prepare_manage_panel($params);

	}

}
