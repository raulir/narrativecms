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
		$this->load->model('cms/cms_page_panel_cms_model');

		$cms_page_panel_id = (int)$this->input->post('cms_page_panel_id');
		if ($cms_page_panel_id < 1){
			$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
		}

		$scan = $this->scan_orphan_panel_data($cms_page_panel_id);

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
			$this->purge_panel_param_keys(
					(int)$scan['panel_id'],
					$panel_keys,
					$allowed_panel
			);
		}

		// Settings orphans (settings panel row, or current id when editing settings)
		if ($settings_keys !== [] && (int)$scan['settings_id'] > 0){
			$this->purge_panel_param_keys(
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
				$this->purge_panel_translation_languages($pid, $lang_keys);
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
		$this->load->model('cms/cms_page_panel_cms_model');

		$scan = $this->scan_orphan_panel_data((int)$cms_page_panel_id);
		$params['cms_page_panel_id'] = (int)$cms_page_panel_id;
		$params['scan'] = $scan;
		$params['has_orphans'] = (
				!empty($scan['panel_fields'])
				|| !empty($scan['settings_fields'])
				|| !empty($scan['languages'])
		) ? 1 : 0;

		return $params;

	}


	function _ensure_panel_model(){
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_panel_cms_model');
		$this->cms_page_panel_model->_ensure_language_model();
	}

	function collect_definition_field_names($fields){

		$names = [];
		if (!is_array($fields)){
			return $names;
		}
		foreach ($fields as $field){
			if (!is_array($field)){
				continue;
			}
			$name = trim((string)($field['name'] ?? ''));
			if ($name === '' || $name === '_noname'){
				continue;
			}
			$names[$name] = 1;
		}
		return array_keys($names);

	}

	/**
	 * Keys that are CMS meta / never treated as orphan content fields.
	 */

	function is_panel_param_meta_key($key){

		$key = (string)$key;
		if ($key === '' || $key[0] === '_'){
			return true;
		}
		// Columns / merge noise sometimes present in blobs
		static $system = [
				'cms_page_panel_id' => 1,
				'cms_page_id' => 1,
				'parent_id' => 1,
				'panel_name' => 1,
				'sort' => 1,
				'show' => 1,
				'title' => 1,
				'create_time' => 1,
				'update_time' => 1,
				'create_cms_user_id' => 1,
				'update_cms_user_id' => 1,
		];
		return !empty($system[$key]);

	}

	/**
	 * Settings panel row for panel_name (cms_page_id 0, parent 0, sort 0), or 0.
	 */

	function find_orphan_param_fields($params, $allowed_names){

		$out = [];
		if (!is_array($params)){
			return $out;
		}
		$allowed = [];
		foreach ((array)$allowed_names as $n){
			$allowed[(string)$n] = 1;
		}
		foreach ($params as $key => $value){
			$key = (string)$key;
			if ($this->is_panel_param_meta_key($key)){
				continue;
			}
			if (!empty($allowed[$key])){
				continue;
			}
			$out[$key] = $this->format_param_value_preview($value);
		}
		ksort($out);
		return $out;

	}

	/**
	 * Orphan field keys stored only (or also) under _translations.{lang}.* that are not
	 * in the panel definition. Ghost labels (e.g. yearly_badge on a pricing instance)
	 * live here and override shared settings after language merge.
	 *
	 * @return array field_name => preview "(en, es): −18%"
	 */

	function find_orphan_translation_fields($params, $allowed_names){

		$out = [];
		if (!is_array($params)){
			return $out;
		}
		$tr = $params['_translations'] ?? null;
		if (!is_array($tr) || $tr === []){
			return $out;
		}

		$allowed = [];
		foreach ((array)$allowed_names as $n){
			$allowed[(string)$n] = 1;
		}

		// field => [ lang => value ]
		$by_field = [];
		foreach ($tr as $lang => $branch){
			if (!is_array($branch)){
				continue;
			}
			$lang = (string)$lang;
			foreach ($branch as $key => $value){
				$key = (string)$key;
				if ($this->is_panel_param_meta_key($key)){
					continue;
				}
				if (!empty($allowed[$key])){
					continue;
				}
				if (!isset($by_field[$key])){
					$by_field[$key] = [];
				}
				$by_field[$key][$lang] = $value;
			}
		}

		foreach ($by_field as $key => $langs){
			$lang_ids = array_keys($langs);
			sort($lang_ids);
			$first = reset($langs);
			$preview = $this->format_param_value_preview($first);
			$out[$key] = '('.implode(', ', $lang_ids).'): '.$preview;
		}
		ksort($out);
		return $out;

	}

	/**
	 * Merge top-level + translation-branch orphans (translation preview preferred if both).
	 *
	 * @return array name => preview
	 */

	function merge_orphan_field_maps($top_level, $translation_level){

		$out = is_array($top_level) ? $top_level : [];
		if (!is_array($translation_level)){
			return $out;
		}
		foreach ($translation_level as $name => $preview){
			if (!isset($out[$name])){
				$out[$name] = $preview;
			} else {
				// Keep base preview and append translation note
				$out[$name] = $out[$name].' | tr '.$preview;
			}
		}
		ksort($out);
		return $out;

	}

	function format_param_value_preview($value, $max_len = 120){

		if (is_array($value)){
			$encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
			if ($encoded === false){
				$encoded = '[array]';
			}
			$text = $encoded;
		} else if (is_bool($value)){
			$text = $value ? '1' : '0';
		} else if ($value === null){
			$text = '';
		} else {
			$text = (string)$value;
		}
		$text = preg_replace('/\s+/u', ' ', trim($text));
		if (function_exists('mb_strlen') && function_exists('mb_substr')){
			if (mb_strlen($text) > $max_len){
				return mb_substr($text, 0, $max_len - 1).'…';
			}
			return $text;
		}
		if (strlen($text) > $max_len){
			return substr($text, 0, $max_len - 1).'…';
		}
		return $text;

	}

	/**
	 * Full orphan scan for panel editor (instance + settings + dead translation languages).
	 *
	 * @return array{
	 *   panel_id:int, settings_id:int, panel_name:string,
	 *   panel_fields:array, settings_fields:array, languages:array,
	 *   is_settings_panel:bool
	 * }
	 */

	function scan_orphan_panel_data($cms_page_panel_id){
		$this->_ensure_panel_model();

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$empty = [
				'panel_id' => $cms_page_panel_id,
				'settings_id' => 0,
				'panel_name' => '',
				'panel_fields' => [],
				'settings_fields' => [],
				'languages' => [],
				'is_settings_panel' => 0,
		];
		if ($cms_page_panel_id < 1){
			return $empty;
		}

		// Row only — avoid settings merge / language overlay
		$sql = "select panel_name, cms_page_id, parent_id, sort from cms_page_panel where cms_page_panel_id = ? limit 1 ";
		$query = $this->db->query($sql, [$cms_page_panel_id]);
		if (!$query->num_rows()){
			return $empty;
		}
		$block = $query->row_array();

		$panel_name = (string)($block['panel_name'] ?? '');
		$is_settings = empty($block['cms_page_id']) && empty($block['parent_id']) && empty($block['sort']);

		$this->load->model('cms/cms_panel_model');
		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
		$item_names = $this->collect_definition_field_names($config['item'] ?? []);
		$settings_names = $this->collect_definition_field_names($config['settings'] ?? []);

		// Raw cache (no language merge)
		$panel_params = $this->cms_page_panel_model->get_cms_page_panel_params($cms_page_panel_id, '');
		if (!is_array($panel_params)){
			$panel_params = [];
		}

		$settings_id = $this->cms_page_panel_model->get_settings_panel_id($panel_name);
		$settings_params = [];
		if ($settings_id > 0){
			if ($settings_id === $cms_page_panel_id){
				$settings_params = $panel_params;
			} else {
				$settings_params = $this->cms_page_panel_model->get_cms_page_panel_params($settings_id, '');
				if (!is_array($settings_params)){
					$settings_params = [];
				}
			}
		}

		$panel_fields = [];
		$settings_fields = [];
		if ($is_settings){
			// Editing settings panel: orphans vs settings fields only (top-level + ghost tr fields)
			$settings_fields = $this->merge_orphan_field_maps(
					$this->find_orphan_param_fields($panel_params, $settings_names),
					$this->find_orphan_translation_fields($panel_params, $settings_names)
			);
			$settings_id = $cms_page_panel_id;
		} else {
			// Page/list instance: item fields only — settings labels under _translations are orphans
			$panel_fields = $this->merge_orphan_field_maps(
					$this->find_orphan_param_fields($panel_params, $item_names),
					$this->find_orphan_translation_fields($panel_params, $item_names)
			);
			if ($settings_id > 0){
				$settings_fields = $this->merge_orphan_field_maps(
						$this->find_orphan_param_fields($settings_params, $settings_names),
						$this->find_orphan_translation_fields($settings_params, $settings_names)
				);
			}
		}

		// Configured CMS languages
		$this->cms_page_panel_model->_ensure_language_model();
		$configured = [];
		$langs_map = $GLOBALS['language']['languages'] ?? [];
		if (is_array($langs_map)){
			foreach ($langs_map as $lid => $unused){
				$configured[$this->cms_page_panel_model->cms_language_model->normalise_language_id($lid)] = 1;
			}
		}
		// Also accept languages settings if globals empty
		if ($configured === []){
			$lang_settings = $this->get_cms_page_panel_settings('cms/cms_languages');
			if (!empty($lang_settings['languages']) && is_array($lang_settings['languages'])){
				foreach ($lang_settings['languages'] as $row){
					if (!is_array($row)){
						continue;
					}
					$lid = $this->cms_page_panel_model->cms_language_model->normalise_language_id($row['language_id'] ?? '');
					if ($lid !== ''){
						$configured[$lid] = 1;
					}
				}
			}
		}

		$translation_langs = [];
		foreach ([$panel_params, $settings_params] as $bag){
			$tr = $bag['_translations'] ?? [];
			if (!is_array($tr)){
				continue;
			}
			foreach ($tr as $lid => $unused){
				$norm = $this->cms_page_panel_model->cms_language_model->normalise_language_id($lid);
				if ($norm === ''){
					continue;
				}
				$translation_langs[$norm] = 1;
			}
		}

		$orphan_langs = [];
		foreach ($translation_langs as $lid => $unused){
			if (empty($configured[$lid])){
				$orphan_langs[] = $lid;
			}
		}
		sort($orphan_langs);

		return [
				'panel_id' => $cms_page_panel_id,
				'settings_id' => $settings_id,
				'panel_name' => $panel_name,
				'panel_fields' => $panel_fields,
				'settings_fields' => $settings_fields,
				'languages' => $orphan_langs,
				'is_settings_panel' => $is_settings ? 1 : 0,
		];

	}

	/**
	 * Delete top-level param keys (base + each translation branch) and rewrite param bag.
	 * $allowed_orphan_map: name => anything (only those keys may be removed when provided).
	 */

	function purge_panel_param_keys($cms_page_panel_id, $keys, $allowed_orphan_map = null){
		$this->_ensure_panel_model();

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id < 1 || !is_array($keys) || $keys === []){
			return 0;
		}

		$params = $this->cms_page_panel_model->get_cms_page_panel_params($cms_page_panel_id, '');
		if (!is_array($params)){
			$params = [];
		}

		$deleted = 0;
		foreach ($keys as $key){
			$key = trim((string)$key);
			if ($key === '' || $this->is_panel_param_meta_key($key)){
				continue;
			}
			if (is_array($allowed_orphan_map) && !array_key_exists($key, $allowed_orphan_map)){
				continue;
			}
			if (array_key_exists($key, $params)){
				unset($params[$key]);
				$deleted++;
			}
			// Also strip from every language branch
			if (!empty($params['_translations']) && is_array($params['_translations'])){
				foreach ($params['_translations'] as $lang => $branch){
					if (is_array($branch) && array_key_exists($key, $branch)){
						unset($params['_translations'][$lang][$key]);
						$deleted++;
					}
				}
			}
		}

		if ($deleted < 1){
			return 0;
		}

		$this->_rewrite_panel_params_bag($cms_page_panel_id, $params);
		return $deleted;

	}

	/**
	 * Delete translation language branches no longer in CMS; rewrite param bag.
	 */

	function purge_panel_translation_languages($cms_page_panel_id, $language_ids){
		$this->_ensure_panel_model();

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id < 1 || !is_array($language_ids) || $language_ids === []){
			return 0;
		}

		$this->cms_page_panel_model->_ensure_language_model();
		$params = $this->cms_page_panel_model->get_cms_page_panel_params($cms_page_panel_id, '');
		if (!is_array($params)){
			$params = [];
		}

		$remove = [];
		foreach ($language_ids as $lid){
			$lid = $this->cms_page_panel_model->cms_language_model->normalise_language_id($lid);
			if ($lid === ''){
				continue;
			}
			$remove[$lid] = 1;
		}

		if ($remove === []){
			return 0;
		}

		$deleted = 0;
		if (!empty($params['_translations']) && is_array($params['_translations'])){
			foreach ($params['_translations'] as $lang => $branch){
				$norm = $this->cms_page_panel_model->cms_language_model->normalise_language_id($lang);
				if (!empty($remove[$norm]) || !empty($remove[$lang])){
					unset($params['_translations'][$lang]);
					$deleted++;
				}
			}
			if (empty($params['_translations'])){
				unset($params['_translations']);
			}
		}

		// Also drop raw language-column rows for those ids
		foreach (array_keys($remove) as $lid){
			$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? and language = ? ";
			$this->db->query($sql, [$cms_page_panel_id, $lid]);
		}

		$this->_rewrite_panel_params_bag($cms_page_panel_id, $params);
		return $deleted;

	}

	/**
	 * Replace all cms_page_panel_param rows for a panel from a cleaned params bag.
	 * Base fields as language=''; translation branches as language-scoped rows.
	 */

	function _rewrite_panel_params_bag($cms_page_panel_id, $params){
		$this->_ensure_panel_model();

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id < 1 || !is_array($params)){
			return;
		}

		$translations = [];
		if (!empty($params['_translations']) && is_array($params['_translations'])){
			$translations = $params['_translations'];
		}
		unset($params['_translations']);

		// Drop meta keys that must not be re-written as content fields
		// (keep _title etc. if present — they are used)
		// Remove empty-name cache if any
		unset($params['']);

		$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? ";
		$this->db->query($sql, [$cms_page_panel_id]);

		// Base language fields
		if ($params !== []){
			$this->cms_page_panel_model->_insert_or_update_param($cms_page_panel_id, '', $params, 0, '');
		}

		// Language branches: name=field, language=lang_id
		$this->cms_page_panel_model->_ensure_language_model();
		foreach ($translations as $lang => $branch){
			$lang = $this->cms_page_panel_model->cms_language_model->normalise_language_id($lang);
			if ($lang === '' || !is_array($branch)){
				continue;
			}
			foreach ($branch as $fname => $fval){
				$fname = (string)$fname;
				if ($fname === '' || $this->is_panel_param_meta_key($fname)){
					continue;
				}
				if (is_array($fval)){
					// Nested translation rare; store JSON string
					$fval = json_encode($fval, JSON_UNESCAPED_UNICODE);
				}
				$this->cms_page_panel_cms_model->set_translated_param($cms_page_panel_id, $fname, (string)$fval, $lang);
			}
		}

		$this->cms_page_panel_cms_model->rebuild_panel_param_cache($cms_page_panel_id);

	}


}
