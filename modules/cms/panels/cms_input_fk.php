<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_fk extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_input_select.scss');
		
	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$add_empty = !empty($params['add_empty']) || !empty($params['mandatory']);
		
		if (!empty($params['list'])){
			
			$params['values'] = [];
			
			$list = $this->cms_page_panel_model->get_list($params['list'], ['show' => [0,1]]);

			// Prefer explicit label_field, then list title_field, then heading/_title/title
			$label_field = $params['label_field'] ?? '';
			if ($label_field === ''){
				$config = $this->cms_panel_model->get_cms_panel_config($params['list']);
				$label_field = $config['list']['title_field'] ?? 'heading';
			}

			foreach($list as $item_id => $item){
				$label = '';
				if ($label_field !== '' && isset($item[$label_field]) && (string)$item[$label_field] !== ''){
					$label = (string)$item[$label_field];
				} else if (!empty($item['heading'])){
					$label = (string)$item['heading'];
				} else if (!empty($item['_title'])){
					$label = (string)$item['_title'];
				} else if (!empty($item['title'])){
					$label = (string)$item['title'];
				} else {
					$label = '#'.(int)$item_id;
				}
				$params['values'][$item_id] = $label;
			}
			
			if ($add_empty){
				$params['values'] = ['' => '-- not specified --'] + $params['values'];
			}
			
			if (empty($params['values'])){
				$params['values'] = ['' => '-- no values --'];
			}
		
		} else {

			$fk_data = $this->cms_panel_model->get_cms_panel_fk_data($params['panel_structure']);
			
			$params['values'] = !empty($fk_data[$params['target']]) ? $fk_data[$params['target']] : ($fk_data[$params['name_clean']] ?? []);
			
			if (isset($params['values'][0]) && $params['values'][0] == '-- not specified --'){
				unset($params['values'][0]);
			}
			
			if ($add_empty){
				$params['values'] = ['' => '-- not specified --'] + $params['values'];
			}

		}

		$params['name_clean'] = str_replace('/', '_', $params['name_clean']);
		
		return $params;
	
	}

}
