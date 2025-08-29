<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_select extends CI_Controller {

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
		
		if(!empty($params['params']['add_empty'])){
			$params['add_empty'] = $params['params']['add_empty'];
		}
		if (!empty($params['add_empty']) && empty($params['values']['']) && empty($params['values'][0])){
			$params['values'] = ['' => '-- not specified --'] + $params['values'];
		}

		if (!empty($params['params']['groups'])){
			
			$params['groups'] = $params['params']['groups'];
			
			if(!is_array($params['groups'])){
				$params['groups'] = [$params['groups']];
			}
			
		}
		
		return $params;
	
	}
	
}
