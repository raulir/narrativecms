<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_page extends MY_Controller{

	function panel_params($params){
		
		$this->load->model('cms/cms_page_model');
		
		$params['values'] = $this->cms_page_model->get_cms_pages();
		
		return $params;
		
	}

}
