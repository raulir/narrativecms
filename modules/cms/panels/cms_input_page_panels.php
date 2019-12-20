<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_page_panels extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_page_panel_button_show.js';
		
	}

	function panel_params($params){

		$params['cms_page_panels'] = [];

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');

		if (!is_array($params['value'])){
			$params['value'] = explode(',', $params['value']);
		}

		// check if panels exist
		$bad_ids = array();
		$good_ids = array();
		foreach($params['value'] as $block_id){
			$panel = $this->cms_page_panel_model->get_cms_page_panel($block_id);
			if (is_array($panel)){
				$params['cms_page_panels'][] = $panel;
			}
		}

		// check for shortcuts
		foreach($params['cms_page_panels'] as $key => $block){
			if (is_numeric($block['panel_name']) && (int)$block['panel_name'] == $block['panel_name']){
				
				$target_page_panel = $this->cms_page_panel_model->get_cms_page_panel($block['panel_name']);
				$target_page = $this->cms_page_model->get_page($target_page_panel['cms_page_id']);
				$params['cms_page_panels'][$key]['title'] = '> ' . ( !empty($target_page['title']) ? $target_page['title'] : '[ no title ]')
				. ' > ' . $target_page_panel['title'];
				$params['cms_page_panels'][$key]['_delete'] = 1;
				
				$params['cms_page_panels'][$key]['_goto'] = 1;
				$params['cms_page_panels'][$key]['goto_id'] = $block['panel_name'];
				
			} else {
				$params['cms_page_panels'][$key]['_edit'] = 1;
			}
		}

		if (!isset($params['cms_page_id']) && isset($params['page_id'])){
			$params['cms_page_id'] = $params['page_id'];
		}
		if (!isset($params['cms_page_panel_id']) && isset($params['block_id'])){
			$params['cms_page_panel_id'] = $params['block_id'];
		}
		
		return $params;

	}

}
