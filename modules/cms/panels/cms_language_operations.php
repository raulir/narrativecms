<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_language_operations extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action(){

		$do = $this->input->post('do');

		if ($do == 'cms_language_set'){
			 
			$cms_language = $this->input->post('language');
			$this->load->model('cms/cms_language_model');
			$resolved_language = $this->cms_language_model->resolve_language_id($cms_language, $GLOBALS['language']['languages'] ?? []);
			$_SESSION['cms_language'] = $resolved_language !== false ? $resolved_language : $cms_language;
			 
		}

	}

}
