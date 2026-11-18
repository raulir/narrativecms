<?php

/**
 * Stripe Checkout success URL for cart orders: {base}stripe/checkout_return/?session_id=cs_…
 * Marks the order paid at once (the webhook may come later), clears the cart cookie, redirects
 * to Stripe settings → Checkout success (else home). Never shows Stripe errors to the buyer.
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

require_once BASEPATH.'core/cms_config.php';
require_once BASEPATH.'core/cms_bootstrap.php';
require_once BASEPATH.'core/controller.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

$ci = new Controller();
$ci->load->model('stripe/stripe_model');
$ci->load->model('stripe/stripe_order_model');
$ci->load->model('shop/shop_model');
$ci->load->model('cms/cms_page_panel_model');

$links = $ci->stripe_model->get_checkout_link_urls();
$home = $ci->stripe_model->absolute_url('/');
$target = $links['cancel_url'] !== '' ? $links['cancel_url'] : $home;

$session_id = (string)($_GET['session_id'] ?? '');
if (preg_match('/^cs_[A-Za-z0-9_]{1,250}$/', $session_id) && ($GLOBALS['config']['stripe_secret'] ?? '') !== ''){

	try {
		$session = \Stripe\Checkout\Session::retrieve(['id' => $session_id, 'expand' => ['shipping_cost.shipping_rate']]);
		if ($ci->stripe_order_model->apply_paid_session($session)){
			$order = $ci->cms_page_panel_model->get_cms_page_panel((int)($session->metadata->cms_order_id ?? 0));
			$cookie_key = $ci->shop_model->get_cart_key_from_cookie();
			if ($cookie_key !== '' && $cookie_key === (string)($order['cart_key'] ?? '')){
				$ci->shop_model->clear_cart_cookie();
			}
			$target = $links['success_url'] !== '' ? $links['success_url'] : $home;
		}
	} catch (\Exception $e){
		$ci->stripe_model->log_error('Checkout return failed', $e->getMessage());
	}

}

header('Location: '.$target, true, 302);
exit();
