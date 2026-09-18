<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Shopify order id on shop/order: webhook apply + Admin GraphQL fallback.
 * After pay: overlay paid lines, drop leftover cart lines.
 * CMS fulfilment only when payment is paid and Shopify is still unfulfilled.
 */
class shopify_order_model extends \Model {

	function webhook_hmac_secret(){

		$secret = trim((string)($GLOBALS['config']['shopify_webhook_secret'] ?? ''));
		if ($secret !== ''){
			return $secret;
		}

		return trim((string)($GLOBALS['config']['shopify_api_secret'] ?? ''));

	}

	function verify_webhook_hmac($raw_body, $hmac_header){

		$secret = $this->webhook_hmac_secret();
		$hmac_header = trim((string)$hmac_header);
		if ($secret === '' || $hmac_header === ''){
			return false;
		}

		$calculated = base64_encode(hash_hmac('sha256', (string)$raw_body, $secret, true));
		if (strlen($calculated) !== strlen($hmac_header)){
			return false;
		}

		return hash_equals($calculated, $hmac_header);

	}

	function _normalize_webhook_order($payload){

		if (!is_array($payload)){
			return [];
		}
		if (!empty($payload['order']) && is_array($payload['order'])){
			return $payload['order'];
		}

		return $payload;

	}

	function _attr_list($order){

		$out = [];
		foreach (['note_attributes', 'noteAttributes', 'customAttributes', 'custom_attributes'] as $key){
			if (empty($order[$key]) || !is_array($order[$key])){
				continue;
			}
			foreach ($order[$key] as $row){
				if (!is_array($row)){
					continue;
				}
				$k = (string)($row['key'] ?? $row['name'] ?? '');
				$v = (string)($row['value'] ?? '');
				if ($k !== ''){
					$out[] = ['key' => $k, 'value' => $v];
				}
			}
		}

		return $out;

	}

	function cms_order_id_from_shopify_order($order){

		foreach ($this->_attr_list($order) as $row){
			if ($row['key'] === 'cms_order_id'){
				$id = (int)$row['value'];
				if ($id > 0){
					return $id;
				}
			}
		}

		return 0;

	}

	function visible_shopify_order_name($order){

		if (!is_array($order)){
			return '';
		}

		$name = trim((string)($order['name'] ?? ''));
		if ($name !== ''){
			return $name;
		}

		$num = $order['order_number'] ?? $order['orderNumber'] ?? '';
		if ($num !== '' && $num !== null && is_numeric($num)){
			return '#'.(string)((int)$num);
		}

		return '';

	}

	function numeric_shopify_order_id($order){

		if (!is_array($order)){
			return '';
		}

		foreach (['legacyResourceId', 'legacy_resource_id'] as $key){
			if (!empty($order[$key]) && ctype_digit((string)$order[$key])){
				return (string)$order[$key];
			}
		}

		$id = $order['id'] ?? '';
		if (is_int($id) || (is_string($id) && ctype_digit($id))){
			return (string)$id;
		}

		$gid = (string)($order['admin_graphql_api_id'] ?? $order['adminGraphqlApiId'] ?? '');
		if ($gid === '' && is_string($id) && strpos($id, 'gid://') === 0){
			$gid = $id;
		}
		if (preg_match('#/Order/(\d+)#', $gid, $m)){
			return $m[1];
		}

		return '';

	}

	function _unix_from_shopify_time($value){

		if ($value === null || $value === ''){
			return 0;
		}
		if (is_int($value) || (is_string($value) && ctype_digit($value))){
			$n = (int)$value;
			return $n > 0 ? $n : 0;
		}

		$ts = strtotime((string)$value);
		return $ts ? $ts : 0;

	}

	function _shopify_payment_status($order, $topic = ''){

		$raw = strtolower((string)($order['financial_status']
				?? $order['displayFinancialStatus']
				?? $order['display_financial_status']
				?? ''));
		$raw = str_replace('_', '', $raw);

		$map = [
				'unpaid' => 'unpaid',
				'pending' => 'pending',
				'authorized' => 'pending',
				'expired' => 'pending',
				'partiallypaid' => 'partially_paid',
				'paid' => 'paid',
				'partiallyrefunded' => 'paid',
				'refunded' => 'refunded',
				'voided' => 'voided',
		];
		if (isset($map[$raw])){
			return $map[$raw];
		}

		$topic = strtolower(str_replace('_', '/', (string)$topic));
		if ($topic === 'orders/paid' || !empty($order['fullyPaid']) || !empty($order['fully_paid'])){
			return 'paid';
		}

		return 'unpaid';

	}

	function _shopify_order_is_paid($order, $topic = ''){

		return $this->_shopify_payment_status($order, $topic) === 'paid';

	}

	function _shopify_should_sync_lines($order, $topic = ''){

		$pay = $this->_shopify_payment_status($order, $topic);
		return in_array($pay, ['paid', 'partially_paid'], true);

	}

