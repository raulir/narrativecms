<?php

namespace search;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Settings control: purge definition HTML cache for the search module (cache/search/).
 */
class search_cms_purge extends \Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params = []){

		if (!is_array($params)){
			$params = [];
		}

		$do = '';
		if (is_object($this->input) && method_exists($this->input, 'post')){
			$do = $this->input->post('do');
		}
		if ($do === null || $do === false || $do === ''){
			$do = $params['do'] ?? ($_POST['do'] ?? '');
		}

		if ($do === 'purge'){
			$params['result'] = $this->_purge_search_cache();
		}

		return $params;

	}

	function panel_params($params){

		add_css('modules/search/css/search_cms_purge.scss');

		return $params;

	}

	/**
	 * Delete all HTML under cache/search/ (definition panel cache for search module).
	 */
	function _purge_search_cache(){

		$dir = $GLOBALS['config']['base_path'].'cache/search/';
		$deleted = 0;
		$errors = 0;

		if (!is_dir($dir)){
			return [
					'deleted' => 0,
					'text' => 'No search cache directory',
			];
		}

		$deleted = $this->_rrmdir_contents($dir, $errors);

		$text = 'Purged '.$deleted.' file'.($deleted === 1 ? '' : 's');
		if ($errors > 0){
			$text .= ' ('.$errors.' error'.($errors === 1 ? '' : 's').')';
		}

		return [
				'deleted' => $deleted,
				'errors' => $errors,
				'text' => $text,
		];

	}

	/**
	 * Remove files and subdirs under $dir; keep $dir itself. Returns file count deleted.
	 */
	function _rrmdir_contents($dir, &$errors = 0){

		$deleted = 0;
		$dir = rtrim($dir, '/\\').DIRECTORY_SEPARATOR;

		$items = @scandir($dir);
		if (!is_array($items)){
			$errors++;
			return 0;
		}

		foreach ($items as $item){
			if ($item === '.' || $item === '..'){
				continue;
			}
			$path = $dir.$item;
			if (is_dir($path)){
				$deleted += $this->_rrmdir_contents($path, $errors);
				if (!@rmdir($path)){
					$errors++;
				}
			} else {
				if (@unlink($path)){
					$deleted++;
				} else {
					$errors++;
				}
			}
		}

		return $deleted;

	}

}
