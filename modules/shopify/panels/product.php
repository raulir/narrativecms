<?php 

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class product extends \CI_Controller{
	
	function panel_heading($params){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$addon = '';
		if (!empty($params['subcategory_id'])){
			
			$subcategory = $this->cms_page_panel_model->get_cms_page_panel($params['subcategory_id']);
			$category = $this->cms_page_panel_model->get_cms_page_panel($subcategory['category_id']);
			
			$addon = ' ('.$category['heading'].' - '.$subcategory['heading'].')';
				
		}

		$return = '<div class="cms_heading_colour" style="background-color: '.($params['colour'] ?? '').'; "></div> '.($params['heading'] ?? '').$addon;
		
		return $return;
	
	}

}
