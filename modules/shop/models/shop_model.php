<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shop_model extends \Model {

	/**
	 * Logged-in front user, or [] when the user module is not installed.
	 * Cart/checkout must not load user/user_model unless user is in config modules.
	 */
	function get_front_user(){

		if (!in_array('user', $GLOBALS['config']['modules'] ?? [], true)){
			return [];
		}

		$this->load->model('user/user_model');
		$user = $this->user_model->get_current();

		return is_array($user) ? $user : [];

	}

	function get_product_variations($product_id, $params = []){

		$this->load->model('shop/shop_dim_model');
		return $this->shop_dim_model->get_product_dim_variations($product_id, $params);

	}

	function get_dim_value_data($dimension_name, $dimension_value_id){

		$this->load->model('shop/shop_dim_model');
		return $this->shop_dim_model->get_dim_value_data($dimension_name, $dimension_value_id);

	}

	function get_dim_label($dimension_name){

		$this->load->model('shop/shop_dim_model');
		return $this->shop_dim_model->get_dim_label($dimension_name);

	}

	/**
	 * Long-lived cookie identity for anonymous carts (not sequential order id).
	 */
	function get_cart_cookie_name(){
		return 'shop_cart';
	}

	function get_cart_cookie_days(){
		return 60;
	}

	function get_cart_key_from_cookie(){
		$name = $this->get_cart_cookie_name();
		$key = $_COOKIE[$name] ?? '';
		if (!is_string($key) || !preg_match('/^[a-f0-9]{32,64}$/', $key)){
			return '';
		}
		return $key;
	}

	function set_cart_cookie($cart_key){
		include_once($GLOBALS['config']['base_path'].'system/helpers/cookie_helper.php');
		cms_cookie_create($this->get_cart_cookie_name(), $cart_key, $this->get_cart_cookie_days());
	}

	function clear_cart_cookie(){
		include_once($GLOBALS['config']['base_path'].'system/helpers/cookie_helper.php');
		// Expire immediately
		cms_cookie_create($this->get_cart_cookie_name(), '', -1);
		unset($_COOKIE[$this->get_cart_cookie_name()]);
	}

	function order_is_draft($order){

		return (string)($order['status'] ?? '') === '';

	}

	/**
	 * Money state. Legacy rows with status=paid and empty payment_status count as paid.
	 */
	function order_payment_status($order){

		$pay = trim((string)($order['payment_status'] ?? ''));
		if ($pay !== ''){
			return $pay;
		}
		if (!empty($order['paid_time']) || (string)($order['status'] ?? '') === 'paid'){
			return 'paid';
		}
		return 'unpaid';

	}

	/**
	 * Physical / fulfilment lifecycle. Legacy status=paid maps to unfulfilled.
	 */
	function order_physical_status($order){

		$st = (string)($order['status'] ?? '');
		if ($st === 'paid'){
			return 'unfulfilled';
		}
		return $st;

	}

	function order_is_paid($order){

		return $this->order_payment_status($order) === 'paid';

	}

	/**
	 * CMS fulfilment emails: paid, not finished. Per-line skip is in _pending_lines.
	 */
	function order_cms_fulfilment_ok($order){

		if ($this->order_is_finished($order)){
			return false;
		}
		if (!$this->order_is_paid($order)){
			return false;
		}
		$st = $this->order_physical_status($order);
		return !in_array($st, ['', 'abandoned', 'cancelled', 'finished'], true);

	}

	function order_is_finished($order){

		return $this->order_physical_status($order) === 'finished';

	}

	/**
	 * Unfinished claims against listed stock. Finished orders have already posted number.
	 */
	function order_holds_stock($order){

		$st = $this->order_physical_status($order);
		if (in_array($st, ['abandoned', 'cancelled', 'finished'], true)){
			return false;
		}
		$pay = $this->order_payment_status($order);
		if (in_array($pay, ['refunded', 'voided'], true)){
			return false;
		}
		return true;

	}

	function order_line_item_id($line){

		$id = (int)($line['product_item_id'] ?? 0);
		if ($id > 0){
			return $id;
		}
		$ref = (int)($line['ref_id'] ?? 0);
		if ($ref <= 0){
			return 0;
		}
		$this->load->model('cms/cms_page_panel_model');
		$row = $this->cms_page_panel_model->get_cms_page_panel($ref);
		if (($row['panel_name'] ?? '') === 'shop/product_item'){
			return $ref;
		}
		return 0;

	}

	function order_line_ref_id($line){

		$id = (int)($line['ref_id'] ?? 0);
		if ($id > 0){
			return $id;
		}
		return $this->order_line_item_id($line);

	}

	function order_line_status($line){

		return trim((string)($line['status'] ?? ''));

	}

	function order_line_is_fulfilable($line){

		$this->load->model('shop/shop_fulfilment_model');
		if ($this->shop_fulfilment_model->_is_delivery_line($line)){
			return false;
		}
		if (trim((string)($line['shopify_variant_id'] ?? $line['shopify_line_id'] ?? '')) !== ''){
			return true;
		}
		$product_id = (int)($line['product_id'] ?? 0);
		if ($product_id <= 0){
			return false;
		}
		$this->load->model('cms/cms_page_panel_model');
		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		$cat = $this->shop_fulfilment_model->_category_for_product(is_array($product) ? $product : []);
		return trim((string)($cat['fulfilment'] ?? '')) !== '';

	}

	function stamp_draft_lines_unfulfilled($order_id){

		$this->load->model('cms/cms_page_panel_model');
		foreach ($this->get_order_lines($order_id) as $line){
			if ($this->order_line_status($line) !== ''){
				continue;
			}
			$this->cms_page_panel_model->update_cms_page_panel($line['cms_page_panel_id'], [
					'status' => 'unfulfilled',
			]);
		}

	}

	function set_line_physical_status($line, $physical){

		$physical = (string)$physical;
		if (!in_array($physical, ['unfulfilled', 'in_progress', 'fulfilled', 'cancelled'], true)){
			return false;
		}
		$this->load->model('shop/shop_fulfilment_model');
		if ($this->shop_fulfilment_model->_is_delivery_line($line)){
			return false;
		}
		$cur = $this->order_line_status($line);
		if ($cur === $physical){
			return false;
		}
		if ($cur === 'cancelled' && $physical !== 'cancelled'){
			return false;
		}
		if (in_array($cur, ['fulfilled', 'in_progress'], true) && $physical === 'unfulfilled'){
			return false;
		}
		if ($cur === 'fulfilled' && $physical === 'in_progress'){
			return false;
		}
		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->update_cms_page_panel($line['cms_page_panel_id'], [
				'status' => $physical,
		]);
		return true;

	}

	function apply_lines_physical_status($order_id, $physical){

		foreach ($this->get_order_lines($order_id) as $line){
			$this->set_line_physical_status($line, $physical);
		}

	}

	function rollup_order_status($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_fulfilment_model');

		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (empty($order['cms_page_panel_id'])){
			return;
		}
		if ($this->order_is_draft($order) || $this->order_is_finished($order)){
			return;
		}
		if ($this->order_physical_status($order) === 'abandoned'){
			return;
		}

		$has = [
				'unfulfilled' => 0,
				'in_progress' => 0,
				'fulfilled' => 0,
				'cancelled' => 0,
		];
		$n = 0;
		foreach ($this->get_order_lines($order_id) as $line){
			if ($this->shop_fulfilment_model->_is_delivery_line($line)){
				continue;
			}
			$st = $this->order_line_status($line);
			if ($st === ''){
				$st = 'unfulfilled';
			}
			if (!isset($has[$st])){
				$st = 'unfulfilled';
			}
			$has[$st]++;
			$n++;
		}

		if ($n === 0){
			$next = 'unfulfilled';
		} else if ($has['unfulfilled'] === 0 && $has['in_progress'] === 0 && $has['fulfilled'] === 0){
			$next = 'cancelled';
		} else if ($has['unfulfilled'] === 0 && $has['in_progress'] === 0){
			$next = 'fulfilled';
		} else if ($has['in_progress'] > 0 || $has['fulfilled'] > 0){
			$next = 'in_progress';
		} else {
			$next = 'unfulfilled';
		}

		if ($this->order_physical_status($order) !== $next){
			$this->cms_page_panel_model->update_cms_page_panel($order_id, [
					'status' => $next,
			]);
			$order['status'] = $next;
		}

		$this->maybe_finish_order($order_id);

	}

	function maybe_finish_order($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (empty($order['cms_page_panel_id']) || $this->order_is_finished($order)){
			return false;
		}
		if (!$this->order_is_paid($order)){
			return false;
		}

		foreach ($this->get_order_lines($order_id) as $line){
			if (!$this->order_line_is_fulfilable($line)){
				continue;
			}
			$st = $this->order_line_status($line);
			if ($st === ''){
				$st = 'unfulfilled';
			}
			if (!in_array($st, ['fulfilled', 'cancelled'], true)){
				return false;
			}
		}

		return $this->finish_order($order_id);

	}

	function finish_order($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (empty($order['cms_page_panel_id'])){
			return false;
		}
		if ($this->order_is_finished($order)){
			$this->post_listed_stock($order_id);
			return true;
		}

		$this->cms_page_panel_model->update_cms_page_panel($order_id, [
				'status' => 'finished',
		]);
		$this->post_listed_stock($order_id);
		return true;

	}

	/**
	 * Count-mode: subtract fulfilled/in_progress line qty from listed item.number once.
	 * Finished orders are then ignored by held_qty.
	 */
	function post_listed_stock($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');
		$this->load->model('shop/shop_fulfilment_model');

		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (empty($order['cms_page_panel_id'])){
			return false;
		}
		if (!empty($order['stock_posted_time'])){
			return true;
		}

		foreach ($this->get_order_lines($order_id) as $line){
			if ($this->shop_fulfilment_model->_is_delivery_line($line)){
				continue;
			}
			$st = $this->order_line_status($line);
			if (!in_array($st, ['fulfilled', 'in_progress'], true)){
				continue;
			}
			$item_id = $this->order_line_item_id($line);
			if ($item_id <= 0){
				continue;
			}
			$item = $this->cms_page_panel_model->get_cms_page_panel($item_id);
			if (($item['panel_name'] ?? '') !== 'shop/product_item'){
				continue;
			}
			if ($this->shop_dim_model->item_stock_control($item) !== 'count'){
				continue;
			}
			$qty = (int)($line['qty'] ?? $line['quantity'] ?? 1);
			if ($qty < 1){
				$qty = 1;
			}
			$listed = (int)($item['number'] ?? 0);
			$this->cms_page_panel_model->update_cms_page_panel($item_id, [
					'number' => $listed - $qty,
			]);
		}

		$this->cms_page_panel_model->update_cms_page_panel($order_id, [
				'stock_posted_time' => time(),
		]);
		return true;

	}

	/**
	 * End draft cart session after remote checkout completes (or force close).
	 * Order is no longer status '' so it will not be reused as a basket.
	 * $status is physical (unfulfilled / in_progress / fulfilled / cancelled).
	 * Legacy $status 'paid' becomes unfulfilled + payment_status paid.
	 */
	function close_cart_order($order_id, $status = 'unfulfilled', $payment_status = ''){

		$this->load->model('cms/cms_page_panel_model');

		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (empty($order['cms_page_panel_id'])){
			return false;
		}

		$physical = (string)$status;
		$pay = trim((string)$payment_status);
		if ($physical === 'paid'){
			$physical = 'unfulfilled';
			if ($pay === ''){
				$pay = 'paid';
			}
		}
		if ($physical === ''){
			$physical = 'unfulfilled';
		}
		if ($pay === ''){
			$pay = 'unpaid';
		}

		$now = time();
		$update = [
				'status' => $physical,
				'payment_status' => $pay,
		];
		if ($pay === 'paid'){
			$update['paid_time'] = !empty($order['paid_time']) ? $order['paid_time'] : $now;
		}
		if (empty($order['order_created'])){
			$update['order_created'] = $now;
		}
		$this->cms_page_panel_model->update_cms_page_panel($order_id, $update);
		$this->stamp_draft_lines_unfulfilled($order_id);

		$cookie_key = $this->get_cart_key_from_cookie();
		if ($cookie_key !== '' && !empty($order['cart_key']) && $cookie_key === $order['cart_key']){
			$this->clear_cart_cookie();
		}

		if (!empty($_SESSION['order_id']) && (int)$_SESSION['order_id'] === (int)$order_id){
			unset($_SESSION['order_id']);
		}
		if (!empty($_SESSION['cart_key']) && !empty($order['cart_key']) && $_SESSION['cart_key'] === $order['cart_key']){
			unset($_SESSION['cart_key']);
		}

		// Invalidate shop settings cache not needed; clear order session shopify side keys
		if (!empty($_SESSION['shopify'])){
			unset($_SESSION['shopify']['shopify_cart_id'], $_SESSION['shopify']['checkout_url']);
		}

		return true;

	}

	/**
	 * Unpaid draft whose remote checkout never became an order. Never sets paid_time.
	 */
	function abandon_cart_order($order_id){

		$this->load->model('cms/cms_page_panel_model');

		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (empty($order['cms_page_panel_id'])){
			return false;
		}
		if ($this->order_is_paid($order)){
			return false;
		}

		$this->cms_page_panel_model->update_cms_page_panel($order_id, [
				'status' => 'abandoned',
				'payment_status' => 'unpaid',
				'shopify_cart_id' => '',
		]);

		$cookie_key = $this->get_cart_key_from_cookie();
		if ($cookie_key !== '' && !empty($order['cart_key']) && $cookie_key === $order['cart_key']){
			$this->clear_cart_cookie();
		}

		if (!empty($_SESSION['order_id']) && (int)$_SESSION['order_id'] === (int)$order_id){
			unset($_SESSION['order_id']);
		}
		if (!empty($_SESSION['cart_key']) && !empty($order['cart_key']) && $_SESSION['cart_key'] === $order['cart_key']){
			unset($_SESSION['cart_key']);
		}
		if (!empty($_SESSION['shopify'])){
			unset($_SESSION['shopify']['shopify_cart_id'], $_SESSION['shopify']['checkout_url']);
		}

		return true;

	}

	/**
	 * Stable hash of lines for site → provider push comparison (not for remote pull).
	 */
	function calculate_order_lines_fingerprint($lines){

		$parts = [];
		foreach($lines as $line){
			$merchandise = $line['merchandise_id'] ?? '';
			$variant = $line['shopify_variant_id'] ?? '';
			if ($merchandise === '' && $variant === ''){
				// Include local-only lines so fingerprint changes if they appear/disappear
				$merchandise = 'local:'.($line['ref_id'] ?? $line['cms_page_panel_id'] ?? '');
			}
			$qty = (int)($line['qty'] ?? $line['quantity'] ?? 1);
			$attrs = $line['attributes'] ?? [];
			if (is_array($attrs)){
				ksort($attrs);
				$attr_s = json_encode($attrs);
			} else {
				$attr_s = (string)$attrs;
			}
			$parts[] = $merchandise.'|'.$variant.'|'.$qty.'|'.$attr_s;
		}
		sort($parts);
		return md5(implode("\n", $parts));

	}

	/**
	 * Order ID / Worldpay cartId = CMS panel id (decimal string).
	 */
	function ensure_order_identity($order){

		if (empty($order['cms_page_panel_id'])){
			return $order;
		}

		$this->load->model('cms/cms_page_panel_model');

		$id_str = (string)(int)$order['cms_page_panel_id'];
		$update = [];
		if (($order['heading'] ?? '') === ''){
			$update['heading'] = $id_str;
			$order['heading'] = $id_str;
		}
		if (($order['number'] ?? '') === ''){
			$update['number'] = $id_str;
			$order['number'] = $id_str;
		}
		if ($update){
			$this->cms_page_panel_model->update_cms_page_panel($order['cms_page_panel_id'], $update);
		}

		return $order;

	}

	function ensure_order_cart_key($order){
		if (empty($order['cms_page_panel_id'])){
			return $order;
		}
		$this->load->model('cms/cms_page_panel_model');
		$order = $this->ensure_order_identity($order);
		if (empty($order['cart_key']) || !preg_match('/^[a-f0-9]{32,64}$/', $order['cart_key'])){
			$cart_key = bin2hex(random_bytes(16));
			$this->cms_page_panel_model->update_cms_page_panel($order['cms_page_panel_id'], ['cart_key' => $cart_key]);
			$order['cart_key'] = $cart_key;
		}
		$this->set_cart_cookie($order['cart_key']);
		$_SESSION['order_id'] = $order['cms_page_panel_id'];
		$_SESSION['cart_key'] = $order['cart_key'];
		return $order;
	}

	function get_order_by_cart_key($cart_key){
		if ($cart_key === ''){
			return null;
		}
		$orders = $this->cms_page_panel_model->get_list('shop/order', [
				'cart_key' => $cart_key,
				'status' => '',
		]);
		$order = reset($orders);
		if (empty($order) || empty($order['cms_page_panel_id'])){
			return null;
		}
		return $order;
	}

	/**
	 * Resolve active draft order: user account, durable cookie cart_key, then session cache.
	 */
	function get_current_order($user){
		
		$this->load->model('cms/cms_page_panel_model');

		$order = null;
		$cookie_key = $this->get_cart_key_from_cookie();
		$cookie_order = $this->get_order_by_cart_key($cookie_key);
		
		if(!empty($user['user_id'])){
			
			$orders = $this->cms_page_panel_model->get_list('shop/order', [
					'user_id' => $user['user_id'], 
					'status' => '', 
			]);
			$order = reset($orders);
			if (empty($order)){
				$order = null;
			}

		}

		// Anonymous identity from long-lived cookie (preferred over session alone)
		$guest_order_id = 0;
		if (!empty($cookie_order['cms_page_panel_id'])){
			$guest_order_id = (int)$cookie_order['cms_page_panel_id'];
		} else if (!empty($_SESSION['order_id'])){
			$guest_order_id = (int)$_SESSION['order_id'];
		}
		
		// Merge guest cart into logged-in user order
		if (!empty($order) && $guest_order_id && (int)$order['cms_page_panel_id'] != $guest_order_id){
			
			$account_refs = [];
			
			$account_order_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order['cms_page_panel_id']]);
			
			foreach($account_order_lines as $line){
			
				if(!empty($line['ref_id'])){
					$account_refs[] = $line['ref_id'];
				}
			
			}

			$guest_order_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $guest_order_id]);
				
			foreach($guest_order_lines as $line){
					
				// Move freeform / shopify lines always; product_item by ref uniqueness
				$is_ref_line = !empty($line['ref_id']);
				if (!$is_ref_line || !in_array($line['ref_id'], $account_refs)){
					$this->cms_page_panel_model->update_cms_page_panel($line['cms_page_panel_id'], ['order_id' => $order['cms_page_panel_id']]);
				}
					
			}
			
			$this->delete_order($guest_order_id);

		}

		// Assign full anonymous order to user
		if(empty($order) && $guest_order_id && !empty($user['user_id'])){
			
			$this->cms_page_panel_model->update_cms_page_panel($guest_order_id, ['user_id' => $user['user_id']]);
			
			$order = $this->cms_page_panel_model->get_cms_page_panel($guest_order_id);
			
			if (empty($order) || ($order['status'] ?? '') != ''){
				$order = null;
			}

		}
		
		// Guest: use cookie/session draft if not owned by another user
		if(empty($order) && $guest_order_id){
			$order = $this->cms_page_panel_model->get_cms_page_panel($guest_order_id);
			if (empty($order) || !empty($order['user_id']) || ($order['status'] ?? '') != ''){
				$order = null;
			}
		}
		
		if (empty($order) || ($order['status'] ?? '') != ''){

			$cart_key = $cookie_key !== '' ? $cookie_key : bin2hex(random_bytes(16));
		
			$order = [
					'panel_name' => 'shop/order',
					'show' => 1,
					'sort' => 'first',
					'heading' => '',
					'number' => '',
					'status' => '',
					'payment_status' => 'unpaid',
					'created' => time(),
					'order_created' => '',
					'paid_time' => '',
					'last_result' => '',
					'cart_key' => $cart_key,
					'user_id' => !empty($user['user_id']) ? $user['user_id'] : '',
			];
				
			$order_id = $this->cms_page_panel_model->create_cms_page_panel($order);
			$id_str = (string)(int)$order_id;
			$this->cms_page_panel_model->update_cms_page_panel($order_id, [
					'heading' => $id_str,
					'number' => $id_str,
			]);
		
			$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		
		}

		return $this->ensure_order_cart_key($order);
		
	}

	/**
	 * Optional: do not create a draft order when cart is empty (badge shell).
	 */
	function get_current_order_if_any($user = []){

		$this->load->model('cms/cms_page_panel_model');

		if (!empty($user['user_id'])){
			$orders = $this->cms_page_panel_model->get_list('shop/order', [
					'user_id' => $user['user_id'],
					'status' => '',
			]);
			$order = reset($orders);
			if (!empty($order['cms_page_panel_id']) && ($order['status'] ?? '') === ''){
				return $this->ensure_order_cart_key($order);
			}
		}

		$cookie_key = $this->get_cart_key_from_cookie();
		$order = $this->get_order_by_cart_key($cookie_key);
		if (!empty($order)){
			if (!empty($order['user_id']) && empty($user['user_id'])){
				return null;
			}
			return $this->ensure_order_cart_key($order);
		}

		if (!empty($_SESSION['order_id'])){
			$order = $this->cms_page_panel_model->get_cms_page_panel($_SESSION['order_id']);
			if (!empty($order['cms_page_panel_id']) && ($order['status'] ?? '') === ''){
				if (!empty($order['user_id']) && empty($user['user_id'])){
					return null;
				}
				return $this->ensure_order_cart_key($order);
			}
		}

		return null;

	}

	function get_order_lines($order_id){

		$this->load->model('cms/cms_page_panel_model');
		if (empty($order_id)){
			return [];
		}
		return $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);

	}

	function get_order_quantity($order_id){

		$qty = 0;
		foreach($this->get_order_lines($order_id) as $line){
			$qty += (int)($line['qty'] ?? $line['quantity'] ?? 1);
		}
		return $qty;

	}

	/**
	 * All panels that provide a service (config provides registry).
	 * @return array panel_name => ['panel','module','service','label']
	 */
	function get_provides($service){

		$providers = $GLOBALS['config']['provides'][$service] ?? [];
		if (!is_array($providers) || empty($providers)){
			return [];
		}
		// Legacy single entry
		if (isset($providers['panel']) && is_string($providers['panel'])){
			$panel = $providers['panel'];
			return [
					$panel => [
							'panel' => $panel,
							'module' => $providers['module'] ?? '',
							'service' => $service,
							'label' => $providers['label'] ?? $panel,
					],
			];
		}
		return $providers;

	}

	function get_shop_settings(){

		if (!empty($GLOBALS['shop_settings_cache']) && is_array($GLOBALS['shop_settings_cache'])){
			return $GLOBALS['shop_settings_cache'];
		}

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/shop');
		if (!is_array($settings)){
			$settings = [];
		}

		$GLOBALS['shop_settings_cache'] = $settings;
		return $settings;

	}

	/**
	 * Selected checkout panel from shop settings (panel name, e.g. shopify/checkout).
	 * Empty string if not configured.
	 */
	function get_checkout_panel(){

		$settings = $this->get_shop_settings();
		$panel = $settings['shop_checkout'] ?? '';
		if (!is_string($panel)){
			return '';
		}
		return trim($panel);

	}

	/**
	 * image_compose provider panel. Setting wins; if empty and exactly one provider, use it.
	 */
	function get_image_compose_panel(){

		$settings = $this->get_shop_settings();
		$panel = $settings['image_compose'] ?? '';
		if (is_string($panel)){
			$panel = trim($panel);
			if ($panel !== ''){
				return $panel;
			}
		}

		$list = $GLOBALS['config']['provides']['image_compose'] ?? [];
		if (!is_array($list) || count($list) !== 1){
			return '';
		}
		$first = reset($list);
		if (!is_array($first)){
			return '';
		}
		$from_list = trim((string)($first['panel'] ?? ''));
		return $from_list;

	}
	
	function create_order_line($order_id, $params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');
		
		$ref_id = (int)($params['product_item_id'] ?? $params['ref_id'] ?? 0);
		$product_item_id = 0;
		$description = '';
		$product = [];
		$product_item = [];

		$dims_param = $params['dims'] ?? [];
		if (is_string($dims_param) && $dims_param !== ''){
			$decoded = json_decode($dims_param, true);
			$dims_param = is_array($decoded) ? $decoded : [];
		}
		if (!is_array($dims_param)){
			$dims_param = [];
		}
		
		if ($ref_id > 0){

			$row = $this->cms_page_panel_model->get_cms_page_panel($ref_id);
			if (($row['panel_name'] ?? '') === 'shop/product_item'){
				$product_item = $row;
				$product_item_id = $ref_id;
				$product = $this->cms_page_panel_model->get_cms_page_panel($product_item['product_id']);
				$params['shopify_variant_id'] = (string)($product_item['shopify_variant_id'] ?? '');
				$params['merchandise_id'] = '';
			}

		}

		if ($product_item_id <= 0 && $dims_param){
			$description = $this->shop_dim_model->dims_description([
					'dims' => $this->shop_dim_model->dims_rows_from_map($dims_param),
			]);
		}

		// Checkout connector on the item (shopify extend). Cart is the CMS item + dims.
		if (!empty($params['shopify_variant_id']) || !empty($params['merchandise_id'])){

			$product_id = (int)($params['product_id'] ?? ($product['cms_page_panel_id'] ?? 0));
			if ($product_id && empty($product['cms_page_panel_id'])){
				$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
			}

			$variant_id = $params['shopify_variant_id'] ?? '';
			$merchandise_id = $params['merchandise_id'] ?? '';
			if ($merchandise_id === '' && $variant_id !== ''){
				if (strpos((string)$variant_id, 'gid://') === 0){
					$merchandise_id = $variant_id;
					if (preg_match('/ProductVariant\/(\d+)/', $variant_id, $m)){
						$variant_id = $m[1];
					}
				} else {
					$merchandise_id = 'gid://shopify/ProductVariant/'.$variant_id;
				}
			}

			$qty = max(1, (int)($params['qty'] ?? $params['quantity'] ?? 1));
			$price = $params['price'] ?? $params['expected_price'] ?? ($product['price'] ?? 0);
			$attributes = $params['attributes'] ?? [];
			if (is_string($attributes) && $attributes !== ''){
				$decoded = json_decode($attributes, true);
				if (is_array($decoded)){
					$attributes = $decoded;
				} else {
					$attributes = [];
				}
			}

			$attr_bits = [];
			if (is_array($attributes)){
				foreach($attributes as $k => $v){
					if (is_array($v) && isset($v['key'])){
						$attr_bits[] = ($v['key'] ?? '').': '.($v['value'] ?? '');
					} else if (!is_array($v)){
						$attr_bits[] = $k.': '.$v;
					}
				}
			}
			if ($attr_bits){
				$attr_s = implode("\n", $attr_bits);
				$description = $description !== '' ? $description."\n".$attr_s : $attr_s;
			}

			$order_line = [
					'panel_name' => 'shop/order_line',
					'show' => 1,
					'sort' => 'first',
					'ref_id' => $ref_id,
					'product_item_id' => $product_item_id ? $product_item_id : '',
					'product_id' => $product_id,
					'shopify_variant_id' => (string)$variant_id,
					'merchandise_id' => $merchandise_id,
					'line_type' => 'shopify_variant',
					'qty' => $qty,
					'quantity' => $qty,
					'price' => $price,
					'expected_price' => $params['expected_price'] ?? $price,
					'description' => $description,
					'item' => $params['item'] ?? ($product['heading'] ?? ''),
					'image' => $params['image'] ?? ($product['image'] ?? ''),
					'attributes' => is_array($attributes) ? $attributes : [],
					'order_id' => $order_id,
			];

			return $this->cms_page_panel_model->create_cms_page_panel($order_line);

		}
		
		if (empty($product['cms_page_panel_id']) && !empty($params['product_id'])){
			$product = $this->cms_page_panel_model->get_cms_page_panel($params['product_id']);
		}

		$qty = max(1, (int)($params['qty'] ?? $params['quantity'] ?? 1));
		$order_line = [
				'panel_name' => 'shop/order_line',
				'show' => 1,
				'sort' => 'first',
				'ref_id' => $ref_id,
				'product_item_id' => $product_item_id ? $product_item_id : '',
				'product_id' => (int)($product['cms_page_panel_id'] ?? $params['product_id'] ?? 0),
				'line_type' => 'local_item',
				'qty' => $qty,
				'quantity' => $qty,
				'price' => !empty($product_item['price']) ? $product_item['price'] : (!empty($product['price']) ? $product['price'] : 0),
				'description' => $description,
				'item' => !empty($product['heading']) ? $product['heading'] : ($product_item['heading'] ?? ''),
				'image' => $params['image'] ?? ($product['image'] ?? ''),
				'order_id' => $order_id,
		];
		
		return $this->cms_page_panel_model->create_cms_page_panel($order_line);
		
	}
	
	function delete_order($order_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$this->cms_page_panel_model->delete_cms_page_panel($order_id);
		
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
		foreach($lines as $line){
			$this->cms_page_panel_model->delete_cms_page_panel($line['cms_page_panel_id']);
		}
		
	}
	
	function delete_order_line($order_id, $order_line_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$line = $this->cms_page_panel_model->get_cms_page_panel($order_line_id);
		
		if ($line['order_id'] == $order_id){
			
			$this->cms_page_panel_model->delete_cms_page_panel($order_line_id);
			
			// check if only delivery remaining
			$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
			if (count($lines) == 1){
				
				$line_to_check = reset($lines);
				
				if (!empty($line_to_check['ref_id'])){
					$ref_item = $this->cms_page_panel_model->get_cms_page_panel($line_to_check['ref_id']);
					if ($ref_item['panel_name'] == 'shop/delivery'){
						$this->cms_page_panel_model->delete_cms_page_panel($line_to_check['cms_page_panel_id']);
					}
				}

			}
			
		}

	}
	
	function calculate_product_item_hash($ref){

		$this->load->model('shop/shop_dim_model');
		return $this->shop_dim_model->item_dims_hash($ref);

	}
	
	function set_order_paid($order_id, $wp_data = []){
		
		$this->load->model('cms/cms_page_panel_model');

		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		$now = time();
		$update = [
				'status' => 'unfulfilled',
				'payment_status' => 'paid',
				'last_result' => 'authorized',
				'paid_time' => !empty($order['paid_time']) ? $order['paid_time'] : $now,
				'meta' => json_encode(['worldpay' => $wp_data], JSON_PRETTY_PRINT),
		];
		if (empty($order['order_created'])){
			$update['order_created'] = $now;
		}
		
		$this->cms_page_panel_model->update_cms_page_panel($order_id, $update);
		$this->stamp_draft_lines_unfulfilled($order_id);

		$this->load->model('shop/shop_fulfilment_model');
		$this->shop_fulfilment_model->request_fulfilment($order_id);
		$this->rollup_order_status($order_id);
		
		// recipients
		$shop_config = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/shop');
		
		$mailto = [];
		foreach($shop_config['emails'] as $email){
			$mailto[] = $email['email'];
		}
		
		$this->load->model('mail/mail_model');
		$this->mail_model->send_mail([
				'panel' => 'shop/mail_order',
				'order_id' => $order_id,
				'mail_to' => $mailto,
		]);

	}
	
	function is_product_in_basket($product_id, $order_id){
		
		$return = false;
	
		$this->load->model('cms/cms_page_panel_model');
	
		$items = [];
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
		foreach($lines as $line){
			
			$item_id = $this->order_line_item_id($line);
			if ($item_id <= 0){
				continue;
			}
			$product_item = $this->cms_page_panel_model->get_cms_page_panel($item_id);

			if(!empty($product_item['product_id']) && $product_item['product_id'] == $product_id){
				$return = true;
			}
		}
	
		return $return;
	
	}

	/**
	 * Shop default currency panel (shop settings default_currency_id).
	 */
	function get_default_currency(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/shop');
		$id = (int)($settings['default_currency_id'] ?? 0);
		if ($id < 1){
			return null;
		}

		return $this->get_currency($id);

	}

	/**
	 * Currency list item by cms_page_panel_id.
	 */
	function get_currency($currency_id){

		$currency_id = (int)$currency_id;
		if ($currency_id < 1){
			return null;
		}

		$this->load->model('cms/cms_page_panel_model');
		$c = $this->cms_page_panel_model->get_cms_page_panel($currency_id);
		if (empty($c) || !is_array($c)){
			return null;
		}
		if (($c['panel_name'] ?? '') !== 'shop/currency' && strpos((string)($c['panel_name'] ?? ''), 'currency') === false){
			// Still allow if id points at a currency list item
		}

		$rate = (float)str_replace(',', '.', (string)($c['rate'] ?? '1'));
		if ($rate <= 0){
			$rate = 1.0;
		}

		return [
				'cms_page_panel_id' => (int)($c['cms_page_panel_id'] ?? $currency_id),
				'heading' => (string)($c['heading'] ?? ''),
				'sign' => (string)($c['sign'] ?? ''),
				'rate' => $rate,
		];

	}

	/**
	 * Currencies for selectors / pricing.
	 * $ids null or empty → all shown shop/currency list items (list order).
	 * Otherwise resolve each id via get_currency (skip missing).
	 *
	 * @param int[]|null $ids
	 * @return array[] list of get_currency() rows
	 */
	function get_currencies($ids = null){

		$this->load->model('cms/cms_page_panel_model');

		if ($ids === null || $ids === [] || $ids === ''){
			$rows = $this->cms_page_panel_model->get_list('shop/currency');
			$out = [];
			foreach ($rows as $row){
				$id = (int)($row['cms_page_panel_id'] ?? 0);
				if ($id < 1){
					continue;
				}
				$c = $this->get_currency($id);
				if (!empty($c)){
					$out[] = $c;
				}
			}
			return $out;
		}

		if (!is_array($ids)){
			$ids = [(int)$ids];
		}

		$out = [];
		foreach ($ids as $id){
			$id = (int)$id;
			if ($id < 1){
				continue;
			}
			$c = $this->get_currency($id);
			if (!empty($c)){
				$out[] = $c;
			}
		}
		return $out;

	}

	/**
	 * Convert amount in main (default) currency to target currency via rate.
	 */
	function convert_from_main($amount_main, $currency){

		$amount_main = (float)$amount_main;
		if (!is_array($currency)){
			$currency = $this->get_currency((int)$currency);
		}
		if (empty($currency)){
			return round($amount_main, 2);
		}
		$rate = (float)($currency['rate'] ?? 1);
		if ($rate <= 0){
			$rate = 1.0;
		}

		return round($amount_main * $rate, 2);

	}

	/**
	 * Format amount with optional currency sign prefix.
	 */
	function format_amount($amount, $currency = null){

		$amount = (float)$amount;
		$sign = '';
		if (is_array($currency)){
			$sign = (string)($currency['sign'] ?? '');
		} else if ($currency){
			$c = $this->get_currency((int)$currency);
			$sign = $c ? (string)$c['sign'] : '';
		}

		if (abs($amount - round($amount)) < 0.001){
			$num = (string)(int)round($amount);
		} else {
			$num = number_format($amount, 2, '.', '');
		}

		return $sign.$num;

	}

	/**
	 * Resolve product price in a currency.
	 * Uses prices[] override for currency_id if set; else main price × rate.
	 *
	 * @return array{price:float,formatted:string,currency:array|null,stripe_price_id:string,source:string}
	 */
	function get_product_price_in_currency($product, $currency_id){

		if (!is_array($product)){
			$this->load->model('cms/cms_page_panel_model');
			$product = $this->cms_page_panel_model->get_cms_page_panel((int)$product);
		}
		if (!is_array($product)){
			return [
					'price' => 0.0,
					'formatted' => $this->format_amount(0),
					'currency' => null,
					'stripe_price_id' => '',
					'source' => 'none',
			];
		}

		$currency_id = (int)$currency_id;
		$currency = $currency_id > 0 ? $this->get_currency($currency_id) : $this->get_default_currency();
		if (empty($currency) && $currency_id > 0){
			$currency = $this->get_default_currency();
		}

		// Product base price (default currency). min_price is a legacy fallback only.
		$main = 0.0;
		if (isset($product['price']) && $product['price'] !== '' && $product['price'] !== null){
			$main = (float)str_replace(',', '.', (string)$product['price']);
		} else if (isset($product['min_price']) && $product['min_price'] !== '' && $product['min_price'] !== null){
			$main = (float)str_replace(',', '.', (string)$product['min_price']);
		}

		$stripe = '';
		$source = 'rate';
		$price = $main;

		if (!empty($currency) && !empty($product['prices']) && is_array($product['prices'])){
			$cid = (int)$currency['cms_page_panel_id'];
			foreach ($product['prices'] as $row){
				if (!is_array($row)){
					continue;
				}
				if ((int)($row['currency_id'] ?? 0) === $cid){
					if (isset($row['price']) && $row['price'] !== '' && $row['price'] !== null){
						$price = (float)str_replace(',', '.', (string)$row['price']);
						$source = 'override';
					}
					$stripe = trim((string)($row['stripe_price_id'] ?? ''));
					break;
				}
			}
		}

		if ($source === 'rate' && !empty($currency)){
			$price = $this->convert_from_main($main, $currency);
		}

		// Product-level stripe id fallback (legacy subscription field)
		if ($stripe === '' && !empty($product['stripe_price_id'])){
			$stripe = trim((string)$product['stripe_price_id']);
		}

		$price = round($price, 2);

		return [
				'price' => $price,
				'formatted' => $this->format_amount($price, $currency),
				'currency' => $currency,
				'stripe_price_id' => $stripe,
				'source' => $source,
		];

	}

	/**
	 * True unless product is explicitly hidden (show 0 / false / '0').
	 */
	function product_is_shown($product){

		if (!is_array($product) || !isset($product['show'])){
			return true;
		}
		$show = $product['show'];

		return !($show === 0 || $show === '0' || $show === false || $show === '');

	}

	/**
	 * cms_page_panel_ids that list this collection_id under Organisation → collections.
	 *
	 * @return int[]
	 */
	function get_product_ids_for_collection($collection_id){

		$collection_id = (int)$collection_id;
		if ($collection_id < 1){
			return [];
		}

		$value = (string)$collection_id;
		$ids = [];

		// Prefer indexed param lookup (works even if JSON cache is stale)
		$sql = "select distinct cms_page_panel_id from cms_page_panel_param ".
				"where name like 'collections.%collection_id' and value = ? ";
		$query = $this->db->query($sql, [$value]);
		if ($query){
			foreach ($query->result_array() as $row){
				$pid = (int)$row['cms_page_panel_id'];
				if ($pid > 0){
					$ids[$pid] = true;
				}
			}
		}

		// Also match unpadded / alternate key shapes
		if (empty($ids)){
			$sql = "select distinct cms_page_panel_id from cms_page_panel_param ".
					"where name like 'collections.%' and name like '%collection_id' and value = ? ";
			$query = $this->db->query($sql, [$value]);
			if ($query){
				foreach ($query->result_array() as $row){
					$pid = (int)$row['cms_page_panel_id'];
					if ($pid > 0){
						$ids[$pid] = true;
					}
				}
			}
		}

		return array_keys($ids);

	}

	/**
	 * Full product rows for a collection (shown only), keyed by cms_page_panel_id.
	 *
	 * @return array<int,array>
	 */
	function get_products_for_collection($collection_id){

		$this->load->model('cms/cms_page_panel_model');

		$products = [];
		foreach ($this->get_product_ids_for_collection($collection_id) as $pid){
			$product = $this->cms_page_panel_model->get_cms_page_panel($pid);
			if (empty($product['cms_page_panel_id'])){
				continue;
			}
			if (!$this->product_is_shown($product)){
				continue;
			}
			$products[$pid] = $product;
		}

		return $products;

	}

	/**
	 * Products in a collection that also belong to a category (via subcategory).
	 * Keyed by cms_page_panel_id.
	 *
	 * @return array<int,array>
	 */
	function get_products_for_category_and_collection($category_id, $collection_id){

		$category_id = (int)$category_id;
		$collection_id = (int)$collection_id;
		if ($category_id < 1 || $collection_id < 1){
			return [];
		}

		$this->load->model('cms/cms_page_panel_model');

		$subcategories = $this->cms_page_panel_model->get_list('shop/subcategory', [
				'category_id' => $category_id,
		]);
		$sub_ids = [];
		if (is_array($subcategories)){
			foreach ($subcategories as $sub){
				$sid = (int)($sub['cms_page_panel_id'] ?? 0);
				if ($sid > 0){
					$sub_ids[$sid] = true;
				}
			}
		}
		if (empty($sub_ids)){
			return [];
		}

		$return = [];
		foreach ($this->get_products_for_collection($collection_id) as $pid => $product){
			$sid = (int)($product['subcategory_id'] ?? 0);
			if (!empty($sub_ids[$sid])){
				$return[$pid] = $product;
			}
		}

		return $return;

	}

	/**
	 * Keep products that list $collection_id in their collections[] param.
	 *
	 * @param array $products
	 * @param int $collection_id
	 * @return array
	 */
	function filter_products_by_collection($products, $collection_id){

		$collection_id = (int)$collection_id;
		if ($collection_id < 1 || !is_array($products)){
			return $products;
		}

		$return = [];
		foreach ($products as $key => $product){
			$ok = false;
			if (!empty($product['collections']) && is_array($product['collections'])){
				foreach ($product['collections'] as $row){
					if ((int)($row['collection_id'] ?? 0) === $collection_id){
						$ok = true;
						break;
					}
				}
			}
			if ($ok){
				$return[$key] = $product;
			}
		}

		return $return;

	}

	/**
	 * Product list for products grid filters (local catalogue only).
	 *
	 * @return array products keyed by cms_page_panel_id or list-shaped array
	 */
	function get_products_for_filters($category_id = 0, $subcategory_id = 0, $collection_id = 0){

		$this->load->model('cms/cms_page_panel_model');

		$category_id = (int)$category_id;
		$subcategory_id = (int)$subcategory_id;
		$collection_id = (int)$collection_id;

		$products = [];

		if ($subcategory_id > 0){

			$products = $this->cms_page_panel_model->get_list('shop/product', [
					'subcategory_id' => $subcategory_id,
			]);
			if (!is_array($products)){
				$products = [];
			}
			if ($collection_id > 0){
				$products = $this->filter_products_by_collection($products, $collection_id);
			}

		} else if ($collection_id > 0 && $category_id > 0){

			$products = $this->get_products_for_category_and_collection($category_id, $collection_id);

		} else if ($collection_id > 0){

			$products = $this->get_products_for_collection($collection_id);

		} else if ($category_id > 0){

			$subcategories = $this->cms_page_panel_model->get_list('shop/subcategory', [
					'category_id' => $category_id,
			]);
			$sub_ids = [];
			if (is_array($subcategories)){
				foreach ($subcategories as $sub){
					$sid = (int)($sub['cms_page_panel_id'] ?? 0);
					if ($sid > 0){
						$sub_ids[] = $sid;
					}
				}
			}
			if ($sub_ids){
				$products = $this->cms_page_panel_model->get_list('shop/product', [
						'subcategory_id' => $sub_ids,
				]);
			}
			if (!is_array($products)){
				$products = [];
			}

		} else {

			$products = $this->cms_page_panel_model->get_list('shop/product');
			if (!is_array($products)){
				$products = [];
			}

		}

		// Drop hidden products when list did not already filter show
		$return = [];
		foreach ($products as $key => $product){
			if (!$this->product_is_shown($product)){
				continue;
			}
			$pid = (int)($product['cms_page_panel_id'] ?? 0);
			if ($pid > 0){
				$return[$pid] = $product;
			} else {
				$return[$key] = $product;
			}
		}

		return $return;

	}

	/**
	 * shop/collection rows for products under $category_id (or all collections with products when 0).
	 *
	 * @return array[]
	 */
	function get_collections_for_filters($category_id = 0){

		$this->load->model('cms/cms_page_panel_model');

		$category_id = (int)$category_id;
		$collection_ids = [];

		if ($category_id > 0){

			$subcategories = $this->cms_page_panel_model->get_list('shop/subcategory', [
					'category_id' => $category_id,
			]);
			if (!is_array($subcategories) || empty($subcategories)){
				return [];
			}

			$sub_ids = [];
			foreach ($subcategories as $sub){
				$sid = (int)($sub['cms_page_panel_id'] ?? 0);
				if ($sid > 0){
					$sub_ids[$sid] = true;
				}
			}
			if (empty($sub_ids)){
				return [];
			}

			$products = $this->cms_page_panel_model->get_list('shop/product', [
					'subcategory_id' => array_keys($sub_ids),
			]);
			if (!is_array($products)){
				return [];
			}

			foreach ($products as $product){
				if (!$this->product_is_shown($product)){
					continue;
				}
				if (empty($product['collections']) || !is_array($product['collections'])){
					continue;
				}
				foreach ($product['collections'] as $row){
					$cid = (int)($row['collection_id'] ?? 0);
					if ($cid > 0){
						$collection_ids[$cid] = true;
					}
				}
			}

		} else {

			// All collections referenced by any shown product
			$products = $this->cms_page_panel_model->get_list('shop/product');
			if (!is_array($products)){
				$products = [];
			}
			foreach ($products as $product){
				if (!$this->product_is_shown($product)){
					continue;
				}
				if (empty($product['collections']) || !is_array($product['collections'])){
					continue;
				}
				foreach ($product['collections'] as $row){
					$cid = (int)($row['collection_id'] ?? 0);
					if ($cid > 0){
						$collection_ids[$cid] = true;
					}
				}
			}

		}

		if (empty($collection_ids)){
			return [];
		}

		$collections = [];
		foreach (array_keys($collection_ids) as $cid){
			$col = $this->cms_page_panel_model->get_cms_page_panel($cid);
			if (empty($col['cms_page_panel_id'])){
				continue;
			}
			if (isset($col['show']) && ($col['show'] === 0 || $col['show'] === '0' || $col['show'] === false)){
				continue;
			}
			$collections[] = $col;
		}

		usort($collections, function($a, $b){
			return strcasecmp((string)($a['heading'] ?? ''), (string)($b['heading'] ?? ''));
		});

		return $collections;

	}

}