	function _shopify_physical_status($order){

		if (!is_array($order)){
			return 'unfulfilled';
		}

		$cancel_reason = $order['cancel_reason'] ?? $order['cancelReason'] ?? null;
		if (!empty($order['cancelled_at']) || !empty($order['cancelledAt'])
				|| (is_string($cancel_reason) && $cancel_reason !== '')){
			return 'cancelled';
		}

		$display = strtolower(str_replace('_', '', (string)(
				$order['displayFulfillmentStatus']
				?? $order['display_fulfillment_status']
				?? ''
		)));
		if ($display === 'fulfilled'){
			return 'fulfilled';
		}
		if (in_array($display, ['inprogress', 'pendingfulfillment'], true)){
			return 'in_progress';
		}

		$rest = strtolower((string)($order['fulfillment_status'] ?? $order['fulfillmentStatus'] ?? ''));
		if ($rest === 'fulfilled'){
			return 'fulfilled';
		}

		$fulfillments = $order['fulfillments'] ?? [];
		if (is_array($fulfillments)){
			foreach ($fulfillments as $row){
				if (!is_array($row)){
					continue;
				}
				$st = strtolower(str_replace('_', '', (string)($row['status'] ?? $row['displayStatus'] ?? '')));
				if (in_array($st, ['pending', 'open', 'inprogress'], true)){
					return 'in_progress';
				}
			}
		}

		return 'unfulfilled';

	}

	/**
	 * Physical status only moves forward (except cancelled, which always wins).
	 */
	function _merge_physical_status($current, $incoming){

		if ($current === 'paid'){
			$current = 'unfulfilled';
		}
		if ($incoming === '' || $incoming === 'paid'){
			$incoming = 'unfulfilled';
		}
		if ($current === 'finished'){
			return 'finished';
		}
		if ($current === 'cancelled'){
			return 'cancelled';
		}
		if ($incoming === 'cancelled'){
			return 'cancelled';
		}

		$rank = [
				'' => 0,
				'abandoned' => 0,
				'unfulfilled' => 1,
				'in_progress' => 2,
				'fulfilled' => 3,
				'finished' => 4,
		];
		$cr = $rank[$current] ?? 1;
		$ir = $rank[$incoming] ?? 1;

		return $ir >= $cr ? $incoming : $current;

	}

	function find_cms_order_for_shopify_order($shopify_order){

		$this->load->model('cms/cms_page_panel_model');

		$cms_id = $this->cms_order_id_from_shopify_order($shopify_order);
		if ($cms_id <= 0){
			return null;
		}

		$row = $this->cms_page_panel_model->get_cms_page_panel($cms_id);
		if (!empty($row['cms_page_panel_id']) && ($row['panel_name'] ?? '') === 'shop/order'){
			return $row;
		}

		error_log_user('CMS error [shopify/order]: cms_order_id='.$cms_id.
				' on Shopify order is not a shop/order row');
		return null;

	}

	/**
	 * Write Shopify order id / times / buyer / lines onto the CMS row.
	 * Closes the draft cart when still open. Requests CMS fulfilment when paid and unfulfilled.
	 */
	function apply_shopify_order_to_cms($cms_order, $shopify_order, $topic = ''){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$cms_id = (int)($cms_order['cms_page_panel_id'] ?? 0);
		if ($cms_id <= 0){
			return ['ok' => 0, 'error' => 'CMS order missing'];
		}

		$shopify_id = $this->numeric_shopify_order_id($shopify_order);
		if ($shopify_id === ''){
			error_log_user('CMS error [shopify/order]: Shopify order id missing for cms_order_id='.$cms_id);
			return ['ok' => 0, 'error' => 'Shopify order id missing'];
		}

		$existing = trim((string)($cms_order['shopify_order_id'] ?? ''));
		if ($existing !== '' && $existing !== $shopify_id){
			error_log_user('CMS error [shopify/order]: shopify_order_id already '.$existing.
					' (incoming '.$shopify_id.') cms_order_id='.$cms_id);
			return ['ok' => 0, 'error' => 'Shopify order id already set'];
		}

		$shopify_order = $this->_hydrate_shopify_order($shopify_order);

		$now = time();
		$created = $this->_unix_from_shopify_time($shopify_order['created_at'] ?? $shopify_order['createdAt'] ?? '');
		$processed = $this->_unix_from_shopify_time($shopify_order['processed_at'] ?? $shopify_order['processedAt'] ?? '');
		$payment_status = $this->_shopify_payment_status($shopify_order, $topic);
		$paid = $payment_status === 'paid';
		$physical = $this->_merge_physical_status(
				$this->shop_model->order_physical_status($cms_order),
				$this->_shopify_physical_status($shopify_order)
		);

		if ($paid && $physical === 'unfulfilled'
				&& empty($shopify_order['displayFulfillmentStatus'])
				&& empty($shopify_order['display_fulfillment_status'])){
			$detail = $this->_admin_order_detail('gid://shopify/Order/'.$shopify_id);
			if (is_array($detail) && $detail){
				$shopify_order = array_merge($shopify_order, $detail);
				$physical = $this->_merge_physical_status(
						$physical,
						$this->_shopify_physical_status($shopify_order)
				);
				$payment_status = $this->_shopify_payment_status($shopify_order, $topic);
				$paid = $payment_status === 'paid';
			}
		}

		$was_open = $this->shop_model->order_is_draft($cms_order)
				|| $this->shop_model->order_physical_status($cms_order) === 'abandoned';

		$update = [
				'payment_status' => $payment_status,
				'status' => $physical,
		];
		if ($existing === ''){
			$update['shopify_order_id'] = $shopify_id;
		}
		$order_name = $this->visible_shopify_order_name($shopify_order);
		if ($order_name !== '' && $order_name !== trim((string)($cms_order['shopify_order_name'] ?? ''))){
			$update['shopify_order_name'] = $order_name;
		}
		if (empty($cms_order['order_created'])){
			$update['order_created'] = $created > 0 ? $created : $now;
		}
		if ($paid && empty($cms_order['paid_time'])){
			$update['paid_time'] = $processed > 0 ? $processed : ($created > 0 ? $created : $now);
		}

		foreach ($this->_buyer_shipping_from_shopify($shopify_order) as $key => $value){
			if ($value !== ''){
				$update[$key] = $value;
			}
		}

		if ($update){
			$this->cms_page_panel_model->update_cms_page_panel($cms_id, $update);
			$cms_order = array_merge($cms_order, $update);
		}

		if ($was_open){
			$this->shop_model->close_cart_order($cms_id, $physical, $payment_status);
			$cms_order = $this->cms_page_panel_model->get_cms_page_panel($cms_id);
		}

		if ($this->_shopify_should_sync_lines($shopify_order, $topic)){
			$this->sync_paid_lines($cms_id, $shopify_order);
		}

		$per_line = false;
		if (!$this->shop_model->order_is_finished($cms_order)){
			$per_line = $this->apply_shopify_line_statuses($cms_id, $shopify_order);
			if (!$per_line && in_array($physical, ['unfulfilled', 'in_progress', 'fulfilled', 'cancelled'], true)){
				$this->shop_model->apply_lines_physical_status($cms_id, $physical);
			}
		}

		if ($this->shop_model->order_cms_fulfilment_ok($cms_order)){
			$this->load->model('shop/shop_fulfilment_model');
			$this->shop_fulfilment_model->request_fulfilment($cms_id);
		}
		$this->shop_model->rollup_order_status($cms_id);

		return [
				'ok' => 1,
				'cms_order_id' => $cms_id,
				'shopify_order_id' => $shopify_id,
				'closed' => $was_open ? 1 : 0,
		];

	}

