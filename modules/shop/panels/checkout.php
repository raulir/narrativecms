<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class checkout extends CI_Controller{
	
	function panel_action($params){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('user/user_model');
		$this->load->model('shop/shop_model');
		
		$user = $this->user_model->get_current();
		if (empty($user)){
			$user = [];
		}
		
		$do = $this->input->post('do');
		
		if ($do == 'delivery_method'){
			
			$method_id = $this->input->post('method_id');
				
			$order = $this->shop_model->get_current_order($user);
			$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order['cms_page_panel_id']]);
			
			foreach($lines as $line){
				
				$line['ref'] = $this->cms_page_panel_model->get_cms_page_panel($line['ref_id']);
				
				if ($line['ref']['panel_name'] == 'shop/delivery'){
					$this->cms_page_panel_model->delete_cms_page_panel($line['cms_page_panel_id']);
				}
				
				$this->shop_model->create_order_line($order['cms_page_panel_id'], ['product_item_id' => $method_id, ]);
				
			}

		} elseif ($do == 'delivery_change'){
			
			$order = $this->shop_model->get_current_order($user);
			$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order['cms_page_panel_id']]);
			
			foreach($lines as $line){
				
				$line['ref'] = $this->cms_page_panel_model->get_cms_page_panel($line['ref_id']);
				
				if ($line['ref']['panel_name'] == 'shop/delivery'){
					$this->cms_page_panel_model->delete_cms_page_panel($line['cms_page_panel_id']);
				}

			}

		} elseif ($do == 'delivery_address'){
			
			$address1 = $this->input->post('checkout_input_address1');
			$address2 = $this->input->post('checkout_input_address2');
			$address3 = $this->input->post('checkout_input_address3');
			$postcode = $this->input->post('checkout_input_postcode');
			$county = $this->input->post('checkout_input_county');
			$country = $this->input->post('checkout_input_country');
			$email = $this->input->post('checkout_input_email');
			$name = $this->input->post('checkout_input_name');
			$phone = $this->input->post('checkout_input_phone');
				
			$params['delivery_meta'] = [
					'address1' => $address1,
					'address2' => $address2,
					'address3' => $address3,
					'postcode' => $postcode,
					'county' => $county,
					'country' => $country,
					'email' => $email,
					'name' => $name,
					'phone' => $phone,
			];
			
			if (empty($address1) || empty($postcode) || empty($county) || empty($country) || empty($email)){
				
				$params['address_error'] = true;
				
			} else {
				
				$order = $this->shop_model->get_current_order($user);
				$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $order['cms_page_panel_id']]);
				
				foreach($lines as $line){
					
					$line['ref'] = $this->cms_page_panel_model->get_cms_page_panel($line['ref_id']);
					
					if ($line['ref']['panel_name'] == 'shop/delivery'){
						$this->cms_page_panel_model->update_cms_page_panel($line['cms_page_panel_id'],
								['meta' => json_encode($params['delivery_meta'], JSON_PRETTY_PRINT)]
						);
					}
					
				}
			
			}

		}

		return $params;
		
	}

	function panel_params($params){

		$this->load->model('user/user_model');
		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/delivery_model');
		
		// get current basket
		$user = $this->user_model->get_current();
		if (empty($user)) $user = [];

		$params['order'] = $this->shop_model->get_current_order($user);
		$params['lines'] = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $params['order']['cms_page_panel_id']]);
		
		// if there was a problem, reset
		if (!empty($params['order']['last_result'])){
			$this->cms_page_panel_model->update_cms_page_panel($params['order']['cms_page_panel_id'], ['last_result' => '']);
		}

		$params['delivery'] = [];
		
		$params['total'] = 0;
		$params['tax_multiplier'] = 1;
		
		$products = [];

		foreach($params['lines'] as &$line){
			
			$line['ref'] = $this->cms_page_panel_model->get_cms_page_panel($line['ref_id']);
			
			if (!empty($line['ref']['product_id'])){
				
				$line['product'] = $this->cms_page_panel_model->get_cms_page_panel($line['ref']['product_id']);
				
				$products[] = $line['ref']['product_id'];
				
			}
			
			if(empty($line['price'])) {
				$line['price'] = 0;
			}
			
			$line['price_main'] = floor($line['price']);
			$line['price_decimals'] = round(($line['price'] - $line['price_main']) * 100);
			
			if ($line['ref']['panel_name'] == 'shop/delivery'){
				$params['delivery'] = $line['ref'];
				$params['delivery_line'] = $line;
				if (!empty($line['meta']) && empty($params['delivery_meta'])){
					$params['delivery_meta'] = json_decode($line['meta'], true);
				}
			}

			$params['total'] += $line['price'];

		}
		
		$params['delivery_methods'] = $this->delivery_model->get_deliveries($products);
		
		// check if delivery line is in allowed lines
		if (!empty($params['delivery']['cms_page_panel_id'])){
			
			$found = false;
			foreach($params['delivery_methods'] as $method){
				if ($method['cms_page_panel_id'] == $params['delivery']['cms_page_panel_id']){
					$found = true;
				}
			}
			
			if (!$found){
				if (!empty($params['delivery_line'])){
					$this->cms_page_panel_model->delete_cms_page_panel($params['delivery_line']['cms_page_panel_id']);
					unset($params['delivery_line']);
				}
				if (!empty($params['delivery_meta'])){
					unset($params['delivery_meta']);
				}
				$params['delivery'] = [];
			}
			
		}
		
		// countries
		$params['countries'] = json_decode(file_get_contents($GLOBALS['config']['base_path'].'modules/shop/vendor/countries.json'), true);
		
		if (empty($params['selected_country']) && !empty($params['delivery']) && $params['delivery']['heading'] == 'UK'){
			$params['selected_country'] = 'GB';
		}
		
		// decide, what tab should be active
		$params['active'] = 'delivery';
		
		if (!empty($params['delivery_meta']['address1']) && !empty($params['delivery_meta']['postcode']) && !empty($params['delivery_meta']['county']) &&
				!empty($params['delivery_meta']['country']) && !empty($params['delivery_meta']['email'])){
			
			$params['active'] = 'review';
			
		}

		if (!empty($params['delivery_line'])){
			$params['topay'] = round($params['total'] * $params['delivery_line']['ref']['multiplier'], 2);
			$params['tax'] = round($params['total'] * $params['delivery_line']['ref']['tax_multiplier'], 2);
		} else {
			$params['topay'] = 0;
			$params['tax'] = 0;
		}
		
		$params['topay_main'] = floor($params['topay']);
		$params['topay_decimals'] = round(($params['topay'] - $params['topay_main']) * 100);
		
		$params['show_tax'] = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/shop')['show_tax'];

		return $params;
	
	}
	
}
