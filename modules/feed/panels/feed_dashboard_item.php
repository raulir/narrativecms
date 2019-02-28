<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed_dashboard_item extends CI_Controller{
	
	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		
		$params['block'] = $this->cms_page_panel_model->get_cms_page_panel($params['id']);

		return $params;

	}

}
