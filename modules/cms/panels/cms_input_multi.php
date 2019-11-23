<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_multi extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		
	}
	
	function panel_params($params){
		
		if (empty($params['value'])){
			$params['value'] = [];
		}
		
		return $params;
		
	}

}
