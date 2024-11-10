<?php if (! defined ( 'BASEPATH' )) exit ( 'No direct script access allowed' );

class download extends CI_Controller {
	
	function panel_params($params) {
	
		$this->load->model('cms/cms_page_panel_model');
		
		$params['siblings'] = $this->cms_page_panel_model->get_list_neighbours('download/download', $params['cms_page_panel_id']);
		
		return $params;
		
	}
	
	function panel_heading($params){
	
		$return = '<div class="cms_heading_colour" style="background-color: '.($params['colour'] ?? '').'; "></div> '.$params['heading'];
	
		return $return;
	
	}
	
}
