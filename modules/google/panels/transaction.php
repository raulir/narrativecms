<?php

namespace google;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * App ajax. Saves the pending external-transaction token for the signed-in user.
 */
class transaction extends \Controller {

	function panel_action($params){

		$do = $this->input->post('do') ?? ($params['do'] ?? '');
		if ($do !== 'save'){
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
		$ok = $this->google_model->save_transaction(
				$user_id,
				(string)$this->input->post('token'),
				(string)$this->input->post('google_package_id'),
				(int)$this->input->post('product_id'),
				(int)$this->input->post('currency')
		);

		print(json_encode(['ok' => $ok ? 1 : 0]));
		exit();

	}

}
