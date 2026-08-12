<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Placeable products section: filter menu + product grid (local catalogue).
 */
class products extends \Controller {

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');

		// Merge panel settings (labels, empty message)
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/products');
		if (!is_array($settings)){
			$settings = [];
		}
		$params = array_merge($settings, $params);

		// Initial filter state (optional instance params; default all)
		$params['category_id'] = (int)($params['category_id'] ?? 0);
		$params['subcategory_id'] = (int)($params['subcategory_id'] ?? 0);
		$params['collection_id'] = (int)($params['collection_id'] ?? 0);

		if (empty($params['empty_filter_message'])){
			$params['empty_filter_message'] = 'No products to show with this filter';
		}
		if (empty($params['label_all_categories'])){
			$params['label_all_categories'] = 'All categories';
		}
		if (empty($params['label_all_in_category'])){
			$params['label_all_in_category'] = 'All';
		}
		if (empty($params['label_all_collections'])){
			$params['label_all_collections'] = 'All collections';
		}

		// Child panel params
		$filter = [
				'category_id' => $params['category_id'],
				'subcategory_id' => $params['subcategory_id'],
				'collection_id' => $params['collection_id'],
				'empty_filter_message' => $params['empty_filter_message'],
				'label_all_categories' => $params['label_all_categories'],
				'label_all_in_category' => $params['label_all_in_category'],
				'label_all_collections' => $params['label_all_collections'],
		];

		$params['menu_params'] = $filter;
		$params['grid_params'] = $filter;

		return $params;

	}

}
