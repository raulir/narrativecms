<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class basket extends \Controller{

	function panel_action($params){
				
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');
		
		if (empty($params['cms_page_panel_id'])){
			$params = array_merge_recursive_ex($params, $this->cms_page_panel_model->get_cms_page_panel($this->input->post('id')));
		}
		
		$user = $this->shop_model->get_front_user();
		
		$do = $this->input->post('do');

		if ($do == 'remove'){
			
			$order = $this->shop_model->get_current_order($user);
			$this->shop_model->delete_order_line($order['cms_page_panel_id'], $params['item_id']);

		}

		return $params;
		
	}

	function panel_params($params){

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');
		
		// get current basket
		$user = $this->shop_model->get_front_user();

		$params['order'] = $this->shop_model->get_current_order($user);
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $params['order']['cms_page_panel_id']]);
		
		$refs = [];
		foreach($lines as $item){
			$rid = $this->shop_model->order_line_ref_id($item);
			if ($rid){
				$refs[$rid] = $this->cms_page_panel_model->get_cms_page_panel($rid);
			}
		}
		
		$products = [];
		foreach($refs as $item){
			if (!empty($item['product_id'])){
				$products[$item['product_id']] = $this->cms_page_panel_model->get_cms_page_panel($item['product_id']);
			}
		}
		
		$params['items'] = [];
		$this->load->model('shop/shop_dim_model');
		foreach($lines as $line){

			$rid = $this->shop_model->order_line_ref_id($line);
			$ref = $refs[$rid] ?? [];
			$dims_source = [];
			$product = [];

			if (($ref['panel_name'] ?? '') == 'shop/product_item'){
				$dims_source = $ref;
				$pid = (int)($ref['product_id'] ?? 0);
				$product = $products[$pid] ?? ($pid ? $this->cms_page_panel_model->get_cms_page_panel($pid) : []);
			} else if ((($line['line_type'] ?? '') === 'local_item') && !empty($line['product_id'])){
				$pid = (int)$line['product_id'];
				$product = $products[$pid] ?? $this->cms_page_panel_model->get_cms_page_panel($pid);
				$item_id = $this->shop_model->order_line_item_id($line);
				if ($item_id){
					$dims_source = $this->cms_page_panel_model->get_cms_page_panel($item_id);
				}
			} else {
				continue;
			}

			$dims_out = [];
			foreach ($this->shop_dim_model->item_dims_map($dims_source) as $did => $dvalue){
				$dims_out[] = [
						'label' => $this->shop_dim_model->get_dim_label($did),
						'value' => $this->shop_dim_model->get_dim_value_data($did, $dvalue)['label'],
				];
			}

			$params['items'][$line['cms_page_panel_id']] = [
				'image' => $product['image'] ?? '',
				'heading' => $product['heading'] ?? ($line['item'] ?? ''),
				'description' => $product['text'] ?? '',
				'price' => $line['price'],
				'dims' => $dims_out,
				'item_id' => $line['cms_page_panel_id'],
				'product_id' => $product['cms_page_panel_id'] ?? ($line['product_id'] ?? 0),
			];

		}

		return $params;
	
	}
	
}
