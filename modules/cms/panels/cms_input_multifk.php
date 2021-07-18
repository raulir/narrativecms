<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_multifk extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->load->model('cms/cms_css_model');
		$this->cms_css_model->add_css('modules/cms/css/cms_input.scss');
		
	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$params['values'] = [];
		foreach($params['targets'] as $target){
			$items = $this->cms_page_panel_model->get_list($target);
			foreach($items as $id => $value){
				$params['values'][$id] = $value['_panel_heading'];
			}
		}
		
		if (empty($params['value'])){
			$params['value'] = [];
		}
		
		if (empty($params['help'])){
			$params['help'] = '';
		}
		
		return $params;
		
	}

}
