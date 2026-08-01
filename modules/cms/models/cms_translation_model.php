<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Admin-only panel field translation (grid page + per-field popup).
 * Not loaded by FE/system panel render — use cms_page_panel_model for content merge.
 */
class cms_translation_model extends \Model {

	/**
	 * Configured AI provider panel for service "ai" (cms_settings.ai_provider).
	 * Empty string = AI features disabled.
	 */
	function get_ai_provider_panel(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_settings');
		$panel = trim((string)($settings['ai_provider'] ?? ''));

		if ($panel === ''){
			return '';
		}

		$providers = $GLOBALS['config']['provides']['ai'] ?? [];
		if (!is_array($providers) || $providers === []){
			return '';
		}

		// Valid if listed in registry
		if (isset($providers[$panel])){
			return $panel;
		}
		foreach ($providers as $provider){
			if (is_array($provider) && ($provider['panel'] ?? '') === $panel){
				return $panel;
			}
		}

		return '';

	}

	/**
	 * Site-wide AI UI options from cms/cms_settings (apply to all providers).
	 */
	function get_ai_ui_options(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_settings');
		if (!is_array($settings)){
			$settings = [];
		}

		// Default Yes when unset
		$ask = 1;
		if (array_key_exists('ai_ask_confirmation', $settings) && (string)$settings['ai_ask_confirmation'] === '0'){
			$ask = 0;
		}

		return [
				'ask_confirmation' => $ask,
				'only_missing' => !empty($settings['ai_only_missing']) ? 1 : 0,
		];

	}

	/**
	 * Text to send to AI as source: base language value, else definition default
	 * (matches runtime fallback when base is empty).
	 */
	function _source_text_for_ai($row){

		$base = trim((string)($row['base_value'] ?? ''));
		if ($base !== ''){
			return (string)($row['base_value'] ?? '');
		}

		return (string)($row['definition_default'] ?? '');

	}

	/**
	 * Selected language text still "missing" a real translation.
	 */
	function _is_missing_translation_row($row){

		$selected = trim((string)($row['selected_value'] ?? ''));
		$base = trim((string)($row['base_value'] ?? ''));
		$default = trim((string)($row['definition_default'] ?? ''));

		if ($selected === ''){
			return true;
		}
		if ($base !== '' && $selected === $base){
			return true;
		}
		if ($default !== '' && $selected === $default){
			return true;
		}

		return false;

	}

