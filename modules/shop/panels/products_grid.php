<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Ajax / embed product grid — local catalogue filters only.
 */
class products_grid extends \Controller {

	function panel_params($params){

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');

		// Labels / empty message from shell settings when not passed
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/products');
		if (!is_array($settings)){
			$settings = [];
		}
		if (empty($params['empty_filter_message']) && !empty($settings['empty_filter_message'])){
			$params['empty_filter_message'] = $settings['empty_filter_message'];
		}
		if (empty($params['empty_filter_message'])){
			$params['empty_filter_message'] = 'No products to show with this filter';
		}

		$category_id = (int)($params['category_id'] ?? 0);
		$subcategory_id = (int)($params['subcategory_id'] ?? 0);
		$collection_id = (int)($params['collection_id'] ?? 0);

		$params['products'] = $this->shop_model->get_products_for_filters(
				$category_id,
				$subcategory_id,
				$collection_id
		);

		if (!is_array($params['products'])){
			$params['products'] = [];
		}

		$params['category_id'] = $category_id;
		$params['subcategory_id'] = $subcategory_id;
		$params['collection_id'] = $collection_id;

		return $params;

	}

}
