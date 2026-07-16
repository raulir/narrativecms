<?php

namespace stock;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class category extends \Controller{
	
	function panel_heading($params){
		
		if (empty($params['heading'])){
			$return = 'Category';
		} else {
			$return = '<div class="cms_heading_colour" style="background-color: '.($params['colour'] ?? '').'; "></div> '.$params['heading'];
		}
	
		return $return;
	
	}

}
