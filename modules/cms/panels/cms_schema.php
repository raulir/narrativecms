<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_schema extends Controller {
	
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
		
		if ($success) {
			return ['success' => true];
		}
		
		return [
			'success' => false,
			'message' => 'Fix failed or no changes were needed'
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

		if ($action_success !== null) {
			$params['success'] = $action_success;
			$params['message'] = $action_message;
			$params['stats'] = $action_stats;
		}

	    return $params;
	
	}

}