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

		$do = $params['cms_do'] ?? '';
		if ($do !== 'subscription_checkout' && $do !== 'checkout' && $do !== 'start'){
			return $params;
		}

		$this->load->model('user/user_model');
		$this->load->model('subscription/subscription_model');

		if (!$this->user_model->is_logged_in()){
			return [
					'ok' => 0,
					'login' => 1,
					'login_url' => $this->subscription_model->get_login_url(),
					'error' => 'Login required',
			];
		}

		// Already entitled — one subscription per user; do not open another Checkout
		if ($this->subscription_model->user_has_paid_subscription()){
			$this->subscription_model->clear_checkout_intent();
			$redirect = $this->subscription_model->manage_page_url();
			if ($redirect === ''){
				$redirect = $this->user_model->get_user_redirect_url();
			}
			return [
					'ok' => 0,
					'already_subscribed' => 1,
					'redirect' => $redirect,
					'error' => 'You already have an active subscription',
			];
		}

		$product_id = (int)($params['product_id'] ?? 0);
		$currency_id = (int)($params['currency_id'] ?? 0);

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
			return ['ok' => 0, 'error' => $valid['error'] ?? 'Invalid product'];
		}

		$provider = $this->subscription_model->get_checkout_provider_panel();
		if ($provider === ''){
			return ['ok' => 0, 'error' => 'Select subscription checkout provider!'];
		}

		// The installed web app posts its shell root, so Stripe returns into it
		$urls = $this->subscription_model->get_checkout_return_urls(
				$this->subscription_model->app_web_root($params['app_root_url'] ?? '')
		);

		// Provider panels read cms_do; do kept for providers in modules not yet converted
		$result = $this->run_action($provider, [
				'cms_do' => 'subscription_checkout',
				'do' => 'subscription_checkout',
				'product_id' => $product_id,
				'currency_id' => $currency_id,
				'success_url' => $urls['success_url'],
				'cancel_url' => $urls['cancel_url'],
				'return_result' => 1,
				'no_html' => 1,
		]);
		if (!is_array($result)){
			return ['ok' => 0, 'error' => 'Checkout provider failed'];
		}

		if (!empty($result['ok'])){
			$this->subscription_model->clear_checkout_intent();
		}

		// The provider's address to open (Stripe Checkout); error comes back as cms_error
		return [
				'ok' => !empty($result['ok']) ? 1 : 0,
				'redirect' => (string)($result['redirect'] ?? ''),
				'error' => (string)($result['error'] ?? ''),
		];

	}

}
