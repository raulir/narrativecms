<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_modules extends \Controller {

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

		// get available modules
		$path = $GLOBALS['config']['base_path'].'modules/*';
		$values = array_map('basename', glob($path , GLOB_ONLYDIR));

		if (empty($params['values']) || !is_array($params['values'])){
			$params['values'] = [];
		}
		foreach($values as $value){
			$params['values'][$value] = $value;
		}

		// cms is always first / not removable (settings page only uses this input)
		if (empty($params['sticky']) || !is_array($params['sticky'])){
			$params['sticky'] = [];
		}
		if (!isset($params['sticky']['cms'])){
			$params['sticky']['cms'] = 'cms';
		}
		$params['values']['cms'] = $params['sticky']['cms'];

		$params['_return'] = false;

		$params['params'] = $params;

		return $params;

	}

}
