<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Basic cart: local order draft (cookie cart_key), badge + popup. Settings arrive via _panel / ajax_panel.
 * Checkout: cart.js runs do=checkout_check, then the shop_checkout provider panel.
 */
class cart extends \Controller{

	function panel_action($params){

		$do = $params['cms_do'] ?? '';
		if ($do === ''){
			return $params;
		}

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');

		$user = $this->shop_model->get_front_user();

		if ($do == 'set_visible'){
			$_SESSION['shop']['cart_visible'] = (($params['value'] ?? '') === '1') ? '1' : '0';
			print(json_encode(['ok' => '1']));
			exit();
		}

		// Cart opened, or Checkout clicked: current prices, stock and hold, then panel_params renders.
		// checkout_check marks the reply ready only when nothing changed (cart.js then calls the provider).
		if ($do == 'open' || $do == 'checkout_check'){
			$params['price_changed_lines'] = [];
			$params['lost_item_ids'] = [];
			$order = $this->shop_model->get_current_order_if_any($user);
			if (!empty($order['cms_page_panel_id'])){
				$this->load->model('shop/shop_dim_model');
				$params['price_changed_lines'] = $this->shop_model->refresh_line_prices($order['cms_page_panel_id']);
				$params['lost_item_ids'] = $this->shop_dim_model->cart_refresh_hold($order['cms_page_panel_id']);
			}
			$params['checkout_ready'] = ($do == 'checkout_check' && !$params['price_changed_lines'] && !$params['lost_item_ids']) ? 1 : 0;
			$params['cart_details'] = 1;
			return $params;
		}

		if ($do == 'add'){

			$order = $this->shop_model->get_current_order($user);

			// Request values (validated here); visitor attributes are cleaned by create_order_line.
			$product_id = (int)($params['product_id'] ?? 0);
			$product_item_id = (int)($params['product_item_id'] ?? 0);
			$qty = max(1, (int)($params['quantity'] ?? 1));
			$attributes = $params['attributes'] ?? [];
			$dims = $params['dims'] ?? [];
			if (is_string($dims)){
				$dims = json_decode($dims, true);
			}
			if (!is_array($dims)){
				$dims = [];
			}

			$this->load->model('shop/shop_dim_model');
			$item_row = $this->shop_dim_model->resolve_cart_item($product_id, $product_item_id, $dims);
			$product_item_id = (int)($item_row['cms_page_panel_id'] ?? 0);
			if ($product_item_id > 0 && $product_id < 1){
				$product_id = (int)$item_row['product_id'];
			}

			$product_row = $product_id > 0 ? $this->cms_page_panel_model->get_cms_page_panel($product_id) : [];
			$variant_id = (string)($item_row['shopify_variant_id'] ?? '');
			$shopify_product = !empty($product_row['shopify_id']);
			$adding_local = !$shopify_product && $variant_id === '';

			if ($shopify_product && $variant_id !== ''
					&& in_array('shopify', $GLOBALS['config']['modules'] ?? [], true)){
				// Pull the current Shopify price onto the CMS item before create_order_line prices the line.
				$this->load->model('shopify/shopify_product_model');
				$this->shopify_product_model->refresh_product($product_id, 1, false);
			}

			// Every line is a storefront product item; no item means no line.
			if ($product_item_id <= 0 || ($shopify_product && $variant_id === '')){
				print(json_encode(['ok' => 0, 'error' => $params['missing_variant_error']]));
				exit();
			}

			if ($adding_local){
				// Same rule as cart_refresh_hold: other orders' holds vs this cart's qty plus the new qty.
				if (!$this->shop_dim_model->cart_add_fits($item_row, $qty, (int)$order['cms_page_panel_id'])){
					print(json_encode(['ok' => 0, 'error' => $params['stock_error']]));
					exit();
				}
				$this->shop_model->create_order_line($order['cms_page_panel_id'], [
						'product_item_id' => $product_item_id,
						'product_id' => $product_id,
						'quantity' => $qty,
						'dims' => $dims,
				]);
			} else {
				$this->shop_model->create_order_line($order['cms_page_panel_id'], [
						'product_id' => $product_id,
						'product_item_id' => $product_item_id,
						'quantity' => $qty,
						'attributes' => $attributes,
						'dims' => $dims,
				]);
			}

			$this->shop_dim_model->cart_refresh_hold($order['cms_page_panel_id']);
			$quantity = $this->shop_model->get_order_quantity($order['cms_page_panel_id']);
			print(json_encode([
					'ok' => 1,
					'quantity' => $quantity,
			]));
			exit();

		}

		if ($do == 'remove'){

			$order = $this->shop_model->get_current_order_if_any($user);
			$line_id = (int)($params['line_id'] ?? 0);
			if (!empty($order['cms_page_panel_id']) && $line_id){
				$this->shop_model->delete_order_line($order['cms_page_panel_id'], $line_id);
				$this->load->model('shop/shop_dim_model');
				$params['lost_item_ids'] = $this->shop_dim_model->cart_refresh_hold($order['cms_page_panel_id']);
			}
			// Fall through to panel_params for full re-render when open
			$params['cart_details'] = 1;

		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');

		$user = $this->shop_model->get_front_user();

		// Panel name saved in shop settings (from provides dropdown) — no module scan at cart time
		$params['checkout_panel'] = $this->shop_model->get_checkout_panel();
		$params['checkout_provider_missing'] = ($params['checkout_panel'] === '');

		// cart_details: 1 from open / checkout_check / remove, 0 for an explicit shell request.
		$want_details = isset($params['cart_details'])
				? (string)$params['cart_details'] === '1'
				: !empty($_SESSION['shop']['cart_visible']);

		// Header display needs only the quantity (one SUM). Lines load only for the popup details.
		$order = $this->shop_model->get_current_order_if_any($user);
		$order_id = (int)($order['cms_page_panel_id'] ?? 0);
		$quantity = $order_id > 0 ? $this->shop_model->get_order_quantity($order_id) : 0;
		$lines = ($want_details && $order_id > 0) ? $this->shop_model->get_order_lines($order_id) : [];

		// Definition: empty mobile badge uses the desktop one.
		$params['cart_label_mobile'] = $params['cart_label_mobile'] !== '' ? $params['cart_label_mobile'] : $params['cart_label'];
		$params['cart'] = [
				'number' => $quantity,
				'items' => null,
				'total' => '',
		];
		$params['cart_visible'] = !empty($_SESSION['shop']['cart_visible']);

		if (!$want_details){
			$params['cart_details'] = 0;
			return $params;
		}

		$this->load->model('shop/shop_dim_model');
		$params['cart_details'] = 1;
		$params['cart']['items'] = [];
		$total = 0.0;
		$total_currency = null;
		$mixed_currency = false;
		// Set by do=open / checkout_check / remove; absent on a plain render.
		$lost_item_ids = $params['lost_item_ids'] ?? [];
		$price_changed_lines = $params['price_changed_lines'] ?? [];

		foreach ($lines as $line){

			$qty = (int)$line['quantity'];
			$line_total = (float)$line['price'] * $qty;
			$currency_id = (int)$line['currency_id'];
			$currency = $currency_id > 0 ? $this->shop_model->get_currency($currency_id) : $this->shop_model->get_shop_currency();
			$currency_key = is_array($currency) ? (int)$currency['cms_page_panel_id'] : 0;
			if ($total_currency === null){
				$total_currency = $currency;
				$total = $line_total;
			} else if ((int)($total_currency['cms_page_panel_id'] ?? 0) === $currency_key){
				$total += $line_total;
			} else {
				$mixed_currency = true;
			}

			$dims = '';
			$warning = '';
			$item_id = $this->shop_model->order_line_item_id($line);
			if ($item_id > 0){
				$item = $this->cms_page_panel_model->get_cms_page_panel($item_id);
				$dims = $this->shop_dim_model->dims_description($item);
				if (in_array($item_id, $lost_item_ids, true)){
					$warning = str_replace('{{item}}', $this->shop_dim_model->item_list_title($item), $params['unavailable_item_warning']);
				}
			}

			// Stored attributes are the cleaned { key, value } list (clean_line_attributes).
			$attributes = [];
			foreach (($line['attributes'] ?? []) as $attribute){
				$attributes[] = [
						'key' => $attribute['key'],
						'value' => str_replace("\n", '|', $attribute['value']),
				];
			}

			$notice = '';
			$changed = $price_changed_lines[(int)$line['cms_page_panel_id']] ?? null;
			if ($changed){
				$notice = str_replace(['{{old}}', '{{new}}'], [$changed['old'], $changed['new']], $params['price_changed_notice']);
			}

			$params['cart']['items'][] = [
					'line_id' => (int)$line['cms_page_panel_id'],
					'heading' => $line['item'],
					'image' => $line['image'],
					'number' => $qty,
					'amount' => $this->shop_model->format_amount($line_total, $currency),
					'dims' => $dims,
					'attributes' => $attributes,
					'description' => ($dims === '' && !$attributes) ? (string)$line['description'] : '',
					'notice' => $notice,
					'warning' => $warning,
			];

		}

		$params['cart']['total'] = $mixed_currency ? '' : $this->shop_model->format_amount($total, $total_currency);

		return $params;

	}

}
