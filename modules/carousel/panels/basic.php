<?php if (! defined ( 'BASEPATH' )) exit ( 'No direct script access allowed' );

class basic extends CI_Controller {
	
	function panel_params($params) {
	
		if (empty($params['speed'])){
			$params['speed'] = 500;
		}
		
		if (empty($params['delay'])){
			$params['delay'] = 3600000;
		}
		
		if (empty($params['cycle'])){
			$params['cycle'] = 1;
		}
		
		return $params;
		
	}
	
}
