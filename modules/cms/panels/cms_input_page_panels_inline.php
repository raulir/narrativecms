<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_page_panels_inline extends \Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		
	}

	function panel_params($params){
				
		$params['cms_page_panels'] = [];

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');

		if (!is_array($params['value'])){
			$params['value'] = explode(',', $params['value']);
		}

		// check if panels exist
		foreach($params['value'] as $key => $cms_page_panel_id){
			$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
			if (is_array($panel)){
				$params['cms_page_panels'][] = $panel;
			} else {
				unset($params['value'][$key]);
			}
		}
		
		$params['value'] = implode(',', $params['value']);

		// check for shortcuts
		foreach($params['cms_page_panels'] as $key => $block){

			if (is_numeric($block['panel_name']) && (int)$block['panel_name'] == $block['panel_name']){
				
				$target_page_panel = $this->cms_page_panel_model->get_cms_page_panels_by([
						'cms_page_panel_id' => $block['panel_name'],
						'_fields' => ['cms_page_panel_id', 'panel_name', 'title', 'cms_page_id', 'sort', 'show'],
				]);
				$target_page_panel = !empty($target_page_panel[0]) ? $target_page_panel[0] : [];
				$target_page = !empty($target_page_panel['cms_page_id']) ? $this->cms_page_model->get_page($target_page_panel['cms_page_id']) : [];
				$shortcut_title = '> ' . ( !empty($target_page['title']) ? $target_page['title'] : '[ no title ]')
				. ' > ' . $this->cms_page_panel_model->get_panel_admin_title($target_page_panel);
				$params['cms_page_panels'][$key] = $block;
				$params['cms_page_panels'][$key]['title'] = $this->cms_page_panel_model->get_panel_admin_title($block).$shortcut_title;
				$params['cms_page_panels'][$key]['_delete'] = 1;
				
				$params['cms_page_panels'][$key]['_goto'] = 1;
				$params['cms_page_panels'][$key]['goto_id'] = $block['panel_name'];
				
			} else {
				
				$params['cms_page_panels'][$key] = $block;
				$params['cms_page_panels'][$key]['title'] = $this->cms_page_panel_model->get_panel_admin_title($block);
				$params['cms_page_panels'][$key]['_edit'] = 1;
			
			}
		}
		
		return $params;

	}

}
