<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class article_thumbnail extends CI_Controller{
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');

		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('news/article_thumbnail');

		return array_merge($params, ['read_more' => $settings['read_more']]);

	}

}
