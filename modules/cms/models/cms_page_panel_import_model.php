<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Page-panel zip import. The panel keeps the upload and the session token.
 */
class cms_page_panel_import_model extends \Model {

	var $zip = null;
	var $manifest = [];
	var $created = [];
	var $importing = [];

	function __construct(){

		// Base Model has no constructor — do not call parent::__construct()
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_image_model');
		$this->load->model('cms/cms_file_model');

	}

	function read_upload($params){

		$params['list_label'] = trim((string)($params['list_label'] ?? $this->input->post('list_panel')));
		$params['view'] = 'upload';
		$params['message'] = '';
		$params['token'] = '';
		$params['blocks'] = [];
		$types = $this->_list_types($params['list_label']);

		if (empty($_FILES['import_file']['tmp_name']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])){
			$params['view'] = 'message';
			$params['message'] = $this->_missing_upload_message();
			return $params;
		}

		$token = bin2hex(random_bytes(16));
		$path = cms_path('tmp').'import_'.$token.'.zip';
		if (!move_uploaded_file($_FILES['import_file']['tmp_name'], $path)){
			$params['view'] = 'message';
			$params['message'] = 'Could not store the file.';
			return $params;
		}

		$manifest = $this->_read_manifest($path);
		if ($manifest === false){
			@unlink($path);
			$params['view'] = 'message';
			$params['message'] = 'No export manifest in the file.';
			return $params;
		}

		$_SESSION['cms_import'][$token] = [
			'path' => $path,
			'types' => $types,
		];
		$this->manifest = $manifest;

		$blocks = [];
		foreach ($manifest['_main'] as $main_id){
			$panel = $manifest['_panels'][$main_id] ?? null;
			if (empty($panel['panel_name']) || !in_array($panel['panel_name'], $types, true)){
				continue;
			}
			$local = $this->_local_panel($main_id);
			$matched = $local && $local['panel_name'] === $panel['panel_name'];
			$blocks[] = [
				'zip_id' => $main_id,
				'zip_title' => (string)($panel['title'] ?? ''),
				'cms_id' => $matched ? (int)$local['cms_page_panel_id'] : 0,
				'cms_title' => $matched ? (string)$local['title'] : '',
				'fks' => $this->_missing_fks($main_id),
			];
		}

		$params['token'] = $token;
		if (!$blocks){
			$params['view'] = 'message';
			$params['message'] = 'No panels of type '.$params['list_label'].' found in the file.';
			return $params;
		}

		$params['view'] = 'list';
		$params['blocks'] = $blocks;

