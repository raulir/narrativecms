<?php

namespace stripe;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Shop cart orders paid through Stripe Checkout (mode payment). See stripe/docs/shop_checkout.md.
 * Needs the shop module; stripe_model (key, URLs, log) is loaded here.
 */
class stripe_order_model extends \Model {

	function __construct(){

		$this->load->model('stripe/stripe_model');

	}

	/**
	 * Cart order → Stripe Checkout session (mode payment): item lines, delivery methods as shipping options.
	 * @return array{ok:int,redirect?:string,error?:string}
	 */
	function create_checkout_session($order){

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');

		$order_id = (int)$order['cms_page_panel_id'];
		$this->_delete_old_locks();
		if (($GLOBALS['config']['stripe_secret'] ?? '') === ''){
			return $this->stripe_model->fail('Cart checkout: stripe_secret not configured', '', 'Checkout is not available');
		}

		$payable = $this->shop_model->get_payable_order($order_id);
		$expires = time() + 1860;
		$session_params = $this->checkout_session_params($order, $payable, $expires);
		if (!$session_params){
			return $this->stripe_model->fail('Cart checkout: no item lines, or no single currency, order '.$order_id, '', 'Checkout is not available');
		}

		try {
			// One open session per cart: an earlier one (buyer went back) is expired first.
			if (!empty($order['stripe_session_id'])){
				try {
					\Stripe\Checkout\Session::retrieve($order['stripe_session_id'])->expire();
				} catch (\Exception $e){
					// Already expired or completed: nothing to do.
				}
			}

			$session = \Stripe\Checkout\Session::create($session_params);
			$url = (string)($session->url ?? '');
			if ($url === ''){
				return $this->stripe_model->fail('Cart checkout: session without URL, order '.$order_id, '', 'Checkout could not be started');
			}

			$this->cms_page_panel_model->update_cms_page_panel($order_id, [
					'stripe_session_id' => (string)$session->id,
					'stripe_checkout_fingerprint' => $payable['fingerprint'],
			]);
			$this->shop_model->hold_cart_until($order_id, $expires + 120);

			return ['ok' => 1, 'redirect' => $url];

		} catch (\Exception $e){
			return $this->stripe_model->fail('Cart checkout: session failed, order '.$order_id, $e->getMessage(), 'Checkout could not be started');
		}

	}

	/**
	 * Stripe Checkout session params for a cart (no Stripe call): item lines, delivery methods as shipping
	 * options, terms acceptance and the note under Pay from Stripe settings. [] when the cart cannot be paid.
	 */
	function checkout_session_params($order, $payable, $expires){

		$order_id = (int)$order['cms_page_panel_id'];

		// Shop's payable order → Stripe line items (amounts in minor units).
		$currency = $payable['currency'];
		$line_items = [];
		foreach ($payable['lines'] as $line){
			if ($line['type'] !== 'item'){
				continue;
			}
			$line_items[] = [
					'price_data' => [
							'currency' => $currency,
							'unit_amount' => (int)round($line['unit_price'] * 100),
							'product_data' => ['name' => trim($line['name'].($line['dims'] !== '' ? ' ('.$line['dims'].')' : ''))],
					],
					'quantity' => $line['quantity'],
			];
		}
		if (!$line_items || !preg_match('/^[a-z]{3}$/', $currency)){
			return [];
		}

		$settings = $this->stripe_model->get_settings();
		$countries = array_values(array_filter(array_map(function($c){ return strtoupper(trim($c)); },
				explode(',', (string)($settings['shipping_countries'] ?? 'GB'))), function($c){ return preg_match('/^[A-Z]{2}$/', $c); }));

		$links = $this->stripe_model->get_checkout_link_urls();
		$home = $this->stripe_model->absolute_url('/');
		$session_params = [
				'mode' => 'payment',
				'line_items' => $line_items,
				'shipping_address_collection' => ['allowed_countries' => $countries ?: ['GB']],
				'phone_number_collection' => ['enabled' => true],
				'metadata' => ['cms_order_id' => (string)$order_id],
				'payment_intent_data' => ['metadata' => ['cms_order_id' => (string)$order_id]],
				'success_url' => $this->stripe_model->absolute_url('stripe/checkout_return/').'?session_id={CHECKOUT_SESSION_ID}',
				'cancel_url' => $links['cancel_url'] !== '' ? $links['cancel_url'] : $home,
				// Stripe's minimum is 30 minutes. The cart holds its stock until then (hold_cart_until below).
				'expires_at' => $expires,
		];

		// Delivery methods from the shop (Stripe takes at most 5). None: free delivery.
		$options = [];
		foreach (array_slice($payable['delivery_options'], 0, 5) as $delivery){
			$options[] = ['shipping_rate_data' => [
					'type' => 'fixed_amount',
					'display_name' => mb_substr($delivery['name'], 0, 100),
					'fixed_amount' => ['amount' => (int)round($delivery['price'] * 100), 'currency' => $currency],
					'metadata' => ['cms_delivery_id' => (string)$delivery['delivery_id']],
			]];
		}
		if ($options){
			$session_params['shipping_options'] = $options;
		}

		// Terms acceptance: Stripe refuses it until Dashboard → Settings → Public details has a Terms of service URL.
		if ((string)($settings['terms_consent'] ?? '0') === '1'){
			$session_params['consent_collection'] = ['terms_of_service' => 'required'];
			$text = str_replace('{{terms_url}}', $this->stripe_model->link_url($settings['terms_link'] ?? ''), trim((string)($settings['terms_text'] ?? '')));
			if ($text !== ''){
				$session_params['custom_text']['terms_of_service_acceptance'] = ['message' => mb_substr($text, 0, 1200)];
			}
		}
		$submit = trim((string)($settings['submit_text'] ?? ''));
		if ($submit !== ''){
			$session_params['custom_text']['submit'] = ['message' => mb_substr($submit, 0, 1200)];
		}

		return $session_params;

	}

	/**
	 * Paid Checkout session (mode payment) → shop order paid. Safe to run twice and at the same time
	 * (webhook + return page). Returns true when the order is paid now or already was. Errors throw.
	 */
	function apply_paid_session($session){

		$s = $this->stripe_model->to_array($session);
		$order_id = (int)($s['metadata']['cms_order_id'] ?? 0);
		if (($s['mode'] ?? '') !== 'payment' || $order_id < 1 || ($s['payment_status'] ?? '') !== 'paid'){
			return false;
		}

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');

		$lock = fopen(cms_path('tmp').'stripe_order_'.$order_id.'.lock', 'c');
		if ($lock){
			flock($lock, LOCK_EX);
		}

		try {

			$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
			if (($order['panel_name'] ?? '') !== 'shop/order'){
				$this->stripe_model->log_error('Paid session for unknown order '.$order_id, 'session='.($s['id'] ?? ''));
				return false;
			}
			if ($this->shop_model->order_is_paid($order)){
				return true;
			}

			// Shipping rate metadata (delivery id) needs the expanded session; a DEV test payload has it inline.
			if (($GLOBALS['config']['stripe_secret'] ?? '') !== '' && !empty($s['id'])){
				try {
					$s = $this->stripe_model->to_array(\Stripe\Checkout\Session::retrieve(['id' => $s['id'], 'expand' => ['shipping_cost.shipping_rate']]));
				} catch (\Exception $e){
					$this->stripe_model->log_error('Paid session re-read failed, order '.$order_id, $e->getMessage());
				}
			}

			$payable = $this->shop_model->get_payable_order($order_id);
			$subtotal = $payable['subtotal'];
			$currency = $payable['currency'];

			// The money is taken: mismatches only add a note for whoever fulfils the order.
			$notes = [];
			if ($currency !== strtolower((string)($s['currency'] ?? ''))){
				$notes[] = 'Paid in '.strtoupper((string)($s['currency'] ?? '?')).', cart is '.strtoupper($currency ?: '?').'.';
			}
			if ((int)round($subtotal * 100) !== (int)($s['amount_subtotal'] ?? -1)){
				$notes[] = 'Items paid '.number_format((int)($s['amount_subtotal'] ?? 0) / 100, 2).', cart lines total '.number_format($subtotal, 2).'.';
			}
			if (($order['stripe_checkout_fingerprint'] ?? '') !== $payable['fingerprint']){
				$notes[] = 'Cart lines changed after checkout started.';
			}

			$customer = $s['customer_details'] ?? [];
			$ship = $s['collected_information']['shipping_details'] ?? ($s['shipping_details'] ?? []);
			$address = $ship['address'] ?? [];
			$rate = $s['shipping_cost']['shipping_rate'] ?? null;
			$pi = $s['payment_intent'] ?? '';
			$delivery_id = is_array($rate) ? (int)($rate['metadata']['cms_delivery_id'] ?? 0) : 0;
			if (!$delivery_id && (int)($s['shipping_cost']['amount_total'] ?? 0) > 0){
				$notes[] = 'Delivery charged, but the method could not be read from Stripe.';
			}

			$this->shop_model->set_order_paid($order_id, [
					'provider' => 'stripe',
					'ref' => is_array($pi) ? (string)($pi['id'] ?? '') : (string)$pi,
					'amount' => (int)($s['amount_total'] ?? 0) / 100,
					'note' => implode(' ', $notes),
					'buyer' => [
							'name' => (string)($customer['name'] ?? ''),
							'email' => (string)($customer['email'] ?? ''),
							'phone' => (string)($customer['phone'] ?? ''),
					],
					'shipping' => [
							'name' => (string)($ship['name'] ?? ''),
							'address1' => (string)($address['line1'] ?? ''),
							'address2' => (string)($address['line2'] ?? ''),
							'city' => (string)($address['city'] ?? ''),
							'postcode' => (string)($address['postal_code'] ?? ''),
							'county' => (string)($address['state'] ?? ''),
							'country' => (string)($address['country'] ?? ''),
					],
					'delivery' => [
							'delivery_id' => $delivery_id,
							'price' => (int)($s['shipping_cost']['amount_total'] ?? 0) / 100,
					],
			]);

			return true;

		} finally {
			if ($lock){
				flock($lock, LOCK_UN);
				fclose($lock);
			}
		}

	}

