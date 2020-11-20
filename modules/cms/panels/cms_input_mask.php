<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_mask extends CI_Controller {

	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');

		$cms_page_panel = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);
		
		$params['target_image'] = !empty($cms_page_panel[$params['target']]) ? $cms_page_panel[$params['target']] : '';

		return $params;
		
	}

}
