<?php if (! defined ( 'BASEPATH' )) exit ( 'No direct script access allowed' );

class download extends CI_Controller {
	
	function panel_params($params) {
	
		$this->load->model('cms/cms_page_panel_model');
		
		$params['siblings'] = $this->cms_page_panel_model->get_list_neighbours('download/download', $params['cms_page_panel_id']);
		
		$GLOBALS['_panel_titles'][] = $params['heading'];
		$GLOBALS['_panel_descriptions'][] = $params['search_description'];
		
		return $params;
		
	}
	
	function panel_heading($params){
		
		$return = '';
	
		if (!empty($params['colour'])){
			$return .= '<div class="cms_heading_colour" style="background-color: '.$params['colour'].'; "></div> ';
		}
		
		if (!empty($params['subtitle'])){
			$return .= $params['subtitle'].' - ';
		}
		
		$return .= ($params['heading'] ?? '');
	
		return $return;
	
	}
	
}
