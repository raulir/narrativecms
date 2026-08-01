<?php

namespace subscription;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Subscription domain: pricing catalogue, checkout intent, entitlement, provider resolution.
 * Does not call Stripe SDK — payment providers implement subscription_checkout.
 */
class subscription_model extends \Model {

	const INTENT_KEY = 'subscription_checkout_intent';
	const INTENT_TTL = 3600; // 1 hour

	function get_shop_settings(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/shop');
		return is_array($settings) ? $settings : [];

	}

	/**
	 * Panel name from shop settings (e.g. stripe/subscription_checkout).
	 */
	function get_checkout_provider_panel(){

		$settings = $this->get_shop_settings();
		$panel = trim((string)($settings['subscription_checkout'] ?? ''));
		return $panel !== '' ? $panel : '';

	}

	function set_checkout_intent($product_id, $currency_id, $resume_url = ''){

		$product_id = (int)$product_id;
		$currency_id = (int)$currency_id;
		if ($product_id < 1){
			return false;
		}

		// Store path-only when possible (open-redirect safe, works with path base_url)
		$sanitized = $this->_sanitize_resume_url($resume_url);
		if ($sanitized === null){
			$sanitized = '';
		}

		$_SESSION[self::INTENT_KEY] = [
				'product_id' => $product_id,
				'currency_id' => $currency_id,
				'resume_url' => $sanitized,
				'ts' => time(),
		];

		return true;

	}

	function get_checkout_intent(){

		$intent = $_SESSION[self::INTENT_KEY] ?? null;
		if (!is_array($intent)){
			return null;
		}

		$ts = (int)($intent['ts'] ?? 0);
		if ($ts < 1 || (time() - $ts) > self::INTENT_TTL){
			$this->clear_checkout_intent();
			return null;
		}

		$product_id = (int)($intent['product_id'] ?? 0);
		if ($product_id < 1){
			$this->clear_checkout_intent();
			return null;
		}

		$resume = $this->_sanitize_resume_url($intent['resume_url'] ?? '');
		if ($resume === null){
			$resume = '';
		}

		return [
				'product_id' => $product_id,
				'currency_id' => (int)($intent['currency_id'] ?? 0),
				'resume_url' => $resume,
				'ts' => $ts,
		];

	}

	function clear_checkout_intent(){

		unset($_SESSION[self::INTENT_KEY]);

	}

	/**
	 * Current subscription row lives in subscription_subscription (archived=0).
	 * API shape matches former meta.subscription keys (interval, active, …).
	 */
	const META_KEY = 'subscription'; // legacy meta key (migrate scripts only)

	/**
	 * Resolve user id (arg or current session).
	 */
	function _resolve_user_id($user_id = 0){

		$this->load->model('user/user_model');

		$user_id = (int)$user_id;
		if ($user_id > 0){
			return $user_id;
		}

		$user = $this->user_model->get_current();
		if (empty($user)){
			return 0;
		}

		return (int)($user['cms_page_panel_id'] ?? $user['user_id'] ?? 0);

	}

	/**
	 * Map DB row → public subscription array (same keys as old meta).
	 */
	function _subscription_row_to_array($row){

		if (empty($row) || !is_array($row)){
			return [];
		}

		return [
				'status' => (string)($row['status'] ?? ''),
				'active' => !empty($row['active']) ? '1' : '0',
				'interval' => (string)($row['billing_interval'] ?? ''),
				'ends' => (string)($row['ends'] ?? ''),
				'price_id' => (string)($row['price_id'] ?? ''),
				'product_id' => (int)($row['product_id'] ?? 0),
				'plan' => (string)($row['plan'] ?? ''),
				'stripe_subscription_id' => (string)($row['stripe_subscription_id'] ?? ''),
				'stripe_customer_id' => (string)($row['stripe_customer_id'] ?? ''),
				'currency_id' => (int)($row['currency_id'] ?? 0),
				'cancel_at_period_end' => !empty($row['cancel_at_period_end']) ? '1' : '0',
				'subscription_subscription_id' => (int)($row['subscription_subscription_id'] ?? 0),
		];

	}

	/**
	 * Current (non-archived) subscription for user — empty array if none.
	 */
	function get_user_subscription($user_id = 0){

		$user_id = $this->_resolve_user_id($user_id);
		if ($user_id < 1){
			return [];
		}

		if (!$this->db->table_exists('subscription_subscription')){
			return [];
		}

		$sql = 'select * from subscription_subscription where user_id = ? and archived = 0 '.
				'order by subscription_subscription_id desc limit 1';
		$query = $this->db->query($sql, [$user_id]);
		if (!$query->num_rows()){
			return [];
		}

		return $this->_subscription_row_to_array($query->row_array());

	}

	/**
	 * Merge fields into current subscription row (or insert).
	 * If stripe_subscription_id changes to a different non-empty id, archive the old row first.
	 *
	 * @param array $fields nested keys (status, active, interval, …)
	 */
	function merge_user_subscription($user_id, $fields){

		$user_id = $this->_resolve_user_id($user_id);
		if ($user_id < 1 || !is_array($fields)){
			return false;
		}

		$sub = $this->get_user_subscription($user_id);
		foreach ($fields as $k => $v){
			if ($k === '' || $k === null){
				continue;
			}
			$sub[$k] = $v;
		}

		return $this->_write_user_subscription($user_id, $sub);

	}

	/**
	 * Persist full subscription state for user (table only).
	 */
	function _write_user_subscription($user_id, $sub){

		$user_id = (int)$user_id;
		if ($user_id < 1 || !is_array($sub)){
			return false;
		}

		if (!$this->db->table_exists('subscription_subscription')){
			return false;
		}

		$now = date('Y-m-d H:i:s');
		$active = $sub['active'] ?? '0';
		$active_int = ($active === '1' || $active === 1 || $active === true) ? 1 : 0;
		$cancel = $sub['cancel_at_period_end'] ?? '0';
		$cancel_int = ($cancel === '1' || $cancel === 1 || $cancel === true) ? 1 : 0;

		$row = [
				'status' => (string)($sub['status'] ?? ''),
				'active' => $active_int,
				'billing_interval' => (string)($sub['interval'] ?? ''),
				'ends' => (string)($sub['ends'] ?? ''),
				'price_id' => (string)($sub['price_id'] ?? ''),
				'product_id' => (int)($sub['product_id'] ?? 0),
				'plan' => (string)($sub['plan'] ?? ''),
				'stripe_subscription_id' => (string)($sub['stripe_subscription_id'] ?? ''),
				'stripe_customer_id' => (string)($sub['stripe_customer_id'] ?? ''),
				'currency_id' => (int)($sub['currency_id'] ?? 0),
				'cancel_at_period_end' => $cancel_int,
		];

		$existing = $this->db->query(
				'select subscription_subscription_id, stripe_subscription_id from subscription_subscription '.
				'where user_id = ? and archived = 0 order by subscription_subscription_id desc limit 1',
				[$user_id]
		)->row_array();

		$new_stripe = $row['stripe_subscription_id'];
		$old_stripe = (string)($existing['stripe_subscription_id'] ?? '');

		// New Stripe subscription id → archive previous current row, insert fresh history row
		$should_archive = !empty($existing['subscription_subscription_id'])
				&& $new_stripe !== ''
				&& $old_stripe !== ''
				&& $new_stripe !== $old_stripe;

		if ($should_archive){
			$this->db->query(
					'update subscription_subscription set archived = 1, updated = ? where subscription_subscription_id = ?',
					[$now, (int)$existing['subscription_subscription_id']]
			);
			$existing = null;
		}

		if (empty($existing['subscription_subscription_id'])){
			$sql = 'insert into subscription_subscription set '.
					'user_id = ?, status = ?, active = ?, billing_interval = ?, ends = ?, '.
					'price_id = ?, product_id = ?, plan = ?, stripe_subscription_id = ?, stripe_customer_id = ?, '.
					'currency_id = ?, cancel_at_period_end = ?, archived = 0, created = ?, updated = ?';
			$this->db->query($sql, [
					$user_id,
					$row['status'],
					$row['active'],
					$row['billing_interval'],
					$row['ends'],
					$row['price_id'],
					$row['product_id'],
					$row['plan'],
					$row['stripe_subscription_id'],
					$row['stripe_customer_id'],
					$row['currency_id'],
					$row['cancel_at_period_end'],
					$now,
					$now,
			]);
			return true;
		}

		$sql = 'update subscription_subscription set '.
				'status = ?, active = ?, billing_interval = ?, ends = ?, price_id = ?, product_id = ?, '.
				'plan = ?, stripe_subscription_id = ?, stripe_customer_id = ?, currency_id = ?, '.
				'cancel_at_period_end = ?, updated = ? where subscription_subscription_id = ?';
		$this->db->query($sql, [
				$row['status'],
				$row['active'],
				$row['billing_interval'],
				$row['ends'],
				$row['price_id'],
				$row['product_id'],
				$row['plan'],
				$row['stripe_subscription_id'],
				$row['stripe_customer_id'],
				$row['currency_id'],
				$row['cancel_at_period_end'],
				$now,
				(int)$existing['subscription_subscription_id'],
		]);

		return true;

	}

