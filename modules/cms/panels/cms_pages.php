<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_pages extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		$this->load->model('cms/cms_page_model');

		$pages = $this->cms_page_model->get_cms_pages();
		
		// get positions
		$return['positions'] = $this->cms_page_model->get_positions();
		
		$return['pages'] = [];
		
		foreach($pages as $page){
			
			if (empty($page['position'])){
				$page['position'] = 'main';
			}
			
			if (empty($return['pages'][$page['position']])){
				$return['pages'][$page['position']] = [];
			}
			
			$return['pages'][$page['position']][] = $page;
			
		}
		
		return $return;

	}

}
