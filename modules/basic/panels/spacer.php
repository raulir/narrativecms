<?php

namespace basic;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class spacer extends \Controller{
	
	function panel_heading($params){

		$return = 'Spacer (height: '.$params['height'].')';

		return $return;
		
	}
	
}
