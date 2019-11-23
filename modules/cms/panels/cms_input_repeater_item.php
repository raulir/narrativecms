<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_repeater_item extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->load->helper('cms/cms_fields_helper');
		
	}

}
