<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class vimeo extends CI_Controller{
	
	function panel_params($params){
		
//		$this->load->model('cms/cms_page_panel_model');
//		$params = array_merge($params, $this->cms_page_panel_model->get_cms_page_panel_settings('basic/vimeo'));

		return $params;
		
	}

}