	/**
	 * Re-read Admin fulfilment/payment onto a CMS order. null = query failed (do not fulfil this tick).
	 */
	function refresh_shopify_order_status($cms_order){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$id = trim((string)($cms_order['shopify_order_id'] ?? ''));
		if ($id === ''){
			return $cms_order;
		}

		$detail = $this->_admin_order_detail('gid://shopify/Order/'.$id);
		if (!$detail){
			return null;
		}

		$physical = $this->_merge_physical_status(
				$this->shop_model->order_physical_status($cms_order),
				$this->_shopify_physical_status($detail)
		);
		$payment = $this->_shopify_payment_status($detail, '');
		$patch = [];
		if ($physical !== $this->shop_model->order_physical_status($cms_order)){
			$patch['status'] = $physical;
		}
		if ($payment !== $this->shop_model->order_payment_status($cms_order)){
			$patch['payment_status'] = $payment;
		}
		if ($payment === 'paid' && empty($cms_order['paid_time'])){
			$processed = $this->_unix_from_shopify_time($detail['processedAt'] ?? $detail['processed_at'] ?? '');
			$created = $this->_unix_from_shopify_time($detail['createdAt'] ?? $detail['created_at'] ?? '');
			$patch['paid_time'] = $processed > 0 ? $processed : ($created > 0 ? $created : time());
		}
		if ($patch){
			$this->cms_page_panel_model->update_cms_page_panel($cms_order['cms_page_panel_id'], $patch);
			$cms_order = array_merge($cms_order, $patch);
		}

		$oid = (int)$cms_order['cms_page_panel_id'];
		if (!$this->shop_model->order_is_finished($cms_order)){
			$per_line = $this->apply_shopify_line_statuses($oid, $detail);
			if (!$per_line && in_array($physical, ['unfulfilled', 'in_progress', 'fulfilled', 'cancelled'], true)){
				$this->shop_model->apply_lines_physical_status($oid, $physical);
			}
		}
		$this->shop_model->rollup_order_status($oid);
		return $this->cms_page_panel_model->get_cms_page_panel($oid);

	}

	function _hydrate_shopify_order($order){

		if ($this->_shopify_line_rows($order)){
			return $order;
		}

		$gid = (string)($order['id'] ?? '');
		if ($gid === '' || strpos($gid, 'gid://') !== 0){
			$num = $this->numeric_shopify_order_id($order);
			if ($num === ''){
				return $order;
			}
			$gid = 'gid://shopify/Order/'.$num;
		}

		$detail = $this->_admin_order_detail($gid);
		if (!is_array($detail) || !$detail){
			return $order;
		}

		return array_merge($order, $detail);

	}

	function _admin_order_detail_query($with_pii){

		$pii = '';
		if ($with_pii){
			$pii = '
					email
					shippingAddress {
						name
						firstName
						lastName
						address1
						address2
						city
						zip
						province
						country
						phone
					}';
		}

		return '
			query OrderDetail($id: ID!) {
				order(id: $id) {
					id
					legacyResourceId
					name
					createdAt
					processedAt
					cancelledAt
					displayFinancialStatus
					displayFulfillmentStatus
					fullyPaid
					customAttributes { key value }
					'.$pii.'
					lineItems(first: 100) {
						nodes {
							id
							title
							variantTitle
							quantity
							unfulfilledQuantity
							sku
							variant { id }
							product { id legacyResourceId }
							customAttributes { key value }
						}
					}
				}
			}
		';

	}

	function _graphql_is_pii_denied($data){

		$bits = [];
		if (!empty($data['_reason'])){
			$bits[] = (string)$data['_reason'];
		}
		foreach (($data['_errors'] ?? []) as $row){
			if (is_array($row) && !empty($row['message'])){
				$bits[] = (string)$row['message'];
			} else if (is_string($row)){
				$bits[] = $row;
			}
		}
		$blob = strtolower(implode(' ', $bits));
		if ($blob === ''){
			return false;
		}
		return (strpos($blob, 'customer object') !== false
				|| strpos($blob, 'personally identifiable') !== false);
	}

	function _admin_order_detail($gid){

		$this->load->model('shopify/shopify_product_model');

		$data = $this->shopify_product_model->graphql($this->_admin_order_detail_query(true), ['id' => $gid], 1);
		if (!empty($data['_soft_fail']) || !empty($data['_errors'])){
			if ($this->_graphql_is_pii_denied($data)){
				$data = $this->shopify_product_model->graphql($this->_admin_order_detail_query(false), ['id' => $gid], 1);
			}
		}
		if (!empty($data['_soft_fail']) || !empty($data['_errors'])){
			$reason = $data['_reason'] ?? '';
			if ($reason === ''){
				$reason = !empty($data['_errors'][0]['message']) ? $data['_errors'][0]['message'] : 'graphql';
			}
			error_log_user('CMS error [shopify/order]: Admin order detail failed ('.$reason.')');
			return [];
		}

		$node = $data['order'] ?? [];
		return is_array($node) ? $node : [];

	}

	function _buyer_shipping_from_shopify($order){

		$ship = $order['shipping_address'] ?? $order['shippingAddress'] ?? [];
		if (!is_array($ship)){
			$ship = [];
		}

		$first = trim((string)($ship['first_name'] ?? $ship['firstName'] ?? ''));
		$last = trim((string)($ship['last_name'] ?? $ship['lastName'] ?? ''));
		$ship_name = trim((string)($ship['name'] ?? ''));
		if ($ship_name === ''){
			$ship_name = trim($first.' '.$last);
		}

		$cust = $order['customer'] ?? [];
		if (!is_array($cust)){
			$cust = [];
		}
		$cust_name = trim((string)($cust['first_name'] ?? $cust['firstName'] ?? '').' '.
				(string)($cust['last_name'] ?? $cust['lastName'] ?? ''));

		$email = trim((string)($order['email'] ?? $cust['email'] ?? ''));

		return [
				'buyer_name' => $cust_name !== '' ? $cust_name : $ship_name,
				'buyer_email' => $email,
				'shipping_name' => $ship_name !== '' ? $ship_name : $cust_name,
				'shipping_address1' => trim((string)($ship['address1'] ?? '')),
				'shipping_address2' => trim((string)($ship['address2'] ?? '')),
				'shipping_city' => trim((string)($ship['city'] ?? '')),
				'shipping_postcode' => trim((string)($ship['zip'] ?? $ship['postal_code'] ?? '')),
				'shipping_county' => trim((string)($ship['province'] ?? '')),
				'shipping_country' => trim((string)($ship['country'] ?? '')),
				'shipping_phone' => trim((string)($ship['phone'] ?? '')),
		];

	}

	function _variant_key($value){

		$value = (string)$value;
		if (preg_match('#ProductVariant/(\d+)#', $value, $m)){
			return $m[1];
		}
		if (ctype_digit($value)){
			return $value;
		}

		return $value !== '' ? $value : '';

	}

	function _line_id_key($value){

		$value = (string)$value;
		if (preg_match('#LineItem/(\d+)#', $value, $m)){
			return $m[1];
		}
		if (ctype_digit($value)){
			return $value;
		}

		return $value !== '' ? $value : '';

	}

	function _shopify_line_physical_status($item, $order_cancelled = false){

		if ($order_cancelled){
			return 'cancelled';
		}
		if (!is_array($item)){
			return '';
		}

		$fs = strtolower(str_replace('_', '', (string)($item['fulfillment_status']
				?? $item['fulfillmentStatus']
				?? '')));
		if ($fs === 'fulfilled'){
			return 'fulfilled';
		}
		if ($fs === 'partial'){
			return 'in_progress';
		}

		$qty = (int)($item['quantity'] ?? 0);
		if (array_key_exists('unfulfilledQuantity', $item) || array_key_exists('unfulfilled_quantity', $item)){
			$unf = (int)($item['unfulfilledQuantity'] ?? $item['unfulfilled_quantity'] ?? 0);
			if ($qty > 0 && $unf <= 0){
				return 'fulfilled';
			}
			if ($qty > 0 && $unf < $qty){
				return 'in_progress';
			}
			return 'unfulfilled';
		}
		if (array_key_exists('fulfillable_quantity', $item)){
			$left = (int)$item['fulfillable_quantity'];
			if ($qty > 0 && $left <= 0){
				return 'fulfilled';
			}
			if ($qty > 0 && $left < $qty){
				return 'in_progress';
			}
			return 'unfulfilled';
		}

		return '';

	}

