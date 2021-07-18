<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_modules extends CI_Controller {

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
		
		// get available modules
		$path = $GLOBALS['config']['base_path'].'modules/*';
		$values = array_map('basename', glob($path , GLOB_ONLYDIR));
		
		foreach($values as $value){
			$params['values'][$value] = $value;
		}
		
		$params['_return'] = false;
		
		$params['params'] = $params;

		return $params;
		
	}

}
