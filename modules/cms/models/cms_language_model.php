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

	function normalise_language_id($language_id){

		return strtolower(trim((string)$language_id));

	}

	function resolve_language_id($language_id, $allowed_languages){

		if ($language_id === '' || $language_id === null || !is_array($allowed_languages)){
			return false;
		}

		if (isset($allowed_languages[$language_id])){
			return $language_id;
		}

		$normalised = $this->normalise_language_id($language_id);
		foreach($allowed_languages as $allowed_id => $unused){
			if ($this->normalise_language_id($allowed_id) === $normalised){
				return $allowed_id;
			}
		}

		return false;

	}

	function resolve_translation_branch($language_id, $translations){

		if (!is_array($translations) || $language_id === '' || $language_id === false){
			return [];
		}

		if (!empty($translations[$language_id])){
			return $translations[$language_id];
		}

		$normalised = $this->normalise_language_id($language_id);
		foreach($translations as $key => $value){
			if ($this->normalise_language_id($key) === $normalised){
				return $value;
			}
		}

		return [];

	}
	
	// site default language
	function get_default(){
		
		if (!empty($GLOBALS['language']['languages']) && count($GLOBALS['language']['languages'])) {
			return array_key_first($GLOBALS['language']['languages']);
		}
		
		if (!empty($GLOBALS['config']['language'])){
			return $GLOBALS['config']['language'];
		}
		
		return 'en';
		
	}
	
	function get_current_language(){
	
		if (!empty($_COOKIE['language'])){
			return $_COOKIE['language'];
		}
	
		return $this->get_default();
	
	}
	
	function get_cms_language(){
	
		if (!empty($_SESSION['cms_language']) && is_string($_SESSION['cms_language'])){
			return $_SESSION['cms_language'];
		}

		if (!empty($_SESSION['cms_language']) && is_array($_SESSION['cms_language'])){
			unset($_SESSION['cms_language']);
		}
		
		if (!empty($GLOBALS['language']['languages']) && count($GLOBALS['language']['languages'])) {
			$default_id = !empty($GLOBALS['language']['default']) ? $GLOBALS['language']['default'] : array_key_first($GLOBALS['language']['languages']);
			$_SESSION['cms_language'] = $default_id;
			return $default_id;
		}
		
		$_SESSION['cms_language'] = 'en';
		return 'en';
	
	}

}
