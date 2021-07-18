<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once($GLOBALS['config']['base_path'].'system/core/loader.php');

class load {
	
	function model($name, $alias = ''){
		
		if (empty($GLOBALS['loader'])){
			$GLOBALS['loader'] = new loader();
		}
		
// print('<pre>');
// print_r($name);
// print('</pre>');

		$GLOBALS['loader']->model($name, $alias, $this->parent);
		
	}
	
	function library($name, $params = null, $alias = null){
		
		if (empty($GLOBALS['loader'])){
			$GLOBALS['loader'] = new loader();
		}
		
// print('<pre>library: ');
// print_r($name);
// print('</pre>');

		$GLOBALS['loader']->library($name, $params, $alias);
		
	}
		
	function helper($helpers = []){
	
		if (!is_array($helpers)){
			$helpers = [$helpers];
		}
	
		if (empty($GLOBALS['helpers'])){
			$GLOBALS['helpers'] = [];
		}
		
		foreach ($helpers as $helper){
	
			if (isset($GLOBALS['helpers'][$helper])){
				continue;
			}
	
			if (stristr($helper, '/')){
				list($_module, $_helper) = explode('/', $helper);
				$filename = $GLOBALS['config']['base_path'].'modules/'.$_module.'/helpers/'.$_helper.'.php';
			} else {
				$filename = $GLOBALS['config']['base_path'].'system/helpers/'.$helper.'.php';
			}
	
			if (file_exists($filename)){
				require_once($filename);
				$GLOBALS['helpers'][$helper] = true;
			} else {
				show_error('Unable to load the requested file: helpers/'.$helper.'.php');
			}
		}
	}

}