	/**
	 * Batch AI suggestions for translation grid (does not save).
	 *
	 * @param array|null $ui_values Optional map field_name => current UI edit value
	 *        (unsaved). Used so emptied fields in the editor are treated as missing.
	 */
	function suggest_translations($cms_page_panel_id, $cms_language = '', $ui_values = null){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_language_model');

		$provider = $this->get_ai_provider_panel();
		if ($provider === ''){
			return ['error' => 'AI provider is not configured (Site settings → AI provider)'];
		}

		if ($cms_language === null || $cms_language === ''){
			$cms_language = $this->cms_language_model->get_cms_language();
		}
		$cms_language = $this->cms_language_model->normalise_language_id($cms_language);
		$default_lang = $this->cms_language_model->normalise_language_id(
				$this->cms_language_model->get_default()
		);

		if ($cms_language === $default_lang){
			return ['error' => 'Switch CMS language away from the base language to request AI translations'];
		}

		$grid = $this->get_panel_translation_grid($cms_page_panel_id, $cms_language);
		if (!empty($grid['error'])){
			return $grid;
		}

		$ui = $this->get_ai_ui_options();
		$only_missing = !empty($ui['only_missing']);
		if (!is_array($ui_values)){
			$ui_values = null;
		}

		$items = [];
		foreach ($grid['rows'] as $row){
			if (!empty($row['readonly'])){
				continue;
			}
			// Unsaved editor state wins for "missing" detection (emptied fields)
			if ($ui_values !== null && array_key_exists($row['field_name'], $ui_values)){
				$row['selected_value'] = is_scalar($ui_values[$row['field_name']])
						? (string)$ui_values[$row['field_name']]
						: '';
			}
			// Prefer base language text; if empty, use definition default (as the site would fall back)
			$source = $this->_source_text_for_ai($row);
			if (trim($source) === ''){
				continue;
			}
			if ($only_missing && !$this->_is_missing_translation_row($row)){
				continue;
			}
			$items[] = [
					'key' => $row['field_name'],
					'label' => $row['label'],
					'source' => $source,
					'current' => (string)($row['selected_value'] ?? ''),
					'definition_default' => (string)($row['definition_default'] ?? ''),
					'field_type' => $row['field_type'] ?? 'text',
			];
		}

		if ($items === []){
			if ($only_missing){
				return ['error' => 'No missing texts to translate (need empty/default/same-as-base edit value and a base or default source)'];
			}
			return ['error' => 'No source text to translate (base language and definition defaults are empty)'];
		}

		$payload = [
				'source_language' => $default_lang,
				'target_language' => $cms_language,
				'items' => $items,
				'context' => [
						'panel_name' => $grid['panel_name'] ?? '',
						'admin_title' => $grid['admin_title'] ?? '',
						'cms_page_panel_id' => $cms_page_panel_id,
				],
		];

		$CI =& get_instance();
		$response = $CI->run_panel_method($provider, 'ai_request', [
				'task' => 'translate',
				'payload' => $payload,
		]);

		if (!is_array($response)){
			return ['error' => 'AI provider returned an invalid response'];
		}

		// run_panel_method may return params unchanged if method missing
		if (isset($response['task']) && !isset($response['ok'])){
			return ['error' => 'AI provider panel does not implement ai_request'];
		}

		if (empty($response['ok'])){
			return ['error' => $response['error'] ?? 'AI request failed'];
		}

		$result = $response['result'] ?? [];
		$suggestions = $result['suggestions'] ?? [];
		if (!is_array($suggestions) || $suggestions === []){
			// Build from items[]
			$suggestions = [];
			foreach ($result['items'] ?? [] as $item){
				if (!is_array($item)){
					continue;
				}
				$key = (string)($item['key'] ?? '');
				if ($key === ''){
					continue;
				}
				$suggestions[$key] = is_scalar($item['suggestion'] ?? '') ? (string)$item['suggestion'] : '';
			}
		}

		// Only known keys
		$allowed = [];
		foreach ($grid['rows'] as $row){
			$allowed[$row['field_name']] = 1;
		}
		$clean = [];
		foreach ($suggestions as $key => $text){
			if (!empty($allowed[$key])){
				$text = is_scalar($text) ? (string)$text : '';
				if (function_exists('cms_utf8_string')){
					$text = cms_utf8_string($text);
				}
				$clean[$key] = $text;
			}
		}

		return [
				'ok' => 1,
				'suggestions' => $clean,
				'cms_language' => $cms_language,
				'provider' => $provider,
		];

	}

	/**
	 * Per-field popup: all languages for one field.
	 */
	function get_translate_string_data($cms_page_panel_id, $field_name, $field_type = ''){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$path = $this->_param_path_from_name($field_name);

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_language_model');

		$sql = "select panel_name, cms_page_id, parent_id, sort from cms_page_panel where cms_page_panel_id = ? limit 1 ";
		$query = $this->db->query($sql, [$cms_page_panel_id]);

		if (!$query->num_rows()){
			return ['error' => 'Panel not found'];
		}

		$row = $query->row_array();
		$params = $this->cms_page_panel_model->get_cms_page_panel_params($cms_page_panel_id, '');
		if (!is_array($params)){
			$params = [];
		}

		$struct = $this->_find_field_definition($row['panel_name'], $path, $row);
		$definition_default = '';
		if (is_array($struct) && array_key_exists('default', $struct)){
			$definition_default = $this->_resolve_field_default_display($struct['default']);
		}

		if ($field_type === '' && is_array($struct) && !empty($struct['type'])){
			$field_type = $struct['type'];
			if ($field_type == 'color'){
				$field_type = 'colour';
			}
		}

		$default_lang = $this->cms_language_model->normalise_language_id(
				$this->cms_language_model->get_default()
		);
		$main_resolved = $this->_get_translation_value_for_path($params, $path, $default_lang, $default_lang);

		$other_rows = [];
		foreach ($this->_get_configured_languages() as $language_row){
			$language_id = $language_row['language_id'];
			if ($this->cms_language_model->normalise_language_id($language_id) === $default_lang){
				continue;
			}
			$other_rows[] = [
					'language_id' => $language_id,
					'value' => $this->_get_translation_value_for_path($params, $path, $language_id, $default_lang),
			];
		}

		return [
				'field_name' => $field_name,
				'field_path' => $path,
				'field_type' => $field_type,
				'definition_default' => $definition_default,
				'default_language' => $default_lang,
				'main_value' => $main_resolved,
				'other_rows' => $other_rows,
				'readonly' => !empty($struct['readonly']),
		];

	}

