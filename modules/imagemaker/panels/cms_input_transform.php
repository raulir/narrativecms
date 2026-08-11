<?php

namespace imagemaker;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_transform extends \Controller {

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');

		$base_id = (int)($params['base_id'] ?? 0);
		$target = $params['target'] ?? 'print_background';
		$params['target'] = $target;

		$params['target_image'] = '';
		if ($base_id > 0){
			$cms_page_panel = $this->cms_page_panel_model->get_cms_page_panel($base_id);
			if (!empty($cms_page_panel[$target])){
				$params['target_image'] = $cms_page_panel[$target];
			}
		}

		// Hidden field stores JSON string
		$value = $params['value'] ?? '';
		if (is_array($value)){
			$value = json_encode($value);
		}
		$params['value'] = is_string($value) ? $value : '';

		$params['points'] = (int)($params['points'] ?? 5);
		if ($params['points'] < 2){
			$params['points'] = 5;
		}

		return $params;

	}

}
