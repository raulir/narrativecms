<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class regionselector extends CI_Controller {
	
	function panel_action($params){
	
		$do = $this->input->post('do');
	
		if ($do == 'region_set'){
	
			$region_id = $this->input->post('region_id');
			
			$found = false;
			foreach($params['regions'] as $region){
				if ($region['region_id'] == $region_id){
					$found = true;
				}
			}
			
			if ($found || $region_id == ''){
	
				$_SESSION['targets']['region'] = $region_id;
	
				print(json_encode(['result' => 'ok'], JSON_PRETTY_PRINT));

				die();
					
			}
	
		}
	
		return $params;
	
	}

	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('localisation/location_model');
		
		$params['active_region'] = $this->location_model->get_country_by_ip();
		
		$found = false;
		foreach($params['regions'] as $region){
			if ($region['region_id'] == $params['active_region']){
				$found = true;
			}
		}
		if (!$found){
			$params['active_region'] = '';
		}

		// reorder regions active to top
		uasort($params['regions'], function($a, $b) use ($params){
			if($a['region_id'] == $params['active_region']) return -1;
			return 1;
		});
		
		return $params;

	}
	
}