	/**
	 * Per-field popup save: map language_id => value.
	 */
	function save_translate_string($cms_page_panel_id, $field_name, $values, $cms_language = ''){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$path = $this->_param_path_from_name($field_name);

		if ($cms_page_panel_id < 1 || $path === ''){
			return ['error' => 'Invalid save request'];
		}

		if (!is_array($values)){
			$values = [];
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_language_model');

		foreach ($values as $language_id => $value){
			$this->cms_page_panel_model->set_translated_param(
					$cms_page_panel_id,
					$path,
					$value,
					$language_id
			);
		}

		$this->cms_page_panel_model->rebuild_panel_param_cache($cms_page_panel_id);

		if ($cms_language === null || $cms_language === ''){
			$cms_language = $this->cms_language_model->get_cms_language();
		}

		$normalised_cms = $this->cms_language_model->normalise_language_id($cms_language);
		$sync_value = null;

		foreach ($values as $language_id => $value){
			if ($this->cms_language_model->normalise_language_id($language_id) === $normalised_cms){
				$sync_value = is_scalar($value) ? (string)$value : '';
				break;
			}
		}

		return [
				'ok' => 1,
				'sync_language' => $cms_language,
				'sync_value' => $sync_value,
		];

	}

	function list_translatable_fields($cms_page_panel_id){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id < 1){
			return [];
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');

		$sql = "select panel_name, cms_page_id, parent_id, sort from cms_page_panel where cms_page_panel_id = ? limit 1 ";
		$query = $this->db->query($sql, [$cms_page_panel_id]);
		if (!$query->num_rows()){
			return [];
		}

		$row = $query->row_array();
		$config = $this->cms_panel_model->get_cms_panel_config($row['panel_name']);
		$structure = $this->cms_panel_model->get_cms_panel_edit_structure(
				$config,
				$row['cms_page_id'] ?? 0,
				$row['parent_id'] ?? 0,
				$row['sort'] ?? 0
		);

		$params = $this->cms_page_panel_model->get_cms_page_panel_params($cms_page_panel_id, '');
		if (!is_array($params)){
			$params = [];
		}

		return $this->_collect_translatable_fields($structure, $params, '', []);

	}

	function get_panel_translation_grid($cms_page_panel_id, $cms_language = ''){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_language_model');

		if ($cms_page_panel_id < 1){
			return ['error' => 'Invalid panel'];
		}

		$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id, '');
		if (empty($block) || empty($block['cms_page_panel_id'])){
			return ['error' => 'Panel not found'];
		}

		if ($cms_language === null || $cms_language === ''){
			$cms_language = $this->cms_language_model->get_cms_language();
		}
		$cms_language = $this->cms_language_model->normalise_language_id($cms_language);
		$default_lang = $this->cms_language_model->normalise_language_id(
				$this->cms_language_model->get_default()
		);

		$params = $this->cms_page_panel_model->get_cms_page_panel_params($cms_page_panel_id, '');
		if (!is_array($params)){
			$params = [];
		}

		$fields = $this->list_translatable_fields($cms_page_panel_id);
		$rows = [];
		foreach ($fields as $field){
			$path = $field['field_path'];
			$rows[] = [
					'field_name' => $field['field_name'],
					'field_path' => $path,
					'label' => $field['label'],
					'field_type' => $field['field_type'],
					'definition_default' => $field['definition_default'],
					'base_value' => $this->_get_translation_value_for_path($params, $path, $default_lang, $default_lang),
					'selected_value' => $this->_get_translation_value_for_path($params, $path, $cms_language, $default_lang),
					'readonly' => !empty($field['readonly']),
			];
		}

		$ai_provider = $this->get_ai_provider_panel();
		$ai_available = ($ai_provider !== '' && $cms_language !== $default_lang);
		$ai_ui = $ai_provider !== '' ? $this->get_ai_ui_options() : [
				'ask_confirmation' => 1,
				'only_missing' => 0,
		];

		return [
				'cms_page_panel_id' => $cms_page_panel_id,
				'panel_name' => $block['panel_name'] ?? '',
				'admin_title' => $this->cms_page_panel_model->get_panel_admin_title($block),
				'default_language' => $default_lang,
				'cms_language' => $cms_language,
				'rows' => $rows,
				'field_count' => count($rows),
				'languages' => $this->_get_configured_languages(),
				'ai_provider' => $ai_provider,
				'ai_available' => $ai_available ? 1 : 0,
				'ai_ask_confirmation' => !empty($ai_ui['ask_confirmation']) ? 1 : 0,
				'ai_only_missing' => !empty($ai_ui['only_missing']) ? 1 : 0,
		];

	}

