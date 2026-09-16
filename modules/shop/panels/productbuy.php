<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class productbuy extends \Controller{

	function panel_action($params){

		$this->load->model('shop/shop_model');
		$this->load->model('shop/shop_dim_model');
		$this->load->model('cms/cms_page_panel_model');

		$do = $this->input->post('do');

		if ($do == 'add'){

			$params['product_id'] = $this->input->post('product_id');

			$user = $this->shop_model->get_front_user();
			$order = $this->shop_model->get_current_order($user);
			$product = $this->cms_page_panel_model->get_cms_page_panel($params['product_id']);
			$type = $this->shop_dim_model->resolve_product_type($product);

			if ($this->shop_dim_model->cart_source_conflict($order, true)){
				$params['success'] = 0;
				$params['errors'] = ['_cart' => $params['mixed_source_error'] ?? 'Cart can\'t contain mixed source items'];
				return $params;
			}

			$dims_map = [];
			foreach ($type['dims'] as $row){
				$did = (int)($row['product_dim_id'] ?? 0);
				if ($did <= 0){
					continue;
				}
				$dim = $this->cms_page_panel_model->get_cms_page_panel($did);
				$slug = (string)($dim['id'] ?? '');
				$posted = $this->input->post($slug);
				if ($posted === null || $posted === ''){
					$posted = $this->input->post((string)$did);
				}
				if ($posted === null || $posted === ''){
					$params['errors'][$slug !== '' ? $slug : $did] = ($params['select_error'] ?? 'Please select').' '.
							$this->shop_dim_model->get_dim_label($did);
					continue;
				}
				$dims_map[$did] = $posted;
			}

			if (!empty($params['errors'])){
				$params['success'] = 0;
				return $params;
			}

			$qty = max(1, (int)$this->input->post('quantity') ?: 1);
			$item_id = 0;

			if (($type['stock_control'] ?? 'none') === 'count'){
				$master = $this->shop_dim_model->find_master_item($params['product_id'], $dims_map);
				if (empty($master['cms_page_panel_id'])){
					$params['success'] = 0;
					$params['errors']['_stock'] = $params['stock_error_heading'] ?? 'This product is sold out';
					return $params;
				}
				if (!$this->shop_dim_model->type_allows_negative($type)){
					$avail = $this->shop_dim_model->master_available($master, (int)$order['cms_page_panel_id']);
					if ($avail < $qty){
						$params['success'] = 0;
						$params['errors']['_stock'] = $params['stock_error_heading'] ?? 'This product is sold out';
						return $params;
					}
				}
				$item_id = (int)$master['cms_page_panel_id'];
			}

			$this->shop_model->create_order_line($order['cms_page_panel_id'], [
					'product_item_id' => $item_id,
					'product_id' => $params['product_id'],
					'qty' => $qty,
					'dims' => $dims_map,
			]);
			$params['success'] = 1;

		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('shop/shop_model');
		$this->load->model('shop/shop_dim_model');
		$this->load->model('cms/cms_page_panel_model');

		$user = $this->shop_model->get_front_user();
		$order = $this->shop_model->get_current_order($user);

		$params['product'] = $this->cms_page_panel_model->get_cms_page_panel($params['product_id']);
		$type = $this->shop_dim_model->resolve_product_type($params['product']);

		$params['variations'] = $this->shop_model->get_product_variations($params['product_id']);
		if (empty($type['dims'])){
			if (($type['stock_control'] ?? 'none') === 'count'){
				$params['one_size'] = !empty($this->shop_dim_model->get_master_items($params['product_id']));
			} else {
				$params['one_size'] = true;
			}
		}

		$params['dim_labels'] = [];
		foreach ($params['variations'] as $key => $data){
			$params['dim_labels'][$key] = $this->shop_model->get_dim_label($key);
		}

		$params['in_basket'] = $this->shop_model->is_product_in_basket($params['product_id'], $order['cms_page_panel_id']);

		return $params;

	}

}
