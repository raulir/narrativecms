<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class language extends CI_Controller {

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'language_set'){
			 
			$language_id = $this->input->post('language_id');
			$this->load->model('cms/cms_language_model');
			$resolved_language_id = $this->cms_language_model->resolve_language_id($language_id, $GLOBALS['language']['languages'] ?? []);

			if ($resolved_language_id !== false){
				
				$GLOBALS['language']['label'] = $GLOBALS['language']['languages'][$resolved_language_id];
				$GLOBALS['language']['language_id'] = $resolved_language_id;
				
				$this->load->helper('cookie_helper');
				cms_cookie_create('language', $resolved_language_id, 90);

				print(json_encode(['result' => 'ok'], JSON_PRETTY_PRINT));
				
				die();
			
			}
			 
		}
		
		return $params;

	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');

		$params['languages'] = [];
		foreach($GLOBALS['language']['languages'] as $language_id => $language_name){
			$params['languages'][$language_id] = [
					'label' => $language_name,
					'language_id' => $language_id,
			];
		}

		if (empty($params['select_label'])){
			$basic_settings = $this->cms_page_panel_model->get_cms_page_panel_settings('basic/language');
			if (!empty($basic_settings['select_label'])){
				$params['select_label'] = $basic_settings['select_label'];
			} else {
				$cms_languages = $this->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_languages');
				if (!empty($cms_languages['select_label'])){
					$params['select_label'] = $cms_languages['select_label'];
				}
			}
		}

		if (empty($params['select_label'])){
			$params['select_label'] = 'Select language:';
		}

		$params['active_language'] = $GLOBALS['language']['language_id'];

		return $params;

	}
	
}