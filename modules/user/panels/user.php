<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class user extends MY_Controller{
	
	function panel_heading($params){

		return $params['username'] . ' (' . $params['fullname'] . ') &lt;' . $params['email'] . '&gt;';
	
	}
	
}
