<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Page panel versions (cms_versions.md): before a save changes a page panel, what it held is kept as an export zip
 * in tmp/cms_page_panel_versions/<module>/<cms_page_panel_id>/. Gears menu → Versions lists, compares and restores
 * them. A restore is an Overwrite import of the version zip.
 */
class cms_page_panel_version_model extends \Model {

	// Bigger files go into a version as metadata only
	const MAX_FILE_BYTES = 2097152;
	const MAX_VERSIONS = 25;
	const COMPARE_CHARS = 100;

	function __construct(){

		// Base Model has no constructor — do not call parent::__construct()
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_file_model');
		$this->load->model('cms/cms_page_panel_export_model');

	}

	function enabled(){

		return (string)($GLOBALS['config']['page_panel_versioning'] ?? '1') !== '0';

	}

	/**
	 * Versions kept per page panel: the setting, 0 keeps one, at most 25.
	 */
	function max_versions(){

		$n = (int)($GLOBALS['config']['page_panel_versions'] ?? 5);

		return max(1, min(self::MAX_VERSIONS, $n));

	}

	/**
	 * Export options of a version: the panel, its children and their files; no fk targets (other panels a
	 * restore does not overwrite) and no settings row.
	 */
	function export_options(){

		return [
			'include_database' => true,
			'include_fk' => false,
			'include_files' => true,
			'optimised_images' => false,
			'optimised_videos' => false,
			'include_panel_files' => false,
			'include_settings' => false,
		];

	}

	function _panel_row($id){

		$query = $this->db->query('select cms_page_panel_id, panel_name from cms_page_panel where cms_page_panel_id = ? limit 1', [(int)$id]);

		return $query->num_rows() ? $query->row_array() : [];

	}

	/**
	 * The folder of a page panel's versions ('' for a panel without a module/name type).
	 */
	function folder($id, $panel_name){

		$id = (int)$id;
		$module = explode('/', (string)$panel_name)[0];
		if ($id < 1 || !preg_match('/^[a-z0-9_]+$/i', $module) || strpos((string)$panel_name, '/') === false){
			return '';
		}

		return cms_path('tmp').'cms_page_panel_versions/'.$module.'/'.$id.'/';

	}

	/**
	 * Hash of a version's content: its panels without the save bookkeeping (time, user, cache stamps).
	 */
	function content_hash($data){

		$panels = is_array($data['_panels'] ?? null) ? $data['_panels'] : [];
		ksort($panels);
		foreach ($panels as $key => $panel){
			if (!is_array($panel)){
				continue;
			}
			foreach (array_keys($panel) as $name){
				if (in_array($name, ['update_time', 'update_cms_user_id', '_title'], true) || (is_string($name) && strpos($name, '_cache') === 0)){
					unset($panels[$key][$name]);
				}
			}
		}

		// Key order is not content: a save can store the same _translations languages in another order
		return sha1(json_encode($this->_sorted($panels), JSON_UNESCAPED_UNICODE));

	}

	function _sorted($value){

		if (!is_array($value)){
			return $value;
		}
		ksort($value);
		foreach ($value as $key => $item){
			$value[$key] = $this->_sorted($item);
		}

		return $value;

	}

	function hash_now($id){

		return $this->content_hash($this->cms_page_panel_export_model->collect([(int)$id], $this->export_options()));

	}

	/**
	 * Before a save: what the panel holds now, with copies of its CMS file uploads (a save deletes an upload it
	 * removes). null when versioning is off or the panel is not there.
	 */
	function stage($id){

		if (!$this->enabled()){
			return null;
		}

		$row = $this->_panel_row($id);
		if (empty($row['panel_name']) || $this->folder($id, $row['panel_name']) === ''){
			return null;
		}

		$data = $this->cms_page_panel_export_model->collect([(int)$id], $this->export_options());
		$stage = [
			'id' => (int)$id,
			'panel_name' => $row['panel_name'],
			'data' => $data,
			'hash' => $this->content_hash($data),
			'dir' => '',
			'uploads' => [],
		];

		foreach ($data['_files'] ?? [] as $filename => $meta){
			if (($meta['resource_type'] ?? '') !== 'file'){
				continue;
			}
			$source = $this->cms_file_model->file_path($filename);
			if ($source === '' || !is_file($source) || filesize($source) > self::MAX_FILE_BYTES){
				continue;
			}
			if ($stage['dir'] === ''){
				$stage['dir'] = cms_path('tmp').'cms_page_panel_versions/_staging/'.bin2hex(random_bytes(8)).'/';
				mkdir($stage['dir'], 0777, true);
			}
			$copy = $stage['dir'].count($stage['uploads']);
			if (copy($source, $copy)){
				$stage['uploads'][$filename] = $copy;
			}
		}

		return $stage;

	}

