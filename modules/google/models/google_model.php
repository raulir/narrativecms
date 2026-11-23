<?php

namespace google;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Play Billing for the Android app.
 * The website checkout provider stays Stripe. This module is called by the app
 * and by the subscription domain when a sale must be reported or confirmed.
 */
class google_model extends \Model {

	const META_TRANSACTION = 'google_transaction';
	const META_PLAY = 'google_play';
	const META_PROVIDER = 'subscription_provider';
	const TOKEN_WINDOW = 86400;

	function get_settings(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('google/google');
		return is_array($settings) ? $settings : [];

	}

	function package_name(){

		$settings = $this->get_settings();
		$name = trim((string)($settings['package_name'] ?? ''));
		return $name !== '' ? $name : 'com.scoretutor.app';

	}

	/**
	 * Pending external-transaction record. One object, replaced by a newer tap
	 * until the first sale has been reported.
	 */
	function save_transaction($user_id, $token, $play_product_id, $product_id, $currency_id){

		$user_id = (int)$user_id;
		$token = trim((string)$token);
		if ($user_id < 1 || $token === ''){
			return false;
		}

		// Checked on the way in: the token and the Play id are reported to Google with the sale, the plan with them
		$play_product_id = trim((string)$play_product_id);
		$product_id = (int)$product_id;
		$refused = '';
		if (!$this->play_token_ok($token)){
			$refused = 'a malformed token';
		} else if ($play_product_id !== ''){
			if (!$this->play_id_ok($play_product_id) || $this->play_product_for($play_product_id) !== $product_id){
				$refused = 'plan '.$product_id.' that does not match Play product "'.substr($play_product_id, 0, 160).'"';
			}
		} else {
			$this->load->model('cms/cms_page_panel_model');
			$product = $product_id > 0 ? $this->cms_page_panel_model->get_cms_page_panel($product_id) : [];
			if (!is_array($product) || ($product['panel_name'] ?? '') !== 'shop/product'){
				$refused = 'plan '.$product_id.' that is not a shop product';
			}
		}
		if ($refused !== ''){
			error_log_user('google save_transaction: refused '.$refused.' for user '.$user_id);
			return false;
		}

		$this->load->model('user/user_model');
		$current = $this->transaction_for_user($user_id);
		if (!empty($current['active'])){
			return true;
		}

		return $this->user_model->set_user_meta_for_id($user_id, self::META_TRANSACTION, [
				'token' => $token,
				'time' => time(),
				'google_package_id' => trim((string)$play_product_id),
				'product_id' => (int)$product_id,
				'currency' => (int)$currency_id,
				'active' => 0,
		]);

	}

	function transaction_for_user($user_id){

		$this->load->model('user/user_model');
		$value = $this->user_model->get_user_meta_value_for_id((int)$user_id, self::META_TRANSACTION, []);
		return is_array($value) ? $value : [];

	}

	function provider_for_user($user_id){

		$this->load->model('user/user_model');
		$provider = trim((string)$this->user_model->get_user_meta_value_for_id((int)$user_id, self::META_PROVIDER, ''));
		if ($provider === 'google' || $provider === 'stripe'){
			return $provider;
		}
		return 'stripe';

	}

	function set_provider($user_id, $provider){

		if ($provider !== 'google' && $provider !== 'stripe'){
			return false;
		}
		$this->load->model('user/user_model');
		return $this->user_model->set_user_meta_for_id((int)$user_id, self::META_PROVIDER, $provider);

	}

