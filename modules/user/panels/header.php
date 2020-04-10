<?php 

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class header extends \CI_Controller{
	
	function panel_params($params){

		// check if logged in
		$params['loggedin'] = 0;
		
		$params['user_image'] = $params['icon'];
		$params['user_name'] = 'Test';
		
		return $params;
	
	}
	
}
