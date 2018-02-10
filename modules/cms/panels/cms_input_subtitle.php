<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_subtitle extends MY_Controller{

	function panel_params($params){
		
		$params['width'] = !empty($params['width']) ? $params['width'] : 'wide';

		return $params;
		
	}

}
