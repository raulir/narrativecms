<?php

namespace user;

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Frontend translation mount + panel list for cms_translate users.
 * Lives in user module (FE keys / session). Uses cms translation models only for data.
 */
class page_translate extends \Controller {

	function _can_translate(){

		if (!empty($_SESSION['cms_user']['cms_user_id'])){
			return true;
		}

		$this->load->model('cms/cms_access_model');

		return $this->cms_access_model->user_has_access('cms_translate');

	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do !== 'page_translate_list'){
			return $params;
		}

		if (!$this->_can_translate()){
			print(json_encode(['result' => ['ok' => 0, 'items' => []]], JSON_PRETTY_PRINT));
			die();
		}

		$cms_page_id = (int)$this->input->post('cms_page_id');
		// SPA: footer mount may still have old id; client prefers main position.
		// If id missing, resolve from current path slug (e.g. /login/ → login).
		if ($cms_page_id < 1){
			$path = (string)$this->input->post('path');
			$cms_page_id = $this->_page_id_from_path($path);
		}
		$unit_id = (int)$this->input->post('unit_id');
		$types_raw = $this->input->post('types');
		$types = [];
		if (is_string($types_raw) && $types_raw !== ''){
			$decoded = cms_json_decode($types_raw, 'page_translate_types');
			if (is_array($decoded)){
				$types = $decoded;
			}
		} else if (is_array($types_raw)){
			$types = $types_raw;
		}

		$items = $this->_list_translatable_panels($cms_page_id, $unit_id, $types);

