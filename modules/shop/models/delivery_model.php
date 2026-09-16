<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class delivery_model extends \Model {

	function get_deliveries($products){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$delivery_sets = [];
		$has_primary = false;
		
		foreach($products as $product_id){
		
			$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
			
			$this->load->model('shop/shop_dim_model');
			$product_type = $this->shop_dim_model->resolve_product_type($product);
			if (!empty($product_type['delivery_set_id'])){
				$delivery_set = $this->cms_page_panel_model->get_cms_page_panel($product_type['delivery_set_id']);
				if (!empty($delivery_set['cms_page_panel_id'])){
					if (empty($delivery_set['secondary'])){
						$has_primary = true;
					}
					$delivery_sets[$product_type['delivery_set_id']] = $delivery_set;
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
