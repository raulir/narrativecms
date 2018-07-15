<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_targets extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	/*
	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'cms_page_panel_targets'){
			
			$cms_page_panel_id = $this->input->post('targets_id');
			

			
			return $params;
			
		}
		
		return $params;

	}
	
	*/
	
	function panel_params($params){
		
		$cms_page_panel_id = $this->input->post('targets_id');
		
		$this->load->model('cms/cms_page_panel_model');
		
		$params['page_panel'] = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		
		// get targets configuration
		$params['groups'] = [];
		if (!empty($_SESSION['config']['targets']['groups'])){
			
			foreach($_SESSION['config']['targets']['groups'] as $group){
				
				$params['groups'][] = [
						'heading' => $group['heading'],
						'values' => explode('|', $group['labels']),
						'selected' => '',
						'strategy' => $group['strategy'],
				];
			
			}
			
		} else {
			
			$params['message'] = 'No groups defined in "CMS" -> "Target groups"';
		
		}
		
		// get current values for page panel
		foreach($params['groups'] as $key => $group){
			if (!empty($params['page_panel']['_targets'][$group['heading']])){
				
				$params['groups'][$key]['selected'] = $params['page_panel']['_targets'][$group['heading']];
			
			}
		}
		
		return $params;
		
	}

}
