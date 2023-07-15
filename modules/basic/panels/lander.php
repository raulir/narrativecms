<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class lander extends CI_Controller{
	
	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_slug_model');
		
		if (!empty($params['_cms_page_panel_id'])){
		
			$cms_page_panel = $this->cms_page_panel_model->get_cms_page_panel($params['_cms_page_panel_id']);
			
			$GLOBALS['_panel_titles'][] = mb_substr($cms_page_panel['heading'], 0, 100);
			$GLOBALS['_panel_descriptions'][] = mb_substr($cms_page_panel['text'], 0, 300);
			$GLOBALS['_panel_images'][] = $cms_page_panel['image'];
		
		}
		
		if (!empty($params['_page_id'])){
			$params['hash'] = $this->cms_slug_model->get_cms_slug_by_target($params['_page_id']);
		} else {
			$params['hash'] = '';
		}

		return $params;
		
	}
	
}
