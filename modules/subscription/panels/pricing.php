<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Pricing table UI. Catalogue build and intent live in subscription_model.
 */
class pricing extends \Controller {

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do === 'set_checkout_intent'){

			$this->load->model('subscription/subscription_model');

			$product_id = (int)$this->input->post('product_id');
			$currency_id = (int)$this->input->post('currency_id');
			$resume_url = trim((string)$this->input->post('resume_url'));

			$ok = $this->subscription_model->set_checkout_intent($product_id, $currency_id, $resume_url);
			$login_url = $this->subscription_model->get_login_url();

			print(json_encode([
					'ok' => $ok ? 1 : 0,
					'login_url' => $login_url,
			], JSON_UNESCAPED_UNICODE));
			exit();

		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('subscription/subscription_model');
		return $this->subscription_model->prepare_pricing_panel($params);

	}

}
