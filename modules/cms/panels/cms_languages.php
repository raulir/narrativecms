<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_languages extends CI_Controller {

	function _ensure_models(){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_language_model');

	}

	function ds_languages($params){

		$this->_ensure_models();

		$cms_page_panel_id = (int)($params['id'] ?? 0);

		if ($params['do'] == 'S'){

			return $params['fields'] ?? [];

		} else if ($params['do'] == 'L'){

			$languages = $this->_load_languages($cms_page_panel_id);

			return $this->_languages_to_lines($languages, $cms_page_panel_id);

		} else if ($params['do'] == 'C'){

			$languages = $this->_load_languages($cms_page_panel_id);
			$languages[] = ['language_id' => '', 'label' => '', 'endonym' => ''];
			$this->_save_languages($cms_page_panel_id, $languages);

			return ['web' => 1];

		} else if ($params['do'] == 'D'){

			$languages = $this->_load_languages($cms_page_panel_id);
			$row_id = (int)($params['row_id'] ?? $params['id'] ?? -1);

			if (isset($languages[$row_id])){
				$removed_id = $languages[$row_id]['language_id'] ?? '';
				unset($languages[$row_id]);
				$languages = array_values($languages);
				$this->_save_languages($cms_page_panel_id, $languages);
				if ($removed_id !== ''){
					$this->_remove_local_labels($cms_page_panel_id, $removed_id);
				}
				$this->_sync_targets($languages);
				$this->_enable_targets();
			}

			return ['web' => 1];

		} else if ($params['do'] == 'U'){

			$languages = $this->_load_languages($cms_page_panel_id);
			$row_id = (int)($params['row_id'] ?? -1);
			$col = $params['col'] ?? '';

			if (!isset($languages[$row_id]) || $col === ''){
				return ['web' => 1];
			}

			if ($col == 'local_label'){

				$language_id = $languages[$row_id]['language_id'] ?? '';
				if ($language_id === ''){
					return ['web' => 1];
				}

				$cms_language = $this->_resolve_cms_language($params['cms_language'] ?? '');
				$this->_save_local_label($cms_page_panel_id, $cms_language, $language_id, $params['value'] ?? '');

				return ['web' => 1];

			}

			if ($col == 'language_id'){

				$current_id = $languages[$row_id]['language_id'] ?? '';
				if ($current_id !== ''){
					return ['web' => 1];
				}

				$value = $this->_normalise_language_id($params['value'] ?? '');
				if ($value === '' || $this->_language_id_exists($languages, $value, $row_id)){
					return ['web' => 1];
				}

				$languages[$row_id]['language_id'] = $value;

			} else if ($col == 'label'){

				$languages[$row_id]['label'] = trim($params['value'] ?? '');

			} else if ($col == 'endonym'){

				$languages[$row_id]['endonym'] = trim($params['value'] ?? '');

			} else {

				return ['web' => 1];

			}

			$this->_save_languages($cms_page_panel_id, $languages);

			if ($col == 'language_id' || $col == 'label'){
				$this->_sync_targets($languages);
				$this->_enable_targets();
			}

			return ['web' => 1];

		}

		return [];

	}

	function on_update($data){

		$this->_ensure_models();

		$cms_page_panel_id = (int)($data['cms_page_panel_id'] ?? 0);
		if (!$cms_page_panel_id){
			return $data;
		}

		if (empty($data['languages']) || !is_array($data['languages'])){
			$data['languages'] = $this->_load_languages($cms_page_panel_id);
		}

		$languages = $data['languages'];
		$this->_sync_targets($languages);
		$this->_enable_targets();
		$this->_sync_basic_select_label($cms_page_panel_id, $data);

		return $data;

	}

	function _load_languages($cms_page_panel_id){

		$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		$languages = $panel['languages'] ?? [];

		if (!is_array($languages)){
			$languages = [];
		}

		if (empty($languages)){
			$languages = $this->_bootstrap_from_targets($cms_page_panel_id, $panel);
		}

		return $this->_normalise_languages_list($languages);

	}

	function _save_languages($cms_page_panel_id, $languages){

		$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, [
				'languages' => $this->_normalise_languages_list($languages),
		]);

	}

	function _normalise_languages_list($languages){

		$return = [];

		foreach ($languages as $row){
			if (!is_array($row)){
				continue;
			}
			$return[] = [
					'language_id' => $this->_normalise_language_id($row['language_id'] ?? ''),
					'label' => trim($row['label'] ?? ''),
					'endonym' => trim($row['endonym'] ?? ''),
			];
		}

		return $return;

	}

	function _languages_to_lines($languages, $cms_page_panel_id){

		$local_labels = [];

		if ($cms_page_panel_id){
			$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
			$cms_language = $this->cms_page_panel_model->get_cms_language();
			$local_labels = $panel['_translations'][$cms_language]['local_labels'] ?? [];
			if (!is_array($local_labels)){
				$local_labels = [];
			}
		}

		$lines = [];

		foreach ($languages as $row_index => $row){

			$language_id = $row['language_id'] ?? '';

			$lines[] = [
					'id' => $row_index,
					'language_id' => $language_id,
					'label' => $row['label'] ?? '',
					'endonym' => $row['endonym'] ?? '',
					'local_label' => ($language_id !== '' && !empty($local_labels[$language_id])) ? $local_labels[$language_id] : '',
			];

		}

		return $lines;

	}

	function _bootstrap_from_targets($cms_page_panel_id, $panel){

		$languages = [];
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
							'language_id' => $this->_normalise_language_id($language_id),
							'label' => $labels[$key] ?? $language_id,
							'endonym' => '',
					];
				}
				break;
			}
		}

		if (empty($languages)){
			$default_id = '';
			$cms_settings = $this->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_settings');
			if (!empty($cms_settings['language'])){
				$default_id = $this->_normalise_language_id($cms_settings['language']);
			}
			if ($default_id === ''){
				$default_id = 'en';
			}
			$languages[] = ['language_id' => $default_id, 'label' => '', 'endonym' => ''];
		}

		$update = ['languages' => $languages];

		if (empty($panel['select_label'])){
			$basic = $this->cms_page_panel_model->get_cms_page_panel_settings('basic/language');
			if (!empty($basic['select_label'])){
				$update['select_label'] = $basic['select_label'];
			}
		}

		$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, $update);

		return $languages;

	}

	function _sync_targets($languages){

		$targets_panel_id = $this->_get_settings_panel_id('cms/cms_targets');
		if (!$targets_panel_id){
			return;
		}

		$targets = $this->cms_page_panel_model->get_cms_page_panel($targets_panel_id);
		$groups = $targets['groups'] ?? [];
		if (!is_array($groups)){
			$groups = [];
		}

		$ids = [];
		$labels = [];

		foreach ($languages as $row){
			$language_id = $row['language_id'] ?? '';
			$label = trim($row['label'] ?? '');
			if ($language_id === '' || $label === ''){
				continue;
			}
			$ids[] = $language_id;
			$labels[] = $label;
		}

		if (empty($ids)){
			return;
		}

		$language_group = [
				'heading' => 'language',
				'strategy' => 'language',
				'settings' => implode('|', $ids),
				'labels' => implode('|', $labels),
		];

		$found = false;
		foreach ($groups as $key => $group){
			if (($group['heading'] ?? '') === 'language'){
				$groups[$key] = $language_group;
				$found = true;
				break;
			}
		}

		if (!$found){
			$groups[] = $language_group;
		}

		$this->cms_page_panel_model->update_cms_page_panel($targets_panel_id, ['groups' => $groups], true);

	}

	function _enable_targets(){

		$settings_panel_id = $this->_get_settings_panel_id('cms/cms_settings');
		if (!$settings_panel_id){
			return;
		}

		$this->cms_page_panel_model->update_cms_page_panel($settings_panel_id, ['targets_enabled' => '1']);

	}

	function _sync_basic_select_label($cms_page_panel_id, $data){

		$basic_panel_id = $this->_get_settings_panel_id('basic/language');
		if (!$basic_panel_id){
			return;
		}

		$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		$basic = $this->cms_page_panel_model->get_cms_page_panel($basic_panel_id);
		$basic_translations = $basic['_translations'] ?? [];
		if (!is_array($basic_translations)){
			$basic_translations = [];
		}

		$select_label = $data['select_label'] ?? $panel['select_label'] ?? '';
		$update = ['select_label' => $select_label];

		$source_translations = $data['_translations'] ?? $panel['_translations'] ?? [];
		if (is_array($source_translations)){
			foreach ($source_translations as $lang => $branch){
				if (!is_array($branch) || !isset($branch['select_label'])){
					continue;
				}
				if (!isset($basic_translations[$lang]) || !is_array($basic_translations[$lang])){
					$basic_translations[$lang] = [];
				}
				$basic_translations[$lang]['select_label'] = $branch['select_label'];
			}
		}

		$update['_translations'] = $basic_translations;

		$this->cms_page_panel_model->update_cms_page_panel($basic_panel_id, $update);

	}

	function _save_local_label($cms_page_panel_id, $cms_language, $language_id, $value){

		$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		$translations = $panel['_translations'] ?? [];
		if (!is_array($translations)){
			$translations = [];
		}

		if (!isset($translations[$cms_language]) || !is_array($translations[$cms_language])){
			$translations[$cms_language] = [];
		}

		if (!isset($translations[$cms_language]['local_labels']) || !is_array($translations[$cms_language]['local_labels'])){
			$translations[$cms_language]['local_labels'] = [];
		}

		$value = trim($value);
		if ($value === ''){
			unset($translations[$cms_language]['local_labels'][$language_id]);
		} else {
			$translations[$cms_language]['local_labels'][$language_id] = $value;
		}

		$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, ['_translations' => $translations]);

	}

	function _remove_local_labels($cms_page_panel_id, $language_id){

		$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		$translations = $panel['_translations'] ?? [];
		if (!is_array($translations)){
			return;
		}

		$changed = false;

		foreach ($translations as $cms_language => $branch){
			if (!is_array($branch) || empty($branch['local_labels'][$language_id])){
				continue;
			}
			unset($translations[$cms_language]['local_labels'][$language_id]);
			$changed = true;
		}

		if ($changed){
			$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, ['_translations' => $translations]);
		}

	}

	function _get_settings_panel_id($panel_name){

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => $panel_name,
				'cms_page_id' => 0,
				'parent_id' => 0,
				'sort' => 0,
		]);

		return !empty($panels[0]['cms_page_panel_id']) ? (int)$panels[0]['cms_page_panel_id'] : 0;

	}

	function _normalise_language_id($language_id){

		$this->_ensure_models();

		return $this->cms_language_model->normalise_language_id($language_id);

	}

	function _resolve_cms_language($cms_language){

		$this->_ensure_models();

		if ($cms_language === '' || $cms_language === null){
			return $this->cms_page_panel_model->get_cms_language();
		}

		$allowed = $GLOBALS['language']['languages'] ?? [];
		$resolved = $this->cms_language_model->resolve_language_id($cms_language, $allowed);

		return $resolved !== false ? $resolved : $this->cms_page_panel_model->get_cms_language();

	}

	function _language_id_exists($languages, $language_id, $skip_index = -1){

		foreach ($languages as $index => $row){
			if ($index === $skip_index){
				continue;
			}
			if (($row['language_id'] ?? '') === $language_id){
				return true;
			}
		}

		return false;

	}

}