		print(json_encode(['result' => ['ok' => 1, 'items' => $items]], JSON_PRETTY_PRINT));
		die();

	}

	function panel_params($params){

		if (!$this->_can_translate()){
			$params['allowed'] = 0;
			return $params;
		}

		$params['allowed'] = 1;

		// Only this panel's CSS/JS — no cms.scss / input / admin chrome
		add_css('modules/user/css/page_translate.scss');
		add_css('modules/user/css/page_translation.scss');
		$GLOBALS['_panel_js'][] = 'modules/user/js/page_translate.js';
		$GLOBALS['_panel_js'][] = 'modules/user/js/page_translation.js';

		$cms_page_id = (int)($params['cms_page_id'] ?? 0);
		if ($cms_page_id < 1){
			$cms_page_id = (int)($params['_cms_page_id'] ?? 0);
		}

		$this->load->model('cms/cms_page_panel_model');
		$visitor_language = $this->cms_page_panel_model->get_current_language();

		$params['cms_page_id'] = $cms_page_id;
		$params['visitor_language'] = $visitor_language;
		$params['is_cms_user'] = !empty($_SESSION['cms_user']['cms_user_id']) ? 1 : 0;

		return $params;

	}

	/**
	 * Resolve main page id from URL path when SPA client could not supply cms_page_id.
	 */
	function _page_id_from_path($path){

		$path = trim((string)$path);
		if ($path === ''){
			return 0;
		}

		// Accept full URL or path
		if (strpos($path, '://') !== false){
			$parts = parse_url($path);
			$path = $parts['path'] ?? '';
		}

		$path = trim($path, '/');
		if ($path === ''){
			// Homepage often uses empty slug
			$this->load->model('cms/cms_page_model');
			$page = $this->cms_page_model->get_page_by_slug('');
			return !empty($page['cms_page_id']) ? (int)$page['cms_page_id'] : 0;
		}

		// Prefer last segment as public slug (multi-segment list items still work via main page id from SPA)
		$segments = explode('/', $path);
		$slug = end($segments);
		$slug = is_string($slug) ? $slug : '';

		$this->load->model('cms/cms_page_model');
		$page = $this->cms_page_model->get_page_by_slug($slug);
		if (!empty($page['cms_page_id'])){
			return (int)$page['cms_page_id'];
		}

		// Full path as slug (rare)
		if ($slug !== $path){
			$page = $this->cms_page_model->get_page_by_slug($path);
			if (!empty($page['cms_page_id'])){
				return (int)$page['cms_page_id'];
			}
		}

		return 0;

	}

	function _list_translatable_panels($cms_page_id, $unit_id = 0, $types = []){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_translation_model');
		$this->load->model('cms/cms_panel_model');

		$items = [];
		$seen = [];

		$add = function($id, $label, $panel_name, $kind) use (&$items, &$seen){

			$id = (int)$id;
			if ($id < 1 || isset($seen[$id])){
				return;
			}
			$seen[$id] = 1;
			$items[] = [
					'id' => $id,
					'label' => $label !== '' ? $label : ('Panel #'.$id),
					'panel_name' => (string)$panel_name,
					'kind' => (string)$kind,
			];

		};

		$page_ids = [];
		if ($cms_page_id > 0){
			$page_ids[] = $cms_page_id;

			$this->load->model('cms/cms_page_model');
			$page = $this->cms_page_model->get_page($cms_page_id);
			if (!empty($page['positions']) && is_array($page['positions'])){
				foreach ($page['positions'] as $position){
					$pos_id = (int)($position['value'] ?? 0);
					if ($pos_id > 0 && !in_array($pos_id, $page_ids, true)){
						$page_ids[] = $pos_id;
					}
				}
			}
		}

		$panel_names_on_page = [];

		foreach ($page_ids as $page_id){

			$blocks = $this->cms_page_panel_model->get_cms_page_panels_by([
					'cms_page_id' => $page_id,
					'show' => 1,
					'_fields' => ['cms_page_panel_id', 'cms_page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name'],
			]);

			if (!is_array($blocks)){
				continue;
			}

			foreach ($blocks as $block){

				$panel_name = (string)($block['panel_name'] ?? '');
				if ($panel_name === '' || $panel_name === 'user/page_translate' || $panel_name === 'cms/cms_page_translate'){
					continue;
				}

				$panel_names_on_page[$panel_name] = 1;

				$id = (int)($block['cms_page_panel_id'] ?? 0);
				if ($id < 1){
					continue;
				}

				$fields = $this->cms_translation_model->list_translatable_fields($id);
				if (empty($fields)){
					continue;
				}

				$title = $this->cms_page_panel_model->get_panel_admin_title($block);
				if ($title === ''){
					$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
					$title = !empty($config['label']) ? $config['label'] : $panel_name;
				}

				$add($id, $title, $panel_name, 'page');

			}

		}

		$settings_panel_names = array_keys($panel_names_on_page);

		$extra_names = $this->_get_extra_settings_panel_names($cms_page_id, $unit_id, $types, $panel_names_on_page);
		foreach ($extra_names as $extra_name){
			if (!in_array($extra_name, $settings_panel_names, true)){
				$settings_panel_names[] = $extra_name;
			}
		}

		foreach ($settings_panel_names as $panel_name){

			$settings_id = $this->cms_page_panel_model->get_settings_panel_id($panel_name);
			if ($settings_id < 1){
				continue;
			}

			$fields = $this->cms_translation_model->list_translatable_fields($settings_id);
			if (empty($fields)){
				continue;
			}

			$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
			$label = !empty($config['label']) ? $config['label'].' settings' : $panel_name.' settings';

			$block = $this->cms_page_panel_model->get_cms_page_panel($settings_id, '');
			$admin_title = is_array($block) ? $this->cms_page_panel_model->get_panel_admin_title($block) : '';
			if ($admin_title !== ''){
				$label = $admin_title;
			}

			$add($settings_id, $label, $panel_name, 'settings');

		}

		// List-item extras (e.g. unit material) — by cms_page_panel_id
		foreach ($this->_get_extra_panels($cms_page_id, $unit_id, $types, $panel_names_on_page) as $extra){
			$id = (int)($extra['id'] ?? 0);
			if ($id < 1){
				continue;
			}
			$add(
					$id,
					(string)($extra['label'] ?? ''),
					(string)($extra['panel_name'] ?? ''),
					(string)($extra['kind'] ?? 'page')
			);
		}

		return $items;

	}

	/**
	 * Modules that may supply FE translate list extras (settings names + panel rows).
	 */
	function _translate_extra_modules(){

		$wanted = ['music', 'subscription'];
		$loaded = $GLOBALS['config']['modules'] ?? [];
		$out = [];
		foreach ($wanted as $mod){
			if (in_array($mod, $loaded, true)){
				$out[] = $mod;
			}
		}
		return $out;

	}

	function _get_extra_settings_panel_names($cms_page_id, $unit_id, $types, $panel_names_on_page){

		$names = [];
		$CI =& get_instance();
		$context = [
				'cms_page_id' => $cms_page_id,
				'unit_id' => $unit_id,
				'types' => $types,
				'panel_names' => array_keys($panel_names_on_page),
		];

		foreach ($this->_translate_extra_modules() as $mod){

			$model_path = $mod.'/'.$mod.'_model';
			$CI->load->model($model_path);
			$prop = $mod.'_model';
			if (empty($CI->$prop) || !method_exists($CI->$prop, 'get_page_translate_extra_panel_names')){
				continue;
			}

			$extra = $CI->$prop->get_page_translate_extra_panel_names($context);
			if (!is_array($extra)){
				continue;
			}

			foreach ($extra as $name){
				$name = trim((string)$name);
				if ($name !== '' && strpos($name, '/') !== false && !in_array($name, $names, true)){
					$names[] = $name;
				}
			}

		}

		return $names;

	}

	/**
	 * Module hook: full panel rows (materials, subscription products, …).
	 */
	function _get_extra_panels($cms_page_id, $unit_id, $types, $panel_names_on_page){

		$items = [];
		$CI =& get_instance();
		$context = [
				'cms_page_id' => $cms_page_id,
				'unit_id' => $unit_id,
				'types' => $types,
				'panel_names' => array_keys($panel_names_on_page),
		];

		foreach ($this->_translate_extra_modules() as $mod){

			$model_path = $mod.'/'.$mod.'_model';
			$CI->load->model($model_path);
			$prop = $mod.'_model';
			if (empty($CI->$prop) || !method_exists($CI->$prop, 'get_page_translate_extra_panels')){
				continue;
			}

			$extra = $CI->$prop->get_page_translate_extra_panels($context);
			if (!is_array($extra)){
				continue;
			}

			foreach ($extra as $row){
				if (!is_array($row) || empty($row['id'])){
					continue;
				}
				$items[] = $row;
			}

		}

		return $items;

	}

}
