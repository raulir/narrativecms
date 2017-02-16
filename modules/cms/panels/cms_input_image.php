<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_image extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		if (empty($params['name_clean'])) {
			$params['name_clean'] = $params['name'];
		}

		if(!file_exists($GLOBALS['config']['upload_path'].$params['value'])){
			$params['error'] = 'Missing image file<br>Update resources or database<br>or upload a new image';
		}

		return $params;

	}

}
