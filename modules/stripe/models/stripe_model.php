<?php

namespace stripe;

if (!defined('BASEPATH')) exit('No direct script access allowed');

// Composer: stripe/stripe-php → vendor/stripe/stripe-php/
$stripe_init = $GLOBALS['config']['base_path'].'vendor/stripe/stripe-php/init.php';
if (is_file($stripe_init)){
	require_once $stripe_init;
} else if (is_file($GLOBALS['config']['base_path'].'vendor/autoload.php')){
	require_once $GLOBALS['config']['base_path'].'vendor/autoload.php';
}

/**
 * Stripe core: SDK and API key, absolute URLs, settings, error log, webhook verification and dispatch.
 * Cart orders: stripe_order_model. Subscriptions: stripe_subscription_model.
 */
class stripe_model extends \Model {

	function __construct(){

		$this->_set_api_key();

	}

	function _set_api_key(){

		$key = $GLOBALS['config']['stripe_secret'] ?? '';
		if ($key !== ''){
			\Stripe\Stripe::setApiKey($key);
		}
		// Avoid TelemetryId mkdir under $HOME/.config/stripe (often unwritable on web hosts)
		if (class_exists('\\Stripe\\Stripe') && method_exists('\\Stripe\\Stripe', 'setEnableTelemetry')){
			\Stripe\Stripe::setEnableTelemetry(false);
		}

	}

	/**
	 * Stripe Checkout requires absolute https?:// URLs — CMS links are often path-only.
	 */
	function absolute_url($url){

		$url = trim((string)$url);
		if ($url === ''){
			return '';
		}
		if (preg_match('#^https?://#i', $url)){
			return $url;
		}
		// protocol-relative
		if (strpos($url, '//') === 0){
			$proto = (!empty($GLOBALS['config']['protocol']))
					? $GLOBALS['config']['protocol']
					: ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
			return $proto.':'.$url;
		}

		$host = trim((string)($GLOBALS['config']['base_host'] ?? ''));
		if ($host === ''){
			$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
			$host = ($https ? 'https://' : 'http://').($_SERVER['HTTP_HOST'] ?? 'localhost');
		}
		$host = rtrim($host, '/');

		$base = (string)($GLOBALS['config']['base_url'] ?? '/');
		// base_url may be "/" or "/music/"
		if ($url[0] === '/'){
			// Absolute path on site: host + path (do not double base_url if already full path)
			return $host.$url;
		}

		$base = rtrim($base, '/').'/';
		if (strpos($base, 'http') === 0){
			return rtrim($base, '/').'/'.ltrim($url, '/');
		}

		return $host.rtrim($base, '/').'/'.ltrim($url, '/');

	}

	function get_settings(){

		$this->load->model('cms/cms_page_panel_model');
		$s = $this->cms_page_panel_model->get_cms_page_panel_settings('stripe/stripe');
		return is_array($s) ? $s : [];

	}

	function get_webhook_secret(){

		$s = $this->get_settings();
		return trim((string)($s['webhook_secret'] ?? ($GLOBALS['config']['stripe_webhook_secret'] ?? '')));

	}

	/**
	 * Digest via error_log_user (PHP User → cms_log_rotate). Details also in cache/stripe.log.
	 */
	function log_error($short, $detail = ''){

		$short = function_exists('cms_error_one_line') ? cms_error_one_line($short) : trim((string)$short);
		if ($short === ''){
			$short = 'Stripe error';
		}
		if (function_exists('error_log_user')){
			error_log_user('CMS error [stripe]: '.$short);
		}

		$line = $short;
		$detail = trim((string)$detail);
		if ($detail !== ''){
			$detail = function_exists('cms_error_one_line') ? cms_error_one_line($detail) : $detail;
			$line .= ' | '.$detail;
		}

		@file_put_contents(cms_path('log').'stripe.log', date('c').' '.$line."\n", FILE_APPEND | LOCK_EX);

	}

	function fail($short, $detail, $public){

		$this->log_error($short, $detail);

		return ['ok' => 0, 'error' => $public];

	}

