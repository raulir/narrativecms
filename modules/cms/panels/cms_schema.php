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
	
	}
	
	function panel_action($params) {
		$this->load->model('cms/cms_schema_model');

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

			unset($params['do'], $params['module']);

			return array_merge($params, [
				'success' => $success ? 1 : 0,
				'message' => $message,
				'stats' => $stats,
			]);
		}

		if (empty($params['do']) || $params['do'] !== 'fix_schema') {
			return [];
		}
		
		$key = trim($params['key'] ?? '');
		if (!$key) {
			return [
				'success' => false,
				'message' => 'No key provided'
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

		if ($success) {
			return ['success' => true];
		}

		$message = 'Fix failed or no changes were needed';
		if (!empty($sql_errors)) {
			$message = $sql_errors[0]['message'];
		}

		return [
			'success' => false,
			'message' => $message,
		];
	}
	
	function panel_params($params) {
		
	    $this->load->model('cms/cms_schema_model');

		$action_success = $params['success'] ?? null;
		$action_message = $params['message'] ?? null;
		$action_stats = $params['stats'] ?? null;
	
	    $data = $this->cms_schema_model->get_schema_errors_with_status();
	
	    $params['grouped_errors'] = $data['grouped'];
	    $params['has_errors']     = $data['has_errors'];
	    $params['panel_table_modules_pending'] = $this->cms_schema_model->get_panel_table_modules_pending();
	    $params['latest_fix_errors'] = $_SESSION['cms_schema_latest_errors'] ?? [];

		if ($action_success !== null) {
			$params['success'] = $action_success;
			$params['message'] = $action_message;
			$params['stats'] = $action_stats;
		}

	    return $params;
	
	}

}