<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed_dashboard_item extends MY_Controller{
	
	function panel_params($params){

		$this->load->model('cms_page_panel_model');

		$block_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('block_id' => $params['id'], ));
		if (!empty($block_a[0])){
			$params['block'] = $block_a[0];
		} else {
			$params['block'] = array();
		}

		return $params;

	}

}
