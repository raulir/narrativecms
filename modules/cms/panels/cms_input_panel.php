<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_panel extends MY_Controller{

	function panel_params($params){
		
		$this->load->model('cms/cms_panel_model');
		
		if (!empty($params['flag'])){
			$params['values'] = $this->cms_panel_model->get_cms_panels($params['flag']);
		} else {
			$params['values'] = $this->cms_panel_model->get_cms_panels();
		}
		
		return $params;
		
	}

}