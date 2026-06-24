<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_menu extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function _menu_hide_active($value){

		if (!isset($value)){
			return false;
		}

		return $value !== false && $value !== 0 && $value !== '0';

	}

	function _menu_parent_active($parent){

		if (!isset($parent)){
			return false;
		}

		if ($parent === false || $parent === 0 || $parent === '0' || $parent === ''){
			return false;
		}

		return true;

	}

	function _menu_user_has_access($menu_item){

		if (empty($menu_item['access'])){
			return true;
		}

		$this->load->model('cms/cms_access_model');

		foreach($_SESSION['cms_user']['access'] as $access){
			if ($this->cms_access_model->_access_key_matches($access, $menu_item['access'])){
				return true;
			}
		}

		return false;

	}

	function panel_params($params){
		
		$this->load->model('cms/cms_module_model');
		
		$source_items = [];

		foreach($GLOBALS['config']['modules'] as $module){
			
			$config = $this->cms_module_model->get_module_config($module);

			if (!empty($config['cms_menu'])){
				foreach($config['cms_menu'] as $item){
					$source_items[] = $item;
				}
			}

		}

		$flat = [];
		foreach($source_items as $item){

			if (empty($item['id'])){
				continue;
			}

			$id = $item['id'];
			if (empty($flat[$id])){
				$flat[$id] = $item;
			} else {
				$flat[$id] = array_merge($flat[$id], $item);
			}

		}

		$default_order = 9000;
		foreach($flat as $id => &$item){
			if (!isset($item['order'])){
				$item['order'] = $default_order;
				$default_order += 10;
			}
		}
		unset($item);

		foreach($flat as $id => $item){
			if (!$this->_menu_user_has_access($item)){
				unset($flat[$id]);
			}
		}

		foreach($flat as $id => $item){

			if ($this->_menu_hide_active($item['hide'] ?? null)){
				unset($flat[$id]);
				continue;
			}

			unset($flat[$id]['hide']);

		}

		$return['menu_items'] = [];
		$return['children'] = [];

		foreach($flat as $id => $item){

			if ($this->_menu_parent_active($item['parent'] ?? null)){

				$parent = $item['parent'];
				if (empty($flat[$parent])){
					continue;
				}

				if (!isset($return['children'][$parent])){
					$return['children'][$parent] = [];
				}
				$return['children'][$parent][] = $item;

			} else {

				unset($item['parent']);
				$return['menu_items'][$id] = $item;

			}

		}

		foreach($return['menu_items'] as $key => $value){
			if (empty($return['children'][$key]) && empty($value['url'])){
				unset($return['menu_items'][$key]);
			}
		}

		uasort($return['menu_items'], function($a, $b){
			return ((int)($a['order'] ?? 9999)) <=> ((int)($b['order'] ?? 9999));
		});

		foreach($return['children'] as $parent_id => &$group){
			usort($group, function($a, $b){
				return ((int)($a['order'] ?? 9999)) <=> ((int)($b['order'] ?? 9999));
			});
		}
		unset($group);

		return $return;

	}

}