	/**
	 * Save selected-language values for many fields.
	 * $values_by_field_name: map field_path => string
	 */
	function save_panel_translations($cms_page_panel_id, $cms_language, $values_by_field_name){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_language_model');

		if ($cms_page_panel_id < 1){
			return ['error' => 'Invalid panel'];
		}

		if ($cms_language === null || $cms_language === ''){
			$cms_language = $this->cms_language_model->get_cms_language();
		}
		$cms_language = $this->cms_language_model->normalise_language_id($cms_language);

		if (!is_array($values_by_field_name)){
			$values_by_field_name = [];
		}

		$allowed = [];
		foreach ($this->list_translatable_fields($cms_page_panel_id) as $field){
			if (!empty($field['readonly'])){
				continue;
			}
			$allowed[$field['field_name']] = 1;
		}

		$saved = 0;
		foreach ($values_by_field_name as $field_name => $value){
			$field_name = (string)$field_name;
			if ($field_name === '' || empty($allowed[$field_name])){
				continue;
			}

			$path = $this->_param_path_from_name($field_name);
			if ($path === ''){
				continue;
			}

			$this->cms_page_panel_model->set_translated_param(
					$cms_page_panel_id,
					$path,
					$value,
					$cms_language
			);
			$saved++;
		}

		if ($saved > 0){
			$this->cms_page_panel_model->rebuild_panel_param_cache($cms_page_panel_id);
		}

		return [
				'ok' => 1,
				'saved' => $saved,
				'cms_language' => $cms_language,
		];

	}

	function _param_path_from_name($name){

		$name = (string)$name;

		if (strpos($name, 'panel_params[') !== 0){
			return $name;
		}

		if (!preg_match_all('/\[([^\]]*)\]/', $name, $matches)){
			return '';
		}

		return implode('.', $matches[1]);

	}

	function _find_field_definition($panel_name, $path, $panel_row = []){

		$this->load->model('cms/cms_panel_model');

		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
		$structure = $this->cms_panel_model->get_cms_panel_edit_structure(
				$config,
				$panel_row['cms_page_id'] ?? 0,
				$panel_row['parent_id'] ?? 0,
				$panel_row['sort'] ?? 0
		);

		$keys = explode('.', $path);
		$fields = $structure;
		$struct = null;

		foreach ($keys as $key){
			if ($key === '' || is_numeric($key) || preg_match('/^0+\d+$/', $key)){
				continue;
			}
			foreach ($fields as $field){
				if (($field['name'] ?? '') !== $key){
					continue;
				}
				$struct = $field;
				if (($field['type'] ?? '') === 'repeater'){
					$fields = $field['fields'] ?? [];
				}
				break;
			}
		}

		return $struct;

	}

	function _is_translatable_field_type($type){

		$type = strtolower(trim((string)$type));
		if ($type === 'color'){
			$type = 'colour';
		}

		return in_array($type, ['text', 'textarea', 'colour'], true);

	}

	function _collect_translatable_fields($structure, $data, $path_prefix, $label_prefix){

		$out = [];
		if (!is_array($structure)){
			return $out;
		}

		foreach ($structure as $field){
			if (!is_array($field) || empty($field['name'])){
				continue;
			}

			$name = $field['name'];
			$type = strtolower(trim((string)($field['type'] ?? 'text')));
			if ($type === 'color'){
				$type = 'colour';
			}

			$path = $path_prefix === '' ? $name : $path_prefix.'.'.$name;
			$label = trim((string)($field['label'] ?? $name));
			$display_label = $label_prefix === [] ? $label : implode(' › ', array_merge($label_prefix, [$label]));

			if ($type === 'repeater'){
				$items = [];
				if (is_array($data) && isset($data[$name]) && is_array($data[$name])){
					$items = $data[$name];
				}
				$child_fields = $field['fields'] ?? [];
				if (!is_array($child_fields) || $child_fields === []){
					continue;
				}
				foreach ($items as $idx => $item_data){
					$idx_label = is_numeric($idx) ? ((int)$idx + 1) : $idx;
					$child_prefix_labels = array_merge($label_prefix, [$label.' #'.$idx_label]);
					$child_path = $path.'.'.$idx;
					$out = array_merge(
							$out,
							$this->_collect_translatable_fields(
									$child_fields,
									is_array($item_data) ? $item_data : [],
									$child_path,
									$child_prefix_labels
							)
					);
				}
				continue;
			}

			if (empty($field['translate'])){
				continue;
			}

			if (!$this->_is_translatable_field_type($type)){
				continue;
			}

			$definition_default = '';
			if (array_key_exists('default', $field)){
				$definition_default = $this->_resolve_field_default_display($field['default']);
			}

			$out[] = [
					'field_name' => $path,
					'field_path' => $path,
					'label' => $display_label,
					'field_type' => $type,
					'definition_default' => $definition_default,
					'readonly' => !empty($field['readonly']),
			];
		}

		return $out;

	}

