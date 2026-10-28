<?php

namespace google;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * App ajax. Confirms a Play purchase with Google's servers, then writes entitlement.
 */
class purchase extends \Controller {

	function panel_action($params){

		$do = $this->input->post('do') ?? ($params['do'] ?? '');
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
				(int)$this->input->post('product_id'),
				(int)$this->input->post('currency'),
				(string)$this->input->post('purchase_token'),
				(string)$this->input->post('google_package_id')
		);

		print(json_encode($result));
		exit();

	}

}
