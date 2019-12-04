<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed extends CI_Controller{
	
	function panel_heading($params){

		if (!empty($params['_heading_type']) && $params['_heading_type'] == 'short'){
			return $params['heading'];
		}
		
		return _panel('feed/feed_dashboard_item', ['_return' => true, 'block' => $params]);

	}

}
