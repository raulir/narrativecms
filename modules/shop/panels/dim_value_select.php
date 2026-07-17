<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class dim_value_select extends \Controller{
	
	function __construct(){
	
		parent::__construct();
	
		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
	
	}
	
	function panel_action($params){
		
		$do = $this->input->post('do');
		
		if ($do == 'set_dim'){
		
			$item_id = $this->input->post('item_id');
			$dimension = $this->input->post('dimension');
			$value = $this->input->post('value');
		
			$this->load->model('cms/cms_page_panel_model');
			
			$item = $this->cms_page_panel_model->get_cms_page_panel($item_id);
			
			$new_dims = [];
			$updated = false;
			foreach(($item['dimensions'] ?? []) as $dim_k => $dim_v){
				if (!is_string($dim_v['value'] ?? null)){
					$dim_v['value'] = '=';
				}
				list($dim_str, $dim_value_str) = explode('=', $dim_v['value']);
				if ($dim_str == $dimension){
					$new_dims[$dim_k] = ['value' => $dimension.'='.$value];
					$updated = true;
				} else {
					$new_dims[$dim_k] = $dim_v;
				}
			}
			
			if (!$updated){
				$new_dims[] = ['value' => $dimension.'='.$value];
			}
		
			// save data
			$this->cms_page_panel_model->update_cms_page_panel($item_id, ['dimensions' => $new_dims]);
		
		}
		
		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$item = $this->cms_page_panel_model->get_cms_page_panel($params['item_id']);
		
		$params['current'] = '';
		foreach(($item['dimensions'] ?? []) as $dim){
			if (is_string($dim['value'] ?? null) && stristr($dim['value'], $params['dimension'].'=')){
				$params['current'] = $dim['value'];
			}
		}
		
		$params['current'] = str_replace($params['dimension'].'=', '', $params['current']);
		
		$params['available'] = [];
		
		$dimension_a = $this->cms_page_panel_model->get_list('shop/product_dimension', ['id' => $params['dimension']]);
		$dimension = array_pop($dimension_a);
		if (!empty($dimension['values']) && is_array($dimension['values'])){
			foreach($dimension['values'] as $value){
				$params['available'][$value['id']] = $value['label'];
			}
		}
		
		return $params;
	
	}
	
}
