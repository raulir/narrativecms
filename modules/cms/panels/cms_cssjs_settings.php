<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_cssjs_settings extends \Controller {
	
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

		if ($do === 'cms_cssjs_save'){

			$this->load->model('cms/cms_page_panel_model');

			$panels = $this->input->post('panels');

			$settings_a = $this->cms_page_panel_model->get_cms_page_panels_by([
					'panel_name' => 'cms/cms_cssjs_settings',
					'cms_page_id' => 0,
			]);

			if (!count($settings_a)){
				$cms_page_panel_id = $this->cms_page_panel_model->create_cms_page_panel([
						'panel_name' => 'cms/cms_cssjs_settings',
				]);
			} else {
				$cms_page_panel_id = $settings_a[0]['cms_page_panel_id'];
			}

			$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, [
					'css' => $panels,
			]);

			$cache_file = $GLOBALS['config']['base_path'].'cache/cms_cssjs_settings.json';
			if (file_exists($cache_file)){
				unlink($cache_file);
			}

		}

		return $params;

	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_module_model');
		$this->load->model('cms/cms_page_panel_model');
		
		// get current config
		$settings_a = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'cms/cms_cssjs_settings', 'cms_page_id' => 0, ]);
		if (!count($settings_a)){
			$this->cms_page_panel_model->create_cms_page_panel(['panel_name' => 'cms/cms_cssjs_settings', ]);
			$params['current_css'] = [];
		} else {
			if (empty($settings_a[0]['css'])){
				$params['current_css'] = [];
			} else {
				$params['current_css'] = $settings_a[0]['css'];
			}
		}

		// get possible options
		$params['css'] = [];

		$modules = $this->cms_module_model->get_modules();

		foreach($modules as $module){
			if ($module['active']){
				
				$config = $this->cms_module_model->get_module_config($module['name']);
				
				// load possible css panels
				foreach($config['panels'] as $panel){
					
					if(is_file($GLOBALS['config']['base_path'].'modules/'.$module['name'].'/css/'.$panel['id'].'.scss')){
						if (empty($params['current_css']['modules/'.$module['name'].'/css/'.$panel['id'].'.scss'])){
							$params['css']['modules/'.$module['name'].'/css/'.$panel['id'].'.scss'] = $module['name'].'/'.$panel['id'];
						}
					} elseif (is_file($GLOBALS['config']['base_path'].'modules/'.$module['name'].'/css/'.$panel['id'].'.css')){
						if (empty($params['current_css']['modules/'.$module['name'].'/css/'.$panel['id'].'.css'])){
							$params['css']['modules/'.$module['name'].'/css/'.$panel['id'].'.css'] = $module['name'].'/'.$panel['id'];
						}
					}
					
				}
				
			}
		}
		
		
		return $params;
		
	}

}
