<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_grid_editable extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}
	
	function panel_action($params){
		
		if (!empty($params['do'])){
			
			if($params['do'] == 'update_field'){
				
				$this->load->model('cms/cms_page_panel_model');
				$this->cms_page_panel_model->update_cms_page_panel($params['item_id'], [$params['name'] => $params['value']]);
				
			}
			
		}
		
		return $params;
		
	}

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$item = $this->cms_page_panel_model->get_cms_page_panel($params['item_id']);
		
		$params['value'] = $item[$params['name']];

		return $params;

	}

}
