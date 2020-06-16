<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('array_key_first')) {
	function array_key_first(array $arr) {
		foreach($arr as $key => $unused) {
			return $key;
		}
		return NULL;
	}
}

class cms_language_model extends Model {
	
	// site default language
	function get_default(){
		
		if (!empty($GLOBALS['language']['languages']) && count($GLOBALS['language']['languages'])) {
			return array_key_first($GLOBALS['language']['languages']);
		}
		
		return 'en';
		
	}
	
	function get_cms_language(){
	
		if (!empty($_SESSION['cms_language'])){
			return $_SESSION['cms_language'];
		}
		
		if (!empty($GLOBALS['language']['languages']) && count($GLOBALS['language']['languages'])) {
			$_SESSION['cms_language'] = $GLOBALS['language']['languages'];
			return array_key_first($GLOBALS['language']['languages']);
		}
		
		$_SESSION['cms_language'] = 'en';
		return 'en';
	
	}
	
}
