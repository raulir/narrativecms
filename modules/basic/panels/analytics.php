<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class analytics extends CI_Controller {

    function panel_params($params) {

    	$this->load->model('cms/cms_page_panel_model');

        $settings_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('panel_name' => 'basic/analytics', ));
        $params['settings'] = !empty($settings_a[0]) ? $settings_a[0] : array();
    	
		return $params;

    }

}