	/**
	 * After Stripe has recorded a purchase or a renewal.
	 * The first report needs a token younger than 24 hours and the same package.
	 * Later renewals report while active is set, including after that window.
	 */
	function report_stripe_sale($user_id, $facts){

		$user_id = (int)$user_id;
		if ($user_id < 1 || !is_array($facts)){
			return false;
		}

		if (empty($facts['stripe_subscription_id'])){
			return false;
		}

		$status = strtolower(trim((string)($facts['status'] ?? '')));
		if (!in_array($status, ['active', 'trialing'], true)){
			return false;
		}

		$tx = $this->transaction_for_user($user_id);
		$token = trim((string)($tx['token'] ?? ''));
		if ($token === ''){
			return false;
		}

		$ends = trim((string)($facts['subscription_ends'] ?? ''));
		$active = !empty($tx['active']);

		if (!$active){
			$time = (int)($tx['time'] ?? 0);
			if ($time < 1 || (time() - $time) >= self::TOKEN_WINDOW){
				return false;
			}
			if ((int)($tx['product_id'] ?? 0) !== (int)($facts['product_id'] ?? 0)){
				return false;
			}
			if ((int)($tx['currency'] ?? 0) !== (int)($facts['currency_id'] ?? 0)){
				return false;
			}
			$external_id = $this->external_id($user_id, 'p'.(int)$tx['product_id'].'c'.(int)$tx['currency'].'t'.$time);
			$ok = $this->create_external_transaction($user_id, $tx, $external_id, $token, '');
			if (!$ok){
				return false;
			}
			$tx['active'] = 1;
			$tx['external_id'] = $external_id;
			$tx['reported_ends'] = $ends;
			$this->load->model('user/user_model');
			$this->user_model->set_user_meta_for_id($user_id, self::META_TRANSACTION, $tx);
			return true;
		}

		$external_id = trim((string)($tx['external_id'] ?? ''));
		if ($external_id === '' || $ends === '' || $ends === (string)($tx['reported_ends'] ?? '')){
			return false;
		}

		$renew_id = $this->external_id($user_id, 'e'.substr(sha1($ends), 0, 20));
		$ok = $this->create_external_transaction($user_id, $tx, $renew_id, '', $external_id);
		if (!$ok){
			return false;
		}
		$tx['reported_ends'] = $ends;
		$this->load->model('user/user_model');
		$this->user_model->set_user_meta_for_id($user_id, self::META_TRANSACTION, $tx);
		return true;

	}

	/**
	 * Confirm a purchase token with Google before the entitlement is written.
	 */
	function confirm_purchase($user_id, $product_id, $currency_id, $purchase_token, $play_product_id){

		$user_id = (int)$user_id;
		$purchase_token = trim((string)$purchase_token);
		if ($user_id < 1 || $purchase_token === ''){
			return ['ok' => 0, 'error' => 'Missing purchase'];
		}
		if (!$this->play_token_ok($purchase_token)){
			error_log_user('google confirm_purchase: refused a malformed purchase token for user '.$user_id);
			return ['ok' => 0, 'error' => 'Bad purchase token'];
		}
		// A posted plan must be the one whose price row has this Play product; Google then confirms the product
		if ((int)$product_id > 0){
			$play_id = trim((string)$play_product_id);
			if (!$this->play_id_ok($play_id) || $this->play_product_for($play_id) !== (int)$product_id){
				error_log_user('google confirm_purchase: plan '.(int)$product_id.' does not match Play product "'.substr($play_id, 0, 160).'" for user '.$user_id);
				return ['ok' => 0, 'error' => 'This Play purchase is for a different plan'];
			}
		}

		$remote = $this->fetch_subscription($purchase_token);
		if (empty($remote['ok'])){
			return $remote;
		}

		$play_product_id = trim((string)$play_product_id);
		if (empty($remote['active'])){
			return ['ok' => 0, 'error' => 'The Play purchase is not active'];
		}
		$remote_user = (int)($remote['user_id'] ?? 0);
		if ($remote_user < 1 || $remote_user !== $user_id){
			return ['ok' => 0, 'error' => 'This Play purchase is for a different account'];
		}
		list($want_product, $want_plan) = $this->play_id_parts($play_product_id);
		$remote_product = trim((string)($remote['play_product_id'] ?? ''));
		$remote_plan = trim((string)($remote['play_base_plan_id'] ?? ''));
		if ($want_product === '' || $remote_product === '' || $remote_product !== $want_product){
			return ['ok' => 0, 'error' => 'This Play purchase is for a different package'];
		}
		if ($want_plan !== '' && $remote_plan !== $want_plan){
			return ['ok' => 0, 'error' => 'This Play purchase is for a different package'];
		}

		$stored_id = $want_plan === '' ? $remote_product : $this->play_id_value($remote_product, $remote_plan);
		// A purchase sent again by the app (app_play_purchases) knows only Play's product: find the plan by it.
		// It is taken only as the notification would take it: not over a current Stripe subscription.
		if ((int)$product_id < 1){
			if (!$this->notification_makes_current($user_id)){
				return ['ok' => 0, 'error' => 'Another subscription is current'];
			}
			foreach ([$this->play_id_value($remote_product, $remote_plan), $remote_product] as $key){
				$match = $this->match_play_product($key);
				if (!empty($match['product_id'])){
					$product_id = (int)$match['product_id'];
					$stored_id = $key;
					break;
				}
			}
			if ((int)$product_id < 1){
				return ['ok' => 0, 'error' => 'No plan has this Play product'];
			}
		}
		$page_currency = (int)$currency_id;
		$currency_id = $this->shop_default_currency_id();
		if ($currency_id < 1){
			$currency_id = $page_currency;
		}

		$this->load->model('user/user_model');
		$this->user_model->set_user_meta_for_id($user_id, self::META_PLAY, [
				'purchase_token' => $purchase_token,
				'product_id' => (int)$product_id,
				'currency' => $currency_id,
				'google_package_id' => $stored_id,
		]);
		$this->set_provider($user_id, 'google');

		$result = $this->apply_remote($user_id, $remote, (int)$product_id, $currency_id, $stored_id, true);
		if (!empty($result['ok'])){
			$this->acknowledge($purchase_token, $remote);
		}
		return $result;

	}

