<?php

namespace user;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class profile extends \Controller{
	
	function panel_action($params){

		return $params;
		
	}
	
	function panel_params($params){

		// check if logged in
		$params['loggedin'] = 0;

		return $params;
	
	}
	
}
