<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Checkout handoff + status reconcile.
 * - do=shop_checkout: push local lines → Shopify (reuse cart id when open)
 * - do=reconcile: if remote cart dead after pay → close CMS order / empty site cart
 * Lines never flow Shopify → site.
 */
class checkout extends \Controller{

	function panel_action($params){

		$do = $params['do'] ?? $this->input->post('do');

		if ($do == 'shop_checkout' || $do == 'checkout'){

			$this->load->model('shop/shop_model');
			$this->load->model('shopify/shopify_product_model');
			$this->load->model('user/user_model');

			$user = $this->user_model->get_current();
			if (empty($user)){
				$user = [];
			}

			$order = $this->shop_model->get_current_order_if_any($user);
			if (empty($order['cms_page_panel_id'])){
				print(json_encode(['ok' => 0, 'error' => 'Cart is empty']));
				exit();
			}

			// If previous remote cart is already dead, close site cart instead of reusing
			if (!empty($order['shopify_cart_id'])){
				$remote = $this->shopify_product_model->_storefront_cart_query($order['shopify_cart_id'], false);
				if (empty($remote['id'])){
					$this->shop_model->close_cart_order($order['cms_page_panel_id'], 'paid');
					print(json_encode([
							'ok' => 0,
							'error' => 'Previous checkout completed. Your cart was cleared — add items again.',
							'changed' => 1,
							'quantity' => 0,
							'closed' => 1,
					]));
					exit();
				}
			}

			$result = $this->shopify_product_model->materialise_checkout_from_order($order['cms_page_panel_id']);
			print(json_encode($result));
			exit();

		}

		if ($do == 'reconcile'){

			$this->load->model('shop/shop_model');
			$this->load->model('shopify/shopify_product_model');
			$this->load->model('user/user_model');

			$user = $this->user_model->get_current();
			if (empty($user)){
				$user = [];
			}

			$order = $this->shop_model->get_current_order_if_any($user);
			if (empty($order['cms_page_panel_id']) || empty($order['shopify_cart_id'])){
				print(json_encode(['ok' => 1, 'changed' => 0]));
				exit();
			}

			$result = $this->shopify_product_model->reconcile_checkout_status($order);
			print(json_encode($result));
			exit();

		}

		return $params;

	}

	function panel_params($params){

		$params['message'] = $params['message'] ?? 'Redirecting to checkout…';
		return $params;

	}

}
