<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_menu extends \Controller {

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

	/**
	 * Merge two cms_menu item definitions by id. Later non-empty fields win;
	 * empty/null on the incoming side does not wipe an existing value.
	 */
	function _menu_merge_item($existing, $incoming){

		if (!is_array($existing) || $existing === []){
			return is_array($incoming) ? $incoming : [];
		}
		if (!is_array($incoming) || $incoming === []){
			return $existing;
		}

		foreach($incoming as $key => $value){
			if ($key === 'id'){
				continue;
			}
			if ($value === null || $value === ''){
				continue;
			}
			$existing[$key] = $value;
		}

		if (!empty($incoming['id'])){
			$existing['id'] = $incoming['id'];
		}

		return $existing;

	}

	/**
	 * Whether item has a keyboard ctrl shortcut assigned (top-level keyboard nav).
	 */
	function _menu_has_ctrl($item){

		if (!is_array($item) || !array_key_exists('ctrl', $item)){
			return false;
		}
		$ctrl = $item['ctrl'];
		return $ctrl !== null && $ctrl !== false && $ctrl !== '';

	}

	/**
	 * Sort menu siblings: submenu groups (items that have children) first, then
	 * direct links; within each band sort by order ascending.
	 *
	 * @param array $items list of menu item arrays (with id, order)
	 * @param array $children_map parent_id => children[]
	 * @return array reindexed list
	 */
	function _menu_sort_siblings($items, $children_map){

		if (!is_array($items) || $items === []){
			return is_array($items) ? $items : [];
		}

		usort($items, function($a, $b) use ($children_map){
			$a_id = $a['id'] ?? '';
			$b_id = $b['id'] ?? '';
			$a_sub = ($a_id !== '' && !empty($children_map[$a_id]));
			$b_sub = ($b_id !== '' && !empty($children_map[$b_id]));
			if ($a_sub !== $b_sub){
				return $a_sub ? -1 : 1;
			}
			return ((int)($a['order'] ?? 9999)) <=> ((int)($b['order'] ?? 9999));
		});

		return array_values($items);

	}

	/**
	 * Top-level only: items with ctrl key first (stable by order), then remaining
	 * via submenu-first + order (Shop, Forms, … after Pages/Content/CMS/Tools/…).
	 *
	 * @param array $items list of top-level menu items
	 * @param array $children_map parent_id => children[]
	 * @return array reindexed list
	 */
	function _menu_sort_top_level($items, $children_map){

		if (!is_array($items) || $items === []){
			return is_array($items) ? $items : [];
		}

		$with_ctrl = [];
		$without_ctrl = [];
		foreach($items as $item){
			if ($this->_menu_has_ctrl($item)){
				$with_ctrl[] = $item;
			} else {
				$without_ctrl[] = $item;
			}
		}

		// Ctrl-assigned: keep configured order only (do not reorder by submenu vs link)
		usort($with_ctrl, function($a, $b){
			return ((int)($a['order'] ?? 9999)) <=> ((int)($b['order'] ?? 9999));
		});

		$without_ctrl = $this->_menu_sort_siblings($without_ctrl, $children_map);

		return array_values(array_merge($with_ctrl, $without_ctrl));

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
				// Same id from another module — merge (redefine parent top-level safely)
				$flat[$id] = $this->_menu_merge_item($flat[$id], $item);
			}

		}

		// If a child references a missing parent, ensure a structural parent exists
		// (modules should also redefine top-level parents for name/order/ctrl merge)
		$ensure_parents = true;
		while ($ensure_parents){
			$ensure_parents = false;
			foreach($flat as $id => $item){
				if (!$this->_menu_parent_active($item['parent'] ?? null)){
					continue;
				}
				$parent = $item['parent'];
				if (!empty($flat[$parent])){
					continue;
				}
				$flat[$parent] = [
						'id' => $parent,
						'name' => $parent,
						'order' => 9000,
				];
				$ensure_parents = true;
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

		// Prune mid-level parents (L2+) that have no children left after access/hide filters
		// Walk deepest-first so empty L3 parents drop before their L2 group is cleaned
		$changed = true;
		while ($changed){
			$changed = false;
			foreach($return['children'] as $parent_id => $group){
				foreach($group as $gkey => $child){
					$child_id = $child['id'] ?? '';
					if ($child_id === ''){
						continue;
					}
					// Child is a structural parent (no url) but has no descendants
					if (empty($child['url']) && empty($return['children'][$child_id])){
						unset($return['children'][$parent_id][$gkey]);
						$changed = true;
					}
				}
				if (isset($return['children'][$parent_id])){
					$return['children'][$parent_id] = array_values($return['children'][$parent_id]);
					if (empty($return['children'][$parent_id])){
						unset($return['children'][$parent_id]);
						$changed = true;
					}
				}
			}
			// Re-prune top-level empty parents
			foreach($return['menu_items'] as $key => $value){
				if (empty($return['children'][$key]) && empty($value['url'])){
					unset($return['menu_items'][$key]);
					$changed = true;
				}
			}
		}

		// Top level: ctrl-assigned items first (by order), then rest (submenu-first + order)
		$return['menu_items'] = $this->_menu_sort_top_level(
				array_values($return['menu_items']),
				$return['children']
		);
		$keyed = [];
		foreach($return['menu_items'] as $item){
			if (!empty($item['id'])){
				$keyed[$item['id']] = $item;
			}
		}
		$return['menu_items'] = $keyed;

		// Nested levels: submenu groups before direct links, then by order
		foreach($return['children'] as $parent_id => $group){
			$return['children'][$parent_id] = $this->_menu_sort_siblings($group, $return['children']);
		}

		return $return;

	}

}