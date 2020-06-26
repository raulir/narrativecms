<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('array_key_first')) {
	function array_key_first(array $arr) {
		foreach($arr as $key => $unused) {
			return $key;
		}
		return NULL;
	}
}

class cms_input_groups extends CI_Controller {

	function panel_params($params){
		
		if (empty($params['value'])){
			$params['value'] = array_key_first($params['values']);
		}
		
		return $params;
		
	}

}