	function _get_translation_value_for_path($params, $path, $language_id, $default_lang){

		$this->load->model('cms/cms_language_model');
		$language_id = $this->cms_language_model->normalise_language_id($language_id);
		$default_lang = $this->cms_language_model->normalise_language_id($default_lang);
		$translations = is_array($params) ? ($params['_translations'] ?? []) : [];

		if ($language_id === $default_lang){
			$main_value = $this->_get_param_by_path($params, $path);
			$stored_main = '';
			if (!empty($translations[$default_lang])){
				$stored_main = $this->_get_param_by_path($translations[$default_lang], $path);
			}
			if ($stored_main === '' && is_array($translations)){
				foreach ($translations as $lang_key => $branch){
					if ($this->cms_language_model->normalise_language_id($lang_key) === $default_lang){
						$stored_main = $this->_get_param_by_path($branch, $path);
						break;
					}
				}
			}
			return $stored_main !== '' ? $stored_main : $main_value;
		}

		$stored = '';
		if (!empty($translations[$language_id])){
			$stored = $this->_get_param_by_path($translations[$language_id], $path);
		} else if (is_array($translations)){
			foreach ($translations as $lang_key => $branch){
				if ($this->cms_language_model->normalise_language_id($lang_key) === $language_id){
					$stored = $this->_get_param_by_path($branch, $path);
					break;
				}
			}
		}

		return $stored;

	}

	function _get_param_by_path($params, $path){

		if ($path === '' || $path === null || !is_array($params)){
			return '';
		}

		$keys = explode('.', $path);
		$arr = $params;

		foreach ($keys as $key){
			if (!is_array($arr)){
				return '';
			}
			if (!array_key_exists($key, $arr)){
				if (is_numeric($key)){
					$padded = str_pad($key, 6, '0', STR_PAD_LEFT);
					if (array_key_exists($padded, $arr)){
						$arr = $arr[$padded];
						continue;
					}
				}
				return '';
			}
			$arr = $arr[$key];
		}

		if (is_array($arr)){
			return '';
		}

		return (string)$arr;

	}

	function _resolve_field_default_display($default){

		if (!isset($default) || is_array($default)){
			return '';
		}

		$default = (string)$default;

		if (substr($default, 0, 6) == ':date:'){
			$defparams = explode(':', $default);
			if (empty($defparams[3])){
				return date(substr($default, 6));
			}
			return date($defparams[2], time() + (int)$defparams[3]);
		}

		if (substr($default, 0, 5) == ':rnd:'){
			$length = (int)substr($default, 5);
			if ($length < 1){
				return '';
			}
			$chars = '0123456789abcdefghijklmnopqrstuvwxyz';
			$return = '';
			while (strlen($return) < $length){
				$pos = mt_rand(0, strlen($chars) - 1);
				$return .= $chars[$pos];
			}
			return $return;
		}

		return $default;

	}

	function _get_configured_languages(){

		$this->load->model('cms/cms_language_model');
		$this->load->model('cms/cms_page_panel_model');

		$languages = [];

		if (!empty($GLOBALS['language']['languages']) && is_array($GLOBALS['language']['languages'])){
			foreach ($GLOBALS['language']['languages'] as $language_id => $label){
				$languages[] = [
						'language_id' => $this->cms_language_model->normalise_language_id($language_id),
						'label' => $label,
				];
			}
			return $languages;
		}

		$targets = $this->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_targets');
		$groups = $targets['groups'] ?? [];

		if (is_array($groups)){
			foreach ($groups as $group){
				if (($group['heading'] ?? '') !== 'language' || ($group['strategy'] ?? '') !== 'language'){
					continue;
				}
				$ids = array_map('trim', explode('|', $group['settings'] ?? ''));
				$labels = array_map('trim', explode('|', $group['labels'] ?? ''));
				foreach ($ids as $key => $language_id){
					if ($language_id === ''){
						continue;
					}
					$languages[] = [
							'language_id' => $this->cms_language_model->normalise_language_id($language_id),
							'label' => $labels[$key] ?? $language_id,
					];
				}
				break;
			}
		}

		if (empty($languages)){
			$languages[] = [
					'language_id' => $this->cms_language_model->normalise_language_id(
							$this->cms_language_model->get_default()
					),
					'label' => '',
			];
		}

		return $languages;

	}

}