	function _shopify_line_rows($order){

		$rows = [];
		$nodes = $order['lineItems']['nodes'] ?? null;
		if ($nodes === null && !empty($order['lineItems']['edges']) && is_array($order['lineItems']['edges'])){
			$nodes = [];
			foreach ($order['lineItems']['edges'] as $edge){
				if (!empty($edge['node']) && is_array($edge['node'])){
					$nodes[] = $edge['node'];
				}
			}
		}
		if (is_array($nodes)){
			foreach ($nodes as $item){
				if (!is_array($item)){
					continue;
				}
				$variant = $item['variant']['id'] ?? '';
				$product_sid = (string)($item['product']['legacyResourceId'] ?? '');
				if ($product_sid === '' && !empty($item['product']['id'])){
					if (preg_match('#Product/(\d+)#', (string)$item['product']['id'], $m)){
						$product_sid = $m[1];
					}
				}
				$rows[] = [
						'shopify_line_id' => $this->_line_id_key($item['id'] ?? ''),
						'variant_id' => $this->_variant_key($variant),
						'product_shopify_id' => $product_sid,
						'title' => (string)($item['title'] ?? ''),
						'variant_title' => (string)($item['variantTitle'] ?? ''),
						'quantity' => max(1, (int)($item['quantity'] ?? 1)),
						'sku' => (string)($item['sku'] ?? ''),
						'price' => '',
						'unfulfilledQuantity' => $item['unfulfilledQuantity'] ?? $item['unfulfilled_quantity'] ?? null,
						'fulfillment_status' => $item['fulfillmentStatus'] ?? $item['fulfillment_status'] ?? '',
				];
			}
		}

		if ($rows){
			return $rows;
		}

		if (!empty($order['line_items']) && is_array($order['line_items'])){
			foreach ($order['line_items'] as $item){
				if (!is_array($item)){
					continue;
				}
				$rows[] = [
						'shopify_line_id' => $this->_line_id_key($item['id'] ?? ''),
						'variant_id' => $this->_variant_key($item['variant_id'] ?? ''),
						'product_shopify_id' => (string)($item['product_id'] ?? ''),
						'title' => (string)($item['title'] ?? ''),
						'variant_title' => (string)($item['variant_title'] ?? ''),
						'quantity' => max(1, (int)($item['quantity'] ?? 1)),
						'sku' => (string)($item['sku'] ?? ''),
						'price' => $item['price'] ?? '',
						'fulfillment_status' => $item['fulfillment_status'] ?? '',
						'fulfillable_quantity' => $item['fulfillable_quantity'] ?? null,
						'unfulfilled_quantity' => $item['unfulfilled_quantity'] ?? null,
				];
			}
		}

		return $rows;

	}

