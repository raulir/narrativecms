<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

class cms_grid_editable extends \Controller {

	function __construct(){

		parent::__construct();

		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		if (!empty($params['do']) && $params['do'] == 'update_field'){

			if (!empty($params['ds']) && !empty($params['base_id'])){

				$this->load->model('cms/cms_page_panel_model');
				$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);

				$this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], [
						'do' => 'U',
						'id' => $params['base_id'],
						'row_id' => $params['item_id'],
						'col' => $params['name'],
						'value' => $params['value'],
				]);

			} else {

				$this->load->model('cms/cms_page_panel_model');
				$this->cms_page_panel_model->update_cms_page_panel($params['item_id'], [$params['name'] => $params['value']]);

			}

		}

		return $params;

	}

	function panel_params($params){

		if (isset($params['value'])){
			return $params;
		}

		if (!empty($params['ds']) && !empty($params['base_id'])){

			$this->load->model('cms/cms_page_panel_model');
			$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);
			$rows = $this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], [
					'do' => 'L',
					'id' => $params['base_id'],
			]);

			$params['value'] = '';

			if (is_array($rows)){
				foreach ($rows as $row){
					if (isset($row['id']) && (string)$row['id'] === (string)$params['item_id'] && array_key_exists($params['name'], $row)){
						$params['value'] = $row[$params['name']];
						break;
					}
				}
			}

			return $params;

		}

		$this->load->model('cms/cms_page_panel_model');
		$item = $this->cms_page_panel_model->get_cms_page_panel($params['item_id']);

		$params['value'] = $item[$params['name']] ?? '';

		return $params;

	}

}