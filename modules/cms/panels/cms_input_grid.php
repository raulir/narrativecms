<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_grid extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_input_grid.scss');

	}
	
	function panel_action($params){
		
		if(!empty($params['do'])){
			
			if ($params['do'] == 'create_row'){
				
				$this->load->model('cms/cms_page_panel_model');
				
				$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);
				$params['data'] = $this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], [
						'do' => 'C',
						'id' => $params['base_id'],
				]);
				
				print(json_encode(['result' => $params['data']], JSON_PRETTY_PRINT));
				
				die();
				
			} else if ($params['do'] == 'delete_row'){
				
				$this->load->model('cms/cms_page_panel_model');

				if(!empty($params['base_id'])){
					$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);
					$base_name = $base['panel_name'];
					$ds_params = [
							'do' => 'D',
							'id' => $params['base_id'],
							'row_id' => $params['id'],
					];
				} else {
					$base_name = $params['base_name'];
					$ds_params = [
							'do' => 'D',
							'id' => $params['id'],
					];
				}
				
				$params['data'] = $this->run_panel_method($base_name, 'ds_'.$params['ds'], $ds_params);
				
				print(json_encode(['result' => $params['data']], JSON_PRETTY_PRINT));
				
				die();
				
			}
				
		}
		
		return $params;
		
	}

	function panel_params($params){
		
		$params['data'] = [];

		if(!empty($params['base_id']) || !empty($params['base_name'])){
		
			$this->load->model('cms/cms_page_panel_model');
			$this->load->model('cms/cms_panel_model');
				
			if (!empty($params['base_id'])){
				$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);
				$params['base_name'] = $base['panel_name'];
			} else {
				$base['panel_name'] = $params['base_name'];
			}
			
			$params['fields'] = $params['fields'] ?? [];

			if (!empty($params['operations']) && stristr($params['operations'], 'S')){

				$ds_params = [
					'do' => 'S',
					'id' => $params['base_id'] ?? 0,
					'fields' => $params['fields'],
				];
				
				$fields = $this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], $ds_params);

				if (!empty($fields['fields'])){
					$params['fields'] = $fields['fields'];
				} else {
					$params['fields'] = $fields;
				}
				
				if (!empty($fields['_no_cache'])) unset($params['fields']['_no_cache']);

			}

			$cms_language = '';
			if (!empty($params['base_id'])){
				$cms_language = $this->cms_page_panel_model->get_cms_language();
			}

			foreach ($params['fields'] as $field_key => $field){
				if (!empty($field['label']) && stristr($field['label'], '{cms_language}')){
					$params['fields'][$field_key]['label'] = str_replace('{cms_language}', $cms_language, $field['label']);
				}
			}

			usort($params['fields'], function($a, $b){
				if (empty($a['order'])) $a['order'] = 20;
				if (empty($b['order'])) $b['order'] = 20;
				return ((int)$a['order'] > $b['order'])*2 - 1;
			});
			
			$ds_params = [
					'do' => 'L',
					'id' => $params['base_id'] ?? 0,
			];
			
			$params['data'] = $this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], $ds_params);
			
			if (!empty($params['data']['_no_cache'])) unset($params['data']['_no_cache']);
		
		}
		
		$params['_params'] = &$params;

		return $params;

	}

	/**
	 * Apply a grid change log posted with the panel save. Removes the log from $data.
	 *
	 * @return array{data: array, grid_ids: array, grid_log: array, _reason: string}
	 */
	function apply_logs($params){

		$data = is_array($params['data'] ?? null) ? $params['data'] : [];
		$structure = is_array($params['structure'] ?? null) ? $params['structure'] : [];
		$panel_name = (string)($params['panel_name'] ?? ($data['panel_name'] ?? ''));
		$base_id = (int)($params['base_id'] ?? ($data['cms_page_panel_id'] ?? 0));

		$grid_ids = [];
		$grid_log = [];
		$reason = '';

		foreach ($structure as $struct){
			if (!is_array($struct) || ($struct['type'] ?? '') !== 'grid' || empty($struct['name']) || empty($struct['ds'])){
				continue;
			}

			$name = (string)$struct['name'];
			$log_key = $name.'_log';
			$raw = $data[$log_key] ?? '[]';
			unset($data[$log_key]);

			if (is_array($raw)){
				$ops = $raw;
			} else {
				$ops = json_decode((string)$raw, true);
			}
			if (!is_array($ops)){
				$reason = 'Grid changes could not be read';
				$grid_log[$name] = (string)$raw;
				break;
			}
			if (!$ops){
				continue;
			}

			$map = [];
			$remaining = [];
			$failed = false;
			$changed = false;
			$failed_at = -1;
			$ds_name = (string)$struct['ds'];

			foreach ($ops as $index => $op){
				if (!is_array($op)){
					continue;
				}

				$result = $this->_apply_grid_op($panel_name, $ds_name, $base_id, $op, $map);
				if (empty($result['_reason']) && ($op['op'] ?? '') === 'add'
						&& (!isset($result['id']) || $result['id'] === '' || $result['id'] === null)){
					$result = ['_reason' => 'Grid add did not return a row id'];
				}
				if (!empty($result['_reason'])){
					$failed = true;
					$failed_at = $index;
					$reason = (string)$result['_reason'];
					break;
				}
				$changed = true;
				if (($op['op'] ?? '') === 'add'){
					$tmp_id = (string)$op['id'];
					$map[$tmp_id] = (string)$result['id'];
					if (!isset($grid_ids[$name]) || !is_array($grid_ids[$name])){
						$grid_ids[$name] = [];
					}
					$grid_ids[$name][$tmp_id] = (string)$result['id'];
				}
			}

			if ($failed && $failed_at >= 0){
				$failed_tmp = (string)($ops[$failed_at]['id'] ?? '');
				if ($failed_tmp !== '' && isset($map[$failed_tmp])){
					$this->_apply_grid_op($panel_name, $ds_name, $base_id, [
							'op' => 'delete',
							'id' => $map[$failed_tmp],
					], []);
					unset($map[$failed_tmp]);
					unset($grid_ids[$name][$failed_tmp]);
					if (empty($grid_ids[$name])){
						unset($grid_ids[$name]);
					}
				} else {
					$failed_tmp = '';
				}

				foreach ($ops as $index => $prev){
					if (!is_array($prev)){
						continue;
					}
					$prev_id = (string)($prev['id'] ?? '');
					if ($failed_tmp !== '' && $prev_id === $failed_tmp){
						$remaining[] = $prev;
						continue;
					}
					if ($index >= $failed_at){
						$remaining[] = $this->_remap_grid_op($prev, $map);
					}
				}
			}

			if ($changed){
				$this->load->model('cms/cms_page_panel_model');
				$fresh = $this->cms_page_panel_model->get_cms_page_panel($base_id);
				if (is_array($fresh) && array_key_exists($name, $fresh)){
					$data[$name] = $fresh[$name];
				}
			}

			if ($failed){
				$grid_log[$name] = json_encode(array_values($remaining), JSON_UNESCAPED_UNICODE);
				break;
			}

			$grid_log[$name] = '[]';
		}

		return [
				'data' => $data,
				'grid_ids' => $grid_ids,
				'_reason' => $reason,
				'grid_log' => $grid_log,
		];

	}

	function _apply_grid_op($panel_name, $ds, $base_id, $op, $map){

		$kind = (string)($op['op'] ?? '');
		$id = (string)($op['id'] ?? '');
		if ($id !== '' && isset($map[$id])){
			$id = (string)$map[$id];
		}

		if ($kind === 'add'){
			return $this->run_panel_method($panel_name, 'ds_'.$ds, [
					'do' => 'C',
					'id' => $base_id,
			]);
		}

		if ($kind === 'set'){
			return $this->run_panel_method($panel_name, 'ds_'.$ds, [
					'do' => 'U',
					'id' => $base_id,
					'row_id' => $id,
					'col' => (string)($op['field'] ?? ''),
					'value' => $op['value'] ?? '',
			]);
		}

		if ($kind === 'delete'){
			if ($id === '' || strpos($id, 'tmp_') === 0){
				return ['web' => 1];
			}
			return $this->run_panel_method($panel_name, 'ds_'.$ds, [
					'do' => 'D',
					'id' => $base_id,
					'row_id' => $id,
			]);
		}

		return ['_reason' => 'Unknown grid change'];

	}

	function _remap_grid_op($op, $map){

		if (!empty($op['id']) && isset($map[(string)$op['id']])){
			$op['id'] = (string)$map[(string)$op['id']];
		}

		return $op;

	}

}
