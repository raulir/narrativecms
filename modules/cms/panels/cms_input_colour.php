<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_colour extends CI_Controller {

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

		$params['default_class'] = '';
		if (isset($params['default'])){
			$params['default_class'] = ' cms_input_default ';
		}
		
		$params['mandatory_class'] = (empty($params['mandatory_class']) ? '' : $params['mandatory_class']);
		
		return $params;
		
	}

}
