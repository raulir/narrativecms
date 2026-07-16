<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

class cms_schema extends \Controller {
	
	function __construct(){
	
		parent::__construct();
	
		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_schema.scss');
	
	}
	
	function panel_action($params) {
		$this->load->model('cms/cms_schema_model');

		// Preserve embed/filter flags across action → params → template
		$fragment = !empty($params['fragment']) || !empty($this->input->post('fragment'));
		$filter_module = $params['module'] ?? $this->input->post('module');
		if ($filter_module === null || $filter_module === false){
			$filter_module = '';
		}

		if (!empty($params['do']) && $params['do'] === 'sync_panel_tables') {
			$module = trim($params['module'] ?? '');
			$stats = $this->cms_schema_model->synchronise_panel_table_data($module);

			$success = empty($stats['errors']);

			if ($success && $stats['synced'] === 0 && $stats['skipped'] > 0) {
				$message = 'Already synchronised — skipped '.$stats['skipped'].' row(s)';
			} else {
				$message = 'Synced '.$stats['synced'].' row(s), skipped '.$stats['skipped'];
			}

			if (!empty($stats['errors'])) {
				$message .= '. Errors: '.implode('; ', $stats['errors']);
				if ($stats['synced'] === 0 && $stats['skipped'] === 0 && count($stats['errors']) === 1 && stristr($stats['errors'][0], 'table not found')) {
					$message .= ' — use "fix module" first to create the table';
				}
				$latest = [];
				foreach ($stats['errors'] as $err) {
					$latest[] = [
						'module' => $module,
						'key' => $module.':sync_panel_tables',
						'message' => $err,
						'sql' => '',
					];
				}
				$_SESSION['cms_schema_latest_errors'] = $latest;
			} elseif ($success) {
				unset($_SESSION['cms_schema_latest_errors']);
			}

			unset($params['do']);

			return array_merge($params, [
				'success' => $success ? 1 : 0,
				'message' => $message,
				'stats' => $stats,
				'fragment' => $fragment ? 1 : 0,
				'module' => $filter_module !== '' ? $filter_module : $module,
			]);
		}

		if (empty($params['do']) || $params['do'] !== 'fix_schema') {
			// Still pass through fragment request (check-only embed)
			if ($fragment || $filter_module !== '') {
				return array_merge($params, [
					'fragment' => $fragment ? 1 : 0,
					'module' => $filter_module,
				]);
			}
			return [];
		}
		
		$key = trim($params['key'] ?? '');
		if (!$key) {
			return [
				'success' => false,
				'message' => 'No key provided',
				'fragment' => $fragment ? 1 : 0,
				'module' => $filter_module,
			];
		}
		
		$success = $this->cms_schema_model->fix_schema($key);
		$sql_errors = $this->cms_schema_model->get_fix_errors();

		if (!empty($sql_errors)) {
			$_SESSION['cms_schema_latest_errors'] = $sql_errors;
		} elseif ($success) {
			unset($_SESSION['cms_schema_latest_errors']);
		} else {
			$parts = explode(':', $key);
			$_SESSION['cms_schema_latest_errors'] = [[
				'module' => $parts[0] ?? '',
				'key' => $key,
				'message' => 'Fix failed or no changes were needed',
				'sql' => '',
			]];
		}

		// Prefer module from key when filtering fragment re-render
		if ($filter_module === '' && !empty($key)){
			$parts = explode(':', $key);
			$filter_module = $parts[0] ?? '';
		}

		if ($success) {
			return [
				'success' => true,
				'fragment' => $fragment ? 1 : 0,
				'module' => $filter_module,
			];
		}

		$message = 'Fix failed or no changes were needed';
		if (!empty($sql_errors)) {
			$message = $sql_errors[0]['message'];
		}

		return [
			'success' => false,
			'message' => $message,
			'fragment' => $fragment ? 1 : 0,
			'module' => $filter_module,
		];
	}
	
	function panel_params($params) {
		
	    $this->load->model('cms/cms_schema_model');

		$action_success = $params['success'] ?? null;
		$action_message = $params['message'] ?? null;
		$action_stats = $params['stats'] ?? null;

		$filter_module = $params['module'] ?? $this->input->post('module');
		if ($filter_module === null || $filter_module === false){
			$filter_module = '';
		} else {
			$filter_module = trim((string)$filter_module);
		}

		$fragment = !empty($params['fragment']) || !empty($this->input->post('fragment'));
	
	    $data = $this->cms_schema_model->get_schema_errors_with_status(
	    		$filter_module !== '' ? $filter_module : null);
	
	    $params['grouped_errors'] = $data['grouped'];
	    $params['has_errors']     = $data['has_errors'];

	    $pending = $this->cms_schema_model->get_panel_table_modules_pending();
	    if ($filter_module !== ''){
	    	$pending = array_values(array_filter($pending, function($m) use ($filter_module){
	    		return $m === $filter_module;
	    	}));
	    }
	    $params['panel_table_modules_pending'] = $pending;

	    $latest = $_SESSION['cms_schema_latest_errors'] ?? [];
	    if ($filter_module !== '' && is_array($latest)){
	    	$latest = array_values(array_filter($latest, function($row) use ($filter_module){
	    		return ($row['module'] ?? '') === $filter_module;
	    	}));
	    }
	    $params['latest_fix_errors'] = $latest;

	    $params['fragment'] = $fragment ? 1 : 0;
	    $params['filter_module'] = $filter_module;

		if ($action_success !== null) {
			$params['success'] = $action_success;
			$params['message'] = $action_message;
			$params['stats'] = $action_stats;
		}

	    return $params;
	
	}

}
