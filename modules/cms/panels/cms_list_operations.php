<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_list_operations extends CI_Controller {

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

		if ($do == 'cms_list_set'){
			 
			$cms_page_panel_id = $this->input->post('id'); // to where the shortcut goes
			$field = $this->input->post('field');
			$value = $this->input->post('value');
			 
			$this->load->model('cms_page_panel_model');

			// save data
			$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, array( $field => $value, ), true);
			 
		}

	}

}
