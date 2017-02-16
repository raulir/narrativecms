<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_button_show extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		$this->load->model('cms_page_panel_model');

		$params['cms_page_panel'] = $this->cms_page_panel_model->get_cms_page_panel($params['cms_page_panel_id']);

		return $params;

	}

}
