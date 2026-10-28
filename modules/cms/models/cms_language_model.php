<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('array_key_first') && !function_exists(__NAMESPACE__.'\\array_key_first')) {
	function array_key_first(array $arr) {
		foreach($arr as $key => $unused) {
			return $key;
		}
		return NULL;
	}
}

class cms_language_model extends \Model {

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
	
	/**
	 * Frontend / visitor language (session). Never use for CMS admin UI content.
	 */
	function get_current_language(){

		if (!empty($_SESSION['language']) && is_string($_SESSION['language'])){
			return $_SESSION['language'];
		}

		if (!empty($GLOBALS['language']['language_id'])){
			return $GLOBALS['language']['language_id'];
		}

		return $this->get_default();

	}
	
	/**
	 * CMS admin working language (session). Independent of visitor cookie.
	 */
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

	/**
	 * True for CMS admin UI requests only (not public site, even if admin is logged in).
	 * Flag set by admin controller; also URI admin/* or ajax with Referer /admin/.
	 */
	function is_cms_admin_request(){

		if (!empty($GLOBALS['cms_admin_request'])){
			return true;
		}

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			return false;
		}

		$uri = $GLOBALS['cms_request_uri'] ?? '';
		if ($uri !== '' && (strpos($uri, 'admin') === 0 || strpos($uri, 'admin/') === 0)){
			return true;
		}

		$ref = $_SERVER['HTTP_REFERER'] ?? '';
		if ($ref !== '' && preg_match('#/admin(/|$|\?)#', $ref)){
			return true;
		}

		return false;

	}

	/**
	 * Content language for this request: CMS session in admin UI, visitor session on site.
	 * Keeps admin and frontend language environments separate.
	 */
	function get_content_language(){

		if ($this->is_cms_admin_request()){
			return $this->get_cms_language();
		}

		return $this->get_current_language();

	}

	function language_is_live($language_id){

		$language_id = $this->resolve_language_id($language_id, $GLOBALS['language']['languages'] ?? []);

		if ($language_id === false){
			return false;
		}

		$CI =& get_instance();
		$CI->load->model('cms/cms_page_panel_model');
		$settings = $CI->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_languages');
		$rows = $settings['languages'] ?? [];

		if (!is_array($rows)){
			return false;
		}

		foreach ($rows as $row){
			if (!is_array($row)){
				continue;
			}
			$resolved = $this->resolve_language_id($row['language_id'] ?? '', $GLOBALS['language']['languages'] ?? []);
			if ($resolved === $language_id){
				return (string)($row['livestatus'] ?? '') === '1';
			}
		}

		return false;

	}

	function user_can_see_test_language(){

		if (!empty($_SESSION['cms_user']['cms_user_id'])){
			return true;
		}

		$CI =& get_instance();
		$CI->load->model('cms/cms_access_model');

		return $CI->cms_access_model->user_has_access('music_debug')
				|| $CI->cms_access_model->user_has_access('cms_translate');

	}

	function get_language_endonyms(){

		$allowed = $GLOBALS['language']['languages'] ?? [];

		if (!is_array($allowed) || !$allowed){
			return [];
		}

		$CI =& get_instance();
		$CI->load->model('cms/cms_page_panel_model');

		$cms_languages = $CI->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_languages');
		$rows = $cms_languages['languages'] ?? [];

		if (!is_array($rows)){
			$rows = [];
		}

		$endonym_by_id = [];

		foreach ($rows as $row){
			if (!is_array($row)){
				continue;
			}
			$language_id = $this->resolve_language_id($row['language_id'] ?? '', $allowed);
			if ($language_id === false){
				continue;
			}
			$endonym_by_id[$language_id] = trim((string)($row['endonym'] ?? ''));
		}

		$endonyms = [];

		foreach ($allowed as $language_id => $canonical_label){

			$endonym = $endonym_by_id[$language_id] ?? '';

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
