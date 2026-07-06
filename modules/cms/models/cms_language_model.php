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

	function get_language_endonyms(){

		$allowed = $GLOBALS['language']['languages'] ?? [];

		if (!is_array($allowed) || !$allowed){
			return [];
		}

		$CI =& get_instance();
		$CI->load->model('cms/cms_page_panel_model');

		$panels = $CI->cms_page_panel_model->get_cms_page_panels_by([
			'panel_name' => 'cms/cms_languages',
			'cms_page_id' => 0,
			'parent_id' => 0,
			'sort' => 0,
		]);

		$translations = [];

		if (!empty($panels[0]['cms_page_panel_id'])){
			$panel = $CI->cms_page_panel_model->get_cms_page_panel($panels[0]['cms_page_panel_id']);
			$translations = $panel['_translations'] ?? [];
			if (!is_array($translations)){
				$translations = [];
			}
		}

		$endonyms = [];

		foreach ($allowed as $language_id => $canonical_label){

			$branch = $this->resolve_translation_branch($language_id, $translations);
			$local_labels = $branch['local_labels'] ?? [];

			if (!is_array($local_labels)){
				$local_labels = [];
			}

			$endonym = '';

			if (isset($local_labels[$language_id]) && trim((string)$local_labels[$language_id]) !== ''){
				$endonym = trim((string)$local_labels[$language_id]);
			} else {
				$normalised = $this->normalise_language_id($language_id);
				foreach ($local_labels as $key => $value){
					if ($this->normalise_language_id($key) === $normalised && trim((string)$value) !== ''){
						$endonym = trim((string)$value);
						break;
					}
				}
			}

			if ($endonym === ''){
				$endonym = is_string($canonical_label) ? trim($canonical_label) : '';
			}

			if ($endonym === ''){
				$endonym = (string)$language_id;
			}

			$endonyms[$language_id] = $endonym;

		}

		return $endonyms;

	}

}
