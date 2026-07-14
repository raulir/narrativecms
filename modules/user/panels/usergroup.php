<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class usergroup extends Controller{
	
	function panel_heading($params){

		return $params['name'];
	
	}
	
}
