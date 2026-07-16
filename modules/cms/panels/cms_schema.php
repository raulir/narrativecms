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

	/**
	 * Schema filter for embeds — must NOT use key "module" (panel loader sets that to the panel’s CMS module).
	 */
	function _schema_filter_from_params($params){

		// Prefer dedicated keys; fall back to POST
		$filter = $params['schema_module'] ?? $params['filter_module'] ?? null;
		if ($filter === null || $filter === '' || $filter === false){
			$filter = $this->input->post('schema_module');
		}
		if ($filter === null || $filter === '' || $filter === false){
			$filter = $this->input->post('filter_module');
		}
		// Legacy: only trust POST "module" (request), not $params['module'] (overwritten by panel())
		if (($filter === null || $filter === '' || $filter === false) && $this->input->post('module') !== false && $this->input->post('module') !== null){
			$post_module = $this->input->post('module');
			// Ignore if it is only the panel package name with no intent — still allow cms/music/etc from client
			if ($post_module !== '' && $post_module !== false){
				$filter = $post_module;
			}
		}

		if ($filter === null || $filter === false){
			return '';
		}

		return trim((string)$filter);

	}

	function _wants_fragment($params){

		if (!empty($params['fragment'])){
			return true;
		}
		$post = $this->input->post('fragment');
		return !empty($post);

	}
	
	function panel_action($params) {

		$this->load->model('cms/cms_schema_model');

		$fragment = $this->_wants_fragment($params);
		$filter_module = $this->_schema_filter_from_params($params);

		if (!empty($params['do']) && $params['do'] === 'sync_panel_tables') {
			// Button data-module is the schema package to sync
			$module = trim($params['schema_module'] ?? $this->input->post('schema_module') ?? $this->input->post('module') ?? '');
			// Prefer explicit sync target from POST when panel() overwrote params['module']
			if ($module === '' || $module === 'cms' && $this->input->post('module') && $this->input->post('module') !== 'cms'){
				// keep post module for sync target if set
			}
			$sync_module = $this->input->post('schema_module');
			if ($sync_module === null || $sync_module === false || $sync_module === ''){
				$sync_module = $this->input->post('module');
			}
			if ($sync_module === null || $sync_module === false || $sync_module === ''){
				$sync_module = $module;
			}
			$sync_module = trim((string)$sync_module);

			$stats = $this->cms_schema_model->synchronise_panel_table_data($sync_module);

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
						'module' => $sync_module,
						'key' => $sync_module.':sync_panel_tables',
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
				'schema_module' => $filter_module !== '' ? $filter_module : $sync_module,
				'filter_module' => $filter_module !== '' ? $filter_module : $sync_module,
			]);
		}

		if (empty($params['do']) || $params['do'] !== 'fix_schema') {
			if ($fragment || $filter_module !== '') {
				return array_merge($params, [
					'fragment' => $fragment ? 1 : 0,
					'schema_module' => $filter_module,
					'filter_module' => $filter_module,
				]);
			}
			return [];
		}
		
		$key = trim($params['key'] ?? $this->input->post('key') ?? '');
		if (!$key) {
			return [
				'success' => false,
				'message' => 'No key provided',
				'fragment' => $fragment ? 1 : 0,
				'schema_module' => $filter_module,
				'filter_module' => $filter_module,
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

		if ($filter_module === '' && !empty($key)){
			$parts = explode(':', $key);
			$filter_module = $parts[0] ?? '';
		}

		if ($success) {
			return [
				'success' => true,
				'fragment' => $fragment ? 1 : 0,
				'schema_module' => $filter_module,
				'filter_module' => $filter_module,
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
			'schema_module' => $filter_module,
			'filter_module' => $filter_module,
		];
	}
	
	function panel_params($params) {
		
	    $this->load->model('cms/cms_schema_model');

		$action_success = $params['success'] ?? null;
		$action_message = $params['message'] ?? null;
		$action_stats = $params['stats'] ?? null;

		// Re-read after panel() may have set params['module'] = 'cms' (panel package)
		$filter_module = $this->_schema_filter_from_params($params);
		if ($filter_module === '' && !empty($params['filter_module'])){
			$filter_module = trim((string)$params['filter_module']);
		}
		if ($filter_module === '' && !empty($params['schema_module'])){
			$filter_module = trim((string)$params['schema_module']);
		}

		$fragment = $this->_wants_fragment($params) || !empty($params['fragment']);
	
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
	    $params['schema_module'] = $filter_module;

		if ($action_success !== null) {
			$params['success'] = $action_success;
			$params['message'] = $action_message;
			$params['stats'] = $action_stats;
		}

	    return $params;
	
	}

}