	/**
	 * Expired Checkout session: forget it on the cart, so the next Checkout click starts a fresh one.
	 */
	function session_expired($session){

		$s = $this->stripe_model->to_array($session);
		$order_id = (int)($s['metadata']['cms_order_id'] ?? 0);
		if ($order_id < 1){
			return;
		}
		$this->load->model('cms/cms_page_panel_model');
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (($order['panel_name'] ?? '') === 'shop/order' && ($order['stripe_session_id'] ?? '') === ($s['id'] ?? '')){
			$this->cms_page_panel_model->update_cms_page_panel($order_id, ['stripe_session_id' => '']);
		}

	}

	/**
	 * apply_paid_session leaves one small lock file per paid order; drop those older than a day.
	 */
	function _delete_old_locks(){

		foreach (glob(cms_path('tmp').'stripe_order_*.lock') ?: [] as $file){
			if (filemtime($file) < time() - 86400){
				@unlink($file);
			}
		}

	}

	/**
	 * Refund on a charge (full or partial, Dashboard or API): the cumulative amount_refunded goes to the shop
	 * order with this payment intent. A charge with no shop order (e.g. a subscription) is logged and skipped.
	 */
	function charge_refunded($charge){

		$this->load->model('shop/shop_model');

		$c = $this->stripe_model->to_array($charge);
		$pi = $c['payment_intent'] ?? '';
		$pi = is_array($pi) ? (string)($pi['id'] ?? '') : (string)$pi;
		$order = $this->shop_model->get_order_by_payment_ref('stripe', $pi);
		if (!$order){
			$this->stripe_model->log_error('Refund without a shop order (other charge type), skipped', 'charge='.($c['id'] ?? '').' payment_intent='.$pi);
			return false;
		}

		return $this->shop_model->set_order_refund((int)$order['cms_page_panel_id'], (int)($c['amount_refunded'] ?? 0) / 100);

	}

	/**
	 * Webhook events for cart orders (Checkout sessions with mode payment, charge refunds). Errors throw (Stripe retries).
	 */
	function handle_webhook_event($type, $object){

		if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded'){
			$this->apply_paid_session($object);
		} else if ($type === 'checkout.session.expired'){
			$this->session_expired($object);
		} else if ($type === 'charge.refunded'){
			$this->charge_refunded($object);
		}

	}
}
