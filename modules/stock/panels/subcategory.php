<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class subcategory extends CI_Controller{
	
	function panel_heading($params){
		
		$this->load->model('cms/cms_page_panel_model');

		$category = $this->cms_page_panel_model->get_cms_page_panel($params['category_id']);
		
//		$return = '<div class="cms_heading_colour" style="background-color: '.($params['colour'] ?? '').'; "></div> '.
//				$params['heading'].' ('.($category['heading'] ?? '-').')';
		
		$return = $params['heading'].' ('.($category['heading'] ?? '-').')';
		
		return $return;
	
	}

}
