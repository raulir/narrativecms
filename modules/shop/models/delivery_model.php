<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class delivery_model extends Model {

	function get_deliveries($products){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$delivery_sets = [];
		$has_primary = false;
		
		foreach($products as $product_id){
		
			$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
			
			if (!empty($product['product_stock_id'])){
				$product_stock = $this->cms_page_panel_model->get_cms_page_panel($product['product_stock_id']);
				if (!empty($product_stock['delivery_set_id'])){
					$delivery_set = $this->cms_page_panel_model->get_cms_page_panel($product_stock['delivery_set_id']);
					if (!$delivery_set['secondary']){
						$has_primary = true;
					}
					$delivery_sets[$product_stock['delivery_set_id']] = $delivery_set;
				}
			}
		
		}
		
		$return = [];
		
		foreach($delivery_sets as $delivery_set){
			
			if (!($delivery_set['secondary'] && $has_primary)){
				foreach($delivery_set['delivery_methods'] as $item){
					$delivery = $this->cms_page_panel_model->get_cms_page_panel($item['delivery_id']);
					if ($delivery['show']){
						$return[$item['delivery_id']] = $delivery;
					}
				}
			}
			
		}
		
		return $return;
		
	}
	
}
