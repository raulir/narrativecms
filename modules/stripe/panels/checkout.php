<?php

namespace stripe;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides shop_checkout: cart.js posts cms_do=shop_checkout, the reply redirects to Stripe Checkout.
 * See stripe/docs/shop_checkout.md.
 */
class checkout extends \Controller {

	function panel_action($params){

		if (($params['cms_do'] ?? '') !== 'shop_checkout'){
			return $params;
		}

		$this->load->model('shop/shop_model');
		$this->load->model('stripe/stripe_order_model');

		$order = $this->shop_model->get_current_order_if_any($this->shop_model->get_front_user());
		$order_id = (int)($order['cms_page_panel_id'] ?? 0);
		// The cart's own check ran in the browser flow, so it is not trusted here: same check again.
		if (!$order_id || !$this->shop_model->checkout_check($order_id)['ready']){
			return ['ok' => 0] + $params;
		}

		$result = $this->stripe_order_model->create_checkout_session($order);

		// Only ok and redirect go to the browser (ajax.output); the reason of a failure is in the log.
		return ['ok' => (int)$result['ok'], 'redirect' => (string)($result['redirect'] ?? '')] + $params;

	}

}
