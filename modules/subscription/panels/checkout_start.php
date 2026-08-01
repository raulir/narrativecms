<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Domain entry for paid checkout.
 * Resolves provider from shop settings, validates product, calls provider, clears intent.
 * FE always posts here — never to stripe/* directly.
 */
class checkout_start extends \Controller {

	function panel_action($params){

		$do = $params['do'] ?? $this->input->post('do');

		if ($do !== 'subscription_checkout' && $do !== 'checkout' && $do !== 'start'){
			return $params;
		}

		$this->load->model('user/user_model');
		$this->load->model('subscription/subscription_model');

		$user = $this->user_model->get_current();
		if (empty($user['user_id']) && empty($user['cms_page_panel_id'])){
			print(json_encode([
					'ok' => 0,
					'login' => 1,
					'login_url' => $this->subscription_model->get_login_url(),
					'error' => 'Login required',
			], JSON_UNESCAPED_UNICODE));
			exit();
		}

		// Already entitled — one subscription per user; do not open another Checkout
		if ($this->subscription_model->user_has_active_subscription()){
			$this->subscription_model->clear_checkout_intent();
			$this->load->model('user/user_model');
			$redirect = $this->user_model->get_user_redirect_url();
			$settings = $this->subscription_model->merge_subscription_pricing_settings([]);
			if (!empty($settings['manage_page_link'])){
				$ml = _l($settings['manage_page_link'], false);
				if (is_string($ml) && $ml !== ''){
					$redirect = $ml;
				}
			}
			print(json_encode([
					'ok' => 0,
					'already_subscribed' => 1,
					'redirect' => $redirect,
					'error' => 'You already have an active subscription',
			], JSON_UNESCAPED_UNICODE));
			exit();
		}

		$product_id = (int)($this->input->post('product_id') ?? ($params['product_id'] ?? 0));
		$currency_id = (int)($this->input->post('currency_id') ?? ($params['currency_id'] ?? 0));

		if ($product_id < 1){
			$intent = $this->subscription_model->get_checkout_intent();
			if (!empty($intent['product_id'])){
				$product_id = (int)$intent['product_id'];
				if ($currency_id < 1){
					$currency_id = (int)($intent['currency_id'] ?? 0);
				}
			}
		}

		$valid = $this->subscription_model->validate_paid_product($product_id);
		if (empty($valid['ok'])){
			print(json_encode([
					'ok' => 0,
					'error' => $valid['error'] ?? 'Invalid product',
			], JSON_UNESCAPED_UNICODE));
			exit();
		}

		$provider = $this->subscription_model->get_checkout_provider_panel();
		if ($provider === ''){
			print(json_encode([
					'ok' => 0,
					'error' => 'Select subscription checkout provider!',
			], JSON_UNESCAPED_UNICODE));
			exit();
		}

		$urls = $this->subscription_model->get_checkout_return_urls();

		$provider_params = [
				'do' => 'subscription_checkout',
				'product_id' => $product_id,
				'currency_id' => $currency_id,
				'success_url' => $urls['success_url'],
				'cancel_url' => $urls['cancel_url'],
				'return_result' => 1,
				'no_html' => 1,
		];

		$result = $this->run_action($provider, $provider_params);
		if (!is_array($result)){
			$result = ['ok' => 0, 'error' => 'Checkout provider failed'];
		}

		if (!empty($result['ok'])){
			$this->subscription_model->clear_checkout_intent();
		}

		print(json_encode($result, JSON_UNESCAPED_UNICODE));
		exit();

	}

	function panel_params($params){

		return $params;

	}

}
