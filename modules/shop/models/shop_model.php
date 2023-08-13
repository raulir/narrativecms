<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shop_model extends Model {

	function get_product_variations($product_id, $params = []){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		if (!empty($product['product_stock_id'])){
			$product_stock_group = $this->cms_page_panel_model->get_cms_page_panel($product['product_stock_id']);
		}
		
		$exlude_items = [];
			
		// if stock control limited
		if (!empty($product_stock_group['stock_control']) && $product_stock_group['stock_control'] == 'individual'){
			$product_items = $this->cms_page_panel_model->get_list('stock/product_item', ['product_id' => $product_id, 'order_id' => [0, '']]);
			if (!empty($params['exclude_order_id'])){
				$exclude_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $params['exclude_order_id']]);
				foreach($exclude_lines as $line){
					
					$exlude_items[] = $line['ref_id'];
					
				}
			}
		} else {
			$product_items = $this->cms_page_panel_model->get_list('stock/product_item', ['product_id' => $product_id]);
		}
		
		// filter out if limited to certain dimension value
		if (!empty($params['dimension_name'])){
			
			$params['dimensions'] = [
					$params['dimension_name'] => $params['dimension_value'],
			];
			
		}
		
		if (!empty($params['dimensions'])){

			foreach($product_items as $item_key => $item){

				$found = 0;

				foreach($params['dimensions'] as $dim_name => $dim_value){
					foreach($item['dimensions'] as $variation){
						if ($variation['value'] == ($dim_name . '=' . $dim_value)){
							$found += 1;
						}
					}
				}

				if ($found < count($params['dimensions'])){
					unset($product_items[$item_key]);
				}
			
			}
			
		}

		$return = [];
		foreach($product_items as $item){
			
			if (!in_array($item['cms_page_panel_id'], $exlude_items)){
			
				foreach($item['dimensions'] as $variation){
					
					list($dimension_name, $dimension_value_id) = explode('=', $variation['value']);
					
					if (empty($return[$dimension_name])){
						$return[$dimension_name] = [];
					}
					
					if (empty($return[$dimension_name][$dimension_value_id])){
						$return[$dimension_name][$dimension_value_id] = [
								'data' => $this->get_dimension_value_data($dimension_name, $dimension_value_id),
								'count' => 0,
								'items' => [],
						];
					}
					
					$return[$dimension_name][$dimension_value_id]['count'] += 1;
					$return[$dimension_name][$dimension_value_id]['items'][$item['cms_page_panel_id']] = 
							!empty($item['price']) ? $item['price'] : $product['price'];
					
					// fill in availability, go again over variations
					if (empty($return[$dimension_name][$dimension_value_id]['availability'])){
						$return[$dimension_name][$dimension_value_id]['availability'] = [];
					}
					foreach($item['dimensions'] as $avail_var){
						
						if ($avail_var['value'] != $variation['value']){
							list($avail_var_name, $avail_var_value_id) = explode('=', $avail_var['value']);
							$return[$dimension_name][$dimension_value_id]['availability'][$avail_var_name][$avail_var_value_id] = true;
						}
					
					}
					
				}
			
			}
			
		}
		
		foreach($return as &$dimension_data){
			ksort($dimension_data);
		}

		return $return;

	}

	function get_dimension_value_data($dimension_name, $dimension_value_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$dimensions = $this->cms_page_panel_model->get_list('stock/product_dimension', ['id' => $dimension_name]);
		
		$return = ['label' => '[no value]', 'description' => ''];
		foreach($dimensions as $dimension){
			foreach($dimension['values'] as $value){
				if ($value['id'] == $dimension_value_id){
					$return = ['label' => $value['label'], 'description' => $value['description']];
				}
			}
		}
		
		return $return;
		
	}
	
	function get_dimension_label($dimension_name){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$dimensions = $this->cms_page_panel_model->get_list('stock/product_dimension', ['id' => $dimension_name]);
		
		$return = '[no value]';
		foreach($dimensions as $dimension){
			$return = $dimension['heading'];
		}
	
		return $return;
		
	}
	
	function get_current_order($user){
		
		$this->load->model('cms/cms_page_panel_model');
		
		if(!empty($user['user_id'])){
			
			$orders = $this->cms_page_panel_model->get_list('shop/order', [
					'user_id' => $user['user_id'], 
					'status' => '', 
			]);
			$order = reset($orders);

		}
		
		// if different order in session
		if (!empty($order) && !empty($_SESSION['order_id']) && $order['cms_page_panel_id'] != $_SESSION['order_id']){
			
			$account_refs = [];
			
			$account_order_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order['cms_page_panel_id']]);
			
			foreach($account_order_lines as $line){
			
				if(!empty($line['ref_id'])){
					$account_refs[] = $line['ref_id'];
				}
			
			}

			$session_order_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $_SESSION['order_id']]);
				
			foreach($session_order_lines as $line){
					
				if(!empty($line['ref_id']) && !in_array($line['ref_id'], $account_refs)){
					$this->cms_page_panel_model->update_cms_page_panel($line['cms_page_panel_id'], ['order_id' => $order['cms_page_panel_id']]);
				}
					
			}
			
			$this->delete_order($_SESSION['order_id']);
			
			$_SESSION['order_id'] = $order['cms_page_panel_id'];

		}

		// if we can assign full anonymous order
		if(empty($order) && !empty($_SESSION['order_id']) && !empty($user['user_id'])){
			
			$this->cms_page_panel_model->update_cms_page_panel($_SESSION['order_id'], ['user_id' => $user['user_id']]);
			
			$order = $this->cms_page_panel_model->get_cms_page_panel($_SESSION['order_id']);
			
			if ($order['status'] != ''){
				unset($order);
				unset($_SESSION['order_id']);
			}

		}
		
		// if we have to use existing anonymous order because we are not signed in
		if(empty($order) && !empty($_SESSION['order_id'])){
			$order = $this->cms_page_panel_model->get_cms_page_panel($_SESSION['order_id']);
			if (!empty($order['user_id'])){
				unset($order);
			}
		}
		