	/**
	 * True when meta.subscription.active is paid/active (Stripe path only).
	 */
	function user_has_paid_subscription($user_id = 0){

		$sub = $this->get_user_subscription($user_id);
		$active = $sub['active'] ?? '0';

		return ($active === '1' || $active === 1 || $active === true);

	}

	/**
	 * Admin-granted product id on user panel (elevated_plan FK). 0 if none / product missing.
	 */
	function get_elevated_plan_id($user_id = 0){

		$user_id = $this->_resolve_user_id($user_id);
		if ($user_id < 1){
			return 0;
		}

		$this->load->model('cms/cms_page_panel_model');
		$user = $this->cms_page_panel_model->get_cms_page_panel($user_id);
		if (empty($user) || !is_array($user)){
			return 0;
		}

		$product_id = (int)($user['elevated_plan'] ?? 0);
		if ($product_id < 1){
			return 0;
		}

		// Missing product → not elevated (stale FK)
		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		if (empty($product) || !is_array($product)){
			return 0;
		}

		return $product_id;

	}

	/**
	 * True when elevated_plan FK is set to an existing product.
	 */
	function user_has_elevated_plan($user_id = 0){

		return $this->get_elevated_plan_id($user_id) > 0;

	}

	/**
	 * True when the user has premium access: paid meta or elevated plan.
	 */
	function user_has_active_subscription($user_id = 0){

		if ($this->user_has_paid_subscription($user_id)){
			return true;
		}

		return $this->user_has_elevated_plan($user_id);

	}

	/**
	 * Subscription used for UI / access.
	 * Paid meta wins when active; else synthetic elevated sub; else raw meta (may be inactive).
	 */
	function get_effective_subscription($user_id = 0){

		$user_id = $this->_resolve_user_id($user_id);
		$meta = $this->get_user_subscription($user_id);

		if ($this->user_has_paid_subscription($user_id)){
			$meta['source'] = 'stripe';
			return $meta;
		}

		$product_id = $this->get_elevated_plan_id($user_id);
		if ($product_id > 0){
			$this->load->model('cms/cms_page_panel_model');
			$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
			$interval = strtolower(trim((string)($product['billing_interval'] ?? 'none')));
			if ($interval !== 'month' && $interval !== 'year' && $interval !== 'none'){
				$interval = 'none';
			}

			return [
					'active' => '1',
					'status' => 'elevated',
					'product_id' => $product_id,
					'interval' => $interval,
					'ends' => '',
					'cancel_at_period_end' => '1',
					'source' => 'elevated',
			];
		}

		if (!empty($meta) && is_array($meta)){
			$meta['source'] = 'meta';
			return $meta;
		}

		return [];

	}

	/**
	 * After login/register: resume pricing checkout, or normal landing if already subscribed.
	 *
	 * - No checkout intent → null (caller keeps default success_url, e.g. /start/)
	 * - Intent + already subscribed → clear intent, return normal user redirect (/start/)
	 * - Intent + not subscribed → resume path (e.g. /pricing/) for auto-checkout
	 *
	 * @return string|null
	 */
	function get_post_auth_redirect_url(){

		$intent = $this->get_checkout_intent();
		if (empty($intent)){
			return null;
		}

		// Already paying member: do not send to payment again
		if ($this->user_has_active_subscription()){
			$this->clear_checkout_intent();
			$this->load->model('user/user_model');
			return $this->user_model->get_user_redirect_url();
		}

		$resume = trim((string)($intent['resume_url'] ?? ''));
		if ($resume === ''){
			return null;
		}

		return $resume;

	}

	/**
	 * Normalize resume URL to a same-site path (+ optional query).
	 * Accepts path-only or absolute same-host URLs. Rejects open redirects.
	 *
	 * @return string|null path like /pricing/ or /pricing/?x=1, or null if invalid
	 */
	function _sanitize_resume_url($url){

		$url = trim((string)$url);
		if ($url === ''){
			return null;
		}

		// Protocol-relative //evil.com — never
		if (strpos($url, '//') === 0){
			return null;
		}

		$path = '';
		$query = '';

		if (preg_match('#^https?://#i', $url)){
			$parts = parse_url($url);
			if ($parts === false || empty($parts['host'])){
				return null;
			}
			if (!$this->_resume_host_is_local($parts['host'])){
				return null;
			}
			$path = (string)($parts['path'] ?? '/');
			if ($path === ''){
				$path = '/';
			}
			$query = (string)($parts['query'] ?? '');
		} else {
			// Path or path?query only — must start with single /
			if ($url[0] !== '/'){
				return null;
			}
			$qpos = strpos($url, '?');
			if ($qpos === false){
				$path = $url;
				$query = '';
			} else {
				$path = substr($url, 0, $qpos);
				$query = substr($url, $qpos + 1);
			}
			// Strip fragment if present in path segment
			$hash = strpos($path, '#');
			if ($hash !== false){
				$path = substr($path, 0, $hash);
			}
		}

		if ($path === '' || $path[0] !== '/'){
			return null;
		}

		// Must live under CMS base_url path (when base is not root)
		$base = (string)($GLOBALS['config']['base_url'] ?? '/');
		if ($base !== '/' && $base !== ''){
			$base_path = '/'.trim($base, '/');
			// Allow base_path or base_path/...
			if ($path !== $base_path && strpos($path, $base_path.'/') !== 0){
				return null;
			}
		}

		$out = $path;
		if ($query !== ''){
			$out .= '?'.$query;
		}

		return $out;

	}

	/**
	 * True if host matches request host or configured base_host.
	 */
	function _resume_host_is_local($host){

		$host = strtolower(trim((string)$host));
		if ($host === ''){
			return false;
		}

		// Strip port for compare
		$host_nop = preg_replace('/:\d+$/', '', $host);

		$candidates = [];
		if (!empty($_SERVER['HTTP_HOST'])){
			$candidates[] = strtolower((string)$_SERVER['HTTP_HOST']);
		}
		if (!empty($_SERVER['SERVER_NAME'])){
			$candidates[] = strtolower((string)$_SERVER['SERVER_NAME']);
		}
		$base_host = trim((string)($GLOBALS['config']['base_host'] ?? ''));
		if ($base_host !== ''){
			// base_host may be https://example.com
			$bh = parse_url(
					(strpos($base_host, '://') !== false) ? $base_host : 'https://'.$base_host,
					PHP_URL_HOST
			);
			if (!empty($bh)){
				$candidates[] = strtolower((string)$bh);
			}
		}

		foreach ($candidates as $c){
			$c_nop = preg_replace('/:\d+$/', '', $c);
			if ($host === $c || $host_nop === $c_nop){
				return true;
			}
		}

		return false;

	}

