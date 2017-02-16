<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_menu extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		$menu_items = array();
		foreach($GLOBALS['config']['modules'] as $module){
			$filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/config.json';
			if (file_exists($filename)){
				$json_data = file_get_contents($filename);
				$structure = json_decode($json_data, true);
				if (!empty($structure['cms_menu'])){
						
					$menu_items = array_merge($menu_items, $structure['cms_menu']);

				}
			}
		}

		$return['menu_items'] = array();
		$return['children'] = array();
		foreach($menu_items as $menu_item){
				
			$found = true;
				
			// check if user has access rights
			if (!empty($menu_item['access'])){
				$found = false;
				foreach($_SESSION['cms_user']['access'] as $access){
						
					if (preg_match('/'.str_replace('*', '.*?', $access).'/', $menu_item['access'])){
						$found = true;
					}
						
				}

			}
				
			if ($found){

				if (empty($menu_item['parent'])){
					if (empty($return['menu_items'][$menu_item['id']])){
						$return['menu_items'][$menu_item['id']] = $menu_item;
					}
				} else {
					if (!isset($return['children'][$menu_item['parent']])){
						$return['children'][$menu_item['parent']] = array();
					}
					$return['children'][$menu_item['parent']][] = $menu_item;
				}

			}
				
		}

		// remove lonely parents
		foreach($return['menu_items'] as $key => $value){
			if (empty($return['children'][$key]) && empty($value['url'])){
				unset($return['menu_items'][$key]);
			}
		}

		return $return;

	}

}