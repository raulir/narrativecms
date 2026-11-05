<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Image repeater field: product items that belong to the product being edited.
 */
class image_item_ids extends \Controller {

	function panel_params($params){

		$this->load->model('shop/shop_dim_model');

		$product_id = (int)($params['base_id'] ?? 0);
		$params['choices'] = [];

		if ($product_id > 0){
			foreach ($this->shop_dim_model->get_master_items($product_id) as $item){
				$id = (int)($item['cms_page_panel_id'] ?? 0);
				if ($id < 1){
					continue;
				}
				$params['choices'][$id] = $this->shop_dim_model->item_list_title($item);
			}
		}

		$raw = $params['value'] ?? '';
		$selected = [];
		if (is_array($raw)){
			foreach ($raw as $id_row){
				if (is_array($id_row)){
					$id_row = $id_row['product_item_id'] ?? $id_row['id'] ?? '';
				}
				foreach (preg_split('/\s*,\s*/', trim((string)$id_row)) as $one){
					if ($one !== '' && $one !== '0'){
						$selected[] = $one;
					}
				}
			}
		} else {
			foreach (preg_split('/\s*,\s*/', trim((string)$raw)) as $one){
				if ($one !== '' && $one !== '0'){
					$selected[] = $one;
				}
			}
		}

		$params['selected'] = $selected;
		$params['value_text'] = implode(',', $selected);

		return $params;

	}

}
