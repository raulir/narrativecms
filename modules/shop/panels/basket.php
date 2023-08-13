<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class basket extends CI_Controller{

	function panel_action($params){
				
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('user/user_model');
		$this->load->model('shop/shop_model');
		
		if (empty($params['cms_page_panel_id'])){
			$params = array_merge_recursive_ex($params, $this->cms_page_panel_model->get_cms_page_panel($this->input->post('id')));
		}
		
		$user = $this->user_model->get_current();
		if (empty($user)) $user = [];
		
		$do = $this->input->post('do');

		if ($do == 'remove'){
			
			$order = $this->shop_model->get_current_order($user);
			$this->shop_model->delete_order_line($order['cms_page_panel_id'], $params['item_id']);

		}

		return $params;
		
	}

	function panel_params($params){

		$this->load->model('user/user_model');
		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');
		
		// get current basket
		$user = $this->user_model->get_current();
		if (empty($user)) $user = [];

		$params['order'] = $this->shop_model->get_current_order($user);
		$lines = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $params['order']['cms_page_panel_id']]);
		
		$refs = [];
		foreach($lines as $item){
			$refs[$item['ref_id']] = $this->cms_page_panel_model->get_cms_page_panel($item['ref_id']);
		}
		
		$products = [];
		foreach($refs as $item){
			if (!empty($item['product_id'])){
				$products[$item['product_id']] = $this->cms_page_panel_model->get_cms_page_panel($item['product_id']);
			}
		}
		
		$params['items'] = [];
		foreach($lines as $line){

			if ($refs[$line['ref_id']]['panel_name'] == 'stock/product_item'){
			
				$dimensions = [];
				$dims = $refs[$line['ref_id']]['dimensions'];
				foreach($dims as $dim){
				
					list($dtype, $dvalue) = explode('=', $dim['value']);
					
					$dimensions[] = [
							'label' => $this->shop_model->get_dimension_label($dtype),
							'value' => $this->shop_model->get_dimension_value_data($dtype, $dvalue)['label'],
					];

				}
				
				// dimension values
	
				$params['items'][$line['cms_page_panel_id']] = [
					'image' => $products[$refs[$line['ref_id']]['product_id']]['image'],
					'heading' => $products[$refs[$line['ref_id']]['product_id']]['heading'],
					'description' => $products[$refs[$line['ref_id']]['product_id']]['text'],
					'price' => $line['price'],
					'dimensions' => $dimensions,
					'item_id' => $line['cms_page_panel_id'],
					'product_id' => $refs[$line['ref_id']]['product_id'],
				];
			
			}
			
		}

		return $params;
	
	}
	
}
