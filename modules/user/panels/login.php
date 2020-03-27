<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class login extends CI_Controller{
	
	function panel_params($params){

		// check if logged in
		$params['loggedin'] = 0;

		return $params;
	
	}
	
}
