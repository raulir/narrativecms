<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class faqs extends CI_Controller{
	
	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		
		$params['faqs'] = $this->cms_page_panel_model->get_list('faq/faq');

		return $params;
		
	}

}
