<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class productdimensions extends CI_Controller{
	
	function panel_action($params){

		$this->load->model('shop/shop_model');
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_page_panel_model');

		$do = $this->input->post('do');

		if ($do == 'availability'){

			$params['product_id'] = $this->input->post('product_id');
			$params['name'] = $this->input->post('name');
			$params['value'] = $this->input->post('value');
			
			// get current basket
			$user = $this->user_model->get_current();
			if (empty($user)) $user = [];
			
			$order = $this->shop_model->get_current_order($user);
			
			$params['product'] = $this->cms_page_panel_model->get_cms_page_panel($params['product_id']);
			if (!empty($params['product']['product_stock_id'])){
				$product_stock_group = $this->cms_page_panel_model->get_cms_page_panel($params['product']['product_stock_id']);
			}
				
			// if stock control limited
			if (!empty($product_stock_group['stock_control']) && $product_stock_group['stock_control'] == 'individual'){
				$params['variations'] = $this->shop_model->get_product_variations($params['product_id'],
						['exclude_order_id' => $order['cms_page_panel_id'], 'dimension_name' => $params['name'], 'dimension_value' => $params['value']]);
			} else {
				$params['variations'] = $this->shop_model->get_product_variations($params['product_id'],
						['dimension_name' => $params['name'], 'dimension_value' => $params['value']]);
			}
			
			print(json_encode(['data' => $params['variations']], JSON_PRETTY_PRINT));
			
			die();

		}
		
	}

	function panel_params($params){

		$this->load->model('shop/shop_model');
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_page_panel_model');
		
		// get current basket
		$user = $this->user_model->get_current();
		if (empty($user)) $user = [];

		$order = $this->shop_model->get_current_order($user);
		
		$params['product'] = $this->cms_page_panel_model->get_cms_page_panel($params['product_id']);
		if (!empty($params['product']['product_stock_id'])){
			$product_stock_group = $this->cms_page_panel_model->get_cms_page_panel($params['product']['product_stock_id']);
		}

		// if stock control limited
		if (!empty($product_stock_group['stock_control']) && $product_stock_group['stock_control'] == 'individual'){
			$params['variations'] = $this->shop_model->get_product_variations($params['product_id'], 
					['exclude_order_id' => $order['cms_page_panel_id']]);
		} else {
			$params['variations'] = $this->shop_model->get_product_variations($params['product_id']);
			if (empty($params['variations'])){
				$params['one_size'] = true;
			}
		}

		$params['dimension_labels'] = [];
		
		foreach($params['variations'] as $key => $data){
			
			$params['dimension_labels'][$key] = $this->shop_model->get_dimension_label($key);
			
			// price
			$min = 0;
			$max = 0;
			foreach($data as $dim_data){
				foreach($data as $dim_data){
					foreach($dim_data['items'] as $price){
						if ($price < $min || $min == 0 ){
							$min = $price;
						}
						if ($price > $max){
							$max = $price;
						}
					}
				}
			}
			
			$params['prices'][$key] = ['min' => $min, 'max' => $max, ];
			
		}
		
		$params['in_basket'] = $this->shop_model->is_product_in_basket($params['product_id'], $order['cms_page_panel_id']);

		return $params;
		
	}
	
}
