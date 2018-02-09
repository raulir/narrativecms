<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class weather_model extends CI_Model {
	
	function get_weather_by_coordinates($coordinates, $api_key){


		$coordinates_txt = trim(str_replace(array(' ', '-', ',', ), '_', $coordinates));
		$coordinates_url = trim(str_replace(array(' ', ), '', $coordinates));
		
		list($lat, $lon) = explode(',', $coordinates_url);
		
		// get delta
		$filename = $GLOBALS['config']['base_path'] . 'cache/weather_'.$coordinates_txt.'.json';
		
		if (!file_exists($filename) || time() - filemtime($filename) > 3600){
			
			// to avoid multiple updates during update
			if (file_exists($filename)){
				touch($filename);
			}

			$query_url = 'http://api.openweathermap.org/data/2.5/weather?lat='.$lat.'&lon='.$lon.'&appid='.$api_key;

			$content = file_get_contents($query_url);
			$data = json_decode($content, true);
			
			file_put_contents($filename, $content);
	  		
	  	} else {
		
			$content = file_get_contents($filename);
			$data = json_decode($content, true);
	  	
	  	}
		
		return $data;

	}
	
}
