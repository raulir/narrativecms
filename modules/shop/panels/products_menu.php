<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Filter header for shop/products: category dropdown, subcategory pills, collection dropdown.
 */
class products_menu extends \Controller {

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		// Labels from shell settings
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/products');
		if (!is_array($settings)){
			$settings = [];
		}
		foreach ([
				'label_all_categories',
				'label_all_in_category',
				'label_all_collections',
				'label_category',
				'label_collection',
		] as $key){
			if (empty($params[$key]) && isset($settings[$key])){
				$params[$key] = $settings[$key];
			}
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

		$category_id = (int)($params['category_id'] ?? 0);
		$subcategory_id = (int)($params['subcategory_id'] ?? 0);
		$collection_id = (int)($params['collection_id'] ?? 0);

		$categories = $this->cms_page_panel_model->get_list('shop/category');
		if (!is_array($categories)){
			$categories = [];
		}

		// Subcategories for selected category only (empty when all categories)
		$subcategories = [];
		if ($category_id > 0){
			$subcategories = $this->cms_page_panel_model->get_list('shop/subcategory', [
					'category_id' => $category_id,
			]);
			if (!is_array($subcategories)){
				$subcategories = [];
			}
		}

		// Collections for current category context (0 = all products' collections)
		$collections = $this->shop_model->get_collections_for_filters($category_id);
		if (!is_array($collections)){
			$collections = [];
		}

		// If active collection is not in list, clear it for display
		if ($collection_id > 0){
			$found = false;
			foreach ($collections as $col){
				if ((int)($col['cms_page_panel_id'] ?? 0) === $collection_id){
					$found = true;
					break;
				}
			}
			if (!$found){
				$collection_id = 0;
			}
		}

		// If active subcategory not under category, clear
		if ($subcategory_id > 0 && $category_id > 0){
			$found = false;
			foreach ($subcategories as $sub){
				if ((int)($sub['cms_page_panel_id'] ?? 0) === $subcategory_id){
					$found = true;
					break;
				}
			}
			if (!$found){
				$subcategory_id = 0;
			}
		} else if ($category_id < 1){
			$subcategory_id = 0;
		}

		$params['categories'] = $categories;
		$params['subcategories'] = $subcategories;
		$params['collections'] = $collections;
		$params['category_id'] = $category_id;
		$params['subcategory_id'] = $subcategory_id;
		$params['collection_id'] = $collection_id;
		$params['has_collections'] = !empty($collections) ? 1 : 0;

		// Active category heading for labels
		$params['active_category_heading'] = '';
		if ($category_id > 0){
			foreach ($categories as $cat){
				if ((int)($cat['cms_page_panel_id'] ?? 0) === $category_id){
					$params['active_category_heading'] = $cat['heading'] ?? '';
					break;
				}
			}
		}

		// Active collection heading
		$params['active_collection_heading'] = $params['label_all_collections'];
		if ($collection_id > 0){
			foreach ($collections as $col){
				if ((int)($col['cms_page_panel_id'] ?? 0) === $collection_id){
					$params['active_collection_heading'] = $col['heading'] ?? $params['label_all_collections'];
					break;
				}
			}
		}

		return $params;

	}

}
