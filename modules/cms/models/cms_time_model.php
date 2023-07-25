<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_time_model extends CI_Model {
	
	function get_time_by_coordinates($coordinates, $timestamp = 0){

		if ($timestamp == 0){
			$timestamp = time();
		}
		
		$coordinates_txt = trim(str_replace(array(' ', '-', ',', ), '_', $coordinates));
		$coordinates_url = trim(str_replace(array(' ', ), '', $coordinates));
		
		// get delta
		$filename = $GLOBALS['config']['base_path'] . 'cache/time_'.$coordinates_txt.'.json';
		
		if (!file_exists($filename) || time() - filemtime($filename) > 3600){
			
			// to avoid multiple updates during update
			if (file_exists($filename)){
				touch($filename);
			}

			$query_url = 'https://maps.googleapis.com/maps/api/timezone/json?location='.$coordinates_url.'&timestamp='.
					$timestamp.'&key='.$GLOBALS['config']['google_map_api_key'].'';
			
			$content = file_get_contents($query_url);
			$data = json_decode($content, true);
			
			if ($data['status'] == 'OK'){
				file_put_contents($filename, $content);
			}
	  		
	  	} else {
		
			$content = file_get_contents($filename);
			$data = json_decode($content, true);
	  	
	  	}
		
		return $data['dstOffset'] + $data['rawOffset'] + $timestamp;

	}
	
}
