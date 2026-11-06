<?php

namespace google;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * App ajax. Confirms a Play purchase with Google's servers, then writes entitlement.
 */
class purchase extends \Controller {

	function panel_action($params){

		$do = $params['cms_do'] ?? '';
		if ($do !== 'confirm'){
			return $params;
		}

		$this->load->model('user/user_model');
		if (!$this->user_model->is_logged_in()){
			print(json_encode(['ok' => 0, 'error' => 'Login required']));
			exit();
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

		print(json_encode($result));
		exit();

	}

}