	/**
	 * Resolve optional Checkout success/cancel links from Stripe settings (CMS link fields).
	 * @return array{success_url:string,cancel_url:string}
	 */
	function get_checkout_link_urls(){

		$s = $this->get_settings();

		return [
				'success_url' => $this->link_url($s['success_link'] ?? ''),
				'cancel_url' => $this->link_url($s['cancel_link'] ?? ''),
		];

	}

	/**
	 * A CMS link field value of Stripe settings → absolute URL, or '' when empty.
	 */
	function link_url($link){

		if (empty($link) || !function_exists('_l')){
			return '';
		}
		$u = _l($link, false);

		return is_string($u) && $u !== '' ? $this->absolute_url($u) : '';

	}

	/**
	 * Stripe object or array → plain array (webhook payloads are objects when signed, arrays in DEV tests).
	 */
	function to_array($obj){

		if (is_object($obj) && method_exists($obj, 'toArray')){
			return $obj->toArray();
		}
		return is_array($obj) ? $obj : [];

	}

	function module_enabled($module){

		return in_array($module, $GLOBALS['config']['modules'] ?? [], true);

	}

	/**
	 * Subscriptions need the user module (accounts) and the subscription module (plans, entitlement).
	 */
	function subscriptions_available(){

		return $this->module_enabled('user') && $this->module_enabled('subscription');

	}

	/**
	 * Process raw Stripe webhook. Entitlement applied via subscription module when present.
	 */
	function handle_webhook_request(){

		$payload = @file_get_contents('php://input');
		if ($payload === false){
			$payload = '';
		}

		$secret = $this->get_webhook_secret();
		$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
		$event = null;
		$is_dev = !empty($GLOBALS['config']['environment'])
				&& strtoupper((string)$GLOBALS['config']['environment']) === 'DEV';

		// Unsigned JSON is a local test tool only: DEV, no signing secret configured, and a direct request
		// from this machine (not through a proxy). Everything else must carry a valid Stripe signature.
		$unsigned_ok = $is_dev && $secret === ''
				&& in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
				&& empty($_SERVER['HTTP_X_FORWARDED_FOR']);

		if ($secret === '' && !$unsigned_ok){
			$this->log_error($is_dev ? 'Unsigned webhook refused (DEV accepts it from this machine only)' : 'Webhook secret not configured');
			http_response_code($is_dev ? 400 : 500);
			print(json_encode(['ok' => 0, 'error' => 'Webhook secret not configured']));
			exit();
		}
		if ($secret !== '' && $sig === ''){
			$this->log_error('Missing Stripe-Signature');
			http_response_code(400);
			print(json_encode(['ok' => 0, 'error' => 'Missing Stripe-Signature']));
			exit();
		}

		try {
			if ($secret !== ''){
				$event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
			} else {
				$data = json_decode($payload, true);
				if (is_array($data)){
					$event = $data;
				}
			}
		} catch (\Exception $e){
			$this->log_error('Webhook signature failed', $e->getMessage());
			http_response_code(400);
			print(json_encode(['ok' => 0, 'error' => 'Invalid signature']));
			exit();
		}

		if (empty($event)){
			http_response_code(400);
			$this->log_error('Invalid webhook payload');
			print(json_encode(['ok' => 0, 'error' => 'Invalid payload']));
			exit();
		}

		$type = is_object($event) ? (string)($event->type ?? '') : (string)($event['type'] ?? '');
		$obj = is_object($event) ? ($event->data->object ?? null) : ($event['data']['object'] ?? null);

		// Cart orders are Checkout sessions with mode payment, and refunds (charge.refunded, matched to an order by its
		// payment intent); every other handled event is a subscription event.
		$is_order = (strpos($type, 'checkout.session.') === 0 && ($this->to_array($obj)['mode'] ?? '') === 'payment')
				|| $type === 'charge.refunded';

		// Stripe sends an event again until it gets a 2xx, and may send it twice: each event id is handled once
		$event_id = is_object($event) ? (string)($event->id ?? '') : (string)($event['id'] ?? '');
		$claim = $this->webhook_event_claim($event_id);
		if ($claim === 'done'){
			print(json_encode(['ok' => 1, 'duplicate' => 1]));
			exit();
		}
		if ($claim === 'busy'){
			// The same event is being handled by another request right now: Stripe tries again later
			http_response_code(409);
			print(json_encode(['ok' => 0, 'error' => 'Event in progress']));
			exit();
		}

		try {
			if ($is_order && $this->module_enabled('shop')){
				$this->load->model('stripe/stripe_order_model');
				$this->stripe_order_model->handle_webhook_event($type, $obj);
			} else if (!$is_order && $this->subscriptions_available()){
				$this->load->model('stripe/stripe_subscription_model');
				$this->stripe_subscription_model->handle_webhook_event($type, $obj);
			} else if ($is_order || preg_match('/^(checkout\.session\.completed|customer\.subscription\.|invoice\.)/', $type)){
				// An event one of the two models handles, but its modules are missing here. Other events are ignored.
				$this->log_error('Webhook event '.$type.' skipped: '.($is_order ? 'shop' : 'user / subscription').' module not installed');
			}
		} catch (\Exception $e){
			$this->webhook_event_release($event_id);
			$this->log_error('Webhook handler failed '.$type, $e->getMessage());
			http_response_code(500);
			print(json_encode(['ok' => 0, 'error' => 'Handler error']));
			exit();
		}

		$this->webhook_event_done($event_id);
		print(json_encode(['ok' => 1]));
		exit();

	}