	function get_login_url(){

		$this->load->model('user/user_model');
		return $this->user_model->get_login_redirect_url();

	}

	/**
	 * Shared Pricing labels from subscription/subscription settings.
	 * Settings fill empty panel keys only (instance can override later if needed).
	 */
	function merge_subscription_pricing_settings($params){

		if (!is_array($params)){
			$params = [];
		}

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('subscription/subscription');
		if (!is_array($settings)){
			$settings = [];
		}

		$keys = [
				'monthly_label',
				'yearly_label',
				'yearly_badge',
				'per_none_label',
				'per_month_label',
				'per_year_label',
				'save_prefix',
				'free_label',
				'cta_label',
				'cta_free_label',
				'cta_free_link',
				'checkout_missing_message',
				'checkout_error_message',
				'manage_login_message',
				'manage_basic_label',
				'manage_renews_prefix',
				'manage_ends_prefix',
				'manage_auto_extension_label',
				'manage_on_label',
				'manage_off_label',
				'manage_updating_label',
				'manage_change_cta',
				'manage_page_link',
				'manage_payment_heading',
				'manage_payment_cta',
		];

		foreach ($keys as $key){
			if (!array_key_exists($key, $params) || $params[$key] === '' || $params[$key] === null){
				if (array_key_exists($key, $settings) && $settings[$key] !== '' && $settings[$key] !== null){
					$params[$key] = $settings[$key];
				}
			}
		}

		return $params;

	}

	/**
	 * Build pricing / manage catalogue params.
	 *
	 * @param array $params panel params
	 * @param array $options mode, include_free, show_interval_toggle, layout, auto_checkout
	 */
	function prepare_pricing_panel($params, $options = []){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');
		$this->load->model('user/user_model');

		if (!is_array($params)){
			$params = [];
		}
		if (!is_array($options)){
			$options = [];
		}

		$mode = (string)($options['mode'] ?? 'public');
		$include_free = !array_key_exists('include_free', $options) ? true : !empty($options['include_free']);
		$show_toggle = !array_key_exists('show_interval_toggle', $options) ? true : !empty($options['show_interval_toggle']);
		$layout = (string)($options['layout'] ?? 'filter');
		$do_auto_checkout = !array_key_exists('auto_checkout', $options) ? true : !empty($options['auto_checkout']);
		// Server-side card set (manage): only these intervals / currency ids (empty = no extra filter)
		$only_intervals = $options['only_intervals'] ?? null;
		if (is_array($only_intervals)){
			$only_intervals = array_values(array_filter(array_map('strval', $only_intervals)));
		} else {
			$only_intervals = null;
		}
		$only_currency_ids = $options['only_currency_ids'] ?? null;
		if (is_array($only_currency_ids)){
			$only_currency_ids = array_values(array_filter(array_map('intval', $only_currency_ids)));
		} else {
			$only_currency_ids = null;
		}

		$params = $this->merge_subscription_pricing_settings($params);

		$params['error'] = '';
		$params['cards'] = [];
		$params['currency_options'] = [];
		$params['currency_ids'] = [];
		$params['show_currency_switcher'] = 0;
		$params['active_currency_id'] = 0;
		$params['has_paid_cards'] = 0;
		$params['mode'] = $mode;
		$params['layout'] = $layout;
		$params['show_interval_toggle'] = $show_toggle ? 1 : 0;
		$params['include_free'] = $include_free ? 1 : 0;
		$params['checkout_provider'] = $this->get_checkout_provider_panel();
		$params['checkout_missing'] = empty($params['checkout_provider']) ? 1 : 0;
		$params['user_logged_in'] = $this->user_model->is_logged_in() ? 1 : 0;
		$params['login_url'] = $this->get_login_url();
		$params['auto_checkout'] = 0;
		$params['auto_product_id'] = 0;
		$params['auto_currency_id'] = 0;

		if ($do_auto_checkout && !empty($params['user_logged_in'])){
			// Already subscribed: drop guest checkout intent — no auto payment
			if ($this->user_has_active_subscription()){
				$this->clear_checkout_intent();
			} else {
				$intent = $this->get_checkout_intent();
				if (!empty($intent['product_id'])){
					$params['auto_checkout'] = 1;
					$params['auto_product_id'] = (int)$intent['product_id'];
					$params['auto_currency_id'] = (int)($intent['currency_id'] ?? 0);
				}
			}
		}

		$currency_options = [];
		$rows = $params['currencies'] ?? [];
		if (is_array($rows)){
			foreach ($rows as $row){
				if (!is_array($row)){
					continue;
				}
				$cid = (int)($row['currency_id'] ?? 0);
				if ($cid < 1){
					continue;
				}
				$c = $this->shop_model->get_currency($cid);
				if (empty($c)){
					continue;
				}
				$currency_options[] = $c;
			}
		}

		if ($currency_options === []){
			$default_c = $this->shop_model->get_default_currency();
			if (!empty($default_c)){
				$currency_options[] = $default_c;
			}
		}

		$params['currency_options'] = $currency_options;
		$currency_ids = [];
		foreach ($currency_options as $copt){
			$cid = (int)($copt['cms_page_panel_id'] ?? 0);
			if ($cid > 0){
				$currency_ids[] = $cid;
			}
		}
		$params['currency_ids'] = $currency_ids;
		$params['show_currency_switcher'] = count($currency_ids) > 1 ? 1 : 0;
		$active_id = (int)($currency_options[0]['cms_page_panel_id'] ?? 0);
		// Caller (manage currency switcher) may force selected currency
		if (!empty($options['active_currency_id'])){
			$forced = (int)$options['active_currency_id'];
			if ($forced > 0){
				$active_id = $forced;
			}
		} else if (!empty($params['active_currency_override'])){
			$forced = (int)$params['active_currency_override'];
			if ($forced > 0){
				$active_id = $forced;
			}
		}
		$params['active_currency_id'] = $active_id;

		$category_id = (int)($params['category_id'] ?? 0);
		if ($category_id < 1){
			$params['error'] = 'Error: no category selected';
			return $params;
		}

		$category = $this->cms_page_panel_model->get_cms_page_panel($category_id);
		if (empty($category) || !is_array($category)){
			$params['error'] = 'Error: category not found';
			return $params;
		}

		$type = trim((string)($category['type'] ?? ''));
		if ($type !== 'subscription'){
			$params['error'] = 'Error: non-subscription category';
			return $params;
		}

		$params['category_heading'] = $category['heading'] ?? '';

		$subcats = $this->cms_page_panel_model->get_list('shop/subcategory', [
				'category_id' => $category_id,
		]);
		$subcats = $this->sort_panels($subcats);

		$free_label = $params['free_label'] ?? 'Free';
		$per_none = $params['per_none_label'] ?? '/always';
		$per_month = $params['per_month_label'] ?? '/month';
		$per_year = $params['per_year_label'] ?? '/year';
		$cta_label = $params['cta_label'] ?? 'Purchase now';
		$cta_free = $params['cta_free_label'] ?? 'Get started';

		$cards = [];
		$has_paid = 0;

		foreach ($subcats as $sub){

			$sub_id = (int)($sub['cms_page_panel_id'] ?? 0);
			if ($sub_id < 1){
				continue;
			}

			$sub_title = (string)($sub['heading'] ?? '');
			$sub_text = (string)($sub['text'] ?? '');

			$products = $this->cms_page_panel_model->get_list('shop/product', [
					'subcategory_id' => $sub_id,
			]);
			$products = $this->sort_panels($products);

			foreach ($products as $product){

				$interval = strtolower(trim((string)($product['billing_interval'] ?? 'none')));
				if ($interval !== 'none' && $interval !== 'month' && $interval !== 'year'){
					$interval = 'none';
				}

				$product_id = (int)($product['cms_page_panel_id'] ?? 0);
				$featured = !empty($product['featured']) ? 1 : 0;
				$features_html = (string)($product['features'] ?? '');
				$title = $sub_title !== '' ? $sub_title : (string)($product['heading'] ?? '');

				$base = $this->shop_model->get_product_price_in_currency($product, $active_id);
				$base_price = (float)($base['price'] ?? 0);

				if ($interval === 'none' && $base_price <= 0){

					if (!$include_free){
						continue;
					}

					$cards[] = [
							'always_visible' => 1,
							'interval' => 'none',
							'currency_id' => 0,
							'subcategory_id' => $sub_id,
							'product_id' => $product_id,
							'title' => $title,
							'text' => $sub_text,
							'featured' => $featured,
							'features_html' => $features_html,
							'price' => 0,
							'price_fmt' => $free_label,
							'period_label' => $per_none,
							'cta_text' => $cta_free,
							'cta_plan' => 'free',
							'cta_class' => 'pricing_cta_free',
					];
					continue;

				}

				// Paid products only month/year for purchase UI
				if ($interval !== 'month' && $interval !== 'year'){
					continue;
				}
				if ($only_intervals !== null && !in_array($interval, $only_intervals, true)){
					continue;
				}

				$period_label = $per_none;
				if ($interval === 'month'){
					$period_label = $per_month;
				} else if ($interval === 'year'){
					$period_label = $per_year;
				}

				foreach ($currency_options as $opt){

					$oid = (int)($opt['cms_page_panel_id'] ?? 0);
					if ($oid < 1){
						continue;
					}
					if ($only_currency_ids !== null && !in_array($oid, $only_currency_ids, true)){
						continue;
					}

					$priced = $this->shop_model->get_product_price_in_currency($product, $oid);

					$cards[] = [
							'always_visible' => 0,
							'interval' => $interval,
							'currency_id' => $oid,
							'subcategory_id' => $sub_id,
							'product_id' => $product_id,
							'title' => $title,
							'text' => $sub_text,
							'featured' => $featured,
							'features_html' => $features_html,
							'price' => (float)$priced['price'],
							'price_fmt' => $priced['formatted'],
							'period_label' => $period_label,
							'cta_text' => $cta_label,
							'cta_plan' => 'paid',
							'cta_class' => 'pricing_cta_paid',
					];
					$has_paid = 1;

				}

			}

		}

		$params['cards'] = $this->finalize_pricing_cards($cards, [
				'layout' => $layout,
				'active_currency_id' => $active_id,
				'cards_all_visible' => !empty($options['cards_all_visible']) || !empty($params['cards_all_visible']),
				'cta_free_link' => $params['cta_free_link'] ?? null,
				'login_url' => (string)($params['login_url'] ?? ''),
		]);
		$params['has_paid_cards'] = $has_paid;

		if (empty($params['checkout_missing_message'])){
			$params['checkout_missing_message'] = 'Select subscription checkout provider!';
		}
		if (empty($params['checkout_error_message'])){
			$params['checkout_error_message'] = 'Checkout could not start. Try again.';
		}
		if (empty($params['manage_login_message'])){
			$params['manage_login_message'] = 'Please log in to manage your subscription.';
		}
		if (empty($params['manage_has_plan_message'])){
			$params['manage_has_plan_message'] = 'You already have Premium. Plan management (upgrade / cancel) is coming soon.';
		}

		return $params;

	}

