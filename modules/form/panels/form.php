<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class form extends MY_Controller{
	
	function panel_params($params){
		
		if(!empty($params['block_id'])){
			$params['cms_page_panel_id'] = $params['block_id'];
		}
		
		return $params;
	
	}
	
}
