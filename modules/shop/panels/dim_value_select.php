<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class dim_value_select extends \Controller{

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'set_dim'){

			$item_id = $this->input->post('item_id');
			$dim_id = (int)$this->input->post('dim');
			$value = $this->input->post('value');

			$this->load->model('cms/cms_page_panel_model');

			$item = $this->cms_page_panel_model->get_cms_page_panel($item_id);
			$new_dims = [];
			$updated = false;
			foreach (($item['dims'] ?? []) as $row){
				$rid = (int)($row['product_dim_id'] ?? 0);
				if ($rid === $dim_id){
					$new_dims[] = ['product_dim_id' => $dim_id, 'value' => $value];
					$updated = true;
				} else {
					$new_dims[] = $row;
				}
			}
			if (!$updated){
				$new_dims[] = ['product_dim_id' => $dim_id, 'value' => $value];
			}

			$this->cms_page_panel_model->update_cms_page_panel($item_id, ['dims' => $new_dims]);

		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');

		$item = $this->cms_page_panel_model->get_cms_page_panel($params['item_id']);
		$dim_id = (int)($params['dim'] ?? $params['dimension'] ?? 0);

		$params['current'] = '';
		foreach (($item['dims'] ?? []) as $row){
			if ((int)($row['product_dim_id'] ?? 0) === $dim_id){
				$params['current'] = $row['value'] ?? '';
			}
		}

		$params['available'] = [];
		$dim = $dim_id > 0 ? $this->cms_page_panel_model->get_cms_page_panel($dim_id) : [];
		if (!empty($dim['values']) && is_array($dim['values'])){
			foreach ($dim['values'] as $value){
				$params['available'][$value['id']] = $value['label'];
			}
		}

		$params['dim'] = $dim_id;

		return $params;

	}

}