		return $params;

	}

	/**
	 * Delete the zip stored for this upload and drop the session entry.
	 */
	function discard($request = null){

		if (!is_array($request)){
			$request = [
				'token' => $this->input->post('token'),
			];
		}

		$this->_discard_token((string)($request['token'] ?? ''));

		return ['ok' => 1];

	}

	/**
	 * Import one main panel. $request may supply token, zip_id, mode, and fk_ids
	 * (array or JSON). Otherwise those values are read from the request.
	 */
	function apply($request = null){

		if (!is_array($request)){
			$request = [
				'token' => $this->input->post('token'),
				'zip_id' => $this->input->post('zip_id'),
				'mode' => $this->input->post('mode'),
				'fk_ids' => $this->input->post('fk_ids'),
			];
		}

		$token = (string)($request['token'] ?? '');
		$this->created = [];
		$this->importing = [];
		$entry = $_SESSION['cms_import'][$token] ?? null;
		$path = is_array($entry) ? (string)($entry['path'] ?? '') : '';
		$types = is_array($entry) ? ($entry['types'] ?? []) : [];
		if (!is_array($types)){
			$types = [];
		}

		$manifest = ($path !== '' && is_file($path)) ? $this->_read_manifest($path) : false;
		if ($manifest === false){
			return ['ok' => 0, 'message' => 'Could not read the export file.'];
		}

		$this->manifest = $manifest;
		$zip = new \ZipArchive();
		if ($zip->open($path) !== true){
			return ['ok' => 0, 'message' => 'Could not read the export file.'];
		}
		$this->zip = $zip;

		$old_id = (int)($request['zip_id'] ?? 0);
		$mode = (($request['mode'] ?? '') === 'overwrite') ? 'overwrite' : 'new';
		$fk_ids = $this->_fk_id_list($request['fk_ids'] ?? []);
		$panel_name = $manifest['_panels'][$old_id]['panel_name'] ?? '';

		if (!$types || empty($manifest['_panels'][$old_id]) || !in_array($old_id, $manifest['_main'], true) || !in_array($panel_name, $types, true)){
			return $this->_fail('That panel is not a main of this list.');
		}

		$offered = [];
		foreach ($this->_missing_fks($old_id) as $fk){
			$offered[(int)$fk['id']] = true;
		}
		foreach ($fk_ids as $fk_id){
			if (empty($offered[$fk_id])){
				return $this->_fail('That target was not offered for this panel.');
			}
			$fk_name = (string)($manifest['_panels'][$fk_id]['panel_name'] ?? '');
			if (!$this->_definition_exists($fk_name)){
				$label = $fk_name !== '' ? $fk_name : 'unknown';
				return $this->_fail('Panel type '.$label.' is not in this CMS.');
			}
		}

		$map = [];
		$this->created = [];
		$this->importing = [];

		try {

			// Targets go into the map before children are saved, or a child's fk field keeps the zip id.
			foreach ($fk_ids as $fk_id){
				$fk_new = $this->_import_node($fk_id, 'new', $map, false, 0);
				if (!$fk_new){
					return $this->_fail('The import could not be completed.');
				}
				$this->_set_child_parents($fk_id, $fk_new, $map);
			}

			$new_id = $this->_import_node($old_id, $mode, $map, true, 0);
			if (!$new_id){
				return $this->_fail('The import could not be completed.');
			}
			$this->_set_child_parents($old_id, $new_id, $map);

			if ($panel_name !== ''){
				$this->_import_settings($panel_name);
			}

		} catch (\Throwable $e){
			error_log('cms import failed: '.$e->getMessage());
			return $this->_fail('The import could not be completed.');
		}

		try {
			$zip->close();
		} catch (\Throwable $e){
			error_log('cms import close failed: '.$e->getMessage());
		}
		$this->zip = null;
		$this->created = [];

		return ['ok' => 1];

	}

	function _fail($message){

		$this->_rollback_created();
		if ($this->zip){
			$this->zip->close();
			$this->zip = null;
		}

		return ['ok' => 0, 'message' => $message];

	}

	function _rollback_created(){

		$ids = array_keys($this->created);
		$this->created = [];

		foreach ($ids as $id){
			$id = (int)$id;
			if ($id < 1){
				continue;
			}
			try {
				$row = $this->_local_panel($id);
				if (!empty($row['cms_page_panel_id'])){
					$this->cms_page_panel_model->delete_cms_page_panel($id);
				}
			} catch (\Throwable $e){
				error_log('cms import rollback failed for '.$id.': '.$e->getMessage());
			}
		}

	}

	function _discard_token($token){

		$token = (string)$token;
		if (!preg_match('/^[a-f0-9]{32}$/', $token) || empty($_SESSION['cms_import'][$token]) || !is_array($_SESSION['cms_import'][$token])){
			return;
		}

		$path = (string)($_SESSION['cms_import'][$token]['path'] ?? '');
		$expected = cms_path('tmp').'import_'.$token.'.zip';
		if ($path !== '' && $path === $expected && is_file($path)){
			@unlink($path);
		}
		unset($_SESSION['cms_import'][$token]);

	}

	function _missing_upload_message(){

		$error = (int)($_FILES['import_file']['error'] ?? 0);
		if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE){
			return 'The file is too large.';
		}

		$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
		$max = $this->_ini_bytes((string)ini_get('post_max_size'));
		if ($length > 0 && $max > 0 && $length > $max && empty($_FILES['import_file']['tmp_name'])){
			return 'The file is too large.';
		}

		if ($error && $error !== UPLOAD_ERR_NO_FILE){
			return 'Could not store the file.';
		}

		return 'Choose a zip file.';

	}

	function _ini_bytes($value){

		$value = trim($value);
		if ($value === ''){
			return 0;
		}

		$unit = strtolower(substr($value, -1));
		$number = (float)$value;
		if ($unit === 'g'){
			$number *= 1024 * 1024 * 1024;
		} else if ($unit === 'm'){
			$number *= 1024 * 1024;
		} else if ($unit === 'k'){
			$number *= 1024;
		}

		return (int)$number;

	}

	function _fk_id_list($value){

		if (is_string($value)){
			$value = json_decode($value, true);
		}
		if (!is_array($value)){
			return [];
		}

		$ids = [];
		foreach ($value as $id){
			$id = (int)$id;
			if ($id > 0 && !in_array($id, $ids, true)){
				$ids[] = $id;
			}
		}

		return $ids;

	}

	function _definition_path($panel_name){

		$panel_name = (string)$panel_name;
		if ($panel_name === '' || strpos($panel_name, '/') === false){
			return '';
		}

		return $GLOBALS['config']['base_path'].'modules/'.str_replace('/', '/definitions/', $panel_name).'.json';

	}

	function _definition_exists($panel_name){

		$path = $this->_definition_path($panel_name);

		return $path !== '' && is_file($path);

	}

	function _read_manifest($path){

		$zip = new \ZipArchive();
		if ($zip->open($path) !== true){
			return false;
		}

		$json = $zip->getFromName('data.json');
		$zip->close();

		$data = json_decode((string)$json, true);
		if (!is_array($data) || empty($data['_panels']) || !is_array($data['_panels'])){
			return false;
		}

		$panels = [];
		foreach ($data['_panels'] as $key => $panel){
			if (!is_array($panel)){
				continue;
			}
			$id = (int)($panel['cms_page_panel_id'] ?? $key);
			if ($id > 0){
				$panels[$id] = $panel;
			}
		}

		$main = [];
		foreach ($data['_main'] ?? [] as $id){
			$id = (int)$id;
			if ($id > 0 && isset($panels[$id])){
				$main[] = $id;
			}
		}

		return [
			'_main' => $main,
			'_panels' => $panels,
			'_settings' => is_array($data['_settings'] ?? null) ? $data['_settings'] : [],
			'_files' => is_array($data['_files'] ?? null) ? $data['_files'] : [],
		];

	}

	function _list_types($panel_name){

		if (strpos($panel_name, '|') !== false){
			return array_values(array_filter(explode('|', $panel_name)));
		}

		return $panel_name !== '' ? [$panel_name] : [];

	}

	function _local_panel($id){

		$sql = 'select cms_page_panel_id, panel_name, title, parent_id, `show` from cms_page_panel where cms_page_panel_id = ? limit 1';
		$query = $this->db->query($sql, [(int)$id]);

		return $query->num_rows() ? $query->row_array() : [];

	}

	function _missing_fks($main_id){

		$seen = [];
		$this->_collect_tree($main_id, $seen);
		$fks = [];

		foreach (array_keys($seen) as $panel_id){
			$panel = $this->manifest['_panels'][$panel_id];
			foreach ($this->_linked_ids($panel, ['fk']) as $fk_id){
				if (isset($seen[$fk_id]) || isset($fks[$fk_id]) || empty($this->manifest['_panels'][$fk_id])){
					continue;
				}
				$local = $this->_local_panel($fk_id);
				$zip = $this->manifest['_panels'][$fk_id];
				if ($local && $local['panel_name'] === ($zip['panel_name'] ?? '')){
					continue;
				}
				$fks[$fk_id] = [
					'id' => $fk_id,
					'title' => (string)($zip['title'] ?? ''),
				];
			}
		}

		return array_values($fks);

	}

	function _collect_tree($id, &$seen){

		$id = (int)$id;
		if ($id < 1 || isset($seen[$id]) || empty($this->manifest['_panels'][$id])){
			return;
		}

		$seen[$id] = true;
		foreach ($this->_linked_ids($this->manifest['_panels'][$id], ['panels', 'cms_page_panels']) as $child_id){
			$this->_collect_tree($child_id, $seen);
		}

	}

	function _named_fields($panel_name, $which){

		if (!$this->_definition_exists($panel_name)){
			return [];
		}

		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
		$fields = $config[$which] ?? [];

		return is_array($fields) ? $fields : [];

	}

	function _linked_ids($panel, $types){

		$ids = [];
		foreach ($this->_named_fields($panel['panel_name'] ?? '', 'item') as $field){
			$ids = array_merge($ids, $this->_field_ids($field, $panel, $types));
		}

		return $ids;

	}

	function _field_ids($field, $data, $types){

		if (!is_array($field)){
			return [];
		}

		$type = $field['type'] ?? '';
		$name = $field['name'] ?? '';
		if ($name === '' || $name === '_noname'){
			return [];
		}

		if ($type === 'repeater'){
			$ids = [];
			foreach ($data[$name] ?? [] as $row){
				if (!is_array($row)){
					continue;
				}
				foreach ($field['fields'] ?? [] as $sub){
					$ids = array_merge($ids, $this->_field_ids($sub, $row, $types));
				}
			}
			return $ids;
		}

		if (!in_array($type, $types, true)){
			return [];
		}

		return $this->_id_list($data[$name] ?? null);

	}

	function _id_list($value){

		if (is_array($value)){
			$ids = [];
			foreach ($value as $item){
				$ids = array_merge($ids, $this->_id_list($item));
			}
			return $ids;
		}

		$id = (int)$value;

		return $id > 0 ? [$id] : [];

	}

	function _parent_points_at($parent_id, $child_id){

		$child = $this->_local_panel($child_id);
		if ((int)($child['parent_id'] ?? 0) === (int)$parent_id){
			return true;
		}

		$parent = $this->_local_panel($parent_id);
		if (empty($parent['panel_name'])){
			return false;
		}

		$params = $this->cms_page_panel_model->get_cms_page_panel_params($parent_id, '');
		if (!is_array($params)){
			$params = [];
		}
		$params['panel_name'] = $parent['panel_name'];

		return in_array((int)$child_id, $this->_linked_ids($params, ['panels', 'cms_page_panels', 'fk']), true);

	}

	function _import_node($old_id, $mode, &$map, $is_root = false, $live_parent_id = 0){

		$old_id = (int)$old_id;
		if (isset($map[$old_id])){
			return $map[$old_id];
		}
		if ($old_id < 1 || empty($this->manifest['_panels'][$old_id]) || !empty($this->importing[$old_id])){
			return 0;
		}

		$this->importing[$old_id] = true;
		$zip_panel = $this->manifest['_panels'][$old_id];
		$local = $this->_local_panel($old_id);
		$same_type = $local && $local['panel_name'] === ($zip_panel['panel_name'] ?? '');
		$overwrite = false;
		if ($mode === 'overwrite' && $same_type){
			if ($is_root){
				$overwrite = true;
			} else if ($live_parent_id && $this->_parent_points_at($live_parent_id, $old_id)){
				$overwrite = true;
			}
		}

		$child_parent = $overwrite ? $old_id : 0;
		foreach ($this->_linked_ids($zip_panel, ['panels', 'cms_page_panels']) as $child_id){
			if (!empty($this->manifest['_panels'][$child_id])){
				$this->_import_node($child_id, $mode, $map, false, $child_parent);
			}
		}

		$existing = $overwrite ? $this->cms_page_panel_model->get_cms_page_panel_params($old_id, '') : [];
		if (!is_array($existing)){
			$existing = [];
		}

		$bag = $this->_content_bag($zip_panel);
		$bag = $this->_apply_fields($bag, $existing, $overwrite, $zip_panel['panel_name'] ?? '', $map);

		if ($overwrite){
			$this->cms_page_panel_model->update_cms_page_panel($old_id, $bag);
			$map[$old_id] = $old_id;
			unset($this->importing[$old_id]);
			return $old_id;
		}

		$bag['panel_name'] = $zip_panel['panel_name'] ?? '';
		$bag['cms_page_id'] = 0;
		$bag['parent_id'] = 0;
		$bag['show'] = 0;
		$bag['sort'] = $this->_next_sort($bag['panel_name']);
		$new_id = (int)$this->cms_page_panel_model->create_cms_page_panel($bag);

		$map[$old_id] = $new_id;
		if ($new_id){
			$this->created[$new_id] = true;
		}
		unset($this->importing[$old_id]);

		return $new_id;

	}

	function _set_child_parents($old_id, $new_id, $map){

		if (!$new_id || empty($this->manifest['_panels'][$old_id])){
			return;
		}

		foreach ($this->_linked_ids($this->manifest['_panels'][$old_id], ['panels', 'cms_page_panels']) as $child_id){
			$child_new = $map[$child_id] ?? 0;
			if ($child_new && !empty($this->created[$child_new])){
				$this->cms_page_panel_model->update_cms_page_panel($child_new, ['parent_id' => $new_id]);
			}
			$this->_set_child_parents($child_id, $child_new, $map);
		}

	}

	function _content_bag($zip_panel){

		$bag = $zip_panel;
		foreach (['cms_page_panel_id', 'block_id', 'cms_page_id', 'parent_id', 'show', 'sort', 'panel_name', 'submenu_anchor', 'submenu_title', 'create_time', 'create_cms_user_id', 'update_time', 'update_cms_user_id'] as $key){
			unset($bag[$key]);
		}
		foreach (array_keys($bag) as $key){
			if (is_string($key) && strncmp($key, '_fk_', 4) === 0){
				unset($bag[$key]);
			}
		}

		return $bag;

	}

	function _apply_fields($bag, $existing, $overwrite, $panel_name, $map){

		foreach ($this->_named_fields($panel_name, 'item') as $field){
			$this->_apply_one_field($bag, $existing, $field, $overwrite, $map);
		}

		return $bag;

	}

	function _apply_one_field(&$data, $existing, $field, $overwrite, $map){

		if (!is_array($field)){
			return;
		}

		$type = $field['type'] ?? '';
		$name = $field['name'] ?? '';
		if ($name === '' || $name === '_noname' || !array_key_exists($name, $data)){
			return;
		}

		if ($type === 'repeater' && is_array($data[$name])){
			foreach ($data[$name] as $i => $row){
				if (!is_array($row)){
					continue;
				}
				$existing_row = (is_array($existing[$name] ?? null) && is_array($existing[$name][$i] ?? null)) ? $existing[$name][$i] : [];
				foreach ($field['fields'] ?? [] as $sub){
					$this->_apply_one_field($data[$name][$i], $existing_row, $sub, $overwrite, $map);
				}
			}
			return;
		}

		if ($type === 'image' || $type === 'file'){
			$current = $existing[$name] ?? '';
			if ($overwrite && (string)$data[$name] === (string)$current){
				unset($data[$name]);
				return;
			}
			if ($data[$name] !== '' && $data[$name] !== null){
				$data[$name] = $this->_import_media($data[$name], $type);
			}
			return;
		}

		if ($type === 'fk'){
			$id = (int)$data[$name];
			if (isset($map[$id])){
				$data[$name] = $map[$id];
			}
			return;
		}

		if ($type === 'panels' || $type === 'cms_page_panels'){
			if (!is_array($data[$name])){
				return;
			}
			foreach ($data[$name] as $i => $id){
				$id = (int)$id;
				if (isset($map[$id])){
					$data[$name][$i] = $map[$id];
				}
			}
		}

	}

	function _import_media($filename, $type){

		$filename = (string)$filename;
		$meta = $this->manifest['_files'][$filename] ?? [];

		if ($type === 'file'){
			$found = $this->cms_file_model->get_cms_file_by_filename($filename);
			if (!empty($found['filename'])){
				$this->_restore_if_missing($found['filename'], $meta);
				return $found['filename'];
			}
			return $this->_create_file_from_zip($filename, $meta);
		}

		$found = $this->cms_image_model->get_cms_image_by_filename($filename);
		if (!empty($found['filename'])){
			$this->_restore_if_missing($found['filename'], $meta);
			return $found['filename'];
		}

		if (!empty($meta['hash'])){
			$by_hash = $this->cms_image_model->get_cms_image_by_hash($meta['hash']);
			if (!empty($by_hash['filename'])){
				$this->_restore_if_missing($by_hash['filename'], $meta);
				return $by_hash['filename'];
			}
		}

		return $this->_create_image_from_zip($filename, $meta);

	}

	function _restore_if_missing($filename, $meta){

		$path = $GLOBALS['config']['upload_path'].$filename;
		if ($filename === '' || is_file($path)){
			return;
		}

		$bytes = $this->_zip_bytes((string)($meta['export_filename'] ?? basename($filename)));
		if ($bytes === ''){
			return;
		}

		$dir = dirname($path);
		if (!is_dir($dir)){
			mkdir($dir, 0777, true);
		}
		file_put_contents($path, $bytes);

	}

	function _zip_bytes($export_filename){

		if (!$this->zip || $export_filename === '' || strpos($export_filename, '..') !== false){
			return '';
		}

		$bytes = $this->zip->getFromName($export_filename);
		if ($bytes === false){
			$bytes = $this->zip->getFromName(basename($export_filename));
		}

		return $bytes === false ? '' : $bytes;

	}

	function _create_image_from_zip($filename, $meta){

		$export_name = (string)($meta['export_filename'] ?? basename($filename));
		$bytes = $this->_zip_bytes($export_name);
		if ($bytes === ''){
			return $filename;
		}

		$created = $this->cms_image_model->create_cms_image(date('Y').'/'.date('m').'/', basename($filename), (string)($meta['category'] ?? ''));
		$dest = $GLOBALS['config']['upload_path'].$created['filename'];
		$dir = dirname($dest);
		if (!is_dir($dir)){
			mkdir($dir, 0777, true);
		}
		file_put_contents($dest, $bytes);

		$update = [];
		if (!empty($meta['title'])){
			$update['title'] = $meta['title'];
		}
		if (!empty($meta['description'])){
			$update['description'] = $meta['description'];
		}
		if (isset($meta['meta']) && $meta['meta'] !== '' && $meta['meta'] !== []){
			$update['meta'] = is_array($meta['meta']) ? json_encode($meta['meta']) : (string)$meta['meta'];
		}
		if ($update){
			$this->cms_image_model->update_cms_image($created['filename'], $update);
		}
		if (method_exists($this->cms_image_model, 'refresh_cms_image_hash')){
			$this->cms_image_model->refresh_cms_image_hash($created['filename']);
		}

		return $created['filename'];

	}

	function _create_file_from_zip($filename, $meta){

		$export_name = (string)($meta['export_filename'] ?? basename($filename));
		$bytes = $this->_zip_bytes($export_name);
		if ($bytes === ''){
			return $filename;
		}

		$created = $this->cms_file_model->create_cms_file('', basename($filename));
		$dest = $GLOBALS['config']['upload_path'].$created['filename'];
		$dir = dirname($dest);
		if (!is_dir($dir)){
			mkdir($dir, 0777, true);
		}
		file_put_contents($dest, $bytes);

		if (!empty($meta['title']) || !empty($meta['icon'])){
			$this->db->query('update cms_file set title = ?, icon = ? where cms_file_id = ?', [
				(string)($meta['title'] ?? ''),
				(string)($meta['icon'] ?? ''),
				(int)$created['cms_file_id'],
			]);
		}

		return $created['filename'];

	}

	function _next_sort($panel_name){

		$sql = 'select max(sort) as max_sort from cms_page_panel where panel_name = ? and cms_page_id = 0';
		$query = $this->db->query($sql, [$panel_name]);
		$row = $query->row_array();

		return ((int)($row['max_sort'] ?? 0)) + 1;

	}

	function _import_settings($panel_name){

		$settings_id = (int)($this->manifest['_settings'][$panel_name] ?? 0);
		$zip_panel = $this->manifest['_panels'][$settings_id] ?? null;
		if (!$settings_id || !is_array($zip_panel)){
			return;
		}

		$local_id = $this->_settings_id($panel_name);
		$existing = $local_id ? $this->cms_page_panel_model->get_cms_page_panel_params($local_id, '') : [];
		if (!is_array($existing)){
			$existing = [];
		}

		$incoming = $this->_content_bag($zip_panel);
		$bag = $local_id ? $this->_only_missing($existing, $incoming) : $incoming;
		if (!$bag){
			return;
		}

		$bag = $this->_apply_settings_fields($bag, $existing, false, $this->_named_fields($panel_name, 'settings'));
		foreach ($bag as $name => $value){
			if (!is_string($value) || !isset($this->manifest['_files'][$value])){
				continue;
			}
			$kind = (($this->manifest['_files'][$value]['resource_type'] ?? '') === 'file') ? 'file' : 'image';
			$bag[$name] = $this->_import_media($value, $kind);
		}

		if ($local_id){
			$this->cms_page_panel_model->update_cms_page_panel($local_id, $bag);
			return;
		}

		$bag['panel_name'] = $panel_name;
		$bag['cms_page_id'] = 0;
		$bag['parent_id'] = 0;
		$bag['sort'] = 0;
		$bag['show'] = 1;
		$bag['title'] = $bag['title'] ?? $panel_name;
		$settings_new = (int)$this->cms_page_panel_model->create_cms_page_panel($bag);
		if ($settings_new){
			$this->created[$settings_new] = true;
		}

	}

	function _only_missing($existing, $incoming){

		$out = [];
		foreach ($incoming as $key => $value){
			if ($key === '_translations'){
				$current = is_array($existing['_translations'] ?? null) ? $existing['_translations'] : [];
				$merged = $this->_merge_missing($current, is_array($value) ? $value : []);
				if ($merged !== $current){
					$out['_translations'] = $merged;
				}
				continue;
			}
			if (!array_key_exists($key, $existing)){
				$out[$key] = $value;
			}
		}

		return $out;

	}

	function _merge_missing($existing, $incoming){

		if (!is_array($existing)){
			$existing = [];
		}
		if (!is_array($incoming)){
			return $existing;
		}

		foreach ($incoming as $key => $value){
			if (!array_key_exists($key, $existing)){
				$existing[$key] = $value;
			} else if (is_array($value) && is_array($existing[$key])){
				$existing[$key] = $this->_merge_missing($existing[$key], $value);
			}
		}

		return $existing;

	}

	function _apply_settings_fields($bag, $existing, $overwrite, $fields){

		foreach ($fields as $field){
			$this->_apply_one_settings_field($bag, $existing, $field, $overwrite);
		}

		return $bag;

	}

	function _apply_one_settings_field(&$data, $existing, $field, $overwrite){

		if (!is_array($field)){
			return;
		}

		$type = $field['type'] ?? '';
		$name = $field['name'] ?? '';
		if ($name === '' || !array_key_exists($name, $data)){
			return;
		}

		if ($type === 'repeater' && is_array($data[$name])){
			foreach ($data[$name] as $i => $row){
				if (!is_array($row)){
					continue;
				}
				$existing_row = (is_array($existing[$name] ?? null) && is_array($existing[$name][$i] ?? null)) ? $existing[$name][$i] : [];
				foreach ($field['fields'] ?? [] as $sub){
					$this->_apply_one_settings_field($data[$name][$i], $existing_row, $sub, $overwrite);
				}
			}
			return;
		}

		if ($type !== 'image' && $type !== 'file'){
			return;
		}

		$current = $existing[$name] ?? '';
		if ($overwrite && (string)$data[$name] === (string)$current){
			unset($data[$name]);
			return;
		}

		if ($data[$name] !== '' && $data[$name] !== null){
			$data[$name] = $this->_import_media($data[$name], $type);
		}

	}

	function _settings_id($panel_name){

		$sql = 'select cms_page_panel_id from cms_page_panel where panel_name = ? and cms_page_id = 0 and parent_id = 0 and sort = 0 limit 1';
		$query = $this->db->query($sql, [$panel_name]);
		$row = $query->row_array();

		return (int)($row['cms_page_panel_id'] ?? 0);

	}

}
