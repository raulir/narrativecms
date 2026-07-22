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
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_page_panel_model');

		$user = $this->user_model->get_current();
		if (empty($user)){
			$user = [];
		}

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
			$variant_id = $params['shopify_variant_id'] ?? $this->input->post('shopify_variant_id') ?? '';
			$merchandise_id = $params['merchandise_id'] ?? $this->input->post('merchandise_id') ?? '';
			$qty = max(1, (int)($params['quantity'] ?? $params['qty'] ?? $this->input->post('quantity') ?? 1));
			$expected_price = $params['expected_price'] ?? $this->input->post('expected_price') ?? '';
			$item = $params['item'] ?? $this->input->post('item') ?? '';
			$image = $params['image'] ?? $this->input->post('image') ?? '';

			if ($product_id && ($item === '' || $image === '')){
				$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
				if ($item === ''){
					$item = $product['heading'] ?? '';
				}
				if ($image === ''){
					$image = $product['image'] ?? '';
				}
			}

			if ($variant_id === '' && $merchandise_id === '' && empty($params['product_item_id'])){
				print(json_encode(['ok' => 0, 'error' => 'Missing product variant']));
				exit();
			}

			if (!empty($params['product_item_id']) || $this->input->post('product_item_id')){
				$this->shop_model->create_order_line($order['cms_page_panel_id'], [
						'product_item_id' => $params['product_item_id'] ?? $this->input->post('product_item_id'),
				]);
			} else {
				$this->shop_model->create_order_line($order['cms_page_panel_id'], [
						'product_id' => $product_id,
						'shopify_variant_id' => $variant_id,
						'merchandise_id' => $merchandise_id,
						'qty' => $qty,
						'expected_price' => $expected_price,
						'price' => $expected_price,
						'attributes' => $attributes,
						'item' => $item,
						'image' => $image,
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
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_page_panel_model');

		$user = $this->user_model->get_current();
		if (empty($user)){
			$user = [];
		}

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

		$params['cart'] = [
				'number' => $quantity,
				'items' => null,
				'total' => '',
				'checkout_url' => '',
		];

		$params['cart']['number_text'] = str_replace(
				'{{number}}',
				'<div class="cart_quantity">'.$quantity.'</div>',
				$params['cart_label']
		);

		$params['cart_visible'] = !empty($_SESSION['shop']['cart_visible']);
		$params['empty_label'] = $params['empty_label'] ?? 'Please add items to cart';

		if ($want_details){

			$params['cart_details'] = 1;
			$params['cart']['items'] = [];
			$total = 0.0;
			$prefix = $params['currency_prefix'] ?? '';

			foreach($lines as $line){

				$qty = (int)($line['qty'] ?? $line['quantity'] ?? 1);
				$unit = (float)($line['price'] ?? $line['expected_price'] ?? 0);
				$line_total = $unit * $qty;
				$total += $line_total;

				$text = $line['description'] ?? '';
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
						$text = '<div class="cart_popup_item_customisation">'.
								($params['customisation_label'] ?? 'Customisation').'<br>'.
								implode('<br>', $bits).'</div>';
					}
				} else if ($text !== ''){
					$text = '<div class="cart_popup_item_customisation">'.
							($params['customisation_label'] ?? 'Customisation').'<br>'.
							nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')).'</div>';
				}

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
