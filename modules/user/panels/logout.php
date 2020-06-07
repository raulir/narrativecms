<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class logout extends CI_Controller{
	
	function panel_action($params){
		
		$do = $this->input->post('do');

		if ($do == 'logout' && isset($_SESSION['user'])){
			unset($_SESSION['user']);
		}

		return $params;
		
	}

}
