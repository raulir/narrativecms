<?php

namespace stripe;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides service subscription_checkout.
 * Inputs: product_id, currency_id, optional success_url / cancel_url.
 * No dependency on subscription module (domain orchestrates via checkout_start).
 */
class subscription_checkout extends \Controller {

	function panel_action($params){

		$do = $params['do'] ?? $this->input->post('do');

		if ($do !== 'subscription_checkout' && $do !== 'checkout'){
			return $params;
		}

		$this->load->model('user/user_model');
		$this->load->model('shop/shop_model');
		$this->load->model('stripe/stripe_model');
		$this->load->model('cms/cms_page_panel_model');

		$return_result = !empty($params['return_result']) || !empty($this->input->post('return_result'));

		$user = $this->user_model->get_current();
		if (empty($user['user_id']) && empty($user['cms_page_panel_id'])){
			$result = [
					'ok' => 0,
					'login' => 1,
					'login_url' => $this->user_model->get_login_redirect_url(),
					'error' => 'Login required',
			];
			return $this->_respond($result, $return_result, $params);
		}

		$product_id = (int)($this->input->post('product_id') ?? ($params['product_id'] ?? 0));
		$currency_id = (int)($this->input->post('currency_id') ?? ($params['currency_id'] ?? 0));

		if ($product_id < 1){
			return $this->_respond(['ok' => 0, 'error' => 'Missing product'], $return_result, $params);
		}

		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		if (empty($product) || !is_array($product)){
			return $this->_respond(['ok' => 0, 'error' => 'Product not found'], $return_result, $params);
		}

		if ($currency_id < 1){
			$def = $this->shop_model->get_default_currency();
			$currency_id = (int)($def['cms_page_panel_id'] ?? 0);
		}

		$priced = $this->shop_model->get_product_price_in_currency($product, $currency_id);
		$stripe_price_id = trim((string)($priced['stripe_price_id'] ?? ''));
		if ($stripe_price_id === ''){
			return $this->_respond([
					'ok' => 0,
					'error' => 'No Stripe price id for this product and currency',
			], $return_result, $params);
		}

		$base = rtrim((string)($GLOBALS['config']['base_url'] ?? '/'), '/') . '/';
		$success_url = trim((string)($this->input->post('success_url') ?? ($params['success_url'] ?? '')));
		$cancel_url = trim((string)($this->input->post('cancel_url') ?? ($params['cancel_url'] ?? '')));

		// Stripe settings link fields when caller did not pass URLs
		if ($success_url === '' || $cancel_url === ''){
			$from_settings = $this->stripe_model->get_checkout_link_urls();
			if ($success_url === '' && !empty($from_settings['success_url'])){
				$success_url = $from_settings['success_url'];
			}
			if ($cancel_url === '' && !empty($from_settings['cancel_url'])){
				$cancel_url = $from_settings['cancel_url'];
			}
		}

		if ($success_url === ''){
			$success_url = $base . '?subscription_checkout=success';
		}
		if ($cancel_url === ''){
			$cancel_url = $base . '?subscription_checkout=cancel';
		}

		$meta = [
				'cms_user_id' => (string)((int)($user['user_id'] ?? $user['cms_page_panel_id'] ?? 0)),
				'cms_product_id' => (string)$product_id,
				'cms_currency_id' => (string)$currency_id,
		];

		$result = $this->stripe_model->create_subscription_checkout_session(
				$user,
				$stripe_price_id,
				$success_url,
				$cancel_url,
				$meta
		);

		return $this->_respond($result, $return_result, $params);

	}

	function _respond($result, $return_result, $params){

		if ($return_result){
			return is_array($result) ? array_merge($params, $result) : $params;
		}

		print(json_encode($result, JSON_UNESCAPED_UNICODE));
		exit();

	}

	function panel_params($params){

		return $params;

	}

}