	/** Handled webhook event ids (tmp/stripe_webhook_events.json): id => [state busy|done, time]. */
	function _webhook_events_file(){

		return cms_path('tmp').'stripe_webhook_events.json';

	}

	/**
	 * Change the event file under one exclusive lock: $fn gets the list (older than 7 days dropped, longer than
	 * Stripe's 3 days of retries) and returns [list, result].
	 */
	function _webhook_events_update(callable $fn){

		$handle = @fopen($this->_webhook_events_file(), 'c+');
		if ($handle === false){
			$this->log_error('Webhook event file cannot be opened');
			list(, $result) = $fn([]);
			return $result;
		}
		flock($handle, LOCK_EX);
		$events = json_decode((string)stream_get_contents($handle), true);
		$since = time() - 7 * 86400;
		$events = array_filter(is_array($events) ? $events : [], function($row) use ($since){
			return is_array($row) && (int)($row['time'] ?? 0) > $since;
		});
		list($events, $result) = $fn($events);
		ftruncate($handle, 0);
		rewind($handle);
		fwrite($handle, json_encode($events));
		fflush($handle);
		flock($handle, LOCK_UN);
		fclose($handle);

		return $result;

	}

	/**
	 * 'new' (this request handles it), 'done' (handled before: answer 200 again) or 'busy' (another request is
	 * on it, for up to 5 minutes). An event without an id (a local unsigned test) is always 'new'.
	 */
	function webhook_event_claim($event_id){

		if (!preg_match('/^evt_[A-Za-z0-9_]{1,250}$/', (string)$event_id)){
			return 'new';
		}

		return $this->_webhook_events_update(function($events) use ($event_id){
			$row = $events[$event_id] ?? null;
			if (($row['state'] ?? '') === 'done'){
				return [$events, 'done'];
			}
			if (($row['state'] ?? '') === 'busy' && (time() - (int)$row['time']) < 300){
				return [$events, 'busy'];
			}
			$events[$event_id] = ['state' => 'busy', 'time' => time()];
			return [$events, 'new'];
		});

	}

	function webhook_event_done($event_id){

		if (!preg_match('/^evt_[A-Za-z0-9_]{1,250}$/', (string)$event_id)){
			return;
		}
		$this->_webhook_events_update(function($events) use ($event_id){
			$events[$event_id] = ['state' => 'done', 'time' => time()];
			return [$events, null];
		});

	}

	/** A failed event: Stripe's retry handles it again. */
	function webhook_event_release($event_id){

		$this->_webhook_events_update(function($events) use ($event_id){
			unset($events[(string)$event_id]);
			return [$events, null];
		});

	}

}
