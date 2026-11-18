<?php

namespace google;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * App ajax. Saves the pending external-transaction token for the signed-in user.
 */
class transaction extends \Controller {

	function panel_action($params){

		if (($params['cms_do'] ?? '') !== 'save'){
			return $params;
		}

		$this->load->model('user/user_model');
		if (!$this->user_model->is_logged_in()){
			return ['ok' => 0, 'error' => 'Login required'];
		}

		$user = $this->user_model->get_current();
		$user_id = (int)($user['cms_page_panel_id'] ?? $user['user_id'] ?? 0);
		$this->load->model('google/google_model');
		$ok = $this->google_model->save_transaction(
				$user_id,
				(string)($params['token'] ?? ''),
				(string)($params['google_package_id'] ?? ''),
				(int)($params['product_id'] ?? 0),
				(int)($params['currency'] ?? 0)
		);

		return ['ok' => $ok ? 1 : 0];

	}

}
