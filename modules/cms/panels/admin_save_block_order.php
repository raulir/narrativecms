<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class admin_save_block_order extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$do = $this->input->post('do');
		if ($do == 'admin_save_block_order'){

			$this->load->model('cms_page_panel_model');

			$block_orders = $this->input->post('block_orders');

			$this->cms_page_panel_model->save_orders($block_orders);

		}
	}

}
