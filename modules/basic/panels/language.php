<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class language extends CI_Controller {

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'language_set'){
			 
			$language_id = $this->input->post('language_id');

			if (!empty($GLOBALS['language']['languages'][$language_id])){
				
				$GLOBALS['language']['label'] = $GLOBALS['language']['languages'][$language_id];
				$GLOBALS['language']['language_id'] = $language_id;
				
				$this->load->helper('cookie_helper');
				cms_cookie_create('language', $language_id, 90);

				print(json_encode(['result' => 'ok'], JSON_PRETTY_PRINT));
				die();
			
			}
			 
		}
		
		return $params;

	}
	
	function panel_params($params){
		
		foreach($GLOBALS['language']['languages'] as $language_id => $language_name){
			$params['languages'][$language_id] = [
					'label' => $language_name,
					'icon' => '',
					'language_id' => $language_id,
			];
		}
		
		// reorder language settings
		foreach($params['language_settings'] as $setting){
			if (!empty($params['languages'][$setting['language_id']])){
				$params['languages'][$setting['language_id']] = $setting;
			}
		}

		$params['active_language'] = $GLOBALS['language']['language_id'];

		return $params;

	}
	
}
