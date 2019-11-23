<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_subtitle extends CI_Controller {

	function panel_params($params){
		
		$params['width'] = !empty($params['width']) ? $params['width'] : 'wide';
		$params['help'] = !empty($params['help']) ? $params['help'] : '';

		return $params;
		
	}

}