// _print_r($order);
		
		if (empty($order) || $order['status'] != ''){
		
			$order = [
					'panel_name' => 'shop/order',
					'show' => 1,
					'sort' => 'first',
					'number' => '',
					'status' => '',
					'paid_time' => '',
					'last_result' => '',
					'user_id' => !empty($user['user_id']) ? $user['user_id'] : '',
			];
				
			$order_id = $this->cms_page_panel_model->create_cms_page_panel($order);
		
			$order_number = substr(md5($order_id), 0, 8);
		
			$this->cms_page_panel_model->update_cms_page_panel($order_id, ['number' => $order_number]);
		
			$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
			
			$_SESSION['order_id'] = $order_id;
		
		}
		
		return $order;
		
	}
	
	function create_order_line($order_id, $params){
// _print_r($params);		
		$this->load->model('cms/cms_page_panel_model');
		
		$ref_id = 0;
		
		if(!empty($params['product_item_id'])){
			
			$product_item = $this->cms_page_panel_model->get_cms_page_panel($params['product_item_id']);
			if ($product_item['panel_name'] == 'stock/product_item'){
				$product = $this->cms_page_panel_model->get_cms_page_panel($product_item['product_id']);
			}
			
			$ref_id = $params['product_item_id'];
			
			$description = '';
			if (!empty($product_item['dimensions'])){
				foreach($product_item['dimensions'] as $dimension){
					list($did, $dval) = explode('=', $dimension['value']);
					$description .= $this->get_dimension_label($did) .': '.$this->get_dimension_value_data($did, $dval)['label'].' ';
				}
			}
			
		}
		
		$order_line = [
				'panel_name' => 'shop/order_line',
				'show' => 1,
				'sort' => 'first',
				'ref_id' => $ref_id,
				'qty' => 1,
				'price' => !empty($product_item['price']) ? $product_item['price'] : (!empty($product['price']) ? $product['price'] : 0),
				'description' => $description,
				'item' => !empty($product['heading']) ? $product['heading'] : $product_item['heading'],
				'order_id' => $order_id,
		];
		
		$this->cms_page_panel_model->create_cms_page_panel($order_line);
		
	}
	
	function delete_order($order_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$this->cms_page_panel_model->delete_cms_page_panel($order_id);
		
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
		foreach($lines as $line){
			$this->cms_page_panel_model->delete_cms_page_panel($line['cms_page_panel_id']);
		}
		
	}
	
	function delete_order_line($order_id, $order_line_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$line = $this->cms_page_panel_model->get_cms_page_panel($order_line_id);
		
		if ($line['order_id'] == $order_id){
			
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
	
	function calculate_product_item_hash($ref){
		
		$rvs = [];
		foreach($ref['dimensions'] as $rd){
			$rvs[] = $rd['value'];
		}
		sort($rvs);
		$ref_dimension = implode('|', $rvs);
		
		return md5($ref_dimension);
		
	}
	
	function set_order_paid($order_id, $wp_data = []){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$this->cms_page_panel_model->update_cms_page_panel($order_id, [
				'status' => 'paid',
				'last_result' => 'authorized',
				'paid_time' => time(),
				'meta' => json_encode(['worldpay' => $wp_data], JSON_PRETTY_PRINT),
		]);
		
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
		
		foreach($lines as $line){
			
			if (!empty($line['ref_id'])){
				
				$ref = $this->cms_page_panel_model->get_cms_page_panel($line['ref_id']);
				
				if ($ref['panel_name'] == 'stock/product_item'){
					
					$this->cms_page_panel_model->update_cms_page_panel($ref['cms_page_panel_id'], ['order_id' => $order_id]);
					
					// check if another ref with same properties is available?
					$ref_hash = $this->calculate_product_item_hash($ref);
					
					$replacement_id = 0;
					
					$refs = $this->cms_page_panel_model->get_list('stock/product_item', ['product_id' => $ref['product_id'], 'order_id' => 0]);
					foreach($refs as $ref_item){
						$ref_item_hash = $this->calculate_product_item_hash($ref_item);
						if ($ref_hash == $ref_item_hash){
							$replacement_id = $ref_item['cms_page_panel_id'];
						}
					}

					// replace or remove product item in all other baskets
					$order_lines = $this->cms_page_panel_model->get_list('shop/order_line', ['ref_id' => $ref['cms_page_panel_id']]);
					foreach($order_lines as $order_line){
						if ($order_line['order_id'] != $order_id){
							if (!empty($replacement_id)){
								$this->cms_page_panel_model->update_cms_page_panel($order_line['cms_page_panel_id'], ['ref_id' => $replacement_id]);
							} else {
								$this->cms_page_panel_model->update_cms_page_panel($order_line['cms_page_panel_id'], ['show' => 0]);
							}
						}
					}
					
				}
				
			}
			
		}
		
		// recipients
		$shop_config = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/shop');
		
		$mailto = [];
		foreach($shop_config['emails'] as $email){
			$mailto[] = $email['email'];
		}
		
		$this->load->model('mail/mail_model');
		$this->mail_model->send_mail([
				'panel' => 'shop/mail_order',
				'order_id' => $order_id,
				'mail_to' => $mailto,
		]);

	}
	
	function is_product_in_basket($product_id, $order_id){
		
		$return = false;
	
		$this->load->model('cms/cms_page_panel_model');
	
		$items = [];
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order_id]);
		foreach($lines as $line){
			
			$product_item = $this->cms_page_panel_model->get_cms_page_panel($line['ref_id']);

			if(!empty($product_item['product_id']) && $product_item['product_id'] == $product_id){
				$return = true;
			}
		}
	
		return $return;
	
	}
	
}
