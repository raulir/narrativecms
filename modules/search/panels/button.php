<?php

namespace search;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class button extends \Controller{

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');

		// Shared UI strings / icon live on search/search settings
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('search/search');
		if (!is_array($settings)){
			$settings = [];
		}

		foreach (['search', 'search_placeholder', 'search_icon'] as $key){
			if (array_key_exists($key, $settings)){
				$params[$key] = $settings[$key];
			}
		}

		return $params;

	}

}