	/**
	 * Tell Google the purchase is granted. An unacknowledged purchase is refunded after 3 days, so the server
	 * does this itself once the subscription is written, not only the app. Failure is logged and tried again
	 * by the next confirm or notification.
	 */
	function acknowledge($purchase_token, $remote){

		if (!empty($remote['acknowledged']) || empty($remote['active'])){
			return true;
		}
		$product = trim((string)($remote['play_product_id'] ?? ''));
		if ($product === ''){
			return false;
		}

		$path = 'applications/'.rawurlencode($this->package_name()).'/purchases/subscriptions/'
				.rawurlencode($product).'/tokens/'.rawurlencode($purchase_token).':acknowledge';
		$response = $this->play_request('POST', $path, (object)[], true);
		if (empty($response['ok'])){
			$this->log('Play acknowledge failed for '.$product);
			return false;
		}
		return true;

	}

	/**
	 * A notification for a purchase whose confirm from the app never arrived: it becomes the user's current
	 * subscription unless another paid subscription (Stripe) is current. Then it stays unacknowledged, and
	 * Google refunds it.
	 */
	function notification_makes_current($user_id){

		if ($this->provider_for_user($user_id) === 'google'){
			return true;
		}
		$this->load->model('subscription/subscription_model');
		$current = $this->subscription_model->get_user_subscription($user_id);
		$active = ($current['active'] ?? '0');
		$paid = ($active === '1' || $active === 1 || $active === true);
		return !($paid && trim((string)($current['stripe_subscription_id'] ?? '')) !== '');

	}

	function sync_user($user_id){

		$user_id = (int)$user_id;
		$this->load->model('user/user_model');
		$play = $this->user_model->get_user_meta_value_for_id($user_id, self::META_PLAY, []);
		if (!is_array($play)){
			$play = [];
		}
		$token = trim((string)($play['purchase_token'] ?? ''));
		if ($token === ''){
			return ['ok' => 1, 'changed' => 0];
		}

		$remote = $this->fetch_subscription($token);
		if (empty($remote['ok'])){
			return $remote;
		}

		$make_current = $this->provider_for_user($user_id) === 'google';
		$result = $this->apply_remote(
				$user_id,
				$remote,
				(int)($play['product_id'] ?? 0),
				(int)($play['currency'] ?? 0),
				trim((string)($play['google_package_id'] ?? '')),
				$make_current
		);
		// My subscription opened: a granted purchase still unacknowledged is acknowledged now
		if (!empty($result['ok']) && $make_current){
			$this->acknowledge($token, $remote);
		}
		return $result;

	}

