<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * When a no-limit type's dims change, create the missing combinations on products that use it.
 */
class product_type extends \Controller {

	function on_update($data){

		$type_id = (int)($data['cms_page_panel_id'] ?? 0);
		if ($type_id < 1){
			return $data;
		}
		if ((string)($data['stock_control'] ?? 'none') !== 'none'){
			return $data;
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');

		$products = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/product',
				'cms_page_id' => 0,
		]);
		foreach ($products as $product){
			$resolved = $this->shop_dim_model->resolve_product_type($product);
			if ((int)($resolved['cms_page_panel_id'] ?? 0) !== $type_id){
				continue;
			}
			$this->shop_dim_model->ensure_unlimited_items($product, $data);
		}

		return $data;

	}

}
