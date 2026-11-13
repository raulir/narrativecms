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

		if (!empty($params['cms_do']) && $params['cms_do'] == 'update_field'){

			$this->load->model('cms/cms_page_panel_cms_model');

			// Only a grid of the block's definition, or a field of the item's definition
			$target = $this->cms_page_panel_cms_model->get_grid_cell_target(
					$params['item_id'] ?? 0, $params['name'] ?? '', $params['base_id'] ?? 0, $params['ds'] ?? '');
			$value = $params['value'] ?? '';

			if (!empty($target['error']) || !is_scalar($value)){
				return $this->_refused($target['error'] ?? 'Unknown value');
			}

			if ($target['ds'] !== ''){

				$updated = $this->run_panel_method($target['panel_name'], 'ds_'.$target['ds'], [
						'cms_do' => 'U',
						'id' => $target['block_id'],
						'row_id' => $params['item_id'] ?? '',
						'col' => $target['name'],
						'value' => $value,
				]);
				if (is_array($updated) && !empty($updated['_reason'])){
					$params['_reason'] = $updated['_reason'];
					$params['_reload_value'] = 1;
				}

			} else {

				$this->load->model('cms/cms_page_panel_model');
				$this->cms_page_panel_model->update_cms_page_panel($target['block_id'], [$target['name'] => $value]);

			}

		}

		return $params;

	}

	/**
	 * A refused cell, merged over the params: the reply carries only the reason (the JS keeps the cell and
	 * restores its old value). The posted item, name, ds and value are not printed back.
	 */
	function _refused($reason){

		return ['_reason' => $reason, '_reload_value' => 0, 'item_id' => '', 'name' => '', 'value' => '', 'base_id' => 0, 'ds' => ''];

	}

	function panel_params($params){

		if (!empty($params['_reason']) && empty($params['_reload_value'])){
			return $params;
		}

		if (isset($params['value']) && is_scalar($params['value']) && empty($params['_reload_value'])){
			return $params;
		}

		// The reload reads only what the cell write may write
		$this->load->model('cms/cms_page_panel_cms_model');
		$target = $this->cms_page_panel_cms_model->get_grid_cell_target(
				$params['item_id'] ?? 0, $params['name'] ?? '', $params['base_id'] ?? 0, $params['ds'] ?? '');

		if (!empty($target['error'])){
			return array_merge($params, $this->_refused($target['error']));
		}

		$params['value'] = '';

		if ($target['ds'] !== ''){

			$rows = $this->run_panel_method($target['panel_name'], 'ds_'.$target['ds'], [
					'cms_do' => 'L',
					'id' => $target['block_id'],
			]);

			foreach (is_array($rows) ? $rows : [] as $row){
				if (is_array($row) && isset($row['id']) && (string)$row['id'] === (string)($params['item_id'] ?? '')){
					$params['value'] = $row[$target['name']] ?? '';
					break;
				}
			}

		} else {

			$this->load->model('cms/cms_page_panel_model');
			$item = $this->cms_page_panel_model->get_cms_page_panel($target['block_id']);
			$params['value'] = $item[$target['name']] ?? '';

		}

		if (!is_scalar($params['value'])){
			$params['value'] = '';
		}

		return $params;

	}

}