	/**
	 * After a save: keep the staged version when the save changed the panel and it is not the newest version
	 * already. Returns the kept version's file name, or ''.
	 */
	function keep_staged($stage){

		if (empty($stage['id'])){
			return '';
		}

		$kept = '';
		if ($this->hash_now($stage['id']) !== $stage['hash']){
			$kept = $this->_write_version($stage);
		}
		$this->drop_staged($stage);

		return $kept;

	}

	function drop_staged($stage){

		if (empty($stage['dir']) || strpos($stage['dir'], cms_path('tmp').'cms_page_panel_versions/_staging/') !== 0){
			return;
		}
		foreach ($stage['uploads'] ?? [] as $copy){
			if (is_file($copy)){
				unlink($copy);
			}
		}
		if (is_dir($stage['dir'])){
			@rmdir($stage['dir']);
		}

	}

	/**
	 * Keep a version of the panel as it is now (before a restore). Skipped when it equals the newest version.
	 */
	function snapshot($id){

		$stage = $this->stage($id);
		if ($stage === null){
			return '';
		}
		$kept = $this->_write_version($stage);
		$this->drop_staged($stage);

		return $kept;

	}

	function _write_version($stage){

		$folder = $this->folder($stage['id'], $stage['panel_name']);
		if ($folder === ''){
			return '';
		}

		$index = $this->_read_index($folder);
		if (!empty($index[0]['hash']) && $index[0]['hash'] === $stage['hash']){
			return '';
		}

		if (!is_dir($folder)){
			mkdir($folder, 0777, true);
		}

		$data = $stage['data'];
		$entries = [];
		foreach ($data['_files'] ?? [] as $filename => $meta){
			$source = $stage['uploads'][$filename] ?? $this->_file_source($filename, $meta);
			$size = ($source !== '' && is_file($source)) ? filesize($source) : (int)($meta['size'] ?? 0);
			$data['_files'][$filename]['size'] = (int)$size;
			if ($source === '' || !is_file($source) || $size > self::MAX_FILE_BYTES){
				// Metadata only
				$data['_files'][$filename]['copied'] = 0;
				$data['_files'][$filename]['hash'] = '';
				unset($data['_files'][$filename]['export_filename']);
				continue;
			}
			$hash = sha1_file($source);
			$export_name = substr($hash, 0, 8).'_'.preg_replace('/^preview_/', '', (string)($meta['export_filename'] ?? basename($filename)));
			$data['_files'][$filename]['hash'] = $hash;
			$data['_files'][$filename]['export_filename'] = $export_name;
			$data['_files'][$filename]['copied'] = 1;
			$entries[$export_name] = $source;
		}

		$name = date('YmdHis').'_'.substr($stage['hash'], 0, 8);
		$file = $name.'.zip';
		for ($i = 2; is_file($folder.$file); $i++){
			$file = $name.'_'.$i.'.zip';
		}

		$zip = new \ZipArchive();
		if ($zip->open($folder.$file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true){
			error_log('cms version: could not create '.$folder.$file);
			return '';
		}
		$zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		foreach ($entries as $export_name => $source){
			$zip->addFile($source, $export_name);
		}
		$zip->close();

		$user = $_SESSION['cms_user'] ?? [];
		array_unshift($index, [
			'file' => $file,
			'time' => time(),
			'cms_user_id' => (int)($user['cms_user_id'] ?? 0),
			'user' => (string)(!empty($user['name']) ? $user['name'] : ($user['username'] ?? '')),
			'hash' => $stage['hash'],
			'size' => (int)filesize($folder.$file),
			'files' => count($data['_files'] ?? []),
		]);

		// Only the newest n stay
		$max = $this->max_versions();
		foreach (array_slice($index, $max) as $old){
			$path = $folder.basename((string)($old['file'] ?? ''));
			if ($this->_version_file_ok($old['file'] ?? '') && is_file($path)){
				unlink($path);
			}
		}
		$this->_write_index($folder, array_slice($index, 0, $max));

		return $file;

	}

	/**
	 * Where a version reads a file's bytes: the image folder for images and videos, the data folder for uploads.
	 */
	function _file_source($filename, $meta){

		if (strpos((string)$filename, '..') !== false){
			return '';
		}
		if (($meta['resource_type'] ?? '') === 'file'){
			return $this->cms_file_model->file_path($filename);
		}

		return $GLOBALS['config']['upload_path'].$filename;

	}

	function _read_index($folder){

		$path = $folder.'versions.json';
		$index = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
		if (!is_array($index)){
			return [];
		}

		// Rows whose zip is gone are dropped
		return array_values(array_filter($index, function($row) use ($folder){
			return is_array($row) && $this->_version_file_ok($row['file'] ?? '') && is_file($folder.$row['file']);
		}));

	}

	function _write_index($folder, $index){

		file_put_contents($folder.'versions.json', json_encode(array_values($index), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

	}

	function _version_file_ok($file){

		return is_string($file) && preg_match('/^[0-9]{14}_[a-f0-9]{8}(_[0-9]+)?\.zip$/', $file);

	}

	/**
	 * A version zip of this panel: its path, or '' when the name is not one of its versions.
	 */
	function _version_path($id, $file){

		$row = $this->_panel_row($id);
		$folder = $this->folder($id, $row['panel_name'] ?? '');
		if ($folder === '' || !$this->_version_file_ok($file)){
			return '';
		}
		foreach ($this->_read_index($folder) as $version){
			if ($version['file'] === $file){
				return $folder.$file;
			}
		}

		return '';

	}

	/**
	 * Versions of a page panel, newest first, for the popup.
	 */
	function list_versions($id){

		$row = $this->_panel_row($id);
		$folder = $this->folder($id, $row['panel_name'] ?? '');
		if ($folder === ''){
			return [];
		}

		$list = [];
		foreach ($this->_read_index($folder) as $version){
			$list[] = [
				'file' => $version['file'],
				'time' => date('j M Y H:i:s', (int)$version['time']),
				'user' => (string)($version['user'] ?? ''),
				'size' => $this->cms_page_panel_export_model->_format_size((int)($version['size'] ?? 0)),
			];
		}

		return $list;

	}

	/**
	 * Keep a version of the current content, then import the chosen version over the panel. Children the panel
	 * has now and the version does not are deleted, as the editor deletes a removed child.
	 */
	function restore($id, $file){

		$id = (int)$id;
		$path = $this->_version_path($id, $file);
		if ($path === ''){
			return ['ok' => 0, 'message' => 'That version is not there any more.'];
		}

		$row = $this->_panel_row($id);
		$before = array_keys($this->cms_page_panel_export_model->collect([$id], $this->export_options())['_panels'] ?? []);

		$this->snapshot($id);

		$this->load->model('cms/cms_page_panel_import_model');
		$result = $this->cms_page_panel_import_model->apply_zip($path, [$row['panel_name']], $id, 'overwrite', [], false);
		if (empty($result['ok'])){
			return ['ok' => 0, 'message' => (string)($result['message'] ?? 'The version could not be restored.')];
		}

		$after = array_keys($this->cms_page_panel_export_model->collect([$id], $this->export_options())['_panels'] ?? []);
		foreach (array_diff($before, $after) as $gone){
			if ((int)$gone !== $id && $this->_panel_row($gone)){
				$this->cms_page_panel_model->delete_cms_page_panel((int)$gone);
			}
		}

		return ['ok' => 1];

	}

	/**
	 * What differs between a version and the live panel: field rows (text values, first 100 characters) and the
	 * files the version has that live does not, or of another size.
	 */
	function compare($id, $file){

		$id = (int)$id;
		$path = $this->_version_path($id, $file);
		if ($path === ''){
			return ['ok' => 0, 'message' => 'That version is not there any more.'];
		}

		$zip = new \ZipArchive();
		$version = [];
		if ($zip->open($path) === true){
			$version = json_decode((string)$zip->getFromName('data.json'), true);
			$zip->close();
		}
		if (!is_array($version) || empty($version['_panels'])){
			return ['ok' => 0, 'message' => 'That version could not be read.'];
		}

		$live = $this->cms_page_panel_export_model->collect([$id], $this->export_options());

		$v_panels = $this->_panels_by_id($version['_panels']);
		$l_panels = $this->_panels_by_id($live['_panels'] ?? []);
		$ids = array_values(array_unique(array_merge([$id], array_keys($v_panels), array_keys($l_panels))));
		$this->load->model('cms/cms_language_model');
		$default_language = (string)$this->cms_language_model->normalise_language_id($this->cms_language_model->get_default());

		$groups = [];
		foreach ($ids as $panel_id){
			$v = $v_panels[$panel_id] ?? [];
			$l = $l_panels[$panel_id] ?? [];
			$panel_name = (string)($v['panel_name'] ?? ($l['panel_name'] ?? ''));
			$shape = $v ? $v : $l;
			$is_settings = empty($shape['cms_page_id']) && empty($shape['parent_id']) && empty($shape['sort']);
			$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
			$fields = $config[$is_settings ? 'settings' : 'item'] ?? [];
			$rows = [];
			$this->_compare_fields(is_array($fields) ? $fields : [], $v, $l, '', $rows);

			// Each translation branch, as "Label (es)"; the default language's branch mirrors the fields above
			$langs = array_unique(array_merge(array_keys(is_array($v['_translations'] ?? null) ? $v['_translations'] : []), array_keys(is_array($l['_translations'] ?? null) ? $l['_translations'] : [])));
			foreach ($langs as $lang){
				if ((string)$lang === $default_language){
					continue;
				}
				$vt = $v['_translations'][$lang] ?? [];
				$lt = $l['_translations'][$lang] ?? [];
				$t_rows = [];
				$this->_compare_fields(is_array($fields) ? $fields : [], is_array($vt) ? $vt : [], is_array($lt) ? $lt : [], '', $t_rows);
				foreach ($t_rows as $t_row){
					$t_row['label'] .= ' ('.$lang.')';
					$rows[] = $t_row;
				}
			}

			if ($rows || !$v || !$l){
				$groups[] = [
					'title' => $panel_id === $id ? '' : trim((string)($shape['title'] ?? '')).' ('.$panel_name.')',
					'note' => !$v ? 'not in this version' : (!$l ? 'not on the live panel' : ''),
					'rows' => $rows,
				];
			}
		}

		$resources = [];
		$l_files = is_array($live['_files'] ?? null) ? $live['_files'] : [];
		foreach ($version['_files'] ?? [] as $filename => $meta){
			$size = (int)($meta['size'] ?? 0);
			if (isset($l_files[$filename]) && (int)($l_files[$filename]['size'] ?? 0) === $size){
				continue;
			}
			$resources[] = $filename.' ('.$this->cms_page_panel_export_model->_format_size($size).')';
		}

		return ['ok' => 1, 'groups' => $groups, 'resources' => $resources];

	}

	function _panels_by_id($panels){

		$out = [];
		foreach (is_array($panels) ? $panels : [] as $key => $panel){
			if (is_array($panel)){
				$out[(int)($panel['cms_page_panel_id'] ?? $key)] = $panel;
			}
		}

		return $out;

	}

	function _compare_fields($fields, $v, $l, $prefix, &$rows){

		foreach ($fields as $field){

			if (!is_array($field)){
				continue;
			}
			$type = $field['type'] ?? '';
			$name = $field['name'] ?? '';
			if ($name === '' || $name === '_noname'){
				continue;
			}
			$label = $prefix.trim((string)($field['label'] ?? $name));
			if ($label === $prefix){
				$label = $prefix.$name;
			}

			if ($type === 'repeater'){
				$vr = is_array($v[$name] ?? null) ? array_values($v[$name]) : [];
				$lr = is_array($l[$name] ?? null) ? array_values($l[$name]) : [];
				for ($i = 0; $i < max(count($vr), count($lr)); $i++){
					$this->_compare_fields($field['fields'] ?? [], is_array($vr[$i] ?? null) ? $vr[$i] : [], is_array($lr[$i] ?? null) ? $lr[$i] : [], $label.' '.($i + 1).' › ', $rows);
				}
				continue;
			}

			if ($type === 'grid' && !empty($field['fields'])){
				$this->_compare_fields($field['fields'], $v, $l, $prefix, $rows);
				continue;
			}

			$vv = $v[$name] ?? '';
			$lv = $l[$name] ?? '';
			if ((!is_scalar($vv) && $vv !== null) || (!is_scalar($lv) && $lv !== null)){
				// Lists of child ids and other arrays: children are compared as their own panels
				continue;
			}
			if ((string)$vv === (string)$lv){
				continue;
			}

			$rows[] = [
				'label' => $label,
				'version' => $this->_cut((string)$vv),
				'live' => $this->_cut((string)$lv),
			];

		}

	}

	function _cut($value){

		if (mb_strlen($value) <= self::COMPARE_CHARS){
			return $value;
		}

		return mb_substr($value, 0, self::COMPARE_CHARS).'…';

	}

}
