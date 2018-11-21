<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class basketmini extends MY_Controller{
	
	function panel_params($params){
		
		$this->js[] = 'modules/cms/js/cms_cookie.js';
		
		$this->load->model('cms/cms_page_panel_model');
		
		// get options
		$params['shop_options'] = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/shop');
		
		// get order
		$orders = $this->cms_page_panel_model->get_list('shop/order');
		$params['order'] = array_shift($orders);

		$params['items'] = $this->cms_page_panel_model->get_list('shop/order_line', ['order_id' => $params['order']['cms_page_panel_id']]);
		
		$params['price_total'] = 0;
		$params['dimension_total'] = 0;
		
		foreach($params['items'] as $item){
			$params['price_total'] += $item['price'];
			$params['dimension_total'] += $item['dimension'];
		}
		
		$params['dimension_total'] = ceil(($params['dimension_total'] + 
				$params['shop_options']['dimension_extra'])/$params['shop_options']['dimension_rounding']) * $params['shop_options']['dimension_rounding'];

		return $params;
	
	}
	
}
