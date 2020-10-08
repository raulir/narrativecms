<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_grid extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		
	}
	
	function panel_action($params){
		
		if(!empty($params['do'])){
			if ($params['do'] == 'create_row'){
				
				$this->load->model('cms/cms_page_panel_model');
				
				$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);
				$params['data'] = $this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], [
						'do' => 'C',
						'id' => $params['base_id'],
				]);
				
				print(json_encode($params['data'], JSON_PRETTY_PRINT));
				
				die();
				
			}
		}
		
		return $params;
		
	}

	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);

		$params['data'] = $this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], [
				'do' => 'L',
				'id' => $params['base_id'],
		]);
		unset($params['data']['_no_cache']);
		
		$params['_params'] = &$params;

		return $params;

	}

}
