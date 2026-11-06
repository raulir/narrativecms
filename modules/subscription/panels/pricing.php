<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Pricing table UI. Catalogue build and intent live in subscription_model.
 */
class pricing extends \Controller {

	function panel_action($params){

		$do = $params['cms_do'] ?? false;

		if ($do === 'browser_discard'){

			$this->load->model('subscription/subscription_model');
			$this->subscription_model->browser_enter_discard((string)($params['token'] ?? ''));
			print(json_encode(['ok' => 1]));
			exit();

		}

		if ($do === 'browser_open'){

			$this->load->model('subscription/subscription_model');

			$url = $this->subscription_model->browser_enter_create(($params['page_path'] ?? false));

			print(json_encode([
					'ok' => $url !== '' ? 1 : 0,
					'url' => $url,
			], JSON_UNESCAPED_UNICODE));
			exit();

		}

		if ($do === 'set_checkout_intent'){

			$this->load->model('subscription/subscription_model');

			$product_id = (int)($params['product_id'] ?? 0);
			$currency_id = (int)($params['currency_id'] ?? 0);
			$resume_url = trim((string)($params['resume_url'] ?? ''));

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
