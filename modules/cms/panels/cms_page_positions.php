<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_positions extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){
		
		// get list of positions on template
		$this->load->model('cms/cms_page_model');
		
		$current_page = $this->cms_page_model->get_page($params['cms_page_id']);
		unset($current_page['meta']);		
		
		$positions = $this->cms_page_model->get_layout_positions($params['cms_page_layout']);
		$positions = array_diff($positions, ['main']);
		
		// get available positions
		$system_positions = $this->cms_page_model->get_positions();
		
		// get available page partials
		$pages = $this->cms_page_model->get_cms_pages();

		$params['positions'] = [];
		foreach($system_positions as $position){
			
			if (in_array($position['id'], $positions)){
				
				// add values
				$position['values'] = [];
				foreach($pages as $page_partial){
					if ($page_partial['position'] == $position['id']){
						$position['values'][$page_partial['cms_page_id']] = $page_partial['title'];
					}
				}
				if (count($position['values']) == 0){
					$position['values'] = [0 => strtolower('-- no '.$position['plural'].' defined --')];
				} else {
					$position['values'] = [0 => strtolower('-- no '.$position['name'].' --')] + $position['values'];
				}
				
				// check if value is set
				$position['value'] = 0;
				if (!empty($current_page['positions'])){
					foreach($current_page['positions'] as $current_position){
						if ($current_position['name'] == $position['id']){
							$position['value'] = $current_position['value'];
						}
					}
				}

				$params['positions'][] = $position;

			}
			
		}

		return $params;
		
	}

}
