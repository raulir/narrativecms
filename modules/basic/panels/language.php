<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class language extends CI_Controller {

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'language_set'){
			 
			$language_id = $this->input->post('language_id');

			if (!empty($GLOBALS['language']['languages'][$language_id])){
				
				$GLOBALS['language']['label'] = $GLOBALS['language']['languages'][$language_id];
				$GLOBALS['language']['language_id'] = $language_id;

				setcookie('language', $language_id, time() + 10000000, '/');
				
				print(json_encode(['result' => 'ok'], JSON_PRETTY_PRINT));
				die();
			
			}
			 
		}
		
		return $params;

	}
	
	function panel_params($params){
		
		$params['language'] = $GLOBALS['language'];
		
		foreach($params['language_settings'] as $setting){
			$params['settings'][$setting['language_id']] = $setting;
		}
		
		return $params;
		
	}
	
}
