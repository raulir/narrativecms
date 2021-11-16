<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class location_model extends Model {
	
	function get_country_by_ip(){
//		return 'GB';
		if (isset($_SESSION['targets']['region'])){
			return $_SESSION['targets']['region'];
		}
		
		$ip = $_SERVER['REMOTE_ADDR'];
		
		$filename = $GLOBALS['config']['base_path'] . 'cache/location_ip_' . md5($ip) . '.json';
		
		if (!file_exists($filename) || time()-filemtime($filename) > 10000000) {
		
			$key = '9f7b41c6b81947c96fcb990dd4f436aa';
		
			$url = 'http://api.ipstack.com/'.$ip.'?access_key='.$key.'&format=1';
				
			$data = file_get_contents($url);
				
			$data = json_decode($data, true);
				
			file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
				
		}
		
		$data = json_decode(file_get_contents($filename), true);
		
		if (empty($data['country_code'])){
			$_SESSION['targets']['region'] = 'GB';
			return 'GB';
		}
		
		$_SESSION['targets']['region'] = $data['country_code'];
		
		return $data['country_code'];

	}
	
}
