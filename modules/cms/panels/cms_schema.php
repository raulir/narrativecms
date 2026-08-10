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

		if (!empty($params['do']) && $params['do'] === 'dump_cms_structure'){

			$dump = $this->cms_schema_model->dump_cms_table_structures();

			unset($params['do']);

			return array_merge($params, [
					'structure_dump' => $dump,
					'success' => 1,
					'message' => 'Dumped '.count($dump).' module table(s)',
					'fragment' => $fragment ? 1 : 0,
					'schema_module' => $filter_module,
					'filter_module' => $filter_module,
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

		$key_module = '';
		if (!empty($key)){
			$parts = explode(':', $key);
			$key_module = trim((string)($parts[0] ?? ''));
		}

		if (!empty($sql_errors)) {
			$_SESSION['cms_schema_latest_errors'] = $sql_errors;
		} elseif ($success) {
			unset($_SESSION['cms_schema_latest_errors']);
		} else {
			$_SESSION['cms_schema_latest_errors'] = [[
				'module' => $key_module,
				'key' => $key,
				'message' => 'Fix failed or no changes were needed',
				'sql' => '',
			]];
		}

		// Fragment embeds (updater): stay scoped to that module.
		// Full schema page: always re-check ALL modules so "all OK" is not a false green.
		if ($fragment){
			if ($filter_module === '' && $key_module !== ''){
				$filter_module = $key_module;
			}
		} else {
			$filter_module = '';
		}

		// Message for cms_notification (top edge) — based on full re-check when not fragment
		$message = '';
		if ($success){
			$recheck = $this->cms_schema_model->get_schema_errors_with_status(null);
			$still_errors = !empty($recheck['has_errors']);
			if ($still_errors){
				$modules_left = is_array($recheck['grouped'] ?? null) ? array_keys($recheck['grouped']) : [];
				$label = $key_module !== '' ? $key_module : 'Schema';
				$message = $label.' fix applied. Other modules still have schema differences';
				if ($modules_left !== []){
					$message .= ' ('.implode(', ', $modules_left).')';
				}
			} else {
				$message = 'All database tables and data match the schema definition files';
			}
		} else {
			$message = 'Fix failed or no changes were needed';
			if (!empty($sql_errors[0]['message'])){
				$message = $sql_errors[0]['message'];
			}
		}

		return [
			'success' => $success ? 1 : 0,
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
		$structure_dump = $params['structure_dump'] ?? null;

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

		if ($structure_dump !== null) {
			$params['structure_dump'] = $structure_dump;
		}

	    return $params;
	
	}

}
