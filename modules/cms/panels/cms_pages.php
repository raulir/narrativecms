<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_pages extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		$this->load->model('cms/cms_page_model');

		// Reserve list templates + system pages (empty shells)
		$this->cms_page_model->ensure_special_pages();

		$pages = $this->cms_page_model->get_cms_pages();

		// Right column: module layout positions (header/footer)
		$return['positions'] = $this->cms_page_model->get_positions();

		$return['pages'] = [];
		$return['main_pages'] = [
			'user' => [],
			'list' => [],
			'system' => [],
		];

		$landing_id = !empty($GLOBALS['config']['landing_page']['_value'])
			? (int)$GLOBALS['config']['landing_page']['_value']
			: 0;

		foreach($pages as $page){

			if (empty($page['position'])){
				$page['position'] = 'main';
			}

			if (empty($return['pages'][$page['position']])){
				$return['pages'][$page['position']] = [];
			}

			$return['pages'][$page['position']][] = $page;

			// Left column sections only for main pages
			if ($page['position'] === 'main'){
				$class = $this->cms_page_model->get_page_class($page);
				$return['main_pages'][$class][] = $page;
			}

		}

		// Alphabetical per section; landing page first in user Pages
		foreach ($return['main_pages'] as $class => $list){
			$return['main_pages'][$class] = $this->_sort_pages_alpha($list, $class === 'user' ? $landing_id : 0);
		}

		foreach ($return['pages'] as $position => $list){
			if ($position === 'main'){
				continue;
			}
			$return['pages'][$position] = $this->_sort_pages_alpha($list, 0);
		}

		return $return;

	}

	/**
	 * Sort by title (case-insensitive). Optional landing cms_page_id pinned first.
	 */
	function _sort_pages_alpha($pages, $landing_id = 0){

		if (!is_array($pages) || count($pages) < 2){
			return $pages;
		}

		usort($pages, function($a, $b) use ($landing_id){

			if ($landing_id){
				$a_land = ((int)($a['cms_page_id'] ?? 0) === $landing_id) ? 0 : 1;
				$b_land = ((int)($b['cms_page_id'] ?? 0) === $landing_id) ? 0 : 1;
				if ($a_land !== $b_land){
					return $a_land - $b_land;
				}
			}

			$ta = strtolower((string)($a['title'] ?? $a['slug'] ?? ''));
			$tb = strtolower((string)($b['title'] ?? $b['slug'] ?? ''));
			if ($ta === $tb){
				return ((int)($a['cms_page_id'] ?? 0)) - ((int)($b['cms_page_id'] ?? 0));
			}
			return $ta <=> $tb;

		});

		return $pages;

	}

}
