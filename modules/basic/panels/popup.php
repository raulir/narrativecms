<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class popup extends CI_Controller{
	
	function panel_params($params){
		
		if (empty($params['popup_id'])){
			$params['popup_id'] = $params['cms_page_panel_id'];
		}

		return $params;
		
	}
	
}
