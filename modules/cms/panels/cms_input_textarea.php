<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_textarea extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		if (!empty($params['tinymce'])){
			$this->js[] = array('script' => 'js/tinymce/tinymce.min.js', 'no_pack' => 1, 'sync' => '', );
		}

		return $params;

	}

}
