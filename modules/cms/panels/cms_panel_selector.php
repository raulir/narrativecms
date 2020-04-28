<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_panel_selector extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
		
		add_css('cms/cms_input.scss');
		add_css('cms/cms_popup.scss');
		
		$GLOBALS['_panel_js'][] = [
				'script' => 'modules/cms/js/cms_popup.js',
				'sync' => 'defer', 
		];
		
	}

	function panel_params($params){
		
		$this->load->model('cms/cms_module_model');
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_page_model');
		
		$params['panel_types'] = [];
		$params['panel_replaced'] = [];
		
		foreach($GLOBALS['config']['modules'] as $module){
				
			$config = $this->cms_module_model->get_module_config($module);
		
			if (!empty($config['panels'])){
				foreach($config['panels'] as $panel){
					
					// get panel config
					$panel_config = $this->cms_panel_model->get_cms_panel_config($panel['id']);
		
					// add panel type to the dropdown of panel types
					$panel_id = $module.'/'.$panel['id'];
					if (!in_array($panel_id, $params['panel_replaced'])){
		
						$params['panel_types'][$panel_id] = [
								'label' => ucfirst($module) . ' / ' . $panel['name'],
								'image' => !empty($panel_config['image']) ? $panel_config['image'] : '',
								'description' => !empty($panel_config['description']) ? $panel_config['description'] : '',
						];
		
						// if showing the panel type hides some other panel type, like when extending the panel for project
						if (!empty($panel['hides'])){
							if (!empty($params['panel_types'][$panel['hides']])){
								unset($params['panel_types'][$panel['hides']]);
							}
							$params['panel_replaced'][$panel['hides']] = $panel['hides'];
						}
		
					}

				}
			}
		}

		if (!empty($params['block']['parent_id']) && !empty($params['parent_field_name'])){
		
			// if has parent, check parent config - get parent type
			$parent = $this->cms_page_panel_model->get_cms_page_panel($params['block']['parent_id']);
			$parent_structure = $this->cms_panel_model->get_cms_panel_definition($parent['panel_name']);
		
			foreach($parent_structure as $key => $field){
				if (!empty($field['name']) && $field['name'] == $params['parent_field_name']){
		
					$params['allowed_panels'] = $field['panels'];
		
				}
			}
		
			$params['parent_field_name'] = $params['parent_field_name'];
		
		}

		if (!empty($params['filter_panels'])){
			$allowed_panels = explode(',', $params['filter_panels']);
		
			foreach ($params['panel_types'] as $key => $value){
		
				$allowed = false;
		
				if (in_array($key, $allowed_panels)){
					$allowed =  true;
				}
		
				if (stristr($key, '/')){
					list($kmodule, $kpanel) = explode('/', $key);
					if (in_array($kpanel, $allowed_panels)){
						$allowed =  true;
					}
				}
		
				if (!$allowed){
					unset($params['panel_types'][$key]);
				}
		
			}
		}
		
		asort($params['panel_types']);
		
		// figure out used modules
		foreach ($params['panel_types'] as $key => $value){
			
			list($mmodule, $mpanel) = explode('/', $key);
			
			$params['modules'][$mmodule] = $mmodule;
			$params['panel_types'][$key]['module'] = $mmodule;
			
		}
		
		// get main module
		if (! function_exists('array_key_last')) {
			function array_key_last($array) {
				if (!is_array($array) || empty($array)) {
					return NULL;
				}
				 
				return array_keys($array)[count($array)-1];
			}
		}
		
		$params['main_module'] = $GLOBALS['config']['modules'][array_key_last($GLOBALS['config']['modules'])];
		
		// shortcuts
		if ($params['target_type'] == 'page'){
		
			$params['shortcuts'] = [];
			// get all possible panels on pages
			$pages = $this->cms_page_model->get_cms_pages();
		
			foreach($pages as $page){
				$blocks = $this->cms_page_panel_model->get_cms_page_panels_by(array('cms_page_id' => $page['cms_page_id'], ));
				foreach ($blocks as $block){
					if ($block['title'] !== ''){
						$params['shortcuts'][$block['cms_page_panel_id']] = $page['title'].' > '.$block['title'];
					}
				}
			}
		
		}

		return $params;
		
	}

}
