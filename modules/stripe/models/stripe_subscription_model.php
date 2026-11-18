<?php

namespace stripe;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Stripe subscriptions: Checkout (mode subscription), webhook entitlement, manage actions for the subscription module.
 * Needs the user and subscription modules (stripe_model::subscriptions_available()).
 */
class stripe_subscription_model extends \Model {

	function __construct(){

		$this->load->model('stripe/stripe_model');

	}

	/**
	 * Ensure Stripe Customer for user; store id in user meta subscription.stripe_customer_id.
	 */
	function ensure_customer_for_user($user){

		if (empty($user) || !is_array($user)){
			return '';
		}

		$this->load->model('user/user_model');

		$user_id = (int)($user['user_id'] ?? $user['cms_page_panel_id'] ?? 0);
		if ($user_id < 1){
			return '';
		}

		$full = $this->user_model->get_user($user_id);
		if (empty($full)){
			$full = $user;
		}

		$this->load->model('subscription/subscription_model');
		$sub = $this->subscription_model->get_user_subscription($user_id);
		$existing = trim((string)($sub['stripe_customer_id'] ?? ''));
		if ($existing !== ''){
			return $existing;
		}

		$email = trim((string)($full['email'] ?? $user['email'] ?? ''));
		$name = trim(trim((string)($full['first_name'] ?? '')) . ' ' . trim((string)($full['last_name'] ?? '')));

		$params = [
				'metadata' => [
						'cms_user_id' => (string)$user_id,
				],
		];
		if ($email !== ''){
			$params['email'] = $email;
		}
		if ($name !== ''){
			$params['name'] = $name;
		}

		$customer = \Stripe\Customer::create($params);
		$cid = (string)($customer->id ?? '');
		if ($cid !== ''){
			$this->subscription_model->merge_user_subscription($user_id, [
					'stripe_customer_id' => $cid,
			]);
		}

		return $cid;

	}

	/**
	 * @return array{ok:int,redirect?:string,error?:string}
	 */
	function create_subscription_checkout_session($user, $stripe_price_id, $success_url, $cancel_url, $meta = []){

		$stripe_price_id = trim((string)$stripe_price_id);
		if ($stripe_price_id === ''){
			return ['ok' => 0, 'error' => 'Missing Stripe price id for this product/currency'];
		}

		$success_url = $this->stripe_model->absolute_url($success_url);
		$cancel_url = $this->stripe_model->absolute_url($cancel_url);
		if ($success_url === '' || $cancel_url === '' || !preg_match('#^https?://#i', $success_url) || !preg_match('#^https?://#i', $cancel_url)){
			return ['ok' => 0, 'error' => 'Checkout success/cancel URLs must be absolute (https://…)'];
		}

		try {
			$customer_id = $this->ensure_customer_for_user($user);
			if ($customer_id === ''){
				return ['ok' => 0, 'error' => 'Could not create Stripe customer'];
			}

			$session_params = [
					'mode' => 'subscription',
					'customer' => $customer_id,
					'line_items' => [
							[
									'price' => $stripe_price_id,
									'quantity' => 1,
							],
					],
					'success_url' => $success_url,
					'cancel_url' => $cancel_url,
					'client_reference_id' => (string)((int)($user['user_id'] ?? $user['cms_page_panel_id'] ?? 0)),
					'metadata' => is_array($meta) ? $meta : [],
					'subscription_data' => [
							'metadata' => is_array($meta) ? $meta : [],
					],
			];

			$user_id = (int)($user['user_id'] ?? $user['cms_page_panel_id'] ?? 0);
			$currency_id = (int)($meta['cms_currency_id'] ?? 0);
			$idempotency_key = 'sub_co_'.$user_id.'_'.$stripe_price_id.'_'.$currency_id;

			$session = \Stripe\Checkout\Session::create($session_params, [
					'idempotency_key' => $idempotency_key,
			]);
			$url = (string)($session->url ?? '');
			if ($url === ''){
				return $this->stripe_model->fail(
						'Checkout session empty URL user '.$user_id,
						'',
						'Checkout could not be started'
				);
			}

			return ['ok' => 1, 'redirect' => $url, 'session_id' => (string)($session->id ?? '')];

		} catch (\Exception $e){
			$user_id = (int)($user['user_id'] ?? $user['cms_page_panel_id'] ?? 0);
			return $this->stripe_model->fail(
					'Checkout session failed user '.$user_id,
					$e->getMessage(),
					'Checkout could not be started'
			);
		}

	}

	function _webhook_checkout_session($session, $event_type = 'checkout.session.completed'){

		if (empty($session)){
			return;
		}

		$user_id = 0;
		$sub_id = '';
		$product_id = 0;
		$currency_id = 0;
		$session_id = '';

		if (is_object($session)){
			$user_id = (int)($session->client_reference_id ?? 0);
			if ($user_id < 1 && !empty($session->metadata->cms_user_id)){
				$user_id = (int)$session->metadata->cms_user_id;
			}
			$sub_id = (string)($session->subscription ?? '');
			$product_id = !empty($session->metadata->cms_product_id) ? (int)$session->metadata->cms_product_id : 0;
			$currency_id = !empty($session->metadata->cms_currency_id) ? (int)$session->metadata->cms_currency_id : 0;
			$session_id = (string)($session->id ?? '');
		} else {
			$user_id = (int)($session['client_reference_id'] ?? 0);
			if ($user_id < 1 && !empty($session['metadata']['cms_user_id'])){
				$user_id = (int)$session['metadata']['cms_user_id'];
			}
			$sub_id = (string)($session['subscription'] ?? '');
			$product_id = !empty($session['metadata']['cms_product_id']) ? (int)$session['metadata']['cms_product_id'] : 0;
			$currency_id = !empty($session['metadata']['cms_currency_id']) ? (int)$session['metadata']['cms_currency_id'] : 0;
			$session_id = (string)($session['id'] ?? '');
		}

		if ($user_id < 1 || $sub_id === ''){
			$this->stripe_model->log_error(
					'Incomplete checkout session',
					'session='.$session_id.' user='.$user_id.' sub='.$sub_id
			);
			$this->_notify_webhook_event($event_type, [
					'note' => 'Incomplete session (missing user or subscription id)',
					'session_id' => $session_id,
					'user_id' => $user_id,
					'subscription_id' => $sub_id,
			]);
			return;
		}

		try {
			$subscription = \Stripe\Subscription::retrieve($sub_id, [
					'expand' => ['items.data.price'],
			]);
			$facts = $this->_facts_from_stripe_subscription($subscription);
			$facts['product_id'] = $product_id;
			$facts['checkout_session_id'] = $session_id;
			if ($currency_id > 0){
				$facts['currency_id'] = $currency_id;
			}
			if ($product_id > 0){
				$this->load->model('cms/cms_page_panel_model');
				$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
				// Product heading includes monthly/yearly (e.g. "ScoreTutor Premium Monthly")
				$plan_title = trim((string)($product['heading'] ?? ''));
				if ($plan_title === '' && !empty($product['subcategory_id'])){
					$subcat = $this->cms_page_panel_model->get_cms_page_panel((int)$product['subcategory_id']);
					if (!empty($subcat['heading'])){
						$plan_title = (string)$subcat['heading'];
					}
				}
				if ($plan_title !== ''){
					$facts['plan_title'] = $plan_title;
				}
			}
			$this->_apply_entitlement($user_id, $facts);
			$facts['user_id'] = $user_id;
			$this->_enrich_notify_facts($facts);
			$this->_notify_webhook_event($event_type, $facts);
		} catch (\Exception $e){
			$this->stripe_model->log_error('Entitlement failed user '.$user_id, $e->getMessage());
			$this->_notify_webhook_event($event_type, [
					'note' => 'Entitlement failed: '.$e->getMessage(),
					'user_id' => $user_id,
					'stripe_subscription_id' => $sub_id,
					'checkout_session_id' => $session_id,
			]);
			throw $e;
		}

	}