	/**
	 * Prepare card display fields for templates (visible, cta_class/link, string keys).
	 * Templates trust these — no empty/escape guards at render.
	 */
	function finalize_pricing_cards($cards, $opts = []){

		if (!is_array($cards)){
			return [];
		}
		if (!is_array($opts)){
			$opts = [];
		}

		$layout = (string)($opts['layout'] ?? 'filter');
		$active_id = (int)($opts['active_currency_id'] ?? 0);
		$all_visible = !empty($opts['cards_all_visible']);
		$cta_free_link = $opts['cta_free_link'] ?? null;
		$login_url = (string)($opts['login_url'] ?? '');
		$has_free_link = !empty($cta_free_link) && (
				(is_array($cta_free_link) && !empty($cta_free_link['url']))
				|| (is_string($cta_free_link) && $cta_free_link !== '')
		);

		$out = [];
		foreach ($cards as $card){
			if (!is_array($card)){
				continue;
			}

			$always = !empty($card['always_visible']) ? 1 : 0;
			$interval = (string)($card['interval'] ?? 'none');
			$currency_id = (int)($card['currency_id'] ?? 0);
			$cta_plan = (string)($card['cta_plan'] ?? 'paid');

			if ($all_visible){
				$visible = 1;
			} else if ($layout === 'side_by_side'){
				$visible = ($always || $currency_id === $active_id) ? 1 : 0;
			} else {
				$visible = ($always || ($interval === 'month' && $currency_id === $active_id)) ? 1 : 0;
			}

			if ($cta_plan === 'free'){
				$cta_class = 'pricing_cta_free';
			} else if ($cta_plan === 'change'){
				$cta_class = 'pricing_cta_change';
			} else {
				$cta_class = 'pricing_cta_paid';
			}

			$card['always_visible'] = $always;
			$card['interval'] = $interval;
			$card['currency_id'] = $currency_id;
			$card['product_id'] = (int)($card['product_id'] ?? 0);
			$card['subcategory_id'] = (int)($card['subcategory_id'] ?? 0);
			$card['featured'] = !empty($card['featured']) ? 1 : 0;
			$card['title'] = (string)($card['title'] ?? '');
			$card['price_fmt'] = (string)($card['price_fmt'] ?? '');
			$card['period_label'] = (string)($card['period_label'] ?? '');
			$card['features_html'] = (string)($card['features_html'] ?? '');
			$card['cta_text'] = (string)($card['cta_text'] ?? '');
			$card['cta_plan'] = $cta_plan;
			$card['cta_class'] = $cta_class;
			$card['visible'] = $visible;

			if ($cta_plan === 'free'){
				if ($has_free_link){
					$card['cta_use_lh'] = 1;
					$card['cta_link'] = $cta_free_link;
					$card['cta_href'] = '';
				} else {
					$card['cta_use_lh'] = 0;
					$card['cta_link'] = null;
					$card['cta_href'] = $login_url !== '' ? $login_url : '#';
				}
			} else {
				$card['cta_use_lh'] = 0;
				$card['cta_link'] = null;
				$card['cta_href'] = '';
			}

			$out[] = $card;
		}

		return $out;

	}

