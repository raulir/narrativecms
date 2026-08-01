<?php

namespace shop;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Embeddable currency selector.
 *
 * Params:
 * - currency_ids (int[]): optional; missing/empty → all shop currencies
 * - default | default_currency_id | value: selected currency id
 * - add_empty (0|1): prepend empty option (CMS admin input style)
 */
class currency_selector extends \Controller {

	function panel_params($params){

		$this->load->model('shop/shop_model');

		if (!is_array($params)){
			$params = [];
		}

		$ids = $params['currency_ids'] ?? null;
		if ($ids === '' || $ids === null){
			$ids = null;
		} else if (!is_array($ids)){
			$ids = [(int)$ids];
		} else {
			$ids = array_values(array_filter(array_map('intval', $ids)));
			if ($ids === []){
				$ids = null;
			}
		}

		$options = $this->shop_model->get_currencies($ids);
		$add_empty = !empty($params['add_empty']) ? 1 : 0;

		$default = $params['default']
				?? $params['default_currency_id']
				?? $params['value']
				?? null;

		$selected = '';
		if ($default !== null && $default !== ''){
			$selected = (string)(int)$default;
			if ((int)$selected < 1){
				$selected = '';
			}
		}

		// Validate selection against options (unless empty allowed)
		if ($selected !== ''){
			$found = false;
			foreach ($options as $opt){
				if ((int)$opt['cms_page_panel_id'] === (int)$selected){
					$found = true;
					break;
				}
			}
			if (!$found){
				$selected = '';
			}
		}

		if ($selected === '' && !$add_empty && $options !== []){
			$selected = (string)(int)$options[0]['cms_page_panel_id'];
		}

		$label = '';
		if ($selected !== ''){
			foreach ($options as $opt){
				if ((int)$opt['cms_page_panel_id'] === (int)$selected){
					$label = (string)$opt['heading'];
					break;
				}
			}
		}

		$params['options'] = $options;
		$params['add_empty'] = $add_empty;
		$params['selected'] = $selected;
		$params['selected_label'] = $label;

		return $params;

	}

}