	function _cms_product_id_for_shopify($shopify_product_id){

		$shopify_product_id = trim((string)$shopify_product_id);
		if ($shopify_product_id === ''){
			return 0;
		}

		$this->load->model('cms/cms_page_panel_model');
		$found = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/product',
				'shopify_id' => $shopify_product_id,
		]);
		if (!$found){
			return 0;
		}
		$row = reset($found);

		return (int)($row['cms_page_panel_id'] ?? 0);

	}

	function _sku_ids_for_shopify_variant($product_id, $variant_id){

		$product_id = (int)$product_id;
		$variant_id = trim((string)$variant_id);
		if ($product_id <= 0 || $variant_id === ''){
			return [];
		}
		$this->load->model('shop/shop_dim_model');
		$item = $this->shop_dim_model->find_master_by_shopify_variant($product_id, $variant_id);
		$iid = (int)($item['cms_page_panel_id'] ?? 0);
		if ($iid <= 0){
			return [];
		}
		return [
				'product_item_id' => $iid,
				'ref_id' => $iid,
		];

	}

	function apply_shopify_line_statuses($cms_order_id, $shopify_order){

		$this->load->model('shop/shop_model');
		$cancelled = $this->_shopify_physical_status($shopify_order) === 'cancelled';
		$remote = $this->_shopify_line_rows($shopify_order);
		$had = false;
		$local = $this->shop_model->get_order_lines($cms_order_id);

		foreach ($remote as $item){
			$incoming = $this->_shopify_line_physical_status($item, $cancelled);
			if ($incoming === ''){
				continue;
			}
			$had = true;
			$match = null;
			foreach ($local as $line){
				$line_sid = $this->_line_id_key($line['shopify_line_id'] ?? '');
				if ($item['shopify_line_id'] !== '' && $line_sid === $item['shopify_line_id']){
					$match = $line;
					break;
				}
				$v_local = $this->_variant_key($line['shopify_variant_id'] ?? $line['merchandise_id'] ?? '');
				if ($item['variant_id'] !== '' && $v_local !== '' && $v_local === $item['variant_id']){
					$match = $line;
					break;
				}
			}
			if ($match){
				$this->shop_model->set_line_physical_status($match, $incoming);
			}
		}

		return $had;

	}

	/**
	 * Overlay paid Shopify lines onto CMS order_line rows. Delete unmatched cart leftovers
	 * that were never fulfilment-sent (checkout edits). Keep already-sent lines.
	 */
	function sync_paid_lines($cms_order_id, $shopify_order){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$remote = $this->_shopify_line_rows($shopify_order);
		if (!$remote){
			return;
		}

		$local = $this->shop_model->get_order_lines($cms_order_id);
		$used = [];

		foreach ($remote as $item){

			$match_id = 0;
			foreach ($local as $line){
				$lid = (int)($line['cms_page_panel_id'] ?? 0);
				if ($lid <= 0 || isset($used[$lid])){
					continue;
				}
				$line_sid = $this->_line_id_key($line['shopify_line_id'] ?? '');
				if ($item['shopify_line_id'] !== '' && $line_sid === $item['shopify_line_id']){
					$match_id = $lid;
					break;
				}
				$v_local = $this->_variant_key($line['shopify_variant_id'] ?? $line['merchandise_id'] ?? '');
				if ($item['variant_id'] !== '' && $v_local !== '' && $v_local === $item['variant_id']){
					$match_id = $lid;
					break;
				}
			}

			$qty = (int)$item['quantity'];
			$desc = trim((string)$item['variant_title']);
			$heading = trim((string)$item['title']);
			if ($match_id){
				$used[$match_id] = 1;
				$patch = [
						'shopify_line_id' => $item['shopify_line_id'],
						'qty' => $qty,
						'quantity' => $qty,
				];
				if ($heading !== ''){
					$patch['item'] = $heading;
				}
				if ($desc !== ''){
					$patch['description'] = $desc;
				}
				if ($item['variant_id'] !== ''){
					$patch['shopify_variant_id'] = $item['variant_id'];
					$patch['merchandise_id'] = 'gid://shopify/ProductVariant/'.$item['variant_id'];
				}
				$product_id = $this->_cms_product_id_for_shopify($item['product_shopify_id']);
				$sku = $this->_sku_ids_for_shopify_variant($product_id, $item['variant_id'] ?? '');
				if ($sku){
					$patch = array_merge($patch, $sku);
				}
				$this->cms_page_panel_model->update_cms_page_panel($match_id, $patch);
				continue;
			}

			$product_id = $this->_cms_product_id_for_shopify($item['product_shopify_id']);
			$sku = $this->_sku_ids_for_shopify_variant($product_id, $item['variant_id'] ?? '');
			$new_row = [
					'panel_name' => 'shop/order_line',
					'show' => 1,
					'sort' => 'first',
					'order_id' => $cms_order_id,
					'product_id' => $product_id ? $product_id : '',
					'shopify_line_id' => $item['shopify_line_id'],
					'shopify_variant_id' => $item['variant_id'],
					'merchandise_id' => $item['variant_id'] !== ''
							? 'gid://shopify/ProductVariant/'.$item['variant_id'] : '',
					'line_type' => 'shopify_variant',
					'qty' => $qty,
					'quantity' => $qty,
					'item' => $heading,
					'description' => $desc,
					'price' => $item['price'],
			];
			if ($sku){
				$new_row = array_merge($new_row, $sku);
			}
			$new_id = $this->cms_page_panel_model->create_cms_page_panel($new_row);
			if ((int)$new_id > 0){
				$used[(int)$new_id] = 1;
			}

		}

		foreach ($local as $line){
			$lid = (int)($line['cms_page_panel_id'] ?? 0);
			if ($lid <= 0 || isset($used[$lid])){
				continue;
			}
			if (!empty($line['fulfilment_sent_time'])){
				continue;
			}
			$this->cms_page_panel_model->delete_cms_page_panel($lid);
		}

	}

	function apply_webhook_order($payload, $topic = ''){

		$order = $this->_normalize_webhook_order($payload);
		if (empty($order)){
			error_log_user('CMS error [shopify/webhook]: empty order payload');
			return ['ok' => 0, 'error' => 'Empty order payload'];
		}

		$cms_id = $this->cms_order_id_from_shopify_order($order);
		if ($cms_id <= 0){
			// Faire / POS / other channels — not from this site cart
			return ['ok' => 1, 'ignored' => 1];
		}

		$cms_order = $this->find_cms_order_for_shopify_order($order);
		if (empty($cms_order['cms_page_panel_id'])){
			return ['ok' => 0, 'error' => 'cms_order_id not a shop/order'];
		}

		return $this->apply_shopify_order_to_cms($cms_order, $order, $topic);

	}

	function _admin_orders_query($days = 14){

		$this->load->model('shopify/shopify_product_model');

		$days = max(1, (int)$days);
		$since = gmdate('Y-m-d', time() - ($days * 86400));
		$query = "created_at:>='".$since."'";

		$gql = '
			query RecentOrders($query: String!) {
				orders(first: 50, query: $query, sortKey: CREATED_AT, reverse: true) {
					edges {
						node {
							id
							legacyResourceId
							name
							createdAt
							processedAt
							cancelledAt
							displayFinancialStatus
							displayFulfillmentStatus
							fullyPaid
							customAttributes { key value }
						}
					}
				}
			}
		';

		$data = $this->shopify_product_model->graphql($gql, ['query' => $query], 1);
		if (!empty($data['_soft_fail']) || !empty($data['_errors'])){
			$reason = $data['_reason'] ?? '';
			if ($reason === ''){
				$reason = !empty($data['_errors'][0]['message']) ? $data['_errors'][0]['message'] : 'graphql';
			}
			error_log_user('CMS error [shopify/order sync]: Admin orders query failed ('.$reason.')');
			return null;
		}

		$edges = $data['orders']['edges'] ?? [];
		$out = [];
		foreach ($edges as $edge){
			if (!empty($edge['node']) && is_array($edge['node'])){
				$out[] = $edge['node'];
			}
		}

		return $out;

	}

	function match_admin_order_for_cms($cms_order, $admin_orders){

		$cms_id = (string)(int)($cms_order['cms_page_panel_id'] ?? 0);
		if ($cms_id === '0'){
			return null;
		}

		foreach ($admin_orders as $shopify_order){
			$attr_id = $this->cms_order_id_from_shopify_order($shopify_order);
			if ($attr_id > 0 && (string)$attr_id === $cms_id){
				return $shopify_order;
			}
		}

		return null;

	}

	function attach_shopify_order_if_possible($order){

		if (empty($order['cms_page_panel_id'])){
			return ['ok' => 0, 'error' => 'Order missing'];
		}
		if (trim((string)($order['shopify_order_id'] ?? '')) !== ''){
			return ['ok' => 1, 'unchanged' => 1];
		}

		$admin_orders = $this->_admin_orders_query(14);
		if ($admin_orders === null){
			return ['ok' => 0, 'error' => 'Admin query failed', 'query_error' => 1];
		}

		$match = $this->match_admin_order_for_cms($order, $admin_orders);
		if (empty($match)){
			return ['ok' => 0, 'error' => 'No matching Shopify order'];
		}

		return $this->apply_shopify_order_to_cms($order, $match, '');

	}

	/**
	 * Remote Storefront cart missing: attach a paid Shopify order if Admin has one,
	 * otherwise abandon the CMS draft. Never marks paid without a Shopify order.
	 */
	function on_storefront_cart_gone($order){

		$this->load->model('shop/shop_model');

		if (empty($order['cms_page_panel_id'])){
			return ['ok' => 1, 'changed' => 0];
		}
		if (!$this->shop_model->order_is_draft($order) || $this->shop_model->order_is_paid($order)){
			return ['ok' => 1, 'changed' => 0];
		}

		$attached = $this->attach_shopify_order_if_possible($order);
		if (!empty($attached['ok'])){
			return [
					'ok' => 1,
					'changed' => empty($attached['unchanged']) ? 1 : 0,
					'quantity' => 0,
					'closed' => 1,
					'shopify_order_id' => $attached['shopify_order_id'] ?? ($order['shopify_order_id'] ?? ''),
			];
		}
		if (!empty($attached['query_error'])){
			return ['ok' => 0, 'error' => $attached['error'] ?? 'Admin query failed', 'changed' => 0];
		}

		$cart_id = trim((string)($order['shopify_cart_id'] ?? ''));
		if ($cart_id !== ''){
			$this->load->model('shopify/shopify_product_model');
			$cart_status = $this->shopify_product_model->storefront_cart_status($cart_id);
			if ($cart_status === 'open' || $cart_status === 'error'){
				return ['ok' => 1, 'changed' => 0, 'open' => $cart_status === 'open' ? 1 : 0];
			}
		}

		$this->shop_model->abandon_cart_order($order['cms_page_panel_id']);

		return [
				'ok' => 1,
				'changed' => 1,
				'quantity' => 0,
				'closed' => 1,
				'abandoned' => 1,
		];

	}

	/**
	 * Cron: carts that were sent to Shopify but never got shopify_order_id.
	 * Storefront carts often stay queryable after pay — match Admin first, do not wait for cart gone.
	 */
	function sync_unsynced_orders($max_seconds = 40){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shopify/shopify_product_model');

		$started = time();
		$max_seconds = max(5, (int)$max_seconds);

		$orders = $this->cms_page_panel_model->get_list('shop/order');
		$candidates = [];
		foreach ($orders as $order){
			if (trim((string)($order['shopify_cart_id'] ?? '')) === ''){
				continue;
			}
			if (trim((string)($order['shopify_order_id'] ?? '')) !== ''){
				continue;
			}
			$candidates[] = $order;
		}

		$checked = 0;
		$attached = 0;
		$open = 0;
		$abandoned = 0;
		$missed = 0;
		$admin_orders = $this->_admin_orders_query(14);
		$stopped = false;

		if ($admin_orders === null){
			return [
					'text' => 'Unsynced 0 — Admin query failed, abandon skipped',
					'checked' => 0,
					'attached' => 0,
					'open' => 0,
					'missed' => 0,
					'stopped' => 0,
			];
		}

		$this->load->model('shop/shop_model');

		foreach ($candidates as $order){

			if ((time() - $started) >= $max_seconds){
				$stopped = true;
				break;
			}

			$checked++;

			$match = $this->match_admin_order_for_cms($order, $admin_orders);
			if (!empty($match)){
				$result = $this->apply_shopify_order_to_cms($order, $match, '');
				if (!empty($result['ok'])){
					$attached++;
				} else {
					$missed++;
				}
				continue;
			}

			if (($order['status'] ?? '') !== ''){
				continue;
			}

			$cart_status = $this->shopify_product_model->storefront_cart_status($order['shopify_cart_id']);
			if ($cart_status === 'open'){
				$this->shopify_product_model->_cart_ensure_cms_order_id(
						$order['shopify_cart_id'],
						$order['cms_page_panel_id']
				);
				$open++;
				continue;
			}
			if ($cart_status === 'error'){
				$open++;
				continue;
			}

			if ($this->shop_model->abandon_cart_order($order['cms_page_panel_id'])){
				$abandoned++;
			} else {
				$missed++;
			}

		}

		$text = 'Unsynced '.$checked.', attached '.$attached.', still open '.$open.
				', abandoned '.$abandoned.', missed '.$missed;
		if ($stopped){
			$text .= ' - stopped ('.$max_seconds.'s limit)';
		} else {
			$text .= ' - done';
		}

		return [
				'text' => $text,
				'checked' => $checked,
				'attached' => $attached,
				'open' => $open,
				'abandoned' => $abandoned,
				'missed' => $missed,
				'stopped' => $stopped,
		];

	}

	/**
	 * Paid CMS orders: fill missing shopify_line_id and/or visible shopify_order_name.
	 */
	function backfill_paid_shopify_lines($max_seconds = 20){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$started = time();
		$max_seconds = max(5, (int)$max_seconds);
		$n = 0;
		$names = 0;

		foreach ($this->cms_page_panel_model->get_list('shop/order') as $order){

			if ((time() - $started) >= $max_seconds){
				break;
			}
			if (trim((string)($order['shopify_order_id'] ?? '')) === ''){
				continue;
			}
			if (!$this->shop_model->order_is_paid($order)){
				continue;
			}

			$need_name = trim((string)($order['shopify_order_name'] ?? '')) === '';
			$has_line = false;
			foreach ($this->shop_model->get_order_lines($order['cms_page_panel_id']) as $line){
				if (trim((string)($line['shopify_line_id'] ?? '')) !== ''){
					$has_line = true;
					break;
				}
			}
			if (!$need_name && $has_line){
				continue;
			}

			$gid = 'gid://shopify/Order/'.$order['shopify_order_id'];
			$detail = $this->_admin_order_detail($gid);
			if (!$detail){
				continue;
			}
			if ($need_name){
				$order_name = $this->visible_shopify_order_name($detail);
				if ($order_name !== ''){
					$this->cms_page_panel_model->update_cms_page_panel(
							$order['cms_page_panel_id'],
							['shopify_order_name' => $order_name]
					);
					$names++;
				}
			}
			if (!$has_line){
				$this->sync_paid_lines($order['cms_page_panel_id'], $detail);
				$n++;
			}

		}

		$bits = [];
		if ($n){
			$bits[] = 'Line backfill '.$n;
		}
		if ($names){
			$bits[] = 'Shopify name '.$names;
		}
		$text = $bits ? implode(', ', $bits) : 'Line backfill 0';

		return ['text' => $text, 'count' => $n + $names];

	}

	function _order_sync_status_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_order_sync_status.txt';

	}

	function _order_sync_lock_path(){

		return $GLOBALS['config']['base_path'].'cache/shopify_order_sync.lock';

	}

	function _order_sync_status_write($text, $done = false){

		$content = $text;
		if ($done){
			$content .= "\ndone";
		}
		file_put_contents($this->_order_sync_status_path(), $content, LOCK_EX);
		clearstatcache(true, $this->_order_sync_status_path());

	}

	function sync_linked_orders($max_seconds = 40){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$started = time();
		$max_seconds = max(5, (int)$max_seconds);
		$checked = 0;
		$updated = 0;
		$missed = 0;
		$stopped = false;

		foreach ($this->cms_page_panel_model->get_list('shop/order') as $order){

			if ((time() - $started) >= $max_seconds){
				$stopped = true;
				break;
			}
			if (trim((string)($order['shopify_order_id'] ?? '')) === ''){
				continue;
			}
			if ($this->shop_model->order_is_finished($order)){
				continue;
			}

			$checked++;
			$this->_order_sync_status_write('Orders '.$checked.' ('.$updated.' updated)', false);
			$gid = 'gid://shopify/Order/'.$order['shopify_order_id'];
			$detail = $this->_admin_order_detail($gid);
			if (!$detail){
				$missed++;
				continue;
			}
			$result = $this->apply_shopify_order_to_cms($order, $detail, '');
			if (!empty($result['ok'])){
				$updated++;
			} else {
				$missed++;
			}

		}

		$text = 'Linked '.$checked.', updated '.$updated.', missed '.$missed;
		if ($stopped){
			$text .= ' - stopped ('.$max_seconds.'s limit)';
		} else {
			$text .= ' - done';
		}

		return [
				'text' => $text,
				'checked' => $checked,
				'updated' => $updated,
				'missed' => $missed,
				'stopped' => $stopped,
		];

	}

	function run_order_sync_batch($max_seconds = 50){

		set_time_limit(0);
		if (function_exists('ignore_user_abort')){
			ignore_user_abort(true);
		}
		if (session_status() === PHP_SESSION_ACTIVE){
			session_write_close();
		}

		$lock = $this->_order_sync_lock_path();
		if (file_exists($lock)){
			$age = time() - (int)@filemtime($lock);
			if ($age < 120){
				return [
						'error' => 'busy',
						'text' => 'Wait, sync is running',
						'running' => true,
						'done' => false,
				];
			}
			@unlink($lock);
		}

		file_put_contents($lock, (string)time());
		$this->_order_sync_status_write('Syncing orders...', false);

		$max_seconds = max(10, (int)$max_seconds);
		$started = time();
		$parts = [];

		try {
			$unsynced = $this->sync_unsynced_orders(max(5, (int)($max_seconds / 3)));
			$parts[] = $unsynced['text'] ?? '';
			$left = $max_seconds - (time() - $started);
			if ($left < 8){
				$left = 8;
			}
			$linked = $this->sync_linked_orders($left);
			$parts[] = $linked['text'] ?? '';
			$left = $max_seconds - (time() - $started);
			if ($left >= 5){
				$this->load->model('shop/shop_fulfilment_model');
				$fulfil = $this->shop_fulfilment_model->fulfil_pending_orders($left);
				$parts[] = $fulfil['text'] ?? '';
			}
		} finally {
			@unlink($lock);
		}

		$text = trim(implode("\n", array_filter($parts)));
		if ($text === ''){
			$text = 'Shopify order sync finished';
		}
		$this->_order_sync_status_write($text, true);

		return [
				'text' => $text,
				'done' => true,
				'unsynced' => $unsynced ?? [],
				'linked' => $linked ?? [],
		];

	}

}
