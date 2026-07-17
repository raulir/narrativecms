<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class subcategory extends \Controller{
	
	function panel_heading($params){
		
		$this->load->model('cms/cms_page_panel_model');

		$category = $this->cms_page_panel_model->get_cms_page_panel($params['category_id'] ?? 0);
		
		$return = ($params['heading'] ?? 'Subcategory').' ('.($category['heading'] ?? '-').')';
		
		return $return;
	
	}

}
