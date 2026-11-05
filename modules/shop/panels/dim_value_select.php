<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Dim value dropdown cell for the product Variants grid. Writes the grid log; Save applies it.
 */
class dim_value_select extends \Controller{

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');

		$item_id = (int)($params['item_id'] ?? 0);
		$item = [];
		if ($item_id > 0){
			$loaded = $this->cms_page_panel_model->get_cms_page_panel($item_id);
			if (is_array($loaded)){
				$item = $loaded;
			}
		}
		$dim_id = (int)$params['dim'];

		$params['current'] = '';
		foreach (($item['dims'] ?? []) as $row){
			if ((int)($row['product_dim_id'] ?? 0) === $dim_id){
				$params['current'] = $row['value'] ?? '';
			}
		}

		$params['available'] = [];
		$dim = $dim_id > 0 ? $this->cms_page_panel_model->get_cms_page_panel($dim_id) : [];
		if (!empty($dim['values']) && is_array($dim['values'])){
			foreach ($dim['values'] as $value){
				$params['available'][$value['id']] = $value['label'];
			}
		}

		$params['dim'] = $dim_id;

		return $params;

	}

}
