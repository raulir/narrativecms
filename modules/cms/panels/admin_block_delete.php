<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class admin_block_delete extends MY_Controller{

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
		if ($do == 'admin_block_delete'){
			 
			$block_id = $this->input->post('block_id');
			 
			$this->load->model('cms_page_panel_model');

			$this->cms_page_panel_model->delete_cms_page_panel($block_id);
			 
		}

	}

}
