<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_list_link extends CI_Controller {
	
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
		if($do == 'get'){
			
			$this->load->model('cms/cms_page_panel_model');
			
			$cms_page_panel = $this->cms_page_panel_model->get_cms_page_panel($params['item_id']);

			$return['link'] = _l($cms_page_panel['panel_name'].'='.$params['item_id'], false);
			
			return $return;
			
		}
		
		return $params;
		
	}

}
