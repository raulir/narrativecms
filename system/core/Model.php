<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class CI_Model {

	function __get($key) {
		
		$CI =& get_instance();
		
		if (empty($CI->$key)){
			$trace = debug_backtrace(0, 1);
			print('Error: class '.$trace[0]['args'][0].' not loaded at '.$trace[0]['file'].':'.$trace[0]['file']);
		}
		
		return $CI->$key;
	}
}
