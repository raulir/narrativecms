<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class iframe extends CI_Controller {
	
	function panel_params($params){

		if ($params['resizer'] == 'yes'){
			$GLOBALS['_panel_js'][] = [
					'script' => 'https://iparl.com/global/includes/iframeResizer.min.js',
					'sync' => 'defer', 
			];
		};

		return $params;
	
	}
	
}
