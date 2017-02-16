<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_list_save_order extends MY_Controller{

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
		if ($do == 'cms_list_save_order'){
			 
			$list_order = $this->input->post('list_order');
			 
			$this->load->model('cms_page_panel_model');
			 
			// get reusable sorts
			$previous_sort = array();
			foreach($list_order as $list_sort => $cms_page_panel_id){
				$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
				$previous_sort[] = $panel['sort'];
			}
			 
			sort($previous_sort);
			 
			// update panels referencing sorted previous sorts
			foreach($list_order as $list_sort => $cms_page_panel_id){
				$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, array('sort' => $previous_sort[$list_sort], ), true);
			}

		}

	}

}
