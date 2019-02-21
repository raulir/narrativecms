<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_textarea extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->scss[] = ['script' => 'modules/cms/css/cms_input.scss', ];
	
	}

	function panel_params($params){

		if (!empty($params['tinymce'])){
			
			$this->js[] = array('script' => 'modules/cms/js/tinymce/tinymce.min.js', 'no_pack' => 1, 'sync' => '', );
			
			if (stristr($params['params']['html'], 'M')){
				$this->js[] = 'modules/cms/js/cms_input_image.js';
			}

		}
		
		return $params;

	}

}
