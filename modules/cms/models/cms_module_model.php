<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_module_model extends CI_Model {
	
	/*
	 * returns list of available modules in system
	 */
	function get_modules(){
		
		$dir = $GLOBALS['config']['base_path'].'modules/';
		$modules = glob($dir . '*' , GLOB_ONLYDIR);
		
		$return = [];
		
		foreach($modules as $module){
			$return[] = [
				'name' => basename($module),
				'directory' => $module.'/',
				'active' => in_array(basename($module), $GLOBALS['config']['modules']),
			];
		}

		return $return;
		
	}
	
	
	function get_module_config($module){
				
		return $GLOBALS['config']['module'][$module];
		
	}
	
}