	function _webhook_subscription($subscription, $event_type = 'customer.subscription.updated'){

		if (empty($subscription)){
			return;
		}

		$user_id = $this->_user_id_from_subscription($subscription);
		$facts = $this->_facts_from_stripe_subscription($subscription);
		$facts['user_id'] = $user_id;

		if ($user_id < 1){
			$this->stripe_model->log_error('Webhook subscription missing cms_user_id', $event_type);
			$this->_notify_webhook_event($event_type, array_merge($facts, [
					'note' => 'No cms_user_id on subscription metadata',
			]));
			return;
		}

		$status = strtolower(trim((string)($facts['status'] ?? '')));
		if (in_array($status, ['canceled', 'incomplete_expired'], true)){
			$this->load->model('subscription/subscription_model');
			$this->subscription_model->end_stripe_subscription($user_id, (string)($facts['stripe_subscription_id'] ?? ''));
		} else {
			$this->_apply_entitlement($user_id, $facts);
		}
		$this->_enrich_notify_facts($facts);
		$this->_notify_webhook_event($event_type, $facts);

	}

	function _subscription_id_from_invoice($invoice){

		if (is_object($invoice)){
			if (!empty($invoice->subscription)){
				$sub = $invoice->subscription;
				return is_object($sub) ? (string)($sub->id ?? '') : (string)$sub;
			}
			if (!empty($invoice->parent->subscription_details->subscription)){
				return (string)$invoice->parent->subscription_details->subscription;
			}
			return '';
		}

		if (!is_array($invoice)){
			return '';
		}

		$sub = $invoice['subscription'] ?? '';
		if (is_array($sub)){
			return (string)($sub['id'] ?? '');
		}
		if ($sub !== '' && $sub !== null){
			return (string)$sub;
		}

		$parent = $invoice['parent']['subscription_details']['subscription'] ?? '';
		return is_array($parent) ? (string)($parent['id'] ?? '') : (string)$parent;

	}

	function _webhook_invoice($invoice, $event_type){

		if (empty($invoice)){
			return;
		}

		$sub_id = $this->_subscription_id_from_invoice($invoice);
		if ($sub_id === ''){
			$this->stripe_model->log_error('Invoice without subscription', $event_type);
			return;
		}

		$subscription = \Stripe\Subscription::retrieve($sub_id, [
				'expand' => ['items.data.price'],
		]);
		$this->_webhook_subscription($subscription, $event_type);

	}

	function _webhook_subscription_deleted($subscription, $event_type = 'customer.subscription.deleted'){

		$user_id = $this->_user_id_from_subscription($subscription);
		$facts = $this->_facts_from_stripe_subscription($subscription);
		$facts['user_id'] = $user_id;
		$facts['status'] = 'canceled';

		if ($user_id > 0){
			$this->load->model('subscription/subscription_model');
			$this->subscription_model->end_stripe_subscription($user_id, (string)($facts['stripe_subscription_id'] ?? ''));
		}

		$this->_enrich_notify_facts($facts);
		$this->_notify_webhook_event($event_type, $facts);

	}

