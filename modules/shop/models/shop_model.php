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
	 * A draft cart holds for shop setting cart_hold_minutes after its last activity, and while a
	 * payment page is open for it (cart_hold_until, set by the checkout provider).
	 */
	function order_holds_stock($order){

		if ($this->order_is_draft($order)){
			$hold = (int)$this->get_shop_settings()['cart_hold_minutes'] * 60;
			return (int)($order['cart_active_time'] ?? 0) >= time() - $hold || (int)($order['cart_hold_until'] ?? 0) >= time();
		}

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

	/**
	 * Visitor cart activity (add, open): the draft holds stock again from now.
	 */
	function touch_cart_order($order_id){

		$this->load->model('cms/cms_page_panel_model');
		if ((int)$order_id > 0){
			$this->cms_page_panel_model->update_cms_page_panel((int)$order_id, ['cart_active_time' => time()]);
		}

	}

	/**
	 * Delete draft carts (and their lines) with no visitor activity for $days days. Returns how many.
	 * Paid and other non-draft orders are never touched.
	 */
	function delete_stale_carts($days){

		$this->load->model('cms/cms_page_panel_model');

		$before = time() - max(1, (int)$days) * 86400;
		$deleted = 0;
		foreach ($this->cms_page_panel_model->get_list('shop/order', ['status' => '']) as $order){
			$last = max((int)($order['cart_active_time'] ?? 0), (int)($order['created'] ?? 0), (int)($order['cart_hold_until'] ?? 0));
			if (!$this->order_is_draft($order) || $this->order_is_paid($order) || $last >= $before){
				continue;
			}
			$this->delete_order((int)$order['cms_page_panel_id']);
			$deleted++;
		}
		return $deleted;

	}

	/**
	 * Checkout provider: a payment page is open until $time, so the cart holds its stock at least that long.
	 */
	function hold_cart_until($order_id, $time){

		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->update_cms_page_panel((int)$order_id, ['cart_hold_until' => (int)$time, 'cart_active_time' => time()]);

	}

	/**
	 * Product item the line sold, or 0 (delivery lines). ref_id is not read here.
	 */
	function order_line_item_id($line){

		return (int)$line['product_item_id'];

	}

	/**
	 * What the line points at: product item, or the delivery method on delivery lines.
	 */
	function order_line_ref_id($line){

		return (int)$line['ref_id'];

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
			$this->log_order_state($order, ['status' => $next]);
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

		// Every item line counts, also lines no provider fulfils (the shop sends those by hand).
		$this->load->model('shop/shop_fulfilment_model');
		foreach ($this->get_order_lines($order_id) as $line){
			if ($this->shop_fulfilment_model->_is_delivery_line($line)){
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

	/**
	 * Hand-set order status. Same rules as the technical order form.
	 * Finished posts listed stock once and cannot be undone.
	 */
	function set_order_status($order_id, $status){

		$this->load->model('cms/cms_page_panel_model');

		$id = (int)$order_id;
		$status = (string)$status;
		$existing = $this->cms_page_panel_model->get_cms_page_panel($id);
		if (($existing['panel_name'] ?? '') !== 'shop/order'){
			return ['status' => '', 'stock_posted_time' => ''];
		}
		if ($this->order_is_finished($existing) || !empty($existing['stock_posted_time'])){
			return [
					'status' => 'finished',
					'stock_posted_time' => (string)($existing['stock_posted_time'] ?? ''),
			];
		}

		if (in_array($status, ['in_progress', 'fulfilled', 'cancelled'], true) && $status !== (string)($existing['status'] ?? '')){
			$this->apply_lines_physical_status($id, $status);
			$this->log_order_state($existing, ['status' => $status]);
			$this->cms_page_panel_model->update_cms_page_panel($id, ['status' => $status]);
			return ['status' => $status, 'stock_posted_time' => ''];
		}
		if ($status !== 'finished'){
			if ($status !== (string)($existing['status'] ?? '')){
				$this->log_order_state($existing, ['status' => $status]);
				$this->cms_page_panel_model->update_cms_page_panel($id, ['status' => $status]);
			}
			return ['status' => $status, 'stock_posted_time' => ''];
		}

		if ($this->order_is_paid($existing)){
			$this->apply_lines_physical_status($id, 'fulfilled');
		}
		$this->finish_order($id);
		$row = $this->cms_page_panel_model->get_cms_page_panel($id);

		return [
				'status' => 'finished',
				'stock_posted_time' => (string)($row['stock_posted_time'] ?? ''),
		];

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

		$this->log_order_state($order, ['status' => 'finished']);
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

		$posted = [];
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
			$qty = (int)$line['quantity'];
			if ($qty < 1){
				$qty = 1;
			}
			$listed = (int)($item['number'] ?? 0);
			$this->cms_page_panel_model->update_cms_page_panel($item_id, [
					'number' => $listed - $qty,
			]);
			$posted[] = $qty.' × '.$this->shop_dim_model->item_list_title($item);
		}

		$this->cms_page_panel_model->update_cms_page_panel($order_id, [
				'stock_posted_time' => time(),
		]);
		if ($posted){
			$this->log_order($order_id, 'Stock taken off the catalogue: '.implode(', ', $posted));
		}
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
		$this->log_order_state($order, $update);
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

		$update = [
				'status' => 'abandoned',
				'payment_status' => 'unpaid',
				'shopify_cart_id' => '',
		];
		$this->log_order_state($order, $update);
		$this->cms_page_panel_model->update_cms_page_panel($order_id, $update);

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
			$qty = (int)$line['quantity'];
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
		$this->load->model('cms/cms_page_panel_model');
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
					'cart_active_time' => time(),
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
			$this->log_order($order_id, 'Cart created');

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

	/**
	 * Total quantity of the order's lines: one SUM on the shop_order_line panel table, no line loads.
	 * Same rows as get_list (visible list items).
	 */
	function get_order_quantity($order_id){

		$sql = "select coalesce(sum(t.quantity), 0) as quantity from shop_order_line t ".
				"join cms_page_panel a on a.cms_page_panel_id = t.cms_page_panel_id ".
				"where t.order_id = ? and a.cms_page_id = 0 and a.show = 1 and a.sort != 0 ";
		return (int)$this->db->query($sql, [(int)$order_id])->row_array()['quantity'];

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
	
	/**
	 * Visitor-posted text for an order line: valid UTF-8, no tags, capped length.
	 * $multiline keeps line breaks (customisation values); otherwise all whitespace is one space.
	 */
	function clean_visitor_text($value, $max, $multiline = false){

		$value = strip_tags(cms_utf8_string(is_scalar($value) ? (string)$value : ''));
		$value = str_replace(["\r\n", "\r"], "\n", $value);
		$value = $multiline ? preg_replace('/[^\S\n]+/u', ' ', $value) : preg_replace('/\s+/u', ' ', $value);
		return mb_substr(trim((string)$value), 0, $max);

	}

	/**
	 * Customisation attributes posted by the visitor (array or JSON), cleaned at write:
	 * a list of { key, value }, at most 20, keys up to 100 and values up to 1000 characters.
	 */
	function clean_line_attributes($attributes){

		if (is_string($attributes)){
			$attributes = json_decode($attributes, true);
		}
		if (!is_array($attributes)){
			return [];
		}

		$out = [];
		foreach ($attributes as $k => $v){
			$key = is_array($v) ? ($v['key'] ?? '') : $k;
			$value = is_array($v) ? ($v['value'] ?? '') : $v;
			if (is_array($key) || is_array($value)){
				continue;
			}
			$key = $this->clean_visitor_text($key, 100);
			if ($key === ''){
				continue;
			}
			$out[] = ['key' => $key, 'value' => $this->clean_visitor_text($value, 1000, true)];
			if (count($out) >= 20){
				break;
			}
		}
		return $out;

	}

	/**
	 * Same product item with the same attributes already in the order: add $qty to that line and
	 * set its price to the current $price, return its id. 0 when allow_multiline or nothing matches.
	 */
	function _merge_into_order_line($order_id, $product, $product_item_id, $attributes, $qty, $price){

		// Products created outside the admin form (e.g. sync) may not have the field: missing is No.
		if ($product_item_id < 1 || (string)($product['allow_multiline'] ?? '0') === '1'){
			return 0;
		}

		foreach ($this->get_order_lines($order_id) as $line){
			if ((int)($line['product_item_id'] ?? 0) !== $product_item_id || ($line['attributes'] ?? []) != $attributes){
				continue;
			}
			$total = (int)$line['quantity'] + $qty;
			$update = [
					'quantity' => $total,
					'price' => $price,
			];
			if (array_key_exists('expected_price', $line)){
				$update['expected_price'] = $price;
			}
			$this->cms_page_panel_model->update_cms_page_panel((int)$line['cms_page_panel_id'], $update);
			return (int)$line['cms_page_panel_id'];
		}

		return 0;

	}

	/**
	 * Re-price item lines from the current variant price, in each line's own currency.
	 * Returns changed lines: line_id => ['old' => formatted, 'new' => formatted]. Delivery lines are skipped.
	 */
	function refresh_line_prices($order_id){

		$this->load->model('cms/cms_page_panel_model');

		$changed = [];
		foreach ($this->get_order_lines($order_id) as $line){
			$item_id = (int)($line['product_item_id'] ?? 0);
			$item = $item_id > 0 ? $this->cms_page_panel_model->get_cms_page_panel($item_id) : [];
			if (($item['panel_name'] ?? '') !== 'shop/product_item'){
				continue;
			}

			$currency = $this->get_currency((int)$line['currency_id']);
			$priced = $this->get_item_price_in_currency($item, (int)$item['product_id'], $currency);
			$old = round((float)$line['price'], 2);
			$new = round((float)$priced['price'], 2);
			if ($old === $new){
				continue;
			}

			$update = ['price' => $priced['price']];
			if (array_key_exists('expected_price', $line)){
				$update['expected_price'] = $priced['price'];
			}
			$this->cms_page_panel_model->update_cms_page_panel((int)$line['cms_page_panel_id'], $update);
			$changed[(int)$line['cms_page_panel_id']] = [
					'old' => $this->format_amount($old, $currency),
					'new' => $priced['formatted'],
			];
		}
		return $changed;

	}

	/**
	 * Order line from a product item (or delivery ref). Item name and image always come from the
	 * product, never from the request; visitor attributes are cleaned here (clean_line_attributes).
	 */
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

			$qty = max(1, (int)($params['quantity'] ?? 1));
			$priced = $this->get_item_price_in_currency($product_item, $product);
			$price = $priced['price'];
			$currency_id = is_array($priced['currency']) ? (int)($priced['currency']['cms_page_panel_id'] ?? 0) : 0;
			$attributes = $this->clean_line_attributes($params['attributes'] ?? []);

			$attr_bits = [];
			foreach ($attributes as $attribute){
				$attr_bits[] = $attribute['key'].': '.$attribute['value'];
			}
			if ($attr_bits){
				$attr_s = implode("\n", $attr_bits);
				$description = $description !== '' ? $description."\n".$attr_s : $attr_s;
			}

			$merged = $this->_merge_into_order_line($order_id, $product, $product_item_id, $attributes, $qty, $price);
			if ($merged){
				return $merged;
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
					'quantity' => $qty,
					'price' => $price,
					'expected_price' => $price,
					'currency_id' => $currency_id,
					'description' => $description,
					'item' => $product['heading'] ?? '',
					'image' => $product['image'] ?? '',
					'attributes' => $attributes,
					'order_id' => $order_id,
			];

			return $this->cms_page_panel_model->create_cms_page_panel($order_line);

		}
		
		if (empty($product['cms_page_panel_id']) && !empty($params['product_id'])){
			$product = $this->cms_page_panel_model->get_cms_page_panel($params['product_id']);
		}

		$qty = max(1, (int)($params['quantity'] ?? 1));
		$priced = $this->get_item_price_in_currency($product_item, $product);
		$merged = $this->_merge_into_order_line($order_id, $product, $product_item_id, [], $qty, $priced['price']);
		if ($merged){
			return $merged;
		}
		$currency_id = is_array($priced['currency']) ? (int)($priced['currency']['cms_page_panel_id'] ?? 0) : 0;
		$order_line = [
				'panel_name' => 'shop/order_line',
				'show' => 1,
				'sort' => 'first',
				'ref_id' => $ref_id,
				'product_item_id' => $product_item_id ? $product_item_id : '',
				'product_id' => (int)($product['cms_page_panel_id'] ?? $params['product_id'] ?? 0),
				'line_type' => 'local_item',
				'quantity' => $qty,
				'price' => $priced['price'],
				'currency_id' => $currency_id,
				'description' => $description,
				'item' => !empty($product['heading']) ? $product['heading'] : ($product_item['heading'] ?? ''),
				'image' => $product['image'] ?? '',
				'order_id' => $order_id,
		];
		
		return $this->cms_page_panel_model->create_cms_page_panel($order_line);
		
	}
	
	/**
	 * Delete an order. Its lines and history go with it through shop/order on_delete (delete_order_parts),
	 * also when the order is deleted from the technical list.
	 */
	function delete_order($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->delete_cms_page_panel((int)$order_id);

	}

	/**
	 * What belongs to an order row that is being deleted: its lines and its history (shop/order on_delete).
	 */
	function delete_order_parts($order_id){

		$this->load->model('cms/cms_page_panel_model');

		$order_id = (int)$order_id;
		foreach ($this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]) as $line){
			$this->cms_page_panel_model->delete_cms_page_panel($line['cms_page_panel_id']);
		}

		try {
			$this->db->query('delete from shop_order_log where order_id = ?', [$order_id]);
		} catch (\Throwable $e){
			error_log_user('shop delete_order_parts: history of order '.$order_id.' not deleted ('.$e->getMessage().')');
		}

	}
	
	/**
	 * Cart quantity of one item line of this draft cart (ids from the request: anything else is ignored).
	 * More of a local item must fit the stock (same rule as add). Returns ok, error (stock / invalid), lost_item_ids.
	 */
	function set_order_line_quantity($order_id, $line_id, $quantity){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');

		$order_id = (int)$order_id;
		$quantity = (int)$quantity;
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		$line = $this->cms_page_panel_model->get_cms_page_panel((int)$line_id);
		if (($order['panel_name'] ?? '') !== 'shop/order' || !$this->order_is_draft($order)
				|| ($line['panel_name'] ?? '') !== 'shop/order_line' || (int)$line['order_id'] !== $order_id
				|| $this->order_line_item_id($line) < 1 || $quantity < 1 || $quantity > 99){
			return ['ok' => 0, 'error' => 'invalid', 'lost_item_ids' => []];
		}

		$current = (int)$line['quantity'];
		if ($quantity > $current && !$this->shop_dim_model->cart_line_is_shopify($line)){
			$item = $this->cms_page_panel_model->get_cms_page_panel($this->order_line_item_id($line));
			if (($item['panel_name'] ?? '') !== 'shop/product_item' || !$this->shop_dim_model->cart_add_fits($item, $quantity - $current, $order_id)){
				return ['ok' => 0, 'error' => 'stock', 'lost_item_ids' => []];
			}
		}

		if ($quantity !== $current){
			$this->cms_page_panel_model->update_cms_page_panel((int)$line['cms_page_panel_id'], ['quantity' => $quantity]);
		}

		return ['ok' => 1, 'error' => '', 'lost_item_ids' => $this->shop_dim_model->cart_refresh_hold($order_id)];

	}

	/**
	 * Delete a line of this order (ids from the request: anything else is ignored), then a lone delivery line.
	 */
	function delete_order_line($order_id, $order_line_id){

		$this->load->model('cms/cms_page_panel_model');

		$line = $this->cms_page_panel_model->get_cms_page_panel((int)$order_line_id);

		if (($line['panel_name'] ?? '') === 'shop/order_line' && (int)$order_id > 0 && (int)$line['order_id'] === (int)$order_id){
			
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
	
	/**
	 * The one place an order becomes paid (any payment module). Returns false when already paid.
	 * $payment: provider, ref, amount, note, buyer [name, email, phone], shipping [name, address1, address2,
	 * city, postcode, county, country], delivery [delivery_id, price]. See shop.md § Paid orders.
	 */
	function set_order_paid($order_id, $payment = []){

		$this->load->model('cms/cms_page_panel_model');

		$order_id = (int)$order_id;
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (($order['panel_name'] ?? '') !== 'shop/order' || $this->order_is_paid($order)){
			return false;
		}

		// Paid although the cart no longer had its stock (hold ran out and another order took it): say so.
		$note = (string)($payment['note'] ?? '');
		if ($this->order_is_draft($order)){
			$this->load->model('shop/shop_dim_model');
			$titles = [];
			foreach ($this->shop_dim_model->cart_refresh_hold($order_id) as $item_id){
				$titles[] = $this->shop_dim_model->item_list_title($this->cms_page_panel_model->get_cms_page_panel($item_id));
			}
			if ($titles){
				$note = trim($note.' Not enough stock at payment (oversold): '.implode(', ', $titles).'.');
			}
		}

		$now = time();
		$update = [
				'status' => 'unfulfilled',
				'payment_status' => 'paid',
				'paid_time' => $now,
				'payment_provider' => $this->clean_visitor_text($payment['provider'] ?? '', 50),
				'payment_ref' => $this->clean_visitor_text($payment['ref'] ?? '', 200),
				'paid_amount' => isset($payment['amount']) ? round((float)$payment['amount'], 2) : '',
				'payment_note' => $this->clean_visitor_text($note, 2000, true),
		];
		if (empty($order['order_created'])){
			$update['order_created'] = $now;
		}

		// Buyer and address come from the payment provider (typed by the visitor there).
		$buyer = is_array($payment['buyer'] ?? null) ? $payment['buyer'] : [];
		$shipping = is_array($payment['shipping'] ?? null) ? $payment['shipping'] : [];
		$update['buyer_name'] = $this->clean_visitor_text($buyer['name'] ?? '', 200);
		$email = $this->clean_visitor_text($buyer['email'] ?? '', 200);
		$update['buyer_email'] = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
		$update['shipping_phone'] = $this->clean_visitor_text($buyer['phone'] ?? '', 50);
		foreach (['name', 'address1', 'address2', 'city', 'postcode', 'county', 'country'] as $field){
			$update['shipping_'.$field] = $this->clean_visitor_text($shipping[$field] ?? '', 200);
		}

		$this->log_order_state($order, $update);
		$this->cms_page_panel_model->update_cms_page_panel($order_id, $update);

		$this->attach_order_user($order_id);

		$delivery = is_array($payment['delivery'] ?? null) ? $payment['delivery'] : [];
		if (!empty($delivery['delivery_id'])){
			// A delivery line put on the cart before payment (shop/checkout) is replaced by the one charged.
			$this->load->model('shop/shop_fulfilment_model');
			foreach ($this->get_order_lines($order_id) as $line){
				if ($this->shop_fulfilment_model->_is_delivery_line($line)){
					$this->cms_page_panel_model->delete_cms_page_panel($line['cms_page_panel_id']);
				}
			}
			$this->add_delivery_line($order_id, (int)$delivery['delivery_id'], (float)($delivery['price'] ?? 0));
		}

		$this->stamp_draft_lines_unfulfilled($order_id);

		$this->load->model('shop/shop_fulfilment_model');
		$this->shop_fulfilment_model->request_fulfilment($order_id);
		$this->rollup_order_status($order_id);

		$this->send_order_emails($order_id);

		return true;

	}

	/**
	 * Paid order → site user: the order's user, else the user with the buyer email, else a new one.
	 * The order's delivery fields are copied onto the user. Returns the user id, 0 when none.
	 */
	function attach_order_user($order_id){

		if (!in_array('user', $GLOBALS['config']['modules'] ?? [], true)){
			return 0;
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('user/user_model');

		$order_id = (int)$order_id;
		$this->cms_page_panel_model->_invalidate_panel_load_slot($order_id);
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (($order['panel_name'] ?? '') !== 'shop/order'){
			return 0;
		}

		$email = (string)($order['buyer_email'] ?? '');

		// A cart bought while logged in keeps its user; the email typed at payment does not move it.
		$user = !empty($order['user_id']) ? $this->user_model->get_user((int)$order['user_id']) : [];
		if (($user['panel_name'] ?? '') !== 'user/user'){
			$user = [];
		}

		if (!$user && $email !== ''){
			$user = $this->user_model->get_user_by_email($email);
			if (!$user && strtolower($email) !== $email){
				$user = $this->user_model->get_user_by_email(strtolower($email));
			}
		}

		if (!$user){

			if ($email === ''){
				error_log_user('shop attach_order_user: order '.$order_id.' has no buyer email, no user attached');
				return 0;
			}

			// No password: the buyer sets one through the password reminder, which proves the email is theirs.
			$name = trim((string)($order['buyer_name'] ?? ''));
			$name_parts = $name !== '' ? explode(' ', $name, 2) : [];
			$created = $this->user_model->create_user([
					'email' => strtolower($email),
					'username' => '',
					'first_name' => $name_parts[0] ?? '',
					'last_name' => $name_parts[1] ?? '',
					'phone' => (string)($order['shipping_phone'] ?? ''),
					'password' => '',
					'email_verified' => 'no',
			]);
			if (empty($created['data']['user_id'])){
				// e.g. a hidden user already has this email
				error_log_user('shop attach_order_user: order '.$order_id.' user not created ('.implode(', ', $created['errors'] ?? []).')');
				return 0;
			}
			$user = $this->user_model->get_user((int)$created['data']['user_id']);
			$this->log_order($order_id, 'Site user account created for '.strtolower($email));

		}

		$user_id = (int)$user['cms_page_panel_id'];

		$update = [];
		foreach (['name', 'address1', 'address2', 'city', 'postcode', 'county', 'country', 'phone'] as $field){
			$update['shipping_'.$field] = (string)($order['shipping_'.$field] ?? '');
		}
		if (trim((string)($user['phone'] ?? '')) === '' && $update['shipping_phone'] !== ''){
			$update['phone'] = $update['shipping_phone'];
		}
		$this->cms_page_panel_model->update_cms_page_panel($user_id, $update);

		if ((int)($order['user_id'] ?? 0) !== $user_id){
			$this->cms_page_panel_model->update_cms_page_panel($order_id, ['user_id' => $user_id]);
			$this->log_order($order_id, 'Order linked to site user '.(string)($user['email'] ?? $email));
		}

		return $user_id;

	}

	/**
	 * Delivery line chosen at payment: ref_id = shop/delivery row, price as charged, no product item.
	 */
	function add_delivery_line($order_id, $delivery_id, $price){

		$this->load->model('cms/cms_page_panel_model');

		$delivery = $this->cms_page_panel_model->get_cms_page_panel($delivery_id);
		if (($delivery['panel_name'] ?? '') !== 'shop/delivery'){
			error_log_user('shop set_order_paid: '.$delivery_id.' is not a delivery method, order '.$order_id);
			return 0;
		}

		$currency = $this->get_shop_currency();
		foreach ($this->get_order_lines($order_id) as $line){
			if ((int)$line['product_item_id'] > 0){
				$currency = $this->get_currency((int)$line['currency_id']) ?: $currency;
				break;
			}
		}

		return $this->cms_page_panel_model->create_cms_page_panel([
				'panel_name' => 'shop/order_line',
				'show' => 1,
				'sort' => 'last',
				'ref_id' => $delivery_id,
				'product_item_id' => '',
				'product_id' => 0,
				'line_type' => 'delivery',
				'quantity' => 1,
				'price' => round($price, 2),
				'currency_id' => (int)($currency['cms_page_panel_id'] ?? 0),
				'item' => (string)($delivery['heading'] ?? ''),
				'order_id' => $order_id,
		]);

	}

	/**
	 * Queue the paid-order emails: notification to Shop settings → Notification emails, confirmation to the buyer.
	 * Texts are the shop/order_email settings, the body is its template (plain text).
	 */
	function send_order_emails($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_email_model');

		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		$texts = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/order_email');
		$data = array_merge($texts, $this->order_email_data($order));
		$template = get_instance()->get_panel_filenames('shop/order_email')['template'];

		$notify_to = [];
		foreach (($this->get_shop_settings()['emails'] ?? []) as $row){
			$address = trim((string)($row['email'] ?? ''));
			if (filter_var($address, FILTER_VALIDATE_EMAIL)){
				$notify_to[] = $address;
			}
		}
		if (!$notify_to){
			error_log_user('shop order '.$order_id.' paid: no Notification emails in Shop settings');
			$this->log_order($order_id, 'New order email not sent: no Notification emails in Shop settings');
		}

		$subject = str_replace('{{order_id}}', $data['order_number'], $texts['notify_subject']);
		$body = $this->load->view($template, ['mail' => 'notify'] + $data, true);
		foreach ($notify_to as $address){
			$ok = $this->cms_email_model->send_mail($address, $subject, $body, ['auto_submitted' => 1]);
			$this->log_order($order_id, ($ok ? 'New order email sent to ' : 'New order email could not be sent to ').$address);
		}

		if ($order['buyer_email'] !== ''){
			$subject = str_replace('{{order_id}}', $data['order_number'], $texts['confirm_subject']);
			$body = $this->load->view($template, ['mail' => 'confirm'] + $data, true);
			$ok = $this->cms_email_model->send_mail($order['buyer_email'], $subject, $body, ['auto_submitted' => 1]);
			$this->log_order($order_id, ($ok ? 'Order confirmation email sent to ' : 'Order confirmation email could not be sent to ').$order['buyer_email']);
		}

	}

	/**
	 * Queue the dispatch email to the buyer (order set Fulfilled on the order page): intro, tracking number
	 * when set, order summary. Stamps dispatch_email_time and logs the result.
	 */
	function send_dispatch_email($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_email_model');

		$order_id = (int)$order_id;
		$this->cms_page_panel_model->_invalidate_panel_load_slot($order_id);
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (($order['panel_name'] ?? '') !== 'shop/order'){
			return false;
		}
		$email = (string)($order['buyer_email'] ?? '');
		if ($email === ''){
			$this->log_order($order_id, 'Dispatch email not sent: no buyer email');
			return false;
		}

		$texts = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/order_email');
		$data = array_merge($texts, $this->order_email_data($order));
		$template = get_instance()->get_panel_filenames('shop/order_email')['template'];

		$subject = str_replace('{{order_id}}', $data['order_number'], $texts['dispatch_subject']);
		$body = $this->load->view($template, ['mail' => 'dispatch'] + $data, true);
		$ok = $this->cms_email_model->send_mail($email, $subject, $body, ['auto_submitted' => 1]);
		if ($ok){
			$this->cms_page_panel_model->update_cms_page_panel($order_id, ['dispatch_email_time' => time()]);
		}
		$this->log_order($order_id, ($ok ? 'Dispatch email sent to ' : 'Dispatch email could not be sent to ').$email);

		return (bool)$ok;

	}

	/**
	 * Order paid through a payment module with this payment reference (e.g. Stripe pi_…), or [].
	 */
	function get_order_by_payment_ref($provider, $ref){

		$this->load->model('cms/cms_page_panel_model');

		$ref = (string)$ref;
		if ($ref === ''){
			return [];
		}
		$sql = "select p.cms_page_panel_id from cms_page_panel_param p join cms_page_panel c on c.cms_page_panel_id = p.cms_page_panel_id "
				."where c.panel_name = 'shop/order' and p.name = 'payment_ref' and p.value = ? order by p.cms_page_panel_id desc";
		foreach ($this->db->query($sql, [$ref])->result_array() as $row){
			$order = $this->cms_page_panel_model->get_cms_page_panel((int)$row['cms_page_panel_id']);
			if ((string)($order['payment_provider'] ?? '') === (string)$provider){
				return $order;
			}
		}

		return [];

	}

	/**
	 * Refund reported by a payment module: $refunded_total is the cumulative refunded amount (major units).
	 * Same total as stored: nothing. Otherwise payment becomes refunded / partially_refunded (logged), a
	 * History line, and the refund notice to the shop. The order status is not changed. Returns true on a change.
	 */
	function set_order_refund($order_id, $refunded_total){

		$this->load->model('cms/cms_page_panel_model');

		$order_id = (int)$order_id;
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (($order['panel_name'] ?? '') !== 'shop/order'){
			return false;
		}

		$refunded_total = round(max(0, (float)$refunded_total), 2);
		$before = round((float)($order['refunded_amount'] ?? 0), 2);
		if ($refunded_total <= 0 || abs($refunded_total - $before) < 0.005){
			return false;
		}

		$paid = (float)($order['paid_amount'] ?? 0);
		$currency = $this->get_payable_order($order_id)['currency_row'] ?: $this->get_shop_currency();
		$update = [
				'refunded_amount' => $refunded_total,
				'payment_status' => ($paid > 0 && $refunded_total < $paid - 0.005) ? 'partially_refunded' : 'refunded',
		];

		$this->log_order($order_id, 'Refund in '.($order['payment_provider'] ?: 'the payment provider').': '
				.$this->format_admin_amount($refunded_total - $before, $currency)
				.' (refunded '.$this->format_admin_amount($refunded_total, $currency).' of '.$this->format_admin_amount($paid, $currency).')');
		$this->log_order_state($order, $update);
		$this->cms_page_panel_model->update_cms_page_panel($order_id, $update);

		$this->send_refund_email($order_id);

		return true;

	}

	/**
	 * Refund notice to Shop settings → Notification emails (texts: Order emails → Shop refund notice).
	 */
	function send_refund_email($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_email_model');

		$order_id = (int)$order_id;
		$this->cms_page_panel_model->_invalidate_panel_load_slot($order_id);
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		$texts = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/order_email');
		$data = array_merge($texts, $this->order_email_data($order));
		$template = get_instance()->get_panel_filenames('shop/order_email')['template'];

		$to = [];
		foreach (($this->get_shop_settings()['emails'] ?? []) as $row){
			$address = trim((string)($row['email'] ?? ''));
			if (filter_var($address, FILTER_VALIDATE_EMAIL)){
				$to[] = $address;
			}
		}
		if (!$to){
			error_log_user('shop order '.$order_id.' refund: no Notification emails in Shop settings');
			$this->log_order($order_id, 'Refund email not sent: no Notification emails in Shop settings');
			return false;
		}

		$subject = str_replace('{{order_id}}', $data['order_number'], $texts['refund_subject']);
		$body = $this->load->view($template, ['mail' => 'refund'] + $data, true);
		foreach ($to as $address){
			$ok = $this->cms_email_model->send_mail($address, $subject, $body, ['auto_submitted' => 1]);
			$this->log_order($order_id, ($ok ? 'Refund email sent to ' : 'Refund email could not be sent to ').$address);
		}

		return true;

	}

	/**
	 * Whether a line of a finished order can go back to stock: its quantity was posted (count-mode item,
	 * line fulfilled / in progress) and it was not returned yet.
	 */
	function line_returnable($order, $line){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');

		if (!$this->order_is_finished($order) || empty($order['stock_posted_time']) || !empty($line['stock_returned_time'])
				|| (int)($line['order_id'] ?? 0) !== (int)($order['cms_page_panel_id'] ?? 0)
				|| !in_array($this->order_line_status($line), ['fulfilled', 'in_progress'], true)){
			return false;
		}
		$item = $this->cms_page_panel_model->get_cms_page_panel($this->order_line_item_id($line));

		return ($item['panel_name'] ?? '') === 'shop/product_item' && $this->shop_dim_model->item_stock_control($item) === 'count';

	}

	/**
	 * Goods sent back after Finished: the whole line's quantity goes back to the variant's listed stock, once.
	 */
	function return_line_to_stock($order_id, $line_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');

		$order = $this->cms_page_panel_model->get_cms_page_panel((int)$order_id);
		$line = $this->cms_page_panel_model->get_cms_page_panel((int)$line_id);
		if (($order['panel_name'] ?? '') !== 'shop/order' || ($line['panel_name'] ?? '') !== 'shop/order_line'
				|| !$this->line_returnable($order, $line)){
			return false;
		}

		$item = $this->cms_page_panel_model->get_cms_page_panel($this->order_line_item_id($line));
		$qty = max(1, (int)$line['quantity']);
		$this->cms_page_panel_model->update_cms_page_panel((int)$line['cms_page_panel_id'], ['stock_returned_time' => time()]);
		$this->cms_page_panel_model->update_cms_page_panel((int)$item['cms_page_panel_id'], ['number' => (int)($item['number'] ?? 0) + $qty]);
		$this->log_order((int)$order_id, 'Returned to stock: '.$qty.' × '.$this->shop_dim_model->item_list_title($item));

		return true;

	}

	/**
	 * Tracking number of the order (order page), cleaned; logged when it changes.
	 */
	function set_order_tracking($order_id, $number){

		$this->load->model('cms/cms_page_panel_model');

		$order_id = (int)$order_id;
		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (($order['panel_name'] ?? '') !== 'shop/order'){
			return false;
		}
		$number = $this->clean_visitor_text($number, 100);
		if ($number === (string)($order['tracking_number'] ?? '')){
			return false;
		}

		$this->cms_page_panel_model->update_cms_page_panel($order_id, ['tracking_number' => $number]);
		$this->log_order($order_id, $number !== '' ? 'Tracking number: '.$number : 'Tracking number removed');

		return true;

	}

	/**
	 * Data for the order email template: formatted lines and total, buyer, address, payment, admin link.
	 */
	function order_email_data($order){

		$order_id = (int)$order['cms_page_panel_id'];
		$payable = $this->get_payable_order($order_id);

		$lines = [];
		foreach ($payable['lines'] as $line){
			$lines[] = [
					'type' => $line['type'],
					'quantity' => $line['quantity'],
					'name' => $line['name'],
					'dims' => $line['dims'],
					'amount' => $this->format_amount($line['total'], $line['currency_row']),
			];
		}

		$address = [];
		foreach (['name', 'address1', 'address2', 'city', 'county', 'postcode', 'country'] as $field){
			if (trim((string)$order['shipping_'.$field]) !== ''){
				$address[] = $order['shipping_'.$field];
			}
		}

		$paid = (string)$order['paid_amount'] !== '' ? (float)$order['paid_amount'] : $payable['total'];
		$base = rtrim((string)$GLOBALS['config']['base_host'], '/').'/'.ltrim((string)$GLOBALS['config']['base_url'], '/');

		return [
				'order_number' => (string)$order['heading'],
				'lines' => $lines,
				'total_paid' => $this->format_amount($paid, $payable['currency_row']),
				'buyer' => trim($order['buyer_name'].' '.$order['buyer_email'].' '.$order['shipping_phone']),
				'address' => $address ? implode(', ', $address) : '-',
				'payment_note' => trim((string)$order['payment_note']),
				'payment' => trim($order['payment_provider'].' '.$order['payment_ref']),
				'tracking_number' => (string)($order['tracking_number'] ?? ''),
				'site_url' => $base,
				'refunded' => (string)($order['refunded_amount'] ?? '') !== '' ? $this->format_admin_amount($order['refunded_amount'], $payable['currency_row']) : '',
				'paid_admin' => $this->format_admin_amount($paid, $payable['currency_row']),
				'admin_url' => $base.'admin/orders/'.$order_id.'/',
		];

	}
	/**
	 * Before payment: re-price the lines and re-check stock (refreshes the cart hold). Used by the cart
	 * and by every shop_checkout provider. 'ready' is true when nothing changed.
	 */
	function checkout_check($order_id){

		$this->load->model('shop/shop_dim_model');

		$changed = $this->refresh_line_prices($order_id);
		$lost = $this->shop_dim_model->cart_refresh_hold($order_id);

		return [
				'price_changed_lines' => $changed,
				'lost_item_ids' => $lost,
				'ready' => !$changed && !$lost,
		];

	}

	/**
	 * One page of orders for the admin list, newest first. $filter: view (orders / archive / carts) or
	 * q (3+ characters: order number or part of the buyer email, all statuses). Item counts: one SUM.
	 */
	function get_admin_orders($filter, $offset, $limit){

		$this->load->model('cms/cms_page_panel_model');

		$filter = is_array($filter) ? $filter : [];
		$view = (string)($filter['view'] ?? 'orders');
		$search = trim((string)($filter['q'] ?? ''));
		$offset = max(0, (int)$offset);
		$limit = max(1, min(50, (int)$limit));

		// Search (3+ characters) covers every order and cart: order number or part of the buyer email.
		$where = [];
		$binds = [];
		if (mb_strlen($search) >= 3){
			$like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
			$where[] = '(cast(o.cms_page_panel_id as char) like ? or o.cms_page_panel_id in '
					."(select p.cms_page_panel_id from cms_page_panel_param p where p.name = 'buyer_email' and p.value like ?))";
			$binds[] = $like;
			$binds[] = $like;
		} else if ($view === 'carts'){
			$where[] = "o.status = ''";
		} else if ($view === 'archive'){
			$where[] = "o.status in ('finished', 'cancelled', 'abandoned')";
		} else {
			$where[] = "o.status in ('unfulfilled', 'in_progress', 'fulfilled', 'paid')";
		}

		$sql = 'select o.cms_page_panel_id from shop_order o where '.implode(' and ', $where)
				.' order by o.cms_page_panel_id desc limit '.(int)$offset.', '.(int)$limit;
		$ids = [];
		foreach ($this->db->query($sql, $binds)->result_array() as $row){
			$ids[] = (int)$row['cms_page_panel_id'];
		}
		if (!$ids){
			return [];
		}

		$counts = [];
		$count_sql = 'select order_id, sum(quantity) as qty from shop_order_line where order_id in ('
				.implode(',', $ids).') and product_id > 0 group by order_id';
		foreach ($this->db->query($count_sql)->result_array() as $row){
			$counts[(int)$row['order_id']] = (int)$row['qty'];
		}

		$orders = [];
		foreach ($ids as $id){
			$order = $this->cms_page_panel_model->get_cms_page_panel($id);
			if (($order['panel_name'] ?? '') !== 'shop/order'){
				continue;
			}
			$payable = [];
			$paid = (string)($order['payment_status'] ?? '') === 'paid' && (string)($order['paid_amount'] ?? '') !== '';
			// Admin amounts always show the sign and two decimals (£0.00), also for an empty cart.
			$currency = $this->get_shop_currency();
			if ($paid){
				$amount = (float)$order['paid_amount'];
			} else {
				$payable = $this->get_payable_order($id);
				$amount = $payable['total'];
				$currency = $payable['currency_row'] ?: $currency;
			}
			$total = ($payable['mixed_currency'] ?? false) ? '' : $this->format_admin_amount($amount, $currency);
			$orders[] = [
					'id' => $id,
					'email' => (string)($order['buyer_email'] ?? ''),
					'name' => (string)($order['buyer_name'] ?? ''),
					'created' => (int)($order['order_created'] ?? 0) > 0 ? (int)$order['order_created'] : (int)($order['created'] ?? $order['cart_active_time'] ?? 0),
					'items' => $counts[$id] ?? 0,
					'total' => $total,
					'payment_status' => (string)($order['payment_status'] ?? 'unpaid'),
					'status' => (string)($order['status'] ?? ''),
					'payment_note' => trim((string)($order['payment_note'] ?? '')),
			];
		}

		return $orders;

	}

	/**
	 * Admin amounts: currency sign and always two decimals (£0.00, £1,250.00).
	 */
	function format_admin_amount($amount, $currency){

		return (string)($currency['sign'] ?? '').number_format((float)$amount, 2, '.', ',');

	}

	/**
	 * VAT rate from Shop settings: ['rate' => float], or ['rate' => null] for N/A (empty setting).
	 * A value that is not a number counts as N/A and is logged.
	 */
	function get_vat(){

		$raw = trim(str_replace([',', '%'], ['.', ''], (string)($this->get_shop_settings()['vat_rate'] ?? '')));
		if ($raw === ''){
			return ['rate' => null];
		}
		if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw >= 100){
			if (empty($GLOBALS['shop_vat_logged'])){
				$GLOBALS['shop_vat_logged'] = 1;
				error_log_user('shop get_vat: Shop settings → VAT rate "'.$raw.'" is not a rate in %, treated as N/A');
			}
			return ['rate' => null];
		}

		return ['rate' => (float)$raw];

	}

	/**
	 * VAT line for the product page ($context product) or under the cart total ($context cart, $amount the
	 * VAT-inclusive total). N/A: the N/A text. '' when the text setting is empty.
	 */
	function vat_text($context, $amount = 0, $currency = null){

		$settings = $this->get_shop_settings();
		$rate = $this->get_vat()['rate'];
		if ($rate === null){
			return (string)($settings['vat_na_text'] ?? '');
		}

		$rate_text = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
		if ($context === 'product'){
			return str_replace('{{rate}}', $rate_text, (string)($settings['vat_product_text'] ?? ''));
		}

		$vat = round((float)$amount * $rate / (100 + $rate), 2);
		$currency = $currency ?: $this->get_shop_currency();

		return str_replace(['{{amount}}', '{{rate}}'], [$this->format_admin_amount($vat, $currency), $rate_text], (string)($settings['vat_cart_text'] ?? ''));

	}

	function order_status_label($status){

		$labels = [
				'' => 'Draft cart',
				'abandoned' => 'Abandoned',
				'unfulfilled' => 'Unfulfilled',
				'in_progress' => 'In progress',
				'fulfilled' => 'Fulfilled',
				'finished' => 'Finished',
				'cancelled' => 'Cancelled',
				'paid' => 'Unfulfilled',
		];

		return $labels[(string)$status] ?? (string)$status;

	}

	function order_payment_label($status){

		$labels = [
				'unpaid' => 'Unpaid',
				'pending' => 'Pending',
				'partially_paid' => 'Partially paid',
				'paid' => 'Paid',
				'partially_refunded' => 'Partially refunded',
				'refunded' => 'Refunded',
				'voided' => 'Voided',
		];

		return $labels[(string)$status] ?? 'Unpaid';

	}

	/**
	 * Add a line to the order history (shop_order_log). $type: event, or comment (admin, shown bold).
	 * The CMS user is stored when one is logged in. A failed write is logged and never stops the caller.
	 */
	function log_order($order_id, $line, $type = 'event'){

		$order_id = (int)$order_id;
		$line = trim((string)$line);
		if ($order_id < 1 || $line === ''){
			return false;
		}

		// Name as the CMS header shows it; stored, as config admins have no cms_user row.
		$user = $_SESSION['cms_user'] ?? [];
		$user_name = (string)(!empty($user['name']) ? $user['name'] : ($user['username'] ?? ''));

		try {
			$this->db->query('insert into shop_order_log (order_id, time, type, cms_user_id, user_name, line) values (?, ?, ?, ?, ?, ?)', [
					$order_id,
					time(),
					$type === 'comment' ? 'comment' : 'event',
					(int)($user['cms_user_id'] ?? 0),
					mb_substr($user_name, 0, 50),
					mb_substr($line, 0, 4000),
			]);
		} catch (\Throwable $e){
			error_log_user('shop log_order: order '.$order_id.' not logged ('.$e->getMessage().')');
			return false;
		}

		return true;

	}

	/**
	 * Order history, newest first: time, type, line, and the CMS user's name ('' when the system did it).
	 */
	function get_order_log($order_id){

		$sql = 'select time, type, line, user_name from shop_order_log where order_id = ? order by time desc, shop_order_log_id desc';

		return $this->db->query($sql, [(int)$order_id])->result_array();

	}

	/**
	 * History lines for an order update that changes status, payment status or the payment note.
	 * Call before the update with the row as it was.
	 */
	function log_order_state($order, $update){

		$id = (int)($order['cms_page_panel_id'] ?? 0);

		if (array_key_exists('status', $update) && (string)$update['status'] !== (string)($order['status'] ?? '')){
			$this->log_order($id, 'Status: '.$this->order_status_label($order['status'] ?? '').' → '.$this->order_status_label($update['status']));
		}

		$pay = (string)($update['payment_status'] ?? '');
		if ($pay !== '' && $pay !== $this->order_payment_status($order)){
			$line = 'Payment: '.$this->order_payment_label($this->order_payment_status($order)).' → '.$this->order_payment_label($pay);
			if ($pay === 'paid' && (string)($update['paid_amount'] ?? '') !== ''){
				$line .= ', '.$this->format_admin_amount($update['paid_amount'], $this->get_payable_order($id)['currency_row'] ?: $this->get_shop_currency())
						.' ('.trim(($update['payment_provider'] ?? '').' '.($update['payment_ref'] ?? '')).')';
			}
			$this->log_order($id, $line);
		}

		if (trim((string)($update['payment_note'] ?? '')) !== '' && (string)$update['payment_note'] !== (string)($order['payment_note'] ?? '')){
			$this->log_order($id, 'Payment note: '.$update['payment_note']);
		}

	}

	/**
	 * Whether the admin may set the payment status by hand: not on a draft or abandoned cart.
	 */
	function payment_editable($order){

		return !in_array($this->order_physical_status($order), ['', 'abandoned'], true);

	}

	/**
	 * Hand-set payment status (order page). Logged; marking paid sets paid_time when there is none.
	 */
	function set_order_payment_status($order_id, $payment_status){

		$this->load->model('cms/cms_page_panel_model');

		$id = (int)$order_id;
		$pay = (string)$payment_status;
		$order = $this->cms_page_panel_model->get_cms_page_panel($id);
		if (($order['panel_name'] ?? '') !== 'shop/order' || !in_array($pay, ['unpaid', 'pending', 'partially_paid', 'paid', 'partially_refunded', 'refunded', 'voided'], true)){
			return false;
		}
		// A draft cart is still a visitor's live cart (and an abandoned one may still be paid at Stripe):
		// a hand-set "paid" would make set_order_paid skip the real payment and its emails.
		if (!$this->payment_editable($order)){
			return false;
		}
		if ($pay === $this->order_payment_status($order)){
			return false;
		}

		$update = ['payment_status' => $pay];
		if ($pay === 'paid' && empty($order['paid_time'])){
			$update['paid_time'] = time();
		}
		$this->log_order_state($order, $update);
		$this->cms_page_panel_model->update_cms_page_panel($id, $update);

		return true;

	}

	/**
	 * Admin comment on the order page (plain text, no formatting).
	 */
	function add_order_comment($order_id, $text){

		$text = $this->clean_visitor_text($text, 4000, true);
		if ($text === ''){
			return false;
		}

		return $this->log_order($order_id, $text, 'comment');

	}

	/**
	 * What there is to pay for an order: the one source for the cart total, payment providers, the
	 * paid-amount check and order emails. Amounts are in major units. See shop.md § Payable order.
	 */
	function get_payable_order($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');

		$raw = $this->get_order_lines($order_id);
		$lines = [];
		$currency_row = null;
		$mixed = false;
		$subtotal = 0.0;
		$delivery_total = 0.0;

		foreach ($raw as $line){

			$row = $this->get_currency((int)$line['currency_id']) ?: $this->get_shop_currency();
			if ($currency_row === null){
				$currency_row = $row;
			} else if ((int)($row['cms_page_panel_id'] ?? 0) !== (int)($currency_row['cms_page_panel_id'] ?? 0)){
				$mixed = true;
			}

			$item_id = $this->order_line_item_id($line);
			$quantity = max(1, (int)$line['quantity']);
			$total = round((float)$line['price'] * $quantity, 2);
			$dims = $item_id > 0 ? $this->shop_dim_model->dims_description($this->cms_page_panel_model->get_cms_page_panel($item_id)) : '';
			if ($item_id > 0){
				$subtotal += $total;
			} else {
				$delivery_total += $total;
			}

			$lines[] = [
					'line_id' => (int)$line['cms_page_panel_id'],
					'type' => $item_id > 0 ? 'item' : 'delivery',
					'item_id' => $item_id,
					'name' => (string)($line['item'] ?? ''),
					'dims' => $dims,
					'unit_price' => round((float)$line['price'], 2),
					'quantity' => $quantity,
					'total' => $total,
					'currency_row' => $row,
			];

		}

		// Delivery methods a provider may offer: shown shop/delivery rows, list order.
		$options = [];
		foreach ($this->cms_page_panel_model->get_list('shop/delivery') as $delivery){
			if (!empty($delivery['show'])){
				$options[] = [
						'delivery_id' => (int)$delivery['cms_page_panel_id'],
						'name' => (string)$delivery['heading'],
						'price' => round((float)($delivery['price'] ?? 0), 2),
				];
			}
		}

		return [
				'order_id' => (int)$order_id,
				'currency' => $mixed ? '' : strtolower((string)($currency_row['heading'] ?? '')),
				'currency_row' => $currency_row,
				'mixed_currency' => $mixed,
				'lines' => $lines,
				'subtotal' => round($subtotal, 2),
				'delivery_total' => round($delivery_total, 2),
				'total' => round($subtotal + $delivery_total, 2),
				'delivery_options' => $options,
				'fingerprint' => $this->calculate_order_lines_fingerprint($raw),
		];

	}

	/**
	 * Currency for a new price.
	 * Today this is the shop default currency.
	 * Later this is where user preference, country, and similar choices belong.
	 * Do not build that resolver yet.
	 */
	function get_shop_currency(){

		return $this->get_default_currency();

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
					'play_product_id' => '',
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
		$play = '';
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
					$play = trim((string)($row['play_product_id'] ?? ''));
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
				'play_product_id' => $play,
				'source' => $source,
		];

	}

	/**
	 * Price of a sellable item in a shop currency.
	 * A set item price, including 0, wins. Otherwise the product price, then rate or prices[].
	 * $currency null uses get_shop_currency().
	 */
	function get_item_price_in_currency($item, $product, $currency = null){

		$this->load->model('cms/cms_page_panel_model');

		if (!is_array($item) && (int)$item > 0){
			$item = $this->cms_page_panel_model->get_cms_page_panel((int)$item);
		}
		if (!is_array($item)){
			$item = [];
		}
		if (!is_array($product)){
			$product = $this->cms_page_panel_model->get_cms_page_panel((int)$product);
		}
		if (!is_array($product)){
			$product = [];
		}

		if ($currency === null){
			$currency = $this->get_shop_currency();
		} else if (!is_array($currency)){
			$currency = $this->get_currency((int)$currency);
		}

		$item_set = array_key_exists('price', $item) && $item['price'] !== '' && $item['price'] !== null;
		if (!$item_set){
			$currency_id = is_array($currency) ? (int)($currency['cms_page_panel_id'] ?? 0) : 0;
			return $this->get_product_price_in_currency($product, $currency_id);
		}

		$main = (float)str_replace(',', '.', (string)$item['price']);
		$default = $this->get_default_currency();
		$default_id = is_array($default) ? (int)($default['cms_page_panel_id'] ?? 0) : 0;
		$currency_id = is_array($currency) ? (int)($currency['cms_page_panel_id'] ?? 0) : 0;
		$source = 'item';
		$price = $main;
		if ($currency_id > 0 && $default_id > 0 && $currency_id !== $default_id){
			$price = $this->convert_from_main($main, $currency);
			$source = 'item_rate';
		}
		$price = round($price, 2);

		return [
				'price' => $price,
				'formatted' => $this->format_amount($price, $currency),
				'currency' => $currency,
				'stripe_price_id' => '',
				'play_product_id' => '',
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