	/**
	 * My subscription panel: login / basic (upgrade) / premium (change + settings).
	 * Premium: no currency switcher — cards locked to user's subscription currency.
	 * One active subscription per user.
	 */
	function prepare_manage_panel($params){

		$this->load->model('user/user_model');

		if (!is_array($params)){
			$params = [];
		}

		$params = $this->merge_subscription_pricing_settings($params);
		$params = $this->_manage_label_defaults($params);
		$params['login_url'] = $this->get_login_url();
		$params['user_logged_in'] = $this->user_model->is_logged_in() ? 1 : 0;
		$params['show_settings_card'] = 0;
		$params['show_payment'] = 0;
		$params['show_currency_switcher'] = 0;
		$params['currency_ids'] = [];
		$params['subscription_source'] = '';
		$params['action_title'] = '';
		$params['status_plan_title'] = '';
		$params['status_period_line'] = '';
		$params['auto_renew_on'] = 1;
		$params['auto_renew_help'] = '';
		$params['ends_display'] = '';
		$params['layout'] = 'side_by_side';
		$params['error'] = '';
		$params['cards'] = [];
		$params['active_currency_id'] = 0;
		$params['checkout_provider'] = $this->get_checkout_provider_panel();
		$params['checkout_missing'] = empty($params['checkout_provider']) ? 1 : 0;
		if (empty($params['checkout_missing_message'])){
			$params['checkout_missing_message'] = 'Select subscription checkout provider!';
		}
		if (empty($params['checkout_error_message'])){
			$params['checkout_error_message'] = 'Checkout could not start. Try again.';
		}

		if (empty($params['user_logged_in'])){
			$params['manage_view'] = 'login';
			return $params;
		}

		$sub = $this->get_effective_subscription();
		$params['subscription'] = $sub;
		$is_premium = $this->user_has_active_subscription();
		$is_elevated = (($sub['source'] ?? '') === 'elevated');
		$params['subscription_source'] = (string)($sub['source'] ?? '');
		$params['show_payment'] = 0;

		if (!$is_premium){
			// Basic: month+year for one currency only (switcher reloads page with currency_id)
			$active_override = (int)($params['active_currency_override'] ?? 0);
			// Resolve currency options first without card filter
			$params = $this->prepare_pricing_panel($params, [
					'mode' => 'manage',
					'include_free' => false,
					'show_interval_toggle' => false,
					'layout' => 'side_by_side',
					'auto_checkout' => false,
					'only_intervals' => ['month', 'year'],
			]);
			$active_id = $active_override > 0 ? $active_override : (int)($params['active_currency_id'] ?? 0);
			if ($active_id > 0){
				$params = $this->prepare_pricing_panel($params, [
						'mode' => 'manage',
						'include_free' => false,
						'show_interval_toggle' => false,
						'layout' => 'side_by_side',
						'auto_checkout' => false,
						'only_intervals' => ['month', 'year'],
						'only_currency_ids' => [$active_id],
						'active_currency_id' => $active_id,
						'cards_all_visible' => 1,
				]);
				$params['active_currency_id'] = $active_id;
			}
			$params['manage_view'] = 'basic';
			$params['status_plan_title'] = $params['manage_basic_label'] ?? 'Basic';
			$params['status_period_line'] = '';
			$params['show_settings_card'] = 0;
			$params['show_currency_switcher'] = (count($params['currency_options'] ?? []) > 1) ? 1 : 0;
			$params['cards_all_visible'] = 1;
			$params['layout'] = 'side_by_side';
			$params = $this->_manage_label_defaults($params);
			return $params;
		}

		// Premium — one subscription per user (paid Stripe or elevated plan)
		$params['manage_view'] = 'premium';
		$params['show_currency_switcher'] = 0;
		$params['cards_all_visible'] = 1;
		$params['layout'] = 'side_by_side';

		// Elevated only: status, no auto-renew / payment / change-plan
		if ($is_elevated){
			$params['show_settings_card'] = 0;
			$params['show_payment'] = 0;
			$params['cards'] = [];
			$params['has_paid_cards'] = 0;
			$params['status_plan_title'] = $this->resolve_subscription_plan_title($sub);
			$params['status_period_line'] = '';
			$params['ends_display'] = '';
			$params['auto_renew_on'] = 0;
			$params = $this->_manage_label_defaults($params);
			return $params;
		}

		$params['show_settings_card'] = 1;
		$params['show_payment'] = 1;

		$interval = strtolower(trim((string)($sub['interval'] ?? '')));
		if ($interval === 'monthly'){
			$interval = 'month';
		}
		if ($interval === 'yearly' || $interval === 'annual'){
			$interval = 'year';
		}

		$plan_name = $this->resolve_subscription_plan_title($sub);
		$params['status_plan_title'] = $plan_name;

		$ends_disp = $this->format_subscription_date($sub['ends'] ?? '');
		$params['ends_display'] = $ends_disp;
		$auto_on = empty($sub['cancel_at_period_end']) || $sub['cancel_at_period_end'] === '0' || $sub['cancel_at_period_end'] === 0;
		$params['auto_renew_on'] = $auto_on ? 1 : 0;

		if ($ends_disp !== ''){
			if ($auto_on){
				$params['status_period_line'] = ($params['manage_renews_prefix'] ?? 'renews').' '.$ends_disp;
			} else {
				$params['status_period_line'] = ($params['manage_ends_prefix'] ?? 'ends').' '.$ends_disp;
			}
		} else {
			$params['status_period_line'] = '';
		}

		$user_currency = (int)($sub['currency_id'] ?? 0);
		$filter_intervals = [];
		if ($interval === 'month'){
			$filter_intervals = ['year'];
		} else if ($interval === 'year' && $this->subscription_within_last_month($sub)){
			$filter_intervals = ['month'];
		}

		if ($filter_intervals !== []){
			$opts = [
					'mode' => 'manage',
					'include_free' => false,
					'show_interval_toggle' => false,
					'layout' => 'side_by_side',
					'auto_checkout' => false,
					'only_intervals' => $filter_intervals,
			];
			if ($user_currency > 0){
				$opts['only_currency_ids'] = [$user_currency];
				$params['currencies'] = [['currency_id' => $user_currency]];
			}
			$params = $this->prepare_pricing_panel($params, $opts);
			$change_cta = $params['manage_change_cta'] ?? ($params['cta_label'] ?? 'Change plan');
			foreach ($params['cards'] as $i => $card){
				$params['cards'][$i]['cta_text'] = $change_cta;
				$params['cards'][$i]['cta_plan'] = 'change';
				$params['cards'][$i]['cta_class'] = 'pricing_cta_change';
			}
			$params['active_currency_id'] = $user_currency > 0
					? $user_currency
					: (int)($params['active_currency_id'] ?? 0);
		} else {
			$params['cards'] = [];
			$params['has_paid_cards'] = 0;
		}

		$params['manage_view'] = 'premium';
		$params['show_settings_card'] = 1;
		$params['show_payment'] = 1;
		$params['show_currency_switcher'] = 0;
		$params['cards_all_visible'] = 1;
		$params['layout'] = 'side_by_side';
		$params = $this->_manage_label_defaults($params);
		$params['status_plan_title'] = $plan_name;
		$params['ends_display'] = $ends_disp;
		$params['auto_renew_on'] = $auto_on ? 1 : 0;
		if ($ends_disp !== ''){
			$params['status_period_line'] = $auto_on
					? (($params['manage_renews_prefix'] ?? 'renews').' '.$ends_disp)
					: (($params['manage_ends_prefix'] ?? 'ends').' '.$ends_disp);
		} else {
			$params['status_period_line'] = '';
		}
		$params['show_settings_card'] = 1;
		$params['show_payment'] = 1;
		$params['manage_view'] = 'premium';

		return $params;

	}

	function _manage_label_defaults($params){

		$defaults = [
				'manage_login_message' => 'Please log in to manage your subscription.',
				'manage_basic_label' => 'Basic',
				'manage_renews_prefix' => 'renews',
				'manage_ends_prefix' => 'ends',
				'manage_auto_extension_label' => 'Automatic extension',
				'manage_on_label' => 'On',
				'manage_off_label' => 'Off',
				'manage_updating_label' => 'Updating …',
				'manage_change_cta' => 'Change plan',
				'manage_payment_heading' => 'Update payment method',
				'manage_payment_cta' => 'Click here',
		];
		foreach ($defaults as $k => $v){
			if (empty($params[$k])){
				$params[$k] = $v;
			}
		}
		return $params;

	}

	/**
	 * Public helper for manage UI after ajax actions.
	 * Loads subscription settings (current language) so prefixes match the page.
	 */
	function build_auto_renew_copy($sub, $params = []){

		if (!is_array($params)){
			$params = [];
		}
		// Ajax actions have no panel params — merge translated settings then English fallbacks
		$params = $this->merge_subscription_pricing_settings($params);
		$params = $this->_manage_label_defaults($params);
		$ends_disp = $this->format_subscription_date($sub['ends'] ?? '');
		$auto_on = empty($sub['cancel_at_period_end']) || $sub['cancel_at_period_end'] === '0' || $sub['cancel_at_period_end'] === 0;
		$period_line = '';
		$help = '';
		if ($ends_disp !== ''){
			if ($auto_on){
				$period_line = $params['manage_renews_prefix'].' '.$ends_disp;
				$help = str_replace('{date}', $ends_disp, $params['manage_renews_help'] ?? 'Subscription automatically renews on {date}');
			} else {
				$period_line = $params['manage_ends_prefix'].' '.$ends_disp;
				$help = str_replace('{date}', $ends_disp, $params['manage_ends_help'] ?? 'Subscription ends on {date}');
			}
		}
		return [
				'auto_renew_on' => $auto_on ? 1 : 0,
				'ends_display' => $ends_disp,
				'status_period_line' => $period_line,
				'auto_renew_help' => $help,
		];

	}

