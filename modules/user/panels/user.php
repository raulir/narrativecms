<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class user extends CI_Controller{
	
	function panel_heading($params){
		
		if (!empty($params['username'])){
			$first_part = $params['username'];
			$last_part = ' &lt;' . $params['email'] . '&gt;';
		} else if (!empty($params['email'])) {
			$first_part = $params['email'];
			$last_part = '';
		} else {
			return 'User';
		}

		return $first_part . ' (' . $params['first_name'] . ' ' . $params['last_name'] . ')' . $last_part;
	
	}
	
}
