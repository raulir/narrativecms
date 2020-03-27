<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class menuitem extends CI_Controller{
	
	function panel_heading($params){
		
		$this->load->model('cms/cms_page_panel_model');

		if (!empty($params['menugroup_id'])){
			
			$menugroup = $this->cms_page_panel_model->get_cms_page_panel($params['menugroup_id']);
			
			$menucategory = $this->cms_page_panel_model->get_cms_page_panel($menugroup['menucategory_id']);
			
			$return = $menucategory['heading'] . ' - ' . $menugroup['heading'] . ' - ' . $params['heading'];
		
		} else {
			
			$return = 'marcella/menuitem='.$params['cms_page_panel_id'];
			
		}
		
		return $return;
	
	}
	
}
