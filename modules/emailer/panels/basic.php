<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class basic extends Controller{
	
	function panel_params($params){
		
		$params['link'] = _l('emailer/basic='.$params['cms_page_panel_id'], false);
		
		return $params;
		
	}

}