	function sort_panels($items){

		if (!is_array($items) || $items === []){
			return [];
		}
		$list = array_values($items);
		usort($list, function($a, $b){
			$sa = (int)($a['sort'] ?? 0);
			$sb = (int)($b['sort'] ?? 0);
			if ($sa !== $sb){
				return $sa <=> $sb;
			}
			return ((int)($a['cms_page_panel_id'] ?? 0)) <=> ((int)($b['cms_page_panel_id'] ?? 0));
		});

		return $list;

	}

	/**
	 * Validate product is a paid recurring subscription offer.
	 * @return array{ok:int,product?:array,error?:string}
	 */
	function validate_paid_product($product_id){

		$this->load->model('cms/cms_page_panel_model');

		$product_id = (int)$product_id;
		if ($product_id < 1){
			return ['ok' => 0, 'error' => 'Missing product'];
		}

		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		if (empty($product) || !is_array($product)){
			return ['ok' => 0, 'error' => 'Product not found'];
		}

		$interval = strtolower(trim((string)($product['billing_interval'] ?? 'none')));
		if ($interval !== 'month' && $interval !== 'year'){
			return ['ok' => 0, 'error' => 'Product is not a paid subscription interval'];
		}

		// Must sit under a type=subscription category (via subcategory)
		$subcat_id = (int)($product['subcategory_id'] ?? 0);
		if ($subcat_id < 1){
			return ['ok' => 0, 'error' => 'Product is not in a subscription catalogue'];
		}
		$subcat = $this->cms_page_panel_model->get_cms_page_panel($subcat_id);
		$cat_id = (int)($subcat['category_id'] ?? 0);
		if ($cat_id < 1){
			return ['ok' => 0, 'error' => 'Product is not in a subscription catalogue'];
		}
		$category = $this->cms_page_panel_model->get_cms_page_panel($cat_id);
		if (empty($category) || trim((string)($category['type'] ?? '')) !== 'subscription'){
			return ['ok' => 0, 'error' => 'Product is not a subscription product'];
		}

		return ['ok' => 1, 'product' => $product];

	}

	/**
	 * Manage ajax: sync CMS meta from Stripe.
	 */
	function action_sync_subscription($user_id){

		$user_id = (int)$user_id;
		if (empty($GLOBALS['config']['modules']) || !in_array('stripe', $GLOBALS['config']['modules'], true)){
			return ['ok' => 1, 'changed' => 0];
		}
		$this->load->model('stripe/stripe_model');
		$result = $this->stripe_model->sync_user_subscription($user_id);
		if (empty($result['ok'])){
			return $result;
		}
		$sub = $result['subscription'] ?? $this->get_user_subscription($user_id);
		$copy = $this->build_auto_renew_copy($sub);
		return array_merge($result, $copy, [
				'is_premium' => $this->user_has_active_subscription($user_id) ? 1 : 0,
		]);

	}

	/**
	 * Manage ajax: auto-renew on/off.
	 */
	function action_set_auto_renew($user_id, $auto_renew){

		$user_id = (int)$user_id;
		if (empty($GLOBALS['config']['modules']) || !in_array('stripe', $GLOBALS['config']['modules'], true)){
			return ['ok' => 0, 'error' => 'Stripe not available'];
		}
		$this->load->model('stripe/stripe_model');
		$result = $this->stripe_model->set_auto_renew_for_user($user_id, !empty($auto_renew));
		if (empty($result['ok'])){
			return $result;
		}
		$sub = $result['subscription'] ?? [];
		return array_merge(['ok' => 1], $this->build_auto_renew_copy($sub));

	}

	/**
	 * Manage ajax: portal payment method update.
	 */
	function action_update_payment_method($user_id, $return_url = ''){

		$user_id = (int)$user_id;
		if (empty($GLOBALS['config']['modules']) || !in_array('stripe', $GLOBALS['config']['modules'], true)){
			return ['ok' => 0, 'error' => 'Stripe not available'];
		}
		if (!$this->user_has_active_subscription($user_id)){
			return ['ok' => 0, 'error' => 'No active subscription'];
		}
		$return_url = trim((string)$return_url);
		if ($return_url === ''){
			$settings = $this->merge_subscription_pricing_settings([]);
			if (!empty($settings['manage_page_link'])){
				$ml = _l($settings['manage_page_link'], false);
				if (is_string($ml) && $ml !== ''){
					$return_url = $ml;
				}
			}
		}
		$this->load->model('stripe/stripe_model');
		return $this->stripe_model->create_payment_method_update_session($user_id, $return_url);

	}

	/**
	 * Manage ajax: change plan (month↔year rules + proration).
	 */
	function action_change_plan($user_id, $product_id, $currency_id = 0){

		$user_id = (int)$user_id;
		$product_id = (int)$product_id;
		$currency_id = (int)$currency_id;

		if (empty($GLOBALS['config']['modules']) || !in_array('stripe', $GLOBALS['config']['modules'], true)){
			return ['ok' => 0, 'error' => 'Stripe not available'];
		}
		if (!$this->user_has_active_subscription($user_id)){
			return ['ok' => 0, 'error' => 'No active subscription'];
		}

		$sub = $this->get_user_subscription($user_id);
		if ($currency_id < 1){
			$currency_id = (int)($sub['currency_id'] ?? 0);
		}

		$valid = $this->validate_paid_product($product_id);
		if (empty($valid['ok'])){
			return ['ok' => 0, 'error' => $valid['error'] ?? 'Invalid product'];
		}

		$product = $valid['product'];
		$target_interval = strtolower(trim((string)($product['billing_interval'] ?? '')));
		$current_interval = strtolower(trim((string)($sub['interval'] ?? '')));
		if ($current_interval === 'monthly'){
			$current_interval = 'month';
		}
		if ($current_interval === 'yearly' || $current_interval === 'annual'){
			$current_interval = 'year';
		}

		if ($current_interval === 'month' && $target_interval !== 'year'){
			return ['ok' => 0, 'error' => 'You can only change to yearly from monthly'];
		}
		if ($current_interval === 'year' && $target_interval !== 'month'){
			return ['ok' => 0, 'error' => 'You can only change to monthly from yearly'];
		}
		if ($current_interval === 'year' && $target_interval === 'month'
				&& !$this->subscription_within_last_month($sub)){
			return ['ok' => 0, 'error' => 'Monthly plan is only available in the last month of your yearly subscription'];
		}

		$stripe_price_id = $this->get_stripe_price_id_for_product($product_id, $currency_id);
		if ($stripe_price_id === ''){
			return ['ok' => 0, 'error' => 'No Stripe price for this plan'];
		}

		$this->load->model('stripe/stripe_model');
		$result = $this->stripe_model->change_subscription_price_for_user($user_id, $stripe_price_id);
		if (empty($result['ok'])){
			return $result;
		}

		$plan_title = trim((string)($product['heading'] ?? ''));
		$facts = [
				'status' => (string)($result['subscription']['status'] ?? 'active'),
				'subscription_interval' => $target_interval,
				'subscription_ends' => $result['subscription']['ends'] ?? '',
				'subscription_price_id' => $stripe_price_id,
				'cancel_at_period_end' => $result['subscription']['cancel_at_period_end'] ?? '0',
				'stripe_subscription_id' => $result['subscription']['stripe_subscription_id'] ?? ($sub['stripe_subscription_id'] ?? ''),
				'stripe_customer_id' => $result['subscription']['stripe_customer_id'] ?? ($sub['stripe_customer_id'] ?? ''),
				'product_id' => $product_id,
				'currency_id' => $currency_id,
				'plan_title' => $plan_title,
		];
		// Prefer live Stripe state after change
		$sync = $this->stripe_model->sync_user_subscription($user_id);
		if (!empty($sync['ok'])){
			// Ensure product_id/currency always set after change
			$this->apply_entitlement_from_provider($user_id, array_merge($facts, [
					// sync may have refreshed ends/status; re-apply product fields
					'subscription_ends' => $sync['subscription']['ends'] ?? $facts['subscription_ends'],
					'subscription_interval' => $sync['subscription']['interval'] ?? $target_interval,
					'status' => $sync['subscription']['status'] ?? $facts['status'],
					'cancel_at_period_end' => $sync['subscription']['cancel_at_period_end'] ?? $facts['cancel_at_period_end'],
					'subscription_price_id' => $sync['subscription']['price_id'] ?? $stripe_price_id,
					'stripe_subscription_id' => $sync['subscription']['stripe_subscription_id'] ?? $facts['stripe_subscription_id'],
					'stripe_customer_id' => $sync['subscription']['stripe_customer_id'] ?? $facts['stripe_customer_id'],
			]));
		} else {
			$this->apply_entitlement_from_provider($user_id, $facts);
		}

		return [
				'ok' => 1,
				'reload' => 1,
				'changed' => 1,
				'subscription' => $this->get_user_subscription($user_id),
		];

	}

