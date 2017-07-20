<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_import extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}
	
	function panel_action($params){

		$this->load->model('cms_page_panel_model');
		$this->load->model('cms_panel_model');
		$this->load->model('cms_image_model');
		$this->load->model('cms_file_model');
		
		$do = $this->input->post('do');

		if ($do == 'cms_page_panel_import'){
			
			// receive file
			
			
			// stats
			$params['time'] = 0;
			$params['panels'] = 0;
			$params['images'] = 0;
			$params['files'] = 0;
			$params['new_images_size'] = 0;
			$params['new_images_count'] = 0;
			
			return $params;

		}
		
		return $params;

	}
	
}
