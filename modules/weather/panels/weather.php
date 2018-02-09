<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class weather extends MY_Controller{
	
	function panel_action($params){
	
		$do = $this->input->post('do');
		if ($do == 'get_weather'){
			
			$this->load->model('cms_page_panel_model');
			$this->load->model('weather_model');
				
			$params = $this->cms_page_panel_model->get_cms_page_panel_settings('jmeel/weather');
			$weather = $this->weather_model->get_weather_by_coordinates($params['coordinates'], $params['weather_api_key']);
			
			print(json_encode(array('weather' => round($weather['main']['temp'] - 273.15) . '&deg; ' . $weather['weather'][0]['description'], )));
			exit();
		
		}
	
	}

	function panel_params($params){
		
		$this->load->model('cms_time_model');
		$this->load->model('weather_model');
		
		$params['time'] = $this->cms_time_model->get_time_by_coordinates($params['coordinates']);
		
		$params['weather'] = $this->weather_model->get_weather_by_coordinates($params['coordinates'], $params['weather_api_key']);
		
		return $params;

	}

}
