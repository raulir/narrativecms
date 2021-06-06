<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class product extends CI_Controller{
	
	function panel_heading($params){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$return = $params['heading'];

		if (!empty($params['category_id'])){
			
			$category = $this->cms_page_panel_model->get_cms_page_panel($params['category_id']);
			$return .= ' ('.$category['heading'];
			
			if (!empty($params['subcategory_id'])){
				$subcategory = $this->cms_page_panel_model->get_cms_page_panel($params['subcategory_id']);
				$return .= ' - '.$subcategory['heading'];
			}
			
			$return .= ')';
			
		}
		
		return $return;
	
	}
	
}
