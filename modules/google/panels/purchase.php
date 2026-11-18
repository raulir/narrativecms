<?php

namespace google;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * App ajax. Confirms a Play purchase with Google's servers, then writes entitlement.
 * Reply: ok, changed, and cms_error (a detail for the console; the screen shows its own text).
 */
class purchase extends \Controller {

	function panel_action($params){

		if (($params['cms_do'] ?? '') !== 'confirm'){
			return $params;
		}

		$this->load->model('user/user_model');
		if (!$this->user_model->is_logged_in()){
			return ['ok' => 0, 'error' => 'Login required'];
		}

		$user = $this->user_model->get_current();
		$user_id = (int)($user['cms_page_panel_id'] ?? $user['user_id'] ?? 0);
		$this->load->model('google/google_model');
		$result = $this->google_model->confirm_purchase(
				$user_id,
				(int)($params['product_id'] ?? 0),
				(int)($params['currency'] ?? 0),
				(string)($params['purchase_token'] ?? ''),
				(string)($params['google_package_id'] ?? '')
		);

		return [
				'ok' => !empty($result['ok']) ? 1 : 0,
				'changed' => !empty($result['changed']) ? 1 : 0,
				'error' => (string)($result['error'] ?? ''),
		];

	}

}
