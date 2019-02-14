<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_multi extends MY_Controller{

	function panel_params($params){
		
		if (empty($params['value'])){
			$params['value'] = [];
		}
		
		return $params;
		
	}

}