	/**
	 * Build success/cancel URLs for provider.
	 * Order: Stripe settings links (if set) → pricing resume intent → home defaults.
	 */
	function get_checkout_return_urls(){

		$base = rtrim((string)($GLOBALS['config']['base_url'] ?? '/'), '/') . '/';
		$success_url = $base . '?subscription_checkout=success';
		$cancel_url = $base . '?subscription_checkout=cancel';

		$intent = $this->get_checkout_intent();
		if (!empty($intent['resume_url'])){
			// Already sanitized to same-site path by get_checkout_intent()
			$resume = (string)$intent['resume_url'];
			$cancel_url = $resume;
			$success_url = $resume . (strpos($resume, '?') !== false ? '&' : '?') . 'subscription_checkout=success';
		}

		// Prefer My subscription page for success (manage after pay)
		$this->load->model('cms/cms_page_panel_model');
		$sub_settings = $this->cms_page_panel_model->get_cms_page_panel_settings('subscription/subscription');
		if (!empty($sub_settings['manage_page_link'])){
			$manage_link = _l($sub_settings['manage_page_link'], false);
			if (is_string($manage_link) && $manage_link !== ''){
				$success_url = $manage_link;
				if (empty($intent['resume_url'])){
					$cancel_url = $manage_link;
				}
			}
		}

		// Optional CMS Stripe settings (link fields) override when set
		if (!empty($GLOBALS['config']['modules']) && in_array('stripe', $GLOBALS['config']['modules'], true)){
			$this->load->model('stripe/stripe_model');
			$from_settings = $this->stripe_model->get_checkout_link_urls();
			// Only override cancel/success if manage page not preferred for success
			if (!empty($from_settings['success_url']) && empty($sub_settings['manage_page_link'])){
				$success_url = $from_settings['success_url'];
			}
			if (!empty($from_settings['cancel_url']) && empty($intent['resume_url'])){
				$cancel_url = $from_settings['cancel_url'];
			}
		}

		// Ensure absolute URLs for payment providers (Stripe rejects path-only)
		if (!empty($GLOBALS['config']['modules']) && in_array('stripe', $GLOBALS['config']['modules'], true)){
			$this->load->model('stripe/stripe_model');
			$success_url = $this->stripe_model->absolute_url($success_url);
			$cancel_url = $this->stripe_model->absolute_url($cancel_url);
		} else {
			$success_url = $this->_absolute_url_fallback($success_url);
			$cancel_url = $this->_absolute_url_fallback($cancel_url);
		}

		return [
				'success_url' => $success_url,
				'cancel_url' => $cancel_url,
		];

	}

	function _absolute_url_fallback($url){

		$url = trim((string)$url);
		if ($url === '' || preg_match('#^https?://#i', $url)){
			return $url;
		}
		$host = trim((string)($GLOBALS['config']['base_host'] ?? ''));
		if ($host === ''){
			$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
			$host = ($https ? 'https://' : 'http://').($_SERVER['HTTP_HOST'] ?? 'localhost');
		}
		return rtrim($host, '/').'/'.ltrim($url, '/');

	}

	/**
	 * Apply entitlement from payment-provider facts (Stripe or others).
	 * Stored as nested user meta: meta.subscription.{status,active,…}.
	 *
	 * @param array $facts keys: status, stripe_subscription_id, stripe_customer_id,
	 *   subscription_ends (unix or ISO), subscription_price_id, subscription_interval,
	 *   product_id, plan_title
	 */
	function apply_entitlement_from_provider($user_id, $facts){

		$user_id = (int)$user_id;
		if ($user_id < 1 || !is_array($facts)){
			return false;
		}

		$status = (string)($facts['status'] ?? '');
		$active = in_array($status, ['active', 'trialing', 'past_due'], true) ? '1' : '0';

		$ends = $facts['subscription_ends'] ?? '';
		if (is_int($ends) || (is_string($ends) && ctype_digit($ends))){
			// Skip epoch-zero so we do not store 1970-01-01
			if ((int)$ends > 0){
				$ends = date('c', (int)$ends);
			} else {
				$ends = '';
			}
		}

		$fields = [
				'status' => $status,
				'active' => $active,
				'interval' => (string)($facts['subscription_interval'] ?? ''),
				'ends' => (string)$ends,
				'price_id' => (string)($facts['subscription_price_id'] ?? ''),
				'cancel_at_period_end' => !empty($facts['cancel_at_period_end']) ? '1' : '0',
		];

		if (!empty($facts['stripe_subscription_id'])){
			$fields['stripe_subscription_id'] = (string)$facts['stripe_subscription_id'];
		}
		if (!empty($facts['stripe_customer_id'])){
			$fields['stripe_customer_id'] = (string)$facts['stripe_customer_id'];
		}
		if (!empty($facts['product_id'])){
			$fields['product_id'] = (int)$facts['product_id'];
		}
		if (!empty($facts['plan_title'])){
			$fields['plan'] = (string)$facts['plan_title'];
		}
		if (!empty($facts['currency_id'])){
			$fields['currency_id'] = (int)$facts['currency_id'];
		}

		return $this->merge_user_subscription($user_id, $fields);

	}

	function clear_entitlement($user_id){

		$user_id = (int)$user_id;
		if ($user_id < 1){
			return false;
		}

		return $this->merge_user_subscription($user_id, [
				'active' => '0',
				'status' => 'canceled',
				'cancel_at_period_end' => '0',
		]);

	}

	/**
	 * Format period end for FE language (visitor cookie).
	 * Example en: 12 Jun 2027
	 */
	function format_subscription_date($ends_iso_or_unix){

		$ts = 0;
		if (is_int($ends_iso_or_unix) || (is_string($ends_iso_or_unix) && ctype_digit($ends_iso_or_unix))){
			$ts = (int)$ends_iso_or_unix;
		} else {
			$raw = trim((string)$ends_iso_or_unix);
			if ($raw !== ''){
				$ts = strtotime($raw);
			}
		}
		if ($ts < 1){
			return '';
		}

		$lang = 'en';
		$this->load->model('cms/cms_language_model');
		$cookie_lang = $this->cms_language_model->get_current_language();
		if (is_string($cookie_lang) && $cookie_lang !== ''){
			$lang = strtolower(str_replace('_', '-', $cookie_lang));
		}

		if (class_exists('\\IntlDateFormatter')){
			try {
				$fmt = new \IntlDateFormatter(
						$lang,
						\IntlDateFormatter::NONE,
						\IntlDateFormatter::NONE,
						null,
						null,
						'd MMM y'
				);
				$out = $fmt->format($ts);
				if (is_string($out) && $out !== ''){
					return $out;
				}
			} catch (\Exception $e) {
				// fall through
			}
		}

		return date('j M Y', $ts);

	}

	/**
	 * Seconds left until period end; 0 if unknown or past.
	 */
	function subscription_seconds_remaining($sub = null){

		if ($sub === null){
			$sub = $this->get_user_subscription();
		}
		if (!is_array($sub)){
			return 0;
		}
		$ends = $sub['ends'] ?? '';
		$ts = 0;
		if (is_int($ends) || (is_string($ends) && ctype_digit($ends))){
			$ts = (int)$ends;
		} else if ($ends !== ''){
			$ts = (int)strtotime((string)$ends);
		}
		if ($ts < 1){
			return 0;
		}
		$left = $ts - time();
		return $left > 0 ? $left : 0;

	}

	/**
	 * True if period end is within the next 30 days (for year → month gate).
	 */
	function subscription_within_last_month($sub = null){

		$left = $this->subscription_seconds_remaining($sub);
		return $left > 0 && $left <= (30 * 86400);

	}

	/**
	 * Display title at panel time: shop product heading from meta.product_id.
	 * Fallback "Premium". No meta write/cache.
	 */
	function resolve_subscription_plan_title($sub, $user_id = 0){

		if (!is_array($sub)){
			return 'Premium';
		}

		$product_id = (int)($sub['product_id'] ?? 0);
		if ($product_id < 1){
			return 'Premium';
		}

		$this->load->model('cms/cms_page_panel_model');
		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		if (empty($product) || !is_array($product)){
			return 'Premium';
		}

		$heading = trim((string)($product['heading'] ?? ''));
		return $heading !== '' ? $heading : 'Premium';

	}

	/**
	 * Resolve stripe_price_id for a paid product + currency.
	 */
	function get_stripe_price_id_for_product($product_id, $currency_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$product = $this->cms_page_panel_model->get_cms_page_panel((int)$product_id);
		if (empty($product)){
			return '';
		}
		$priced = $this->shop_model->get_product_price_in_currency($product, (int)$currency_id);
		return trim((string)($priced['stripe_price_id'] ?? ''));

	}

	/**
	 * FE page translate: settings panel names when pricing/manage is on the page.
	 *
	 * @return string[]
	 */
	function get_page_translate_extra_panel_names($context = []){

		$names = [];
		if (!$this->_page_has_plan_panels($context)){
			return $names;
		}

		// Shared labels used by pricing + manage
		$names[] = 'subscription/subscription';

		return $names;

	}

	/**
	 * FE page translate: subscription products for categories used by plan panels.
	 *
	 * @return array[] id, label, panel_name, kind
	 */
	function get_page_translate_extra_panels($context = []){

		$out = [];
		if (!$this->_page_has_plan_panels($context)){
			return $out;
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_translation_model');

		$category_ids = $this->_plan_category_ids_from_page($context);
		if ($category_ids === []){
			return $out;
		}

		$seen_products = [];

		foreach ($category_ids as $category_id){

			$subcats = $this->cms_page_panel_model->get_list('shop/subcategory', [
					'category_id' => $category_id,
			]);
			if (!is_array($subcats)){
				continue;
			}

			foreach ($subcats as $sub){

				$sub_id = (int)($sub['cms_page_panel_id'] ?? 0);
				if ($sub_id < 1){
					continue;
				}

				// Subcategory title often shown on cards (may lack translate:1 — skip if no fields)
				$this->_append_translate_list_item($out, $sub_id, 'shop/subcategory', 'product', 'Plan group');

				$products = $this->cms_page_panel_model->get_list('shop/product', [
						'subcategory_id' => $sub_id,
				]);
				if (!is_array($products)){
					continue;
				}

				foreach ($products as $product){

					$pid = (int)($product['cms_page_panel_id'] ?? 0);
					if ($pid < 1 || isset($seen_products[$pid])){
						continue;
					}
					$seen_products[$pid] = 1;

					$heading = trim((string)($product['heading'] ?? ''));
					$prefix = $heading !== '' ? $heading : ('Product #'.$pid);
					$this->_append_translate_list_item($out, $pid, 'shop/product', 'product', 'Product: '.$prefix);

				}

			}

		}

		return $out;

	}

	/**
	 * True when page hosts subscription/pricing or subscription/manage.
	 */
	function _page_has_plan_panels($context){

		$panel_names = [];
		if (!empty($context['panel_names']) && is_array($context['panel_names'])){
			$panel_names = $context['panel_names'];
		}

		foreach ($panel_names as $pn){
			$pn = (string)$pn;
			if ($pn === 'subscription/pricing' || $pn === 'subscription/manage'){
				return true;
			}
		}

		// Fallback: scan page blocks
		$cms_page_id = (int)($context['cms_page_id'] ?? 0);
		if ($cms_page_id < 1){
			return false;
		}

		return $this->_plan_category_ids_from_page($context) !== [];

	}

	/**
	 * category_id values from pricing/manage instances on the page (+ layout pages).
	 *
	 * @return int[]
	 */
	function _plan_category_ids_from_page($context){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');

		$cms_page_id = (int)($context['cms_page_id'] ?? 0);
		if ($cms_page_id < 1){
			return [];
		}

		$page_ids = [$cms_page_id];
		$page = $this->cms_page_model->get_page($cms_page_id);
		if (!empty($page['positions']) && is_array($page['positions'])){
			foreach ($page['positions'] as $position){
				$pos_id = (int)($position['value'] ?? 0);
				if ($pos_id > 0 && !in_array($pos_id, $page_ids, true)){
					$page_ids[] = $pos_id;
				}
			}
		}

		$category_ids = [];
		foreach ($page_ids as $page_id){

			$blocks = $this->cms_page_panel_model->get_cms_page_panels_by([
					'cms_page_id' => $page_id,
					'show' => 1,
			]);
			if (!is_array($blocks)){
				continue;
			}

			foreach ($blocks as $block){
				$pn = (string)($block['panel_name'] ?? '');
				if ($pn !== 'subscription/pricing' && $pn !== 'subscription/manage'){
					continue;
				}
				$cid = (int)($block['category_id'] ?? 0);
				if ($cid > 0 && !in_array($cid, $category_ids, true)){
					$category_ids[] = $cid;
				}
			}

		}

		return $category_ids;

	}

	/**
	 * Append one list item to FE translate extras if it has translate fields.
	 */
	function _append_translate_list_item(&$out, $cms_page_panel_id, $expect_panel, $kind, $label){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id < 1){
			return;
		}

		// Dedupe by id within $out
		foreach ($out as $row){
			if ((int)($row['id'] ?? 0) === $cms_page_panel_id){
				return;
			}
		}

		$fields = $this->cms_translation_model->list_translatable_fields($cms_page_panel_id);
		if (empty($fields)){
			return;
		}

		$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id, '');
		$panel_name = (string)($block['panel_name'] ?? $expect_panel);
		if ($expect_panel !== '' && $panel_name !== $expect_panel){
			// still allow if panel name empty
			if ($panel_name !== ''){
				// keep if related
			}
		}

		$admin_title = $this->cms_page_panel_model->get_panel_admin_title($block);
		if ($admin_title !== '' && strpos($label, '#') !== false){
			$label = preg_replace('/#\d+$/', $admin_title, $label);
		} else if ($admin_title !== '' && (strpos($label, 'Product: ') === 0 || strpos($label, 'Plan group') === 0)){
			if (strpos($label, 'Product: ') === 0){
				$label = 'Product: '.$admin_title;
			}
		}

		$out[] = [
				'id' => $cms_page_panel_id,
				'label' => $label,
				'panel_name' => $panel_name !== '' ? $panel_name : $expect_panel,
				'kind' => $kind,
		];

	}

}
