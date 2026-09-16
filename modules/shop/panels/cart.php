<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Basic cart: local order draft (cookie cart_key), badge + popup.
 * Checkout: if a module provides shop_checkout, hand off there; else shop/checkout.
 */
class cart extends \Controller{

	function panel_action($params){

		$do = $params['do'] ?? $this->input->post('do');
		if (empty($do)){
			return $params;
		}

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');

		$user = $this->shop_model->get_front_user();

		if ($do == 'set_visible'){
			$_SESSION['shop']['cart_visible'] = $params['value'] ?? $this->input->post('value');
			print(json_encode(['ok' => '1']));
			exit();
		}

		if ($do == 'add'){

			$order = $this->shop_model->get_current_order($user);

			$attributes = $params['attributes'] ?? $this->input->post('attributes');
			if (is_string($attributes) && $attributes !== ''){
				$decoded = json_decode($attributes, true);
				$attributes = is_array($decoded) ? $decoded : [];
			}
			if (!is_array($attributes)){
				$attributes = [];
			}

			$product_id = (int)($params['product_id'] ?? $this->input->post('product_id') ?? 0);
			$qty = max(1, (int)($params['quantity'] ?? $params['qty'] ?? $this->input->post('quantity') ?? 1));
			$expected_price = $params['expected_price'] ?? $this->input->post('expected_price') ?? '';
			$item = $params['item'] ?? $this->input->post('item') ?? '';
			$image = $params['image'] ?? $this->input->post('image') ?? '';
			$product_item_id = (int)($params['product_item_id'] ?? $this->input->post('product_item_id') ?? 0);

			$dims = $params['dims'] ?? $this->input->post('dims') ?? [];
			if (is_string($dims) && $dims !== ''){
				$decoded = json_decode($dims, true);
				$dims = is_array($decoded) ? $decoded : [];
			}
			if (!is_array($dims)){
				$dims = [];
			}

			$this->load->model('shop/shop_dim_model');
			$item_row = $this->shop_dim_model->resolve_cart_item($product_id, $product_item_id, $dims);
			if (!empty($item_row['cms_page_panel_id'])){
				$product_item_id = (int)$item_row['cms_page_panel_id'];
				if ($product_id <= 0){
					$product_id = (int)($item_row['product_id'] ?? 0);
				}
			} else {
				$item_row = [];
				$product_item_id = 0;
			}

			$product_row = $product_id > 0 ? $this->cms_page_panel_model->get_cms_page_panel($product_id) : [];
			$variant_id = (string)($item_row['shopify_variant_id'] ?? '');
			$shopify_product = !empty($product_row['shopify_id']);
			$adding_local = !$shopify_product && $variant_id === '';

			if ($expected_price === '' && !empty($item_row['price'])){
				$expected_price = $item_row['price'];
			}

			if ($shopify_product && $variant_id !== ''
					&& in_array('shopify', $GLOBALS['config']['modules'] ?? [], true)){
				$this->load->model('shopify/shopify_product_model');
				$refreshed = $this->shopify_product_model->refresh_product($product_id, 1, false);
				$live_price = $this->shopify_product_model->variant_price_from_product(
						is_array($refreshed) ? $refreshed : [],
						$variant_id,
						''
				);
				if ($live_price !== null && $live_price !== ''){
					$expected_price = $live_price;
				}
			}

			if ($product_id && ($item === '' || $image === '')){
				if ($item === ''){
					$item = $product_row['heading'] ?? '';
				}
				if ($image === ''){
					$image = $product_row['image'] ?? '';
				}
			}

			$mixed_error = $params['mixed_source_error'] ?? 'Cart can\'t contain mixed source items';
			$stock_error = $params['stock_error'] ?? 'Not enough in stock';
			$missing_error = $params['missing_variant_error'] ?? 'Missing product variant';

			if ($this->shop_dim_model->cart_source_conflict($order, $adding_local)){
				print(json_encode(['ok' => 0, 'error' => $mixed_error]));
				exit();
			}

			if ($shopify_product && ($product_item_id <= 0 || $variant_id === '')){
				print(json_encode(['ok' => 0, 'error' => $missing_error]));
				exit();
			}

			if ($adding_local){
				$product = is_array($product_row) ? $product_row : [];
				$type = $this->shop_dim_model->resolve_product_type($product);
				if (($type['stock_control'] ?? 'none') === 'count'){
					if ($product_item_id <= 0){
						print(json_encode(['ok' => 0, 'error' => $stock_error]));
						exit();
					}
					if (!$this->shop_dim_model->type_allows_negative($type)){
						$avail = $this->shop_dim_model->master_available($item_row, (int)$order['cms_page_panel_id']);
						if ($avail < $qty){
							print(json_encode(['ok' => 0, 'error' => $stock_error]));
							exit();
						}
					}
				}
				$this->shop_model->create_order_line($order['cms_page_panel_id'], [
						'product_item_id' => $product_item_id,
						'product_id' => $product_id,
						'qty' => $qty,
						'item' => $item,
						'image' => $image,
						'dims' => $dims,
				]);
			} else {
				$this->shop_model->create_order_line($order['cms_page_panel_id'], [
						'product_id' => $product_id,
						'product_item_id' => $product_item_id,
						'qty' => $qty,
						'expected_price' => $expected_price,
						'price' => $expected_price,
						'attributes' => $attributes,
						'item' => $item,
						'image' => $image,
						'dims' => $dims,
				]);
			}

			$quantity = $this->shop_model->get_order_quantity($order['cms_page_panel_id']);
			print(json_encode([
					'ok' => 1,
					'quantity' => $quantity,
			]));
			exit();

		}

		if ($do == 'remove'){

			$order = $this->shop_model->get_current_order_if_any($user);
			$line_id = (int)($params['item_id'] ?? $params['line_id'] ?? $this->input->post('item_id') ?? 0);
			if (!empty($order['cms_page_panel_id']) && $line_id){
				$this->shop_model->delete_order_line($order['cms_page_panel_id'], $line_id);
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

		$want_details = !empty($params['cart_details'])
				|| !empty($_SESSION['shop']['cart_visible']);

		// Explicit shell request
		if (isset($params['cart_details']) && (string)$params['cart_details'] === '0'){
			$want_details = false;
		}

		$order = $this->shop_model->get_current_order_if_any($user);
		$quantity = 0;
		$lines = [];
		if (!empty($order['cms_page_panel_id'])){
			$lines = $this->shop_model->get_order_lines($order['cms_page_panel_id']);
			$quantity = $this->shop_model->get_order_quantity($order['cms_page_panel_id']);
		}

		$cart_source = '';
		if ($lines){
			$this->load->model('shop/shop_dim_model');
			foreach ($lines as $line){
				if ($this->shop_dim_model->cart_line_is_shopify($line)){
					$cart_source = 'shopify';
					break;
				}
				if ($this->shop_dim_model->cart_line_is_local($line)){
					$cart_source = 'local';
				}
			}
		}
		$params['cart_source'] = $cart_source;

		$params['cart'] = [
				'number' => $quantity,
				'items' => null,
				'total' => '',
				'checkout_url' => '',
		];

		$qty_html = '<div class="cart_quantity">'.$quantity.'</div>';
		$params['cart']['number_text'] = str_replace('{{number}}', $qty_html, $params['cart_label']);
		$mobile_label = trim((string)($params['cart_label_mobile'] ?? ''));
		if ($mobile_label === ''){
			$mobile_label = $params['cart_label'];
		}
		$params['cart']['number_text_mobile'] = str_replace('{{number}}', $qty_html, $mobile_label);

		$params['cart_visible'] = !empty($_SESSION['shop']['cart_visible']);
		$params['empty_label'] = $params['empty_label'] ?? 'Please add items to cart';

		if ($want_details){

			$this->load->model('shop/shop_dim_model');
			$params['cart_details'] = 1;
			$params['cart']['items'] = [];
			$total = 0.0;
			$prefix = $params['currency_prefix'] ?? '';

			foreach($lines as $line){

				$qty = (int)($line['qty'] ?? $line['quantity'] ?? 1);
				$unit = (float)($line['price'] ?? $line['expected_price'] ?? 0);
				$line_total = $unit * $qty;
				$total += $line_total;

				$blocks = [];
				$dim_text = '';
				$item_id = $this->shop_model->order_line_item_id($line);
				if ($item_id > 0){
					$ref = $this->cms_page_panel_model->get_cms_page_panel($item_id);
					if (($ref['panel_name'] ?? '') === 'shop/product_item'){
						$dim_text = $this->shop_dim_model->dims_description($ref);
					}
				}
				if ($dim_text !== ''){
					$blocks[] = '<div class="cart_popup_item_dims">'.
							htmlspecialchars($dim_text, ENT_QUOTES, 'UTF-8').'</div>';
				}

				if (!empty($line['attributes']) && is_array($line['attributes'])){
					$bits = [];
					foreach($line['attributes'] as $k => $v){
						if (is_array($v) && isset($v['key'])){
							$bits[] = ($v['key'] ?? '').': '.str_replace("\n", '|', trim($v['value'] ?? ''));
						} else if (!is_array($v)){
							$bits[] = $k.': '.str_replace("\n", '|', trim((string)$v));
						}
					}
					if ($bits){
						$blocks[] = '<div class="cart_popup_item_customisation">'.
								($params['customisation_label'] ?? 'Customisation').'<br>'.
								implode('<br>', $bits).'</div>';
					}
				} else if ($dim_text === '' && !empty($line['description'])){
					$blocks[] = '<div class="cart_popup_item_customisation">'.
							nl2br(htmlspecialchars((string)$line['description'], ENT_QUOTES, 'UTF-8')).'</div>';
				}

				$text = implode('', $blocks);

				$params['cart']['items'][] = [
						'heading' => $line['item'] ?? '',
						'text' => $text,
						'line_id' => $line['cms_page_panel_id'],
						'amount' => trim($prefix.' '.number_format($line_total, 2)),
						'image' => $line['image'] ?? '',
						'number' => $qty,
				];

			}

			$params['cart']['total'] = trim($prefix.' '.number_format($total, 2));

		} else {
			$params['cart_details'] = 0;
		}

		return $params;

	}

}