	/**
	 * Recipients: CMS technical admin (dropdown) + notification_emails repeater.
	 * @return string[]
	 */
	function get_webhook_notification_recipients(){

		$s = $this->stripe_model->get_settings();
		$emails = [];

		$tech = (string)($s['notify_technical'] ?? 'admin_email');
		if ($tech === 'admin_email'){
			$admin = trim((string)($GLOBALS['config']['admin_email'] ?? ''));
			if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)){
				$emails[] = $admin;
			}
		}

		$rows = $s['notification_emails'] ?? [];
		if (is_array($rows)){
			foreach ($rows as $row){
				if (!is_array($row)){
					continue;
				}
				$e = trim((string)($row['email'] ?? ''));
				if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)){
					$emails[] = $e;
				}
			}
		}

		return array_values(array_unique($emails));

	}

	function _enrich_notify_facts(&$facts){

		$user_id = (int)($facts['user_id'] ?? 0);
		if ($user_id < 1){
			return;
		}

		$this->load->model('user/user_model');
		$user = $this->user_model->get_user($user_id);
		if (!empty($user)){
			$facts['user_email'] = (string)($user['email'] ?? '');
			$facts['user_login'] = (string)($user['loginname'] ?? $user['username'] ?? $user['email'] ?? '');
		}

	}

	/**
	 * Email technical + extra recipients about a handled webhook event.
	 */
	function _notify_webhook_event($event_type, $facts = []){

		$recipients = $this->get_webhook_notification_recipients();
		if ($recipients === []){
			return;
		}

		$facts = is_array($facts) ? $facts : [];
		$host = (string)($_SERVER['HTTP_HOST'] ?? ($GLOBALS['config']['base_host'] ?? 'site'));
		$subject = '[Stripe] '.$event_type.' @ '.$host;

		$lines = [
				'Stripe webhook event',
				'--------------------',
				'Event: '.$event_type,
				'Time: '.date('c'),
				'Host: '.$host,
				'',
		];

		$keys = [
				'note' => 'Note',
				'user_id' => 'CMS user id',
				'user_email' => 'User email',
				'user_login' => 'User login',
				'status' => 'Subscription status',
				'subscription_interval' => 'Interval',
				'subscription_ends' => 'Period end (unix)',
				'plan_title' => 'Plan title',
				'product_id' => 'CMS product id',
				'stripe_subscription_id' => 'Stripe subscription',
				'stripe_customer_id' => 'Stripe customer',
				'subscription_price_id' => 'Stripe price',
				'checkout_session_id' => 'Checkout session',
				'session_id' => 'Session id',
				'subscription_id' => 'Subscription id',
		];

		foreach ($keys as $k => $label){
			if (!array_key_exists($k, $facts) || $facts[$k] === '' || $facts[$k] === null){
				continue;
			}
			$val = $facts[$k];
			if ($k === 'subscription_ends' && is_numeric($val) && (int)$val > 0){
				$val = date('c', (int)$val).' ('.(int)$val.')';
			}
			$lines[] = $label.': '.$val;
		}

		$body = implode("\n", $lines);

		$this->load->model('cms/cms_email_model');
		foreach ($recipients as $to){
			$this->cms_email_model->send_mail($to, $subject, $body, [
					'auto_submitted' => 1,
			]);
		}

	}

	function _facts_from_stripe_subscription($subscription){

		$sub_id = is_object($subscription) ? (string)($subscription->id ?? '') : (string)($subscription['id'] ?? '');
		$status = is_object($subscription) ? (string)($subscription->status ?? '') : (string)($subscription['status'] ?? '');
		$customer = is_object($subscription) ? (string)($subscription->customer ?? '') : (string)($subscription['customer'] ?? '');

		$item = $this->_first_subscription_item($subscription);
		$period_end = $this->_subscription_period_end($subscription, $item);

		$price_id = '';
		$interval = '';
		if (is_object($item)){
			if (!empty($item->price->id)){
				$price_id = (string)$item->price->id;
			} else if (!empty($item->plan->id)){
				// Older plan shape
				$price_id = (string)$item->plan->id;
			}
			if (!empty($item->price->recurring->interval)){
				$interval = (string)$item->price->recurring->interval;
			} else if (!empty($item->plan->interval)){
				$interval = (string)$item->plan->interval;
			}
		} else if (is_array($item)){
			if (!empty($item['price']['id'])){
				$price_id = (string)$item['price']['id'];
			} else if (!empty($item['plan']['id'])){
				$price_id = (string)$item['plan']['id'];
			}
			if (!empty($item['price']['recurring']['interval'])){
				$interval = (string)$item['price']['recurring']['interval'];
			} else if (!empty($item['plan']['interval'])){
				$interval = (string)$item['plan']['interval'];
			}
		}

		$cancel_at_period_end = 0;
		if (is_object($subscription)){
			$cancel_at_period_end = !empty($subscription->cancel_at_period_end) ? 1 : 0;
		} else if (is_array($subscription)){
			$cancel_at_period_end = !empty($subscription['cancel_at_period_end']) ? 1 : 0;
		}

		return [
				'status' => $status,
				'stripe_subscription_id' => $sub_id,
				'stripe_customer_id' => $customer,
				'subscription_ends' => $period_end,
				'subscription_price_id' => $price_id,
				'subscription_interval' => $interval,
				'cancel_at_period_end' => $cancel_at_period_end,
		];

	}

	/**
	 * Retrieve Stripe Subscription for a CMS user (one sub per user via meta id).
	 * @return object|null Stripe Subscription
	 */
	function retrieve_subscription_for_user($user_id){

		$user_id = (int)$user_id;
		if ($user_id < 1){
			return null;
		}

		$this->load->model('subscription/subscription_model');
		$sub = $this->subscription_model->get_user_subscription($user_id);
		$sub_id = trim((string)($sub['stripe_subscription_id'] ?? ''));
		if ($sub_id === ''){
			return null;
		}

		try {
			return \Stripe\Subscription::retrieve($sub_id, [
					'expand' => ['items.data.price'],
			]);
		} catch (\Exception $e) {
			$this->stripe_model->log_error('Retrieve subscription failed user '.$user_id, $e->getMessage());
			return null;
		}

	}

	/**
	 * @return array{state:string,subscription?:object} none|found|ended|missing|error
	 */
	function probe_user_stripe_subscription($user_id){

		$user_id = (int)$user_id;
		if ($user_id < 1){
			return ['state' => 'none'];
		}

		$this->load->model('subscription/subscription_model');
		$sub = $this->subscription_model->get_user_subscription($user_id);
		$sub_id = trim((string)($sub['stripe_subscription_id'] ?? ''));
		if ($sub_id === ''){
			return ['state' => 'none'];
		}

		try {
			$subscription = \Stripe\Subscription::retrieve($sub_id, [
					'expand' => ['items.data.price'],
			]);
		} catch (\Stripe\Exception\InvalidRequestException $e) {
			$code = (string)$e->getStripeCode();
			$status = (int)$e->getHttpStatus();
			if ($status === 404 || $code === 'resource_missing'){
				return ['state' => 'missing'];
			}
			$this->stripe_model->log_error('Probe subscription failed user '.$user_id, $e->getMessage());
			return ['state' => 'error'];
		} catch (\Exception $e) {
			$this->stripe_model->log_error('Probe subscription failed user '.$user_id, $e->getMessage());
			return ['state' => 'error'];
		}

		$status = strtolower(trim((string)($subscription->status ?? '')));
		if (in_array($status, ['canceled', 'incomplete_expired'], true)){
			return ['state' => 'ended', 'subscription' => $subscription];
		}

		return ['state' => 'found', 'subscription' => $subscription];

	}

	/**
	 * Pull Stripe state into CMS meta. One subscription per user.
	 * @return array{ok:int,changed:int,error?:string,subscription?:array}
	 */
	function sync_user_subscription($user_id){

		$user_id = (int)$user_id;
		if ($user_id < 1){
			return ['ok' => 0, 'changed' => 0, 'error' => 'Invalid user'];
		}

		$this->load->model('subscription/subscription_model');
		$before = $this->subscription_model->get_user_subscription($user_id);
		$probe = $this->probe_user_stripe_subscription($user_id);
		$state = (string)($probe['state'] ?? 'error');

		if ($state === 'none' || $state === 'error' || $state === 'ended' || $state === 'missing'){
			return [
					'ok' => 1,
					'changed' => 0,
					'state' => $state,
					'subscription' => $before,
			];
		}

		$subscription = $probe['subscription'] ?? null;
		if (empty($subscription)){
			return [
					'ok' => 1,
					'changed' => 0,
					'subscription' => $before,
			];
		}

		$facts = $this->_facts_from_stripe_subscription($subscription);
		// Keep plan/product from CMS if Stripe does not send titles
		if (!empty($before['plan'])){
			$facts['plan_title'] = $before['plan'];
		}
		if (!empty($before['product_id'])){
			$facts['product_id'] = (int)$before['product_id'];
		}
		if (!empty($before['currency_id'])){
			$facts['currency_id'] = (int)$before['currency_id'];
		}

		$this->subscription_model->apply_entitlement_from_provider($user_id, $facts);
		$after = $this->subscription_model->get_user_subscription($user_id);

		$changed = (json_encode($before) !== json_encode($after)) ? 1 : 0;

		return [
				'ok' => 1,
				'changed' => $changed,
				'state' => 'found',
				'subscription' => $after,
		];

	}

	/**
	 * Toggle cancel_at_period_end. $auto_renew true = keep renewing (cancel_at_period_end false).
	 * @return array{ok:int,error?:string,subscription?:array}
	 */
	function set_auto_renew_for_user($user_id, $auto_renew){

		$user_id = (int)$user_id;
		if ($user_id < 1){
			return ['ok' => 0, 'error' => 'Invalid user'];
		}

		$subscription = $this->retrieve_subscription_for_user($user_id);
		if ($subscription === null || empty($subscription->id)){
			return ['ok' => 0, 'error' => 'No Stripe subscription'];
		}

		try {
			$updated = \Stripe\Subscription::update($subscription->id, [
					'cancel_at_period_end' => empty($auto_renew) ? true : false,
			]);
		} catch (\Exception $e) {
			return $this->stripe_model->fail(
					'Auto-renew update failed user '.$user_id,
					$e->getMessage(),
					'Could not update subscription'
			);
		}

		$this->load->model('subscription/subscription_model');
		$facts = $this->_facts_from_stripe_subscription($updated);
		$before = $this->subscription_model->get_user_subscription($user_id);
		if (!empty($before['plan'])){
			$facts['plan_title'] = $before['plan'];
		}
		if (!empty($before['product_id'])){
			$facts['product_id'] = (int)$before['product_id'];
		}
		if (!empty($before['currency_id'])){
			$facts['currency_id'] = (int)$before['currency_id'];
		}
		$this->subscription_model->apply_entitlement_from_provider($user_id, $facts);

		return [
				'ok' => 1,
				'subscription' => $this->subscription_model->get_user_subscription($user_id),
		];

	}

	/**
	 * Sets one subscription not to renew (account deletion). ok with stopped 1 when it is set to end now or
	 * already was, stopped 0 when there is nothing billing (gone or ended); ok 0 when Stripe could not be asked.
	 */
	function stop_renewal_by_id($subscription_id){

		$subscription_id = trim((string)$subscription_id);
		if ($subscription_id === ''){
			return ['ok' => 1, 'stopped' => 0];
		}

		try {
			$subscription = \Stripe\Subscription::retrieve($subscription_id);
		} catch (\Stripe\Exception\InvalidRequestException $e) {
			if ((int)$e->getHttpStatus() === 404 || (string)$e->getStripeCode() === 'resource_missing'){
				return ['ok' => 1, 'stopped' => 0];
			}
			$this->stripe_model->log_error('Stop renewal: retrieve failed '.$subscription_id, $e->getMessage());
			return ['ok' => 0];
		} catch (\Exception $e) {
			$this->stripe_model->log_error('Stop renewal: retrieve failed '.$subscription_id, $e->getMessage());
			return ['ok' => 0];
		}

		$status = strtolower(trim((string)($subscription->status ?? '')));
		if (in_array($status, ['canceled', 'incomplete_expired'], true)){
			return ['ok' => 1, 'stopped' => 0];
		}
		if (!empty($subscription->cancel_at_period_end)){
			return ['ok' => 1, 'stopped' => 1];
		}

		try {
			\Stripe\Subscription::update($subscription_id, ['cancel_at_period_end' => true]);
		} catch (\Exception $e) {
			$this->stripe_model->log_error('Stop renewal: update failed '.$subscription_id, $e->getMessage());
			return ['ok' => 0];
		}

		return ['ok' => 1, 'stopped' => 1];

	}

	/**
	 * Stripe Customer Portal deep-link: payment method update only (no cancel UI).
	 * flow_data.type = payment_method_update
	 *
	 * @return array{ok:int,redirect?:string,error?:string}
	 */
	function create_payment_method_update_session($user_id, $return_url = ''){

		$user_id = (int)$user_id;
		if ($user_id < 1){
			return ['ok' => 0, 'error' => 'Invalid user'];
		}

		$this->load->model('subscription/subscription_model');
		$sub = $this->subscription_model->get_user_subscription($user_id);
		$customer_id = trim((string)($sub['stripe_customer_id'] ?? ''));
		if ($customer_id === ''){
			return ['ok' => 0, 'error' => 'No Stripe customer'];
		}

		$return_url = trim((string)$return_url);
		if ($return_url === ''){
			$return_url = (string)($_SERVER['HTTP_REFERER'] ?? '');
		}
		$return_url = $this->stripe_model->absolute_url($return_url);
		if ($return_url === '' || !preg_match('#^https?://#i', $return_url)){
			// Fall back to site home
			$return_url = $this->stripe_model->absolute_url('/');
		}

		try {
			$session = \Stripe\BillingPortal\Session::create([
					'customer' => $customer_id,
					'return_url' => $return_url,
					'flow_data' => [
							'type' => 'payment_method_update',
							'after_completion' => [
									'type' => 'redirect',
									'redirect' => [
											'return_url' => $return_url,
									],
							],
					],
			]);
			$url = (string)($session->url ?? '');
			if ($url === ''){
				return $this->stripe_model->fail(
						'Payment method portal empty URL user '.$user_id,
						'',
						'Could not open payment method update'
				);
			}
			return ['ok' => 1, 'redirect' => $url];
		} catch (\Exception $e) {
			return $this->stripe_model->fail(
					'Payment method portal failed user '.$user_id,
					$e->getMessage(),
					'Could not open payment method update'
			);
		}

	}

	/**
	 * Change subscription price (proration). One item per user subscription.
	 * @return array{ok:int,error?:string,subscription?:array}
	 */
	function change_subscription_price_for_user($user_id, $stripe_price_id){

		$user_id = (int)$user_id;
		$stripe_price_id = trim((string)$stripe_price_id);
		if ($user_id < 1 || $stripe_price_id === ''){
			return ['ok' => 0, 'error' => 'Missing user or price'];
		}

		$subscription = $this->retrieve_subscription_for_user($user_id);
		if ($subscription === null || empty($subscription->id)){
			return ['ok' => 0, 'error' => 'No Stripe subscription'];
		}

		$item = $this->_first_subscription_item($subscription);
		$item_id = '';
		if (is_object($item) && !empty($item->id)){
			$item_id = (string)$item->id;
		}
		if ($item_id === ''){
			return ['ok' => 0, 'error' => 'No subscription item'];
		}

		try {
			$updated = \Stripe\Subscription::update($subscription->id, [
					'items' => [
							[
									'id' => $item_id,
									'price' => $stripe_price_id,
							],
					],
					'proration_behavior' => 'create_prorations',
					// Keep a single item — one subscription per user
			]);
		} catch (\Exception $e) {
			return $this->stripe_model->fail(
					'Change plan failed user '.$user_id,
					$e->getMessage(),
					'Could not change plan'
			);
		}

		$this->load->model('subscription/subscription_model');
		$facts = $this->_facts_from_stripe_subscription($updated);
		$before = $this->subscription_model->get_user_subscription($user_id);
		if (!empty($before['currency_id'])){
			$facts['currency_id'] = (int)$before['currency_id'];
		}
		if (!empty($before['product_id'])){
			$facts['product_id'] = (int)$before['product_id'];
		}
		if (!empty($before['plan'])){
			$facts['plan_title'] = (string)$before['plan'];
		}
		$this->subscription_model->apply_entitlement_from_provider($user_id, $facts);

		return [
				'ok' => 1,
				'subscription' => $this->subscription_model->get_user_subscription($user_id),
		];

	}

	/**
	 * First subscription item (object or array), or null.
	 * Stripe API ≥ 2025-03-31: billing period lives on the item, not the subscription.
	 */
	function _first_subscription_item($subscription){

		if (is_object($subscription)){
			if (!empty($subscription->items->data[0])){
				return $subscription->items->data[0];
			}
			return null;
		}

		if (is_array($subscription)){
			if (!empty($subscription['items']['data'][0]) && is_array($subscription['items']['data'][0])){
				return $subscription['items']['data'][0];
			}
		}

		return null;

	}

	/**
	 * Current period end as unix timestamp (0 if unknown).
	 * Order: subscription.current_period_end (legacy API) → item.current_period_end
	 * (Basil+ API) → trial_end → cancel_at.
	 */
	function _subscription_period_end($subscription, $item = null){

		if ($item === null){
			$item = $this->_first_subscription_item($subscription);
		}

		// Top-level (pre–2025-03-31 API versions)
		if (is_object($subscription) && !empty($subscription->current_period_end)){
			$ts = (int)$subscription->current_period_end;
			if ($ts > 0){
				return $ts;
			}
		}
		if (is_array($subscription) && !empty($subscription['current_period_end'])){
			$ts = (int)$subscription['current_period_end'];
			if ($ts > 0){
				return $ts;
			}
		}

		// Item-level (API 2025-03-31.basil+)
		if (is_object($item) && !empty($item->current_period_end)){
			$ts = (int)$item->current_period_end;
			if ($ts > 0){
				return $ts;
			}
		}
		if (is_array($item) && !empty($item['current_period_end'])){
			$ts = (int)$item['current_period_end'];
			if ($ts > 0){
				return $ts;
			}
		}

		// Trial / scheduled cancel as last resorts
		if (is_object($subscription)){
			if (!empty($subscription->trial_end)){
				$ts = (int)$subscription->trial_end;
				if ($ts > 0){
					return $ts;
				}
			}
			if (!empty($subscription->cancel_at)){
				$ts = (int)$subscription->cancel_at;
				if ($ts > 0){
					return $ts;
				}
			}
		} else if (is_array($subscription)){
			if (!empty($subscription['trial_end'])){
				$ts = (int)$subscription['trial_end'];
				if ($ts > 0){
					return $ts;
				}
			}
			if (!empty($subscription['cancel_at'])){
				$ts = (int)$subscription['cancel_at'];
				if ($ts > 0){
					return $ts;
				}
			}
		}

		return 0;

	}

	function _user_id_from_subscription($subscription){

		if (is_object($subscription) && !empty($subscription->metadata->cms_user_id)){
			return (int)$subscription->metadata->cms_user_id;
		}
		if (is_array($subscription) && !empty($subscription['metadata']['cms_user_id'])){
			return (int)$subscription['metadata']['cms_user_id'];
		}
		return 0;

	}

	/**
	 * Edge adapter: only load subscription when module is installed.
	 */
	function _apply_entitlement($user_id, $facts){

		if (empty($GLOBALS['config']['modules']) || !in_array('subscription', $GLOBALS['config']['modules'], true)){
			return;
		}

		$this->load->model('subscription/subscription_model');
		$ok = $this->subscription_model->apply_entitlement_from_provider($user_id, $facts);
		if ($ok === false){
			throw new \RuntimeException('Entitlement apply failed for user '.$user_id);
		}

	}

	/**
	 * Webhook events for subscriptions. Errors throw (Stripe retries).
	 */
	function handle_webhook_event($type, $obj){

		if ($type === 'checkout.session.completed'){
			$this->_webhook_checkout_session($obj, $type);
		} else if ($type === 'customer.subscription.updated' || $type === 'customer.subscription.created'){
			$this->_webhook_subscription($obj, $type);
		} else if ($type === 'customer.subscription.deleted'){
			$this->_webhook_subscription_deleted($obj, $type);
		} else if ($type === 'invoice.paid' || $type === 'invoice.payment_succeeded' || $type === 'invoice.payment_failed'){
			$this->_webhook_invoice($obj, $type);
		}

	}
}
