<?php

namespace cms;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Orphaned panel data purge popup (scan + purge).
 */
class cms_page_panel_data_purge extends \Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$do = $this->input->post('do') ?? ($params['do'] ?? '');
		if ($do !== 'cms_page_panel_data_purge'){
			return $params;
		}

		$this->load->model('cms/cms_page_panel_model');

		$cms_page_panel_id = (int)$this->input->post('cms_page_panel_id');
		if ($cms_page_panel_id < 1){
			$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
		}

		$scan = $this->cms_page_panel_model->scan_orphan_panel_data($cms_page_panel_id);

		$panel_fields = $this->input->post('panel_fields');
		if (!is_array($panel_fields)){
			$panel_fields = [];
		}
		$settings_fields = $this->input->post('settings_fields');
		if (!is_array($settings_fields)){
			$settings_fields = [];
		}
		$languages = $this->input->post('languages');
		if (!is_array($languages)){
			$languages = [];
		}

		// Only purge keys that are still orphans on re-scan
		$allowed_panel = $scan['panel_fields'];
		$allowed_settings = $scan['settings_fields'];
		$allowed_langs = [];
		foreach ($scan['languages'] as $lid){
			$allowed_langs[$lid] = 1;
		}

		$panel_keys = [];
		foreach ($panel_fields as $k){
			$k = trim((string)$k);
			if ($k !== '' && array_key_exists($k, $allowed_panel)){
				$panel_keys[] = $k;
			}
		}

		$settings_keys = [];
		foreach ($settings_fields as $k){
			$k = trim((string)$k);
			if ($k !== '' && array_key_exists($k, $allowed_settings)){
				$settings_keys[] = $k;
			}
		}

		$lang_keys = [];
		foreach ($languages as $lid){
			$lid = trim((string)$lid);
			if ($lid !== '' && !empty($allowed_langs[$lid])){
				$lang_keys[] = $lid;
			}
		}

		// Panel instance orphans (page/list panels)
		if ($panel_keys !== [] && (int)$scan['panel_id'] > 0 && empty($scan['is_settings_panel'])){
			$this->cms_page_panel_model->purge_panel_param_keys(
					(int)$scan['panel_id'],
					$panel_keys,
					$allowed_panel
			);
		}

		// Settings orphans (settings panel row, or current id when editing settings)
		if ($settings_keys !== [] && (int)$scan['settings_id'] > 0){
			$this->cms_page_panel_model->purge_panel_param_keys(
					(int)$scan['settings_id'],
					$settings_keys,
					$allowed_settings
			);
		}

		// When editing a settings-only panel, "panel_fields" may also be posted empty;
		// if scan put orphans only under settings_fields, handled above.

		if ($lang_keys !== []){
			$ids = [];
			if ((int)$scan['panel_id'] > 0){
				$ids[(int)$scan['panel_id']] = 1;
			}
			if ((int)$scan['settings_id'] > 0){
				$ids[(int)$scan['settings_id']] = 1;
			}
			foreach (array_keys($ids) as $pid){
				$this->cms_page_panel_model->purge_panel_translation_languages($pid, $lang_keys);
			}
		}

		// Re-scan and re-render popup body
		$params['cms_page_panel_id'] = $cms_page_panel_id;
		$params = $this->_fill_scan($params, $cms_page_panel_id);
		$params['purged'] = 1;

		return $params;

	}

	function panel_params($params){

		$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($cms_page_panel_id < 1){
			$cms_page_panel_id = (int)$this->input->post('cms_page_panel_id');
		}
		if ($cms_page_panel_id < 1){
			$cms_page_panel_id = (int)($params['export_id'] ?? 0);
		}

		return $this->_fill_scan($params, $cms_page_panel_id);

	}

	function _fill_scan($params, $cms_page_panel_id){

		$this->load->model('cms/cms_page_panel_model');

		$scan = $this->cms_page_panel_model->scan_orphan_panel_data((int)$cms_page_panel_id);
		$params['cms_page_panel_id'] = (int)$cms_page_panel_id;
		$params['scan'] = $scan;
		$params['has_orphans'] = (
				!empty($scan['panel_fields'])
				|| !empty($scan['settings_fields'])
				|| !empty($scan['languages'])
		) ? 1 : 0;

		return $params;

	}

}
