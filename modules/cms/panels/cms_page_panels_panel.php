<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panels_panel extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
		
		$this->load->helper('cms/cms_fields_helper');

		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_input_page_panels.js';
	
	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		
		if(!empty($params['cms_page_panel_id'])){
		
			$params['block'] = $this->cms_page_panel_model->get_cms_page_panel($params['cms_page_panel_id']);
			
			$panel_definition = $this->cms_panel_model->get_cms_panel_config($params['block']['panel_name']);
			
			$params['cms_page_panel_id'] = $params['block']['cms_page_panel_id'];
			$params['panel_name'] = $params['block']['panel_name'];
			$params['title'] = $params['block']['title'];
				
		} else {
			
			$params['block'] = [];
			
			$panel_definition = $this->cms_panel_model->get_cms_panel_config($params['panel_name']);
			$params['title'] = $params['panel_name'];
			
		}
		
		$params['panel_params_structure'] = $panel_definition['item'];
		
		return $params;
		
	}

}