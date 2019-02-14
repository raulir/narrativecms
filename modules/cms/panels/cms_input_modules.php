<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_modules extends MY_Controller{

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
