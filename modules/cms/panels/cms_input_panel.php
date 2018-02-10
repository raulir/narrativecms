<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_panel extends MY_Controller{

	function panel_params($params){
		
		$this->load->model('cms/cms_panel_model');
		
		$params['values'] = $this->cms_panel_model->get_cms_panels('cron');
		
		return $params;
		
	}

}
