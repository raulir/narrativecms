<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class productbuy extends CI_Controller{
	
	function panel_action($params){

		$this->load->model('shop/shop_model');
		$this->load->model('user/user_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$do = $this->input->post('do');
		
		if ($do == 'add'){
				
			$params['product_id'] = $this->input->post('product_id');
		
			// get current basket
			$user = $this->user_model->get_current();
			if (empty($user)) $user = [];
	
			$order = $this->shop_model->get_current_order($user);
			$product = $this->cms_page_panel_model->get_cms_page_panel($params['product_id']);
			if (!empty($product['product_stock_id'])){
				$product_stock_group = $this->cms_page_panel_model->get_cms_page_panel($product['product_stock_id']);
			}
			
			// if stock control individual
			if (!empty($product_stock_group['stock_control']) && $product_stock_group['stock_control'] == 'individual'){
			
				$variations = $this->shop_model->get_product_variations($params['product_id'], ['exclude_order_id' => $order['cms_page_panel_id']]);
				
				if(!empty($variations)){
					
					$dim = [];
					$params['errors'] = [];
					foreach($variations as $key => $variation){
						$dim[$key] = $this->input->post($key);
						if (empty($dim[$key])){
							$params['errors'][$key] = $params['select_error'].' '.$this->shop_model->get_dimension_label($key);
						}
					}
					
					if (empty($params['errors'])){

						$product_items = $this->cms_page_panel_model->get_list('cg/product_item', ['product_id' => $params['product_id'], 'order_id' => [0, '']]);
						$exclude_lines = $this->cms_page_panel_model->get_list('cg/order_line', ['order_id' => $order['cms_page_panel_id']]);
						
						$exclude_items = [];
						foreach($exclude_lines as $line){
							$exclude_items[] = $line['ref_id'];
						}
						
						// get available item
						$return_items = [];
						foreach($product_items as $item){
								
							if (!in_array($item['cms_page_panel_id'], $exclude_items)){
								
								$suitable = true;
								foreach($item['dimensions'] as $variation){
									list($dimension_name, $dimension_value_id) = explode('=', $variation['value']);
									if ($params[$dimension_name] != $dimension_value_id){
										$suitable = false;
									}
								}
								
								if ($suitable){
									
									$return_items[] = $item;
									
								}
								
							}
						}

						$items = [];
						foreach($variations as $key => $variation){
							$items[$key] = $variation[$dim[$key]]['items'];
							$available = $variation[$dim[$key]]['items'];
						}
						
						foreach($items as $test){
							$available = array_intersect($available, $test);
						}
						
						if (!empty($return_items)){

							$product_item = reset($return_items);
		
							// add line
							$this->shop_model->create_order_line($order['cms_page_panel_id'], ['product_item_id' => $product_item['cms_page_panel_id'], ]);
		
							$params['success'] = 1;
							
						} else {
							
							$params['success'] = 0;
						
						}
		
					}
					
				}
				
			} else if (!empty($product_stock_group)){
				
				$params['errors'] = [];
 				
				$dims = [];
				foreach($product_stock_group['dimensions'] as $dimension){
					$dim_data = $this->cms_page_panel_model->get_cms_page_panel($dimension['dimension']);
					$dims[$dim_data['id']] = $params[$dim_data['id']];
				}
				
				// by default let user add unlimited items
				
				$variations = $this->shop_model->get_product_variations($params['product_id'], ['dimensions' => $dims, ]);
				
				// check if all dimensions submitted
				foreach($dims as $dim_id => $dim_value){
					
					if (empty($dim_value)){
						$params['errors'][$dim_id] = $params['select_error'].' '.$this->shop_model->get_dimension_label($dim_id);
					}
					
				}
				
				// then check if available
				if (empty($params['errors'])){
					foreach($dims as $dim_id => $dim_value){
					
						if (empty($variations[$dim_id])){
							$params['errors'][$dim_id] = $params['select_error'].' '.$this->shop_model->get_dimension_label($dim_id);
						}
					
					}
				}
				
				if (empty($params['errors'])){

					if(!empty($variations)){

						// get available item
						$items = [];
						foreach($variations as $key => $variation){
							$items[$key] = $variation[$dims[$key]]['items'];
							if (empty($available)){
								$available = $variation[$dims[$key]]['items'];
							} else {
								$available = array_intersect($available, $variation[$dims[$key]]['items']);
							}
						}
						
						$product_item_id = array_key_first($available);
		
						// add line
						$this->shop_model->create_order_line($order['cms_page_panel_id'], ['product_item_id' => $product_item_id, ]);
		
						$params['success'] = 1;
	
					} else {
						
						// if no dimensions
						$items = $this->cms_page_panel_model->get_list('stock/product_item', ['product_id' => $params['product_id']]);

						$item = reset($items);

						$this->shop_model->create_order_line($order['cms_page_panel_id'], ['product_item_id' => $item['cms_page_panel_id'], ]);
						
						$params['success'] = 1;
						
					}
				
				}
				
			}

		}
		
		return $params;
		
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
		if (!empty($product['product_stock_id'])){
			$product_stock_group = $this->cms_page_panel_model->get_cms_page_panel($product['product_stock_id']);
		}
			
		// if stock control limited
		if (!empty($product_stock_group['stock_control']) && $product_stock_group['stock_control'] == 'individual'){
			$params['variations'] = $this->shop_model->get_product_variations($params['product_id'], ['exclude_order_id' => $order['cms_page_panel_id']]);
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