	/**
	 * Real-time developer notification. The purchase is read from Google again.
	 */
	function bearer_token(){

		$headers = [];
		if (!empty($_SERVER['HTTP_AUTHORIZATION'])){
			$headers[] = (string)$_SERVER['HTTP_AUTHORIZATION'];
		}
		if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])){
			$headers[] = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
		}
		if (function_exists('apache_request_headers')){
			$apache = apache_request_headers();
			if (is_array($apache)){
				foreach ($apache as $name => $value){
					if (strcasecmp((string)$name, 'Authorization') === 0){
						$headers[] = (string)$value;
					}
				}
			}
		}

		foreach ($headers as $header){
			if (stripos($header, 'Bearer ') !== 0){
				continue;
			}
			$token = trim(substr($header, 7));
			if ($token !== ''){
				return $token;
			}
		}
		return '';

	}

	/**
	 * Whether the push request carries a Google-signed token for our audience from our push service account.
	 * Returns '' when it does, else the reason (not_configured, no_token, tokeninfo, audience, issuer, email, expired).
	 * The sender email is required: any Google account can sign an id token for any audience.
	 */
	function notification_refusal(){

		$settings = $this->get_settings();
		$audience = trim((string)($settings['notify_audience'] ?? ''));
		$sender = strtolower(trim((string)($settings['notify_email'] ?? '')));
		if ($audience === '' || $sender === ''){
			return 'not_configured';
		}
		$token = $this->bearer_token();
		if ($token === ''){
			return 'no_token';
		}

		$url = 'https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($token);
		$raw = @file_get_contents($url);
		$claims = json_decode((string)$raw, true);
		if (!is_array($claims) || !empty($claims['error'])){
			return 'tokeninfo';
		}
		if (($claims['aud'] ?? '') !== $audience){
			return 'audience';
		}
		$iss = (string)($claims['iss'] ?? '');
		if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com'){
			return 'issuer';
		}
		if (empty($claims['email_verified']) || $claims['email_verified'] === 'false'
				|| strtolower(trim((string)($claims['email'] ?? ''))) !== $sender){
			return 'email';
		}
		if ((int)($claims['exp'] ?? 0) <= time()){
			return 'expired';
		}
		return '';

	}

	function notification_is_from_play(){

		return $this->notification_refusal() === '';

	}

	/**
	 * A Play notification. The answer's 'status' is the HTTP status for google/notify: 403 for a request that is
	 * not from our push subscription (logged, at most once in 10 minutes), 500 when Play could not be read or the
	 * change not saved (Pub/Sub then retries), 200 otherwise. The purchase is read from Google again.
	 */
	function handle_notification($body){

		$refusal = $this->notification_refusal();
		if ($refusal !== ''){
			$this->log_limited('notify_refused', 'Play notification refused: '.$refusal);
			return ['ok' => 0, 'error' => 'Not from Play', 'status' => 403];
		}

		$notice = $this->notification_payload($body);
		$token = trim((string)($notice['purchaseToken'] ?? ''));
		if ($token === ''){
			// A test notification or another kind (one-time products): nothing to read
			return ['ok' => 1, 'ignored' => 1];
		}
		if (!$this->play_token_ok($token)){
			$this->log('Play notification with a malformed purchase token');
			return ['ok' => 1, 'ignored' => 1];
		}

		$remote = $this->fetch_subscription($token);
		if (empty($remote['ok'])){
			$this->log('Play notification: the purchase could not be read, Pub/Sub retries');
			return array_merge($remote, ['status' => 500]);
		}

		$user_id = (int)($remote['user_id'] ?? 0);
		if ($user_id < 1){
			return ['ok' => 1, 'ignored' => 1];
		}

		$make_current = $this->notification_makes_current($user_id);

		$this->load->model('user/user_model');
		$play = $this->user_model->get_user_meta_value_for_id($user_id, self::META_PLAY, []);
		if (!is_array($play)){
			$play = [];
		}
		$play['purchase_token'] = $token;
		$this->user_model->set_user_meta_for_id($user_id, self::META_PLAY, $play);

		$result = $this->apply_remote(
				$user_id,
				$remote,
				(int)($play['product_id'] ?? 0),
				(int)($play['currency'] ?? 0),
				trim((string)($play['google_package_id'] ?? $remote['play_product_id'] ?? '')),
				$make_current
		);
		if (empty($result['ok'])){
			$this->log('Play notification: user '.$user_id.' not updated, Pub/Sub retries');
			return array_merge($result, ['status' => 500]);
		}
		if ($make_current){
			$this->acknowledge($token, $remote);
		}
		return $result;

	}

	function apply_remote($user_id, $remote, $product_id, $currency_id, $play_product_id, $make_current){

		if (empty($GLOBALS['config']['modules']) || !in_array('subscription', $GLOBALS['config']['modules'], true)){
			return ['ok' => 0, 'error' => 'Subscription module is not enabled'];
		}

		$user_id = (int)$user_id;
		$product_id = (int)$product_id;
		$currency_id = (int)$currency_id;
		$play_product_id = trim((string)$play_product_id);
		$default_currency = $this->shop_default_currency_id();
		if ($default_currency > 0){
			$currency_id = $default_currency;
		}
		$remote_sku = $this->remote_play_key($remote, $play_product_id);
		if ($remote_sku !== '' && $remote_sku !== $play_product_id){
			$match = $this->match_play_product($remote_sku);
			if (!empty($match['product_id'])){
				$product_id = (int)$match['product_id'];
				$play_product_id = $remote_sku;
				$this->remember_play_package($user_id, $product_id, $currency_id, $play_product_id);
			}
		}

		// No plan known (a notification for a purchase whose confirm never arrived): find it by Play's ids
		if ($product_id < 1){
			$remote_product = trim((string)($remote['play_product_id'] ?? ''));
			$remote_plan = trim((string)($remote['play_base_plan_id'] ?? ''));
			foreach ([$this->play_id_value($remote_product, $remote_plan), $remote_product] as $key){
				$match = $key !== '' ? $this->match_play_product($key) : [];
				if (!empty($match['product_id'])){
					$product_id = (int)$match['product_id'];
					$play_product_id = $key;
					$this->remember_play_package($user_id, $product_id, $currency_id, $play_product_id);
					break;
				}
			}
		}

		$interval = '';
		if ($product_id > 0){
			$this->load->model('cms/cms_page_panel_model');
			$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
			$interval = is_array($product) ? (string)($product['billing_interval'] ?? '') : '';
		}

		$fields = [
				'status' => !empty($remote['active']) ? 'active' : (!empty($remote['hold']) ? (string)$remote['hold'] : 'canceled'),
				'active' => !empty($remote['active']) ? '1' : '0',
				'interval' => $interval,
				'ends' => (string)($remote['ends'] ?? ''),
				'price_id' => $play_product_id,
				'product_id' => $product_id,
				'currency_id' => $currency_id,
				'cancel_at_period_end' => !empty($remote['cancel_at_period_end']) ? '1' : '0',
		];

		$this->load->model('subscription/subscription_model');
		$before = $this->subscription_model->get_play_subscription($user_id);
		$ok = $this->subscription_model->save_play_subscription($user_id, $fields, $make_current && !empty($remote['active']));

		if ($ok && $make_current && !empty($remote['active'])){
			$this->set_provider($user_id, 'google');
		}

		return [
				'ok' => $ok ? 1 : 0,
				'changed' => ($ok && $this->play_fields_changed($before, $fields)) ? 1 : 0,
				'active' => !empty($remote['active']) ? 1 : 0,
		];

	}

	/**
	 * Shop price row for a Play product id. Empty when none of the products use it.
	 */
	function match_play_product($play_product_id){

		$play_product_id = trim((string)$play_product_id);
		if ($play_product_id === ''){
			return [];
		}

		$this->load->model('cms/cms_page_panel_model');
		$products = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/product',
		]);
		if (!is_array($products)){
			return [];
		}

		foreach ($products as $product){
			if (!is_array($product)){
				continue;
			}
			$prices = $product['prices'] ?? [];
			if (!is_array($prices)){
				continue;
			}
			foreach ($prices as $row){
				if (!is_array($row)){
					continue;
				}
				if (trim((string)($row['play_product_id'] ?? '')) !== $play_product_id){
					continue;
				}
				return [
						'product_id' => (int)($product['cms_page_panel_id'] ?? 0),
						'currency_id' => (int)($row['currency_id'] ?? 0),
				];
			}
		}

		return [];

	}

	/** A Play token (purchase or external transaction) as Google issues it: visible ASCII, at most 1,000 characters. */
	function play_token_ok($token){

		return (bool)preg_match('/^[\x21-\x7e]{1,1000}$/', (string)$token);

	}

	/** A Play id as the price rows hold it: product, or product,plan. */
	function play_id_ok($value){

		return (bool)preg_match('/^[A-Za-z0-9._-]{1,150}(,[A-Za-z0-9._-]{1,150})?$/', (string)$value);

	}

	/**
	 * The shop product whose price row has this Play id: the full value first, then its product part
	 * (as a purchase sent again is matched). 0 when no product uses it.
	 */
	function play_product_for($play_product_id){

		$play_product_id = trim((string)$play_product_id);
		list($product) = $this->play_id_parts($play_product_id);
		foreach (array_unique([$play_product_id, $product]) as $key){
			$match = $this->match_play_product($key);
			if (!empty($match['product_id'])){
				return (int)$match['product_id'];
			}
		}

		return 0;

	}

	function remember_play_package($user_id, $product_id, $currency_id, $play_product_id){

		$this->load->model('user/user_model');
		$play = $this->user_model->get_user_meta_value_for_id((int)$user_id, self::META_PLAY, []);
		if (!is_array($play)){
			$play = [];
		}
		$play['product_id'] = (int)$product_id;
		$play['currency'] = (int)$currency_id;
		$play['google_package_id'] = trim((string)$play_product_id);
		$this->user_model->set_user_meta_for_id((int)$user_id, self::META_PLAY, $play);

	}

	function play_fields_changed($before, $fields){

		if (empty($before)){
			return true;
		}
		foreach (['status', 'active', 'interval', 'ends', 'price_id', 'product_id', 'currency_id', 'cancel_at_period_end'] as $key){
			if ((string)($before[$key] ?? '') !== (string)($fields[$key] ?? '')){
				return true;
			}
		}
		return false;

	}

	function fetch_subscription($purchase_token){

		$path = 'applications/'.rawurlencode($this->package_name()).'/purchases/subscriptionsv2/tokens/'.rawurlencode($purchase_token);
		$response = $this->play_request('GET', $path, null);
		if (empty($response['ok'])){
			return $response;
		}

		$data = $response['data'];
		$state = (string)($data['subscriptionState'] ?? '');
		$active_states = [
				'SUBSCRIPTION_STATE_ACTIVE',
				'SUBSCRIPTION_STATE_CANCELED',
				'SUBSCRIPTION_STATE_IN_GRACE_PERIOD',
		];
		$line = $this->current_play_line($data['lineItems'] ?? []);
		$user_id = (int)($data['externalAccountIdentifiers']['obfuscatedExternalAccountId'] ?? 0);

		return [
				'ok' => 1,
				'active' => in_array($state, $active_states, true) ? 1 : 0,
				'cancel_at_period_end' => $state === 'SUBSCRIPTION_STATE_CANCELED' ? 1 : 0,
				'ends' => (string)($line['expiryTime'] ?? ''),
				'play_product_id' => (string)($line['productId'] ?? ''),
				'play_base_plan_id' => (string)($line['offerDetails']['basePlanId'] ?? ''),
				'user_id' => $user_id,
				'acknowledged' => ($data['acknowledgementState'] ?? '') === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED' ? 1 : 0,
				// Not active, but not over: a payment problem (on hold) or paused by the user
				'hold' => $state === 'SUBSCRIPTION_STATE_ON_HOLD' ? 'on_hold' : ($state === 'SUBSCRIPTION_STATE_PAUSED' ? 'paused' : ''),
		];

	}

	/**
	 * Line in force now. A later replacement is not the current period.
	 * With nothing current, the latest expiry still supplies an end date.
	 */
	function current_play_line($items){

		if (!is_array($items)){
			return [];
		}

		$later = [];
		foreach ($items as $item){
			if (!is_array($item)){
				continue;
			}
			$replacement = trim((string)($item['deferredItemReplacement']['productId'] ?? ''));
			if ($replacement !== ''){
				$later[$replacement] = true;
			}
		}

		$future = [];
		$any = [];
		$now = time();
		foreach ($items as $item){
			if (!is_array($item)){
				continue;
			}
			$expiry = trim((string)($item['expiryTime'] ?? ''));
			$stamp = $expiry === '' ? false : strtotime($expiry);
			if ($stamp === false){
				continue;
			}
			$any[] = ['item' => $item, 'stamp' => $stamp];
			$product_id = trim((string)($item['productId'] ?? ''));
			if ($product_id !== '' && !empty($later[$product_id])){
				continue;
			}
			if ($stamp > $now){
				$future[] = ['item' => $item, 'stamp' => $stamp];
			}
		}

		$pool = $future !== [] ? $future : $any;
		if ($pool === []){
			return [];
		}
		usort($pool, function($a, $b) use ($future){
			if ($future !== []){
				return $a['stamp'] <=> $b['stamp'];
			}
			return $b['stamp'] <=> $a['stamp'];
		});

		return $pool[0]['item'];

	}

	function create_external_transaction($user_id, $tx, $external_id, $token, $initial_id){

		$price = $this->price_for_package((int)($tx['product_id'] ?? 0), (int)($tx['currency'] ?? 0));
		if ($price['micros'] < 1 || $price['currency'] === ''){
			$this->log('Missing shop price for external transaction user '.$user_id);
			return false;
		}

		$recurring = [
				'externalSubscription' => ['subscriptionType' => 'RECURRING'],
		];
		if ($initial_id !== ''){
			$recurring['initialExternalTransactionId'] = $initial_id;
		} else {
			$recurring['externalTransactionToken'] = $token;
		}

		// Shop price is the tax-inclusive amount shown on the site. Report that figure.
		$body = [
				'originalPreTaxAmount' => [
						'currency' => $price['currency'],
						'priceMicros' => (string)$price['micros'],
				],
				'originalTaxAmount' => [
						'currency' => $price['currency'],
						'priceMicros' => '0',
				],
				'transactionTime' => gmdate('Y-m-d\TH:i:s\Z'),
				'recurringTransaction' => $recurring,
		];
		if ($price['region'] !== ''){
			$body['userTaxAddress'] = ['regionCode' => $price['region']];
		}

		$path = 'applications/'.rawurlencode($this->package_name()).'/externalTransactions?externalTransactionId='.rawurlencode($external_id);
		$response = $this->play_request('POST', $path, $body);
		if (empty($response['ok'])){
			$this->log('External transaction failed user '.$user_id);
			return false;
		}
		return true;

	}

	function price_for_package($product_id, $currency_id){

		$out = ['micros' => 0, 'currency' => '', 'region' => ''];
		if ($product_id < 1){
			return $out;
		}
		$this->load->model('shop/shop_model');
		$priced = $this->shop_model->get_product_price_in_currency($product_id, $currency_id);
		$amount = (float)($priced['price'] ?? 0);
		$code = '';
		$region = '';
		if (!empty($priced['currency']) && is_array($priced['currency'])){
			$code = strtoupper(trim((string)($priced['currency']['heading'] ?? '')));
			$region = strtoupper(trim((string)($priced['currency']['region_code'] ?? '')));
		}
		if ($amount <= 0 || strlen($code) !== 3){
			return $out;
		}
		$out['micros'] = (int)round($amount * 1000000);
		$out['currency'] = $code;
		$out['region'] = strlen($region) === 2 ? $region : '';
		return $out;

	}

	function external_id($user_id, $suffix){

		$id = 'st'.$user_id.$suffix;
		$id = preg_replace('/[^A-Za-z0-9_-]/', '', $id);
		if (strlen($id) > 63){
			$id = substr($id, 0, 63);
		}
		return $id;

	}

	/**
	 * Stop Play renewal. The subscription stays until Google’s expiry time.
	 */
	function cancel_renewal_for_user($user_id){

		$user_id = (int)$user_id;
		$this->load->model('user/user_model');
		$play = $this->user_model->get_user_meta_value_for_id($user_id, self::META_PLAY, []);
		$token = is_array($play) ? trim((string)($play['purchase_token'] ?? '')) : '';
		if ($token === ''){
			return ['ok' => 0, 'error' => 'No Play subscription'];
		}

		$remote = $this->fetch_subscription($token);
		if (empty($remote['ok'])){
			$this->log('Play cancel could not be read for user '.$user_id);
			return $remote;
		}

		if (empty($remote['active']) || !empty($remote['cancel_at_period_end'])){
			$this->sync_user($user_id);
			return ['ok' => 1];
		}

		$path = 'applications/'.rawurlencode($this->package_name()).'/purchases/subscriptionsv2/tokens/'.rawurlencode($token).':cancel';
		$response = $this->play_request('POST', $path, [
				'cancellationContext' => [
						'cancellationType' => 'USER_REQUESTED_STOP_RENEWALS',
				],
		], true);
		if (empty($response['ok'])){
			$again = $this->fetch_subscription($token);
			if (empty($again['ok']) || empty($again['cancel_at_period_end'])){
				$this->log('Play cancel failed for user '.$user_id);
				return $response;
			}
		}

		$synced = $this->sync_user($user_id);
		if (empty($synced['ok'])){
			return $synced;
		}

		return ['ok' => 1];

	}

	/**
	 * `subscription id` or `subscription id,base plan id`.
	 */
	function play_id_parts($value){

		$value = trim((string)$value);
		$comma = strpos($value, ',');
		if ($comma === false){
			return [$value, ''];
		}

		return [trim(substr($value, 0, $comma)), trim(substr($value, $comma + 1))];

	}

	function play_id_value($product, $plan){

		$product = trim((string)$product);
		$plan = trim((string)$plan);
		if ($product === '' || $plan === ''){
			return $product;
		}

		return $product.','.$plan;

	}

	/**
	 * Stored values without a base plan stay matched on the subscription id alone.
	 */
	function remote_play_key($remote, $stored){

		$product = trim((string)($remote['play_product_id'] ?? ''));
		$plan = trim((string)($remote['play_base_plan_id'] ?? ''));
		if ($product === ''){
			return '';
		}
		if (strpos((string)$stored, ',') !== false && $plan !== ''){
			return $product.','.$plan;
		}

		return $product;

	}

	function shop_default_currency_id(){

		if (empty($GLOBALS['config']['modules']) || !in_array('shop', $GLOBALS['config']['modules'], true)){
			return 0;
		}
		$this->load->model('shop/shop_model');
		$currency = $this->shop_model->get_default_currency();
		if (!is_array($currency)){
			return 0;
		}

		return (int)($currency['cms_page_panel_id'] ?? 0);

	}

	/**
	 * Play’s own subscription page. Without a product id, the subscriptions list.
	 */
	function subscription_manage_url($play_product_id = ''){

		$url = 'https://play.google.com/store/account/subscriptions';
		list($sku, ) = $this->play_id_parts($play_product_id);
		if ($sku === ''){
			return $url;
		}

		return $url.'?sku='.rawurlencode($sku).'&package='.rawurlencode($this->package_name());

	}

	function play_product_id_for_user($user_id){

		$this->load->model('user/user_model');
		$play = $this->user_model->get_user_meta_value_for_id((int)$user_id, self::META_PLAY, []);
		if (!is_array($play)){
			return '';
		}

		return trim((string)($play['google_package_id'] ?? ''));

	}

	function play_request($method, $path, $body, $allow_empty = false){

		$token = $this->access_token();
		if ($token === ''){
			return ['ok' => 0, 'error' => 'Google Play is not configured'];
		}

		$url = 'https://androidpublisher.googleapis.com/androidpublisher/v3/'.$path;
		$headers = "Authorization: Bearer ".$token."\r\nAccept: application/json\r\n";
		$content = null;
		if ($body !== null){
			$content = json_encode($body);
			$headers .= "Content-Type: application/json\r\n";
		}

		$context = stream_context_create([
				'http' => [
						'method' => $method,
						'header' => $headers,
						'content' => $content,
						'timeout' => 20,
						'ignore_errors' => true,
				],
		]);
		$raw = @file_get_contents($url, false, $context);
		$status = 0;
		if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)){
			$status = (int)$match[1];
		}
		$data = json_decode((string)$raw, true);
		if ($allow_empty && $status >= 200 && $status < 300 && !is_array($data)){
			return ['ok' => 1, 'data' => []];
		}
		if ($status === 401){
			// The kept access token was refused (revoked or rotated key): the next call gets a new one
			if (is_file(cms_path('tmp').'google_play_token.json')){
				unlink(cms_path('tmp').'google_play_token.json');
			}
		}
		if ($status < 200 || $status >= 300 || !is_array($data)){
			$this->log('Play '.$method.' '.$status);
			return ['ok' => 0, 'error' => 'Google Play request failed'];
		}
		return ['ok' => 1, 'data' => $data];

	}

	/**
	 * The Play API access token. Google's tokens last an hour; one is kept in tmp/ for 55 minutes (keyed by
	 * the service account), so a Play call doesn't cost an OAuth round trip each time.
	 */
	function access_token(){

		$file = trim((string)($this->get_settings()['service_account_file'] ?? ''));
		if ($file === '' || !is_file($file)){
			return '';
		}
		$json = json_decode((string)file_get_contents($file), true);
		if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])){
			return '';
		}

		$cache_file = cms_path('tmp').'google_play_token.json';
		$cached = is_file($cache_file) ? json_decode((string)file_get_contents($cache_file), true) : null;
		if (is_array($cached) && ($cached['account'] ?? '') === $json['client_email']
				&& (int)($cached['until'] ?? 0) > time() && !empty($cached['token'])){
			return (string)$cached['token'];
		}

		$token = $this->fetch_access_token($json);
		if ($token !== ''){
			@file_put_contents($cache_file, json_encode([
					'account' => $json['client_email'],
					'token' => $token,
					'until' => time() + 3300,
			]), LOCK_EX);
			@chmod($cache_file, 0600);
		}
		return $token;

	}

	function fetch_access_token($json){

		$now = time();
		$header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
		$claim = $this->base64url(json_encode([
				'iss' => $json['client_email'],
				'scope' => 'https://www.googleapis.com/auth/androidpublisher',
				'aud' => 'https://oauth2.googleapis.com/token',
				'iat' => $now,
				'exp' => $now + 3600,
		]));
		$unsigned = $header.'.'.$claim;
		$signature = '';
		$private = openssl_pkey_get_private($json['private_key']);
		if ($private === false || !openssl_sign($unsigned, $signature, $private, OPENSSL_ALGO_SHA256)){
			return '';
		}

		$jwt = $unsigned.'.'.$this->base64url($signature);
		$body = http_build_query([
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion' => $jwt,
		]);
		$context = stream_context_create([
				'http' => [
						'method' => 'POST',
						'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
						'content' => $body,
						'timeout' => 20,
						'ignore_errors' => true,
				],
		]);
		$raw = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
		$data = json_decode((string)$raw, true);
		return is_array($data) ? trim((string)($data['access_token'] ?? '')) : '';

	}

	function notification_payload($body){

		if (!is_array($body)){
			return [];
		}
		$encoded = (string)($body['message']['data'] ?? '');
		if ($encoded === ''){
			return is_array($body['subscriptionNotification'] ?? null) ? $body['subscriptionNotification'] : [];
		}
		$json = json_decode(base64_decode($encoded), true);
		if (!is_array($json)){
			return [];
		}
		return is_array($json['subscriptionNotification'] ?? null) ? $json['subscriptionNotification'] : [];

	}

	function base64url($value){

		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

	}

	function log($message){

		error_log('google: '.$message);

	}

	/** log(), at most once in $seconds per $kind (a flood of refused requests writes one line). */
	function log_limited($kind, $message, $seconds = 600){

		$file = cms_path('tmp').'google_log_'.preg_replace('/[^a-z0-9_]/', '', (string)$kind);
		if (is_file($file) && (time() - (int)@filemtime($file)) < $seconds){
			return;
		}
		@touch($file);
		$this->log($message);

	}

}
