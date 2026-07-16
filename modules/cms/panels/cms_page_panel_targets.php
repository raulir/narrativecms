<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_targets extends \Controller {

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

		if ($do == 'cms_page_panel_targets'){
			
			$cms_page_panel_id = (int)$this->input->post('targets_id');
			$data = $this->input->post('data');

			if (!is_array($data)){
				$data = [];
			}

			$this->load->model('cms/cms_page_panel_model');
			$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, ['_targets' => $data]);

			$page_panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id, $this->cms_page_panel_model->get_cms_language());
			$params['_title'] = $this->cms_page_panel_model->get_panel_admin_title($page_panel);
			
		}
		
		return $params;

	}

	function _targets_group_values($group){

		$values = [];

		if (($group['strategy'] ?? '') === 'language'){

			$labels = array_map('trim', explode('|', $group['labels'] ?? ''));
			$settings = array_map('trim', explode('|', $group['settings'] ?? ''));

			foreach($settings as $key => $language_id){
				if ($language_id === ''){
					continue;
				}
				$values[] = [
						'id' => $language_id,
						'label' => $labels[$key] ?? $language_id,
				];
			}

		} else {

			foreach(array_map('trim', explode('|', $group['labels'] ?? '')) as $label){
				if ($label === ''){
					continue;
				}
				$values[] = [
						'id' => $label,
						'label' => $label,
				];
			}

		}

		return $values;

	}
	
	function panel_params($params){
		
		$cms_page_panel_id = $this->input->post('targets_id');
		
		$this->load->model('cms/cms_page_panel_model');
		
		$params['page_panel'] = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id, $this->cms_page_panel_model->get_cms_language());
		
		// get targets configuration
		$params['groups'] = [];
		if (!empty($_SESSION['config']['targets']['groups'])){
			
			foreach($_SESSION['config']['targets']['groups'] as $group){
				
				$params['groups'][] = [
						'heading' => $group['heading'],
						'values' => $this->_targets_group_values($group),
						'selected' => '',
						'strategy' => $group['strategy'],
				];
			
			}
			
		} else {
			
			$params['message'] = 'No groups defined in "CMS" -> "Target groups"';
		
		}
		
		// get current values for page panel
		foreach($params['groups'] as $key => $group){
			if (!empty($params['page_panel']['_targets'][$group['heading']])){
				
				$params['groups'][$key]['selected'] = $params['page_panel']['_targets'][$group['heading']];
			
			}
		}
		
		return $params;
		
	}

}