<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once BASEPATH.'helpers/path_helper.php';

class cms_update_model extends \Model {

	/**
	 * Live tree roots for core CMS update area (empty $area).
	 * Legacy CI application/ and root js/ are not used.
	 */
	function _update_core_folders(){

		return [
				'system/',
				'modules/cms/',
		];

	}

	function _update_area_folders($area){

		if ($area === '' || $area === null){
			return $this->_update_core_folders();
		}

		return ['modules/'.$area.'/'];

	}

	/**
	 * Whether a package-relative path may be written for this area.
	 * Master file lists and posted filenames are untrusted: relative, no traversal,
	 * and inside the package (modules/{area}/, or core folders + index.php / LICENSE).
	 */
	function _update_path_allowed($area, $filename){

		if (!is_string($filename) || $filename === ''){
			return false;
		}

		if (strpos($filename, "\0") !== false || strpos($filename, '\\') !== false
				|| strpos($filename, ':') !== false || $filename[0] === '/'){
			return false;
		}

		foreach (explode('/', $filename) as $segment){
			if ($segment === '' || $segment === '.' || $segment === '..'){
				return false;
			}
		}

		$area = $this->normalise_area_name($area === null ? '' : $area);
		if ($area === false){
			return false;
		}

		if ($area === '' && ($filename === 'index.php' || $filename === 'LICENSE')){
			return true;
		}

		foreach ($this->_update_area_folders($area) as $folder){
			if (strpos($filename, $folder) === 0){
				return true;
			}
		}

		return false;

	}

	/**
	 * Release package id under cache/master/ — core (system + modules/cms) uses "cms".
	 */
	function _release_id($area){

		if ($area === '' || $area === null){
			return 'cms';
		}

		return (string)$area;

	}

	function _ensure_update_dirs(){

		static $done = false;
		if ($done){
			return;
		}
		$done = true;
		if (function_exists('cms_ensure_runtime_dirs')){
			cms_ensure_runtime_dirs();
		}
		if (function_exists('cms_migrate_update_master_dirs')){
			cms_migrate_update_master_dirs();
		}

	}

	function _release_dir($area){

		$this->_ensure_update_dirs();

		return cms_path('tmp').'master/'.$this->_release_id($area).'/';

	}

	function _release_version_path($area){

		return $this->_release_dir($area).'version.json';

	}

	function _local_version_path($area){

		if ($area === '' || $area === null){
			return cms_path('tmp').'version.json';
		}

		return cms_path('tmp').'version_'.$area.'.json';

	}

	function _update_extensions(){

		return [
				'',
				'bin',
				'css',
				'dist',
				'eot',
				'gif',
				'htaccess',
				'htc',
				'html',
				'js',
				'json',
				'md',
				'otf',
				'php',
				'png',
				'scss',
				'svg',
				'ttf',
				'txt',
				'woff',
				'jpg',
				'xml',
		];

	}

	/**
	 * Scan live tree for an area. Returns files[] + current_hash.
	 */
	function scan_area_files($area, $folders = []){

		if (empty($area)){
			$folders = $this->_update_core_folders();
		} else if (empty($folders)){
			$folders = $this->_update_area_folders($area);
		}

		$extensions = $this->_update_extensions();
		$hashes = [];
		$version_hashes = [];
		$base = str_replace('\\', '/', $GLOBALS['config']['base_path']);

		foreach ($folders as $folder){

			$full_folder = str_replace('\\', '/', $base.$folder);

			if (!file_exists($full_folder)){
				continue;
			}

			$it = new \RecursiveDirectoryIterator($full_folder);
			foreach (new \RecursiveIteratorIterator($it) as $filename => $file){

				$cms_filename = $folder.str_replace($full_folder, '', str_replace('\\', '/', $filename));

				if (is_dir($filename) || !in_array(pathinfo($cms_filename, PATHINFO_EXTENSION), $extensions, true)){
					continue;
				}

				// Never package release snapshots or local update staging
				if (strpos($cms_filename, 'tmp/master/') === 0 || strpos($cms_filename, 'tmp/update/') === 0
						|| strpos($cms_filename, 'cache/master/') === 0 || strpos($cms_filename, 'cache/update/') === 0){
					continue;
				}

				$cms_md5 = md5_file($filename);
				$hashes[] = [
						'filename' => $cms_filename,
						'hash' => $cms_md5,
						'size' => filesize($filename),
				];
				$version_hashes[] = $cms_md5;

			}

		}

		if (empty($area)){

			$index = $base.'index.php';
			if (is_file($index)){
				$cms_md5 = md5_file($index);
				$hashes[] = [
						'filename' => 'index.php',
						'hash' => $cms_md5,
						'size' => filesize($index),
				];
				$version_hashes[] = $cms_md5;
			}

			$license = $base.'LICENSE';
			if (is_file($license)){
				$cms_md5 = md5_file($license);
				$hashes[] = [
						'filename' => 'LICENSE',
						'hash' => $cms_md5,
						'size' => filesize($license),
				];
				$version_hashes[] = $cms_md5;
			}

		}

		sort($version_hashes);

		return [
				'files' => $hashes,
				'current_hash' => md5(implode($version_hashes)),
		];

	}

	/**
	 * Config major.minor from modules/{area}/config.json (core → modules/cms).
	 * Missing or invalid → 0.0
	 */
	function get_config_version_parts($area){

		if ($area === '' || $area === null){
			$path = $GLOBALS['config']['base_path'].'modules/cms/config.json';
		} else {
			$path = $GLOBALS['config']['base_path'].'modules/'.$area.'/config.json';
		}

		$maj = 0;
		$min = 0;

		if (is_file($path)){
			$raw = file_get_contents($path);
			$data = function_exists('cms_json_decode')
					? cms_json_decode($raw, $path)
					: json_decode($raw, true);
			if (is_array($data) && isset($data['version'])){
				$parts = explode('.', trim((string)$data['version']));
				if (isset($parts[0]) && is_numeric($parts[0])){
					$maj = (int)$parts[0];
				}
				if (isset($parts[1]) && is_numeric($parts[1])){
					$min = (int)$parts[1];
				}
			}
		}

		return ['maj' => $maj, 'min' => $min];

	}

	function get_config_version_string($area){

		$p = $this->get_config_version_parts($area);

		return $p['maj'].'.'.$p['min'];

	}

	/**
	 * Next full version: config maj.min, patch +1 if same maj.min as last release, else patch 0.
	 */
	function compute_next_release_version($area, $last_version = ''){

		$p = $this->get_config_version_parts($area);
		$config_mm = $p['maj'].'.'.$p['min'];

		if ($last_version === '' || $last_version === null){
			return $config_mm.'.0';
		}

		$parts = explode('.', (string)$last_version);
		$last_maj = isset($parts[0]) && is_numeric($parts[0]) ? (int)$parts[0] : 0;
		$last_min = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 0;
		$last_patch = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : 0;

		if ($last_maj === $p['maj'] && $last_min === $p['min']){
			return $config_mm.'.'.($last_patch + 1);
		}

		// maj.min changed in config — patch numbering starts from 0 again
		return $config_mm.'.0';

	}

	function is_area_master($area){

		if (empty($GLOBALS['config']['update']['master'])){
			$GLOBALS['config']['update']['master'] = [];
		}

		if (!empty($GLOBALS['config']['update']['is_master']) && ($area === '' || $area === null)){
			return true;
		}

		return in_array($area === null ? '' : $area, $GLOBALS['config']['update']['master'], true);

	}

	/**
	 * Released package metadata from cache/master/{id}/version.json (null if missing).
	 */
	function get_release_meta($area){

		$path = $this->_release_version_path($area);
		if (!is_file($path)){
			return null;
		}

		$data = json_decode(file_get_contents($path), true);
		if (!is_array($data)){
			return null;
		}

		return $data;

	}

	/**
	 * Public master API: version of last Release (not live tree).
	 */
	function get_release_version($area){

		$meta = $this->get_release_meta($area);
		if ($meta === null){
			return [
					'error' => 'No release — use Release on master first',
					'version' => '',
					'version_hash' => '',
					'current_hash' => '',
					'version_time' => 0,
					'update_time' => 0,
			];
		}

		return [
				'version_hash' => $meta['version_hash'] ?? ($meta['current_hash'] ?? ''),
				'current_hash' => $meta['current_hash'] ?? ($meta['version_hash'] ?? ''),
				'version_time' => !empty($meta['version_time']) ? (int)$meta['version_time'] : 0,
				'update_time' => !empty($meta['update_time']) ? (int)$meta['update_time'] : 0,
				'version' => $meta['version'] ?? '',
				'history' => $this->_release_history_list($meta['history'] ?? []),
		];

	}

	/**
	 * One release history entry: version + tree hash + release time (no file list).
	 */
	function _release_history_entry($meta){

		if (!is_array($meta)){
			return null;
		}

		$hash = !empty($meta['current_hash']) ? $meta['current_hash'] : ($meta['version_hash'] ?? '');
		if ($hash === '' || $hash === '[unknown]'){
			return null;
		}

		return [
				'version' => (string)($meta['version'] ?? ''),
				'current_hash' => $hash,
				'version_time' => !empty($meta['version_time']) ? (int)$meta['version_time'] : 0,
		];

	}

	/**
	 * Normalise a history list: valid entries only, one per hash (first wins), capped.
	 */
	function _release_history_list($history, $exclude_hash = '', $limit = 40){

		$return = [];
		$seen = [];
		if ($exclude_hash !== ''){
			$seen[$exclude_hash] = true;
		}

		if (!is_array($history)){
			return $return;
		}

		foreach ($history as $item){
			$entry = $this->_release_history_entry($item);
			if ($entry === null || isset($seen[$entry['current_hash']])){
				continue;
			}
			$seen[$entry['current_hash']] = true;
			$return[] = $entry;
			if (count($return) >= $limit){
				break;
			}
		}

		return $return;

	}

	/**
	 * Copy live tree into cache/master/{id}/ and write version.json.
	 */
	function release_area($area){

		$area_norm = $this->normalise_area_name($area === null ? '' : $area);
		if ($area_norm === false){
			return ['error' => 'invalid_area'];
		}
		$area = $area_norm;

		if (!$this->is_area_master($area)){
			return ['error' => 'not_master'];
		}

		$scan = $this->scan_area_files($area);
		$files = $scan['files'];
		$current_hash = $scan['current_hash'];

		$prev = $this->get_release_meta($area);
		$last_version = is_array($prev) ? ($prev['version'] ?? '') : '';
		$version = $this->compute_next_release_version($area, $last_version);

		// Older releases (newest first) survive the snapshot wipe below via the new version.json
		$history = [];
		if (is_array($prev)){
			$prev_entry = $this->_release_history_entry($prev);
			if ($prev_entry !== null){
				$history[] = $prev_entry;
			}
			if (!empty($prev['history']) && is_array($prev['history'])){
				$history = array_merge($history, $prev['history']);
			}
		}
		$history = $this->_release_history_list($history, $current_hash);

		$release_dir = $this->_release_dir($area);
		if (is_dir($release_dir)){
			$this->_rrmdir($release_dir);
		}
		if (!mkdir($release_dir, 0777, true) && !is_dir($release_dir)){
			return ['error' => 'mkdir_failed'];
		}

		$base = $GLOBALS['config']['base_path'];

		foreach ($files as $file){

			$rel = $file['filename'];
			$from = $base.$rel;
			$to = $release_dir.$rel;

			if (!is_file($from)){
				continue;
			}

			$to_dir = pathinfo($to, PATHINFO_DIRNAME);
			if (!is_dir($to_dir)){
				mkdir($to_dir, 0777, true);
			}

			copy($from, $to);

		}

		$now = time();
		$meta = [
				'version' => $version,
				'version_hash' => $current_hash,
				'current_hash' => $current_hash,
				'version_time' => $now,
				'update_time' => $now,
				'files' => $files,
				'history' => $history,
		];

		file_put_contents(
				$this->_release_version_path($area),
				json_encode($meta, JSON_PRETTY_PRINT)
		);

		// Keep local working cache in sync for UI rebuild
		$local_path = $this->_local_version_path($area);
		file_put_contents($local_path, json_encode([
				'version' => $version,
				'version_hash' => $current_hash,
				'current_hash' => $current_hash,
				'version_time' => $now,
				'update_time' => $now,
				'files' => $files,
				'version_files' => $this->_files_hash_map($files),
		], JSON_PRETTY_PRINT));

		return [
				'version' => $version,
				'current_hash' => $current_hash,
				'area' => $area,
		];

	}

	// rebuilds area hash caches (local working tree — not the published release)
	function rebuild_area($area, $folders = []){

		$return = ['area' => $area];

		$scan = $this->scan_area_files($area, $folders);
		$hashes = $scan['files'];
		$current_hash = $scan['current_hash'];

		$filename = $this->_local_version_path($area);

		if (file_exists($filename)){
			$old_data = json_decode(file_get_contents($filename), true);
			if (!is_array($old_data)){
				$old_data = [];
			}
		} else {
			$old_data = [];
		}

		// Prefer last release version string for local display baseline
		$release = $this->get_release_meta($area);
		if (empty($old_data['version']) || $old_data['version'] === '0.0.0'){
			if (!empty($release['version'])){
				$old_data['version'] = $release['version'];
				$old_data['version_hash'] = $release['version_hash'] ?? ($release['current_hash'] ?? '');
				$old_data['version_time'] = $release['version_time'] ?? 0;
			} else {
				$old_data['version'] = '0.0.0';
			}
		}

		if (empty($old_data['current_hash']) || $current_hash !== $old_data['current_hash']){

			$new_data = [
					'version' => $old_data['version'],
					'version_hash' => !empty($old_data['version_hash']) ? $old_data['version_hash'] : '[unknown]',
					'version_time' => !empty($old_data['version_time']) ? $old_data['version_time'] : '0',
					'update_time' => !empty($old_data['update_time']) ? $old_data['update_time'] : '0',
					'current_hash' => $current_hash,
					'files' => $hashes,
			];

			// Per-file hashes of the stamped version survive rescans (local-edit detection)
			if (!empty($old_data['version_files']) && is_array($old_data['version_files'])){
				$new_data['version_files'] = $old_data['version_files'];
			}

			file_put_contents($filename, json_encode($new_data, JSON_PRETTY_PRINT));
			$old_data = $new_data;

		}

		$return['local_version'] = $old_data['version'];
		$return['local_updated'] = !empty($old_data['update_time']) ? $old_data['update_time'] : '0';
		$return['local_version_time'] = !empty($old_data['version_time']) ? $old_data['version_time'] : '0';
		$return['local_current_hash'] = $current_hash;
		$return['local_version_hash'] = !empty($old_data['version_hash']) ? $old_data['version_hash'] : '[unknown]';
		$return['local_has_baseline'] = !empty($old_data['version_files']) && is_array($old_data['version_files']);

		return $return;

	}

	// rebuild all areas
	function rebuild(){
		
		$return = [];		
		$return[] = $this->rebuild_area('');
		
		// other areas
		$this->load->model('cms/cms_module_model');
		
		$areas = $this->cms_module_model->get_modules();
		
		foreach($areas as $area){
			if ($area['name'] !== 'cms'){
				
				$return[] = $this->rebuild_area($area['name'], ['modules/'.$area['name'].'/']);
				
			}
		}
		
		return $return;

	}
	
	/**
	 * Local working-tree version cache (client install / live scan).
	 * Master publish state: get_release_version().
	 */
	function get_version($area){
		
		$filename = $this->_local_version_path($area);
		
		// load current version data, if exists
		if (!file_exists($filename)){

			if (!empty($area)){
				if (file_exists($GLOBALS['config']['base_path'].'modules/'.$area.'/')){
					
					$this->rebuild_area($area, ['modules/'.$area.'/']);
					
				}
			} else {
				
				$this->rebuild_area('');
				
			}

		}

		if (!file_exists($filename)){
			return [
					'version_hash' => '',
					'current_hash' => '',
					'version_time' => 0,
					'update_time' => 0,
					'version' => '0.0.0',
			];
		}
		
		$old_data = json_decode(file_get_contents($filename), true);
		if (!is_array($old_data)){
			$old_data = [];
		}
		$return = array(
			'version_hash' => $old_data['version_hash'] ?? '',
			'current_hash' => $old_data['current_hash'] ?? '',
			'version_time' => !empty($old_data['version_time']) ? $old_data['version_time'] : 0,
			'update_time' => !empty($old_data['update_time']) ? $old_data['update_time'] : 0,
			'version' => $old_data['version'] ?? '0.0.0',
		);
		
		return $return;
		
	}
	
	function get_master_version($area = ''){

		if (empty($GLOBALS['config']['cms_update_url'])){
			return false;
		}

		return $this->_master_post([
				'do' => 'version',
				'module' => $area,
				'area' => $area,
		], 10);

	}

	/**
	 * One POST to the remote master updater API (cms_update_url).
	 * Returns the decoded JSON array, or ['error' => …, 'transport_error' => true] when the
	 * master could not be reached or did not answer with JSON. A master-side answer such as
	 * "No release" is returned as decoded (its own 'error', no transport_error).
	 */
	function _master_post($data, $timeout = 15){

		$url = (string)($GLOBALS['config']['cms_update_url'] ?? '');
		if ($url === ''){
			return ['error' => 'No update URL configured', 'transport_error' => true];
		}

		// Send both names. An old master reads do. A new master reads cms_do or do.
		if (!is_array($data)){
			$data = [];
		}
		if (($data['do'] ?? '') !== '' && ($data['cms_do'] ?? '') === ''){
			$data['cms_do'] = $data['do'];
		}
		if (($data['cms_do'] ?? '') !== '' && ($data['do'] ?? '') === ''){
			$data['do'] = $data['cms_do'];
		}

		$header = [
				'Content-type: application/x-www-form-urlencoded',
		];

		// Local master: connect to localhost, keep the virtual host name
		if (stristr($url, 'localhost')){
			$host = parse_url($url, PHP_URL_HOST);
			$header[] = 'Host: '.$host;
			$url = str_replace($host, 'localhost', $url);
		}

		$context = stream_context_create(['http' => [
				'method' => 'POST',
				'header' => $header,
				'content' => http_build_query($data),
				'ignore_errors' => true,
				'timeout' => $timeout,
		]]);

		// Inline quiet handler (not _q): $http_response_header is filled in this scope
		set_error_handler(function(){
			return true;
		});
		try {
			$raw = file_get_contents($url, false, $context);
		} finally {
			restore_error_handler();
		}

		$status = 0;
		if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s?/', $http_response_header[0], $m)){
			$status = (int)$m[1];
		}

		if ($raw === false || $raw === ''){
			$host = parse_url((string)$GLOBALS['config']['cms_update_url'], PHP_URL_HOST);
			error_log('cms_update: master not reachable ('.$host.', do='.($data['do'] ?? '').')');
			return ['error' => 'Master not reachable ('.$host.')', 'transport_error' => true];
		}

		if ($status === 404 || stristr($raw, 'was not found')){
			return ['error' => 'Bad update URL: '.$GLOBALS['config']['cms_update_url'], 'transport_error' => true];
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)){
			error_log('cms_update: invalid master response (HTTP '.$status.', do='.($data['do'] ?? '').')');
			return [
					'error' => 'Invalid response from master'.($status ? ' (HTTP '.$status.')' : ''),
					'transport_error' => true,
			];
		}

		return $decoded;

	}

	/**
	 * All packages the master publishes, in one request: ['modules' => [name => row]] or
	 * ['error' => …]. Rows carry version, current_hash, version_time and history (newer masters).
	 */
	function get_master_index(){

		if (empty($GLOBALS['config']['cms_update_url'])){
			return ['error' => 'No update URL configured'];
		}

		$response = $this->_master_post(['do' => 'modules'], 10);
		if (!empty($response['error'])){
			return ['error' => $response['error']];
		}

		$modules = [];
		foreach (($response['modules'] ?? []) as $row){
			if (is_array($row) && !empty($row['name']) && is_string($row['name'])){
				$modules[$row['name']] = $row;
			}
		}

		return ['modules' => $modules];

	}

	/**
	 * Master version data for one updater row: from the prefetched index when given
	 * (one request for the whole table), else a do=version request (core, single row).
	 */
	function _master_version_for_row($area, $master_index = null){

		if ($area === '' || $master_index === null){
			return $this->get_master_version($area);
		}

		if (!empty($master_index['error'])){
			return ['error' => $master_index['error'], 'transport_error' => true];
		}

		$row = $master_index['modules'][$area] ?? null;
		if ($row === null){
			// Not published (or not released) on master
			return ['error' => 'Not published on master'];
		}

		$hash = !empty($row['current_hash']) ? $row['current_hash'] : ($row['version_hash'] ?? '');

		return [
				'version' => $row['version'] ?? '',
				'version_hash' => $hash,
				'current_hash' => $hash,
				'version_time' => !empty($row['version_time']) ? (int)$row['version_time'] : 0,
				'history' => (isset($row['history']) && is_array($row['history'])) ? $row['history'] : [],
		];

	}

	/**
	 * Whether this install may update/install the given area (host config update.allow).
	 */
	function client_may_use_area($area){

		$allow = $GLOBALS['config']['update']['allow'] ?? [];
		if (empty($allow) || !is_array($allow)){
			return false;
		}

		if (in_array('*', $allow, true)){
			return true;
		}

		// Core package uses empty area string
		return in_array($area, $allow, true) || in_array((string)$area, $allow, true);

	}

	/**
	 * Whether the package is enabled on this site (cms_settings modules). Core is always active.
	 */
	function _area_active($area){

		if ($area === '' || $area === null || $area === 'cms'){
			return true;
		}

		$modules = $GLOBALS['config']['modules'] ?? [];

		return is_array($modules) && in_array($area, $modules, true);

	}

	function normalise_area_name($area){

		$area = (string)$area;
		if ($area === ''){
			return '';
		}

		if (!preg_match('/^[a-zA-Z0-9_]+$/', $area)){
			return false;
		}

		return $area;

	}

	/**
	 * Master host: modules listed in update.master (excluding core '').
	 */
	function get_publishable_modules(){

		$master = $GLOBALS['config']['update']['master'] ?? [];
		if (!is_array($master)){
			$master = [];
		}

		if (!empty($GLOBALS['config']['update']['is_master']) && !in_array('', $master, true)){
			$master[] = '';
		}

		$out = [];

		foreach ($master as $name){

			if ($name === '' || $name === 'cms'){
				continue;
			}

			if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$name)){
				continue;
			}

			$dir = $GLOBALS['config']['base_path'].'modules/'.$name.'/';
			if (!is_dir($dir)){
				continue;
			}

			// Only packages that have been Released (tmp/master/{name}/)
			$v = $this->get_release_version($name);
			if (!empty($v['error']) || empty($v['version'])){
				continue;
			}

			// Same fields as do=version so a client can build its whole table from one request
			$out[] = [
					'name' => $name,
					'version' => $v['version'] ?? '',
					'version_hash' => $v['current_hash'] ?? $v['version_hash'] ?? '',
					'current_hash' => $v['current_hash'] ?? $v['version_hash'] ?? '',
					'version_time' => !empty($v['version_time']) ? (int)$v['version_time'] : 0,
					'history' => $v['history'] ?? [],
			];

		}

		return $out;

	}

	/**
	 * Client: packages on master not present locally, filtered by update.allow.
	 */
	function get_installable_modules($master_index = null){

		if (empty($GLOBALS['config']['cms_update_url']) || empty($GLOBALS['config']['update']['allow'])){
			return [];
		}

		if ($master_index === null){
			$master_index = $this->get_master_index();
		}
		if (!empty($master_index['error']) || empty($master_index['modules'])){
			return [];
		}
		$master_list = array_values($master_index['modules']);

		$local = [];
		foreach (glob($GLOBALS['config']['base_path'].'modules/*', GLOB_ONLYDIR) as $dir){
			$local[basename($dir)] = true;
		}

		$available = [];
		foreach ($master_list as $row){

			$name = $row['name'] ?? '';
			if ($name === '' || $name === 'cms'){
				continue;
			}
			if (!empty($local[$name])){
				continue;
			}
			if (!$this->client_may_use_area($name)){
				continue;
			}

			$available[] = $row;

		}

		return $available;

	}

	function get_master_modules(){

		$index = $this->get_master_index();
		if (!empty($index['error'])){
			return [];
		}

		return array_values($index['modules']);

	}

	/**
	 * Flatten cms_settings modules to unique slugs (order preserved).
	 * cms is sticky first when present or when $keep_cms is true.
	 */
	function _normalise_settings_modules_list($modules, $keep_cms = true){

		if (!is_array($modules)){
			$modules = [];
		}

		$out = [];
		$seen = [];
		foreach ($modules as $m){
			if (is_array($m)){
				// Defensive: multi field sometimes nested
				$m = $m['value'] ?? $m['name'] ?? reset($m);
			}
			$m = trim((string)$m);
			if ($m === ''){
				continue;
			}
			if (isset($seen[$m])){
				continue;
			}
			$seen[$m] = 1;
			$out[] = $m;
		}

		// Sticky cms always first in stored list (UI sticky + boot unshift stay consistent)
		$out = array_values(array_filter($out, function($m){
			return $m !== 'cms';
		}));
		if ($keep_cms){
			array_unshift($out, 'cms');
		}

		return $out;

	}

	/**
	 * Replace modules list on cms/cms_settings: delete old modules.* rows then write
	 * (non-purge updates leave orphan numeric indices → duplicate chips in UI).
	 */
	function _write_settings_modules($settings_id, $modules){

		$settings_id = (int)$settings_id;
		$modules = $this->_normalise_settings_modules_list($modules);

		// Remove scalar + indexed param rows for this field
		$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? "
				."and (name = 'modules' or name like 'modules.%') ";
		$this->db->query($sql, [$settings_id]);

		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->update_cms_page_panel($settings_id, [
				'modules' => $modules,
		]);

		return $modules;

	}

	/**
	 * Enable module in cms/cms_settings modules list as penultimate (before last site module).
	 * cms is never stored — boot always prepends it.
	 */
	function enable_module_penultimate($name){

		$name = $this->normalise_area_name($name);
		if ($name === false || $name === '' || $name === 'cms'){
			return ['error' => 'invalid_module'];
		}

		$this->load->model('cms/cms_page_panel_model');

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'cms/cms_settings',
				'cms_page_id' => 0,
				'parent_id' => 0,
				'sort' => 0,
		]);

		if (empty($panels[0]['cms_page_panel_id'])){
			return ['error' => 'settings_missing'];
		}

		$settings_id = (int)$panels[0]['cms_page_panel_id'];
		$settings = $this->cms_page_panel_model->get_cms_page_panel($settings_id);
		$modules = $this->_normalise_settings_modules_list($settings['modules'] ?? []);

		// Remove module if already present (re-insert as penultimate)
		$modules = array_values(array_filter($modules, function($m) use ($name){
			return $m !== $name;
		}));

		if (count($modules) === 0){
			$modules = [$name];
		} else if (count($modules) === 1){
			$modules[] = $name;
		} else {
			// Insert before last (= site module, e.g. music) so extends load after libs
			$last = array_pop($modules);
			$modules[] = $name;
			$modules[] = $last;
		}

		$modules = $this->_write_settings_modules($settings_id, $modules);

		return ['modules' => $modules];

	}

	/**
	 * Drop module from cms/cms_settings modules list (keep other modules).
	 */
	function disable_module_from_settings($name){

		$name = $this->normalise_area_name($name);
		if ($name === false || $name === '' || $name === 'cms'){
			return ['error' => 'invalid_module'];
		}

		$this->load->model('cms/cms_page_panel_model');

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'cms/cms_settings',
				'cms_page_id' => 0,
				'parent_id' => 0,
				'sort' => 0,
		]);

		if (empty($panels[0]['cms_page_panel_id'])){
			return ['error' => 'settings_missing'];
		}

		$settings_id = (int)$panels[0]['cms_page_panel_id'];
		$settings = $this->cms_page_panel_model->get_cms_page_panel($settings_id);
		$modules = $this->_normalise_settings_modules_list($settings['modules'] ?? []);

		$modules = array_values(array_filter($modules, function($m) use ($name){
			return $m !== $name;
		}));

		$modules = $this->_write_settings_modules($settings_id, $modules);

		return ['modules' => $modules];

	}

	/**
	 * Unregister module, delete modules/{name}/ files, keep DB tables.
	 * Master packages must be taken out of update.master first (is_area_master).
	 * Returns available=true only if master still has a real release to re-install.
	 */
	function remove_module_area($area){

		$name = $this->normalise_area_name($area);
		if ($name === false || $name === '' || $name === 'cms'){
			return ['error' => 'invalid_module'];
		}

		if ($this->is_area_master($name)){
			return [
					'error' => 'Master packages cannot be removed here — remove from update.master / is_master in config first',
			];
		}

		$disabled = $this->disable_module_from_settings($name);
		if (!empty($disabled['error'])){
			return $disabled;
		}

		$dir = $GLOBALS['config']['base_path'].'modules/'.$name.'/';
		if (is_dir($dir)){
			$this->_rrmdir($dir);
		}

		$version_file = $this->_local_version_path($name);
		if (is_file($version_file)){
			$this->_q('unlink', $version_file);
		}

		// Offer re-install only when master still has a released package
		$available = false;
		$available_row = null;
		if ($this->client_may_use_area($name) && !empty($GLOBALS['config']['cms_update_url'])){
			$version_data = $this->get_master_version($name);
			if (is_array($version_data) && empty($version_data['error'])){
				$master_hash = !empty($version_data['current_hash'])
						? $version_data['current_hash']
						: (!empty($version_data['version_hash']) ? $version_data['version_hash'] : '');
				$master_version = !empty($version_data['version']) ? $version_data['version'] : '';
				if ($master_hash !== '' || $master_version !== ''){
					$available = true;
					$available_row = [
							'name' => $name,
							'version' => $master_version,
							'version_time' => !empty($version_data['version_time']) ? (int)$version_data['version_time'] : 0,
					];
				}
			}
		}

		return [
				'area' => $name,
				'available' => $available ? 1 : 0,
				'available_row' => $available_row,
		];

	}
	
	/**
	 * File list for updater protocol.
	 * $from_release true = published snapshot (master API). false = local working cache (client compare).
	 */
	function get_files($area, $from_release = true){

		if ($from_release){
			$release = $this->get_release_meta($area);
			if ($release !== null){
				$files = !empty($release['files']) && is_array($release['files']) ? $release['files'] : [];
				return [
						'files' => $files,
				];
			}

			return [
					'files' => [],
					'error' => 'No release — use Release on master first',
			];
		}

		// Local working list (after rebuild)
		$filename = $this->_local_version_path($area);
		if (file_exists($filename)){
			$old_data = json_decode(file_get_contents($filename), true);
			$files = !empty($old_data['files']) && is_array($old_data['files']) ? $old_data['files'] : [];
			return [
					'files' => $files,
			];
		}

		if (!empty($area) && !is_dir($GLOBALS['config']['base_path'].'modules/'.$area.'/')){
			return [
					'files' => [],
			];
		}

		return [
				'files' => [],
				'error' => 'No local version cache',
		];

	}
	
	function get_master_files($area){

		if (empty($GLOBALS['config']['cms_update_url'])){
			return false;
		}

		return $this->_master_post([
				'do' => 'files',
				'module' => $area,
				'area' => $area,
		], 30);

	}

	/**
	 * Serve one file for updater protocol — from Release snapshot only (never live unreleased tree).
	 */
	function get_file($needed_filename, $area){

		$batch = $this->get_files_content([(string)$needed_filename], $area);
		if (!empty($batch['error']) && empty($batch['files'])){
			return [
					'file' => '',
					'error' => $batch['error'],
			];
		}

		$needed_filename = (string)$needed_filename;
		if (isset($batch['files'][$needed_filename])){
			return [
					'file' => $batch['files'][$needed_filename],
			];
		}

		$err = $batch['errors'][$needed_filename] ?? 'No such file';

		return [
				'file' => '',
				'error' => $err,
		];

	}

	/**
	 * Serve multiple files from Release snapshot (batch do=file).
	 * @param array $filenames Relative paths as in release manifest
	 * @return array{files?:array<string,string>,errors?:array<string,string>,error?:string}
	 */
	function get_files_content($filenames, $area){

		if (!is_array($filenames)){
			$filenames = [];
		}

		// Cap per request (payload / timeout)
		$max = 40;
		if (count($filenames) > $max){
			return [
					'files' => [],
					'errors' => [],
					'error' => 'Too many files (max '.$max.')',
			];
		}

		$release = $this->get_release_meta($area);
		if ($release === null){
			return [
					'files' => [],
					'errors' => [],
					'error' => 'No release — use Release on master first',
			];
		}

		$allowed = [];
		$manifest = !empty($release['files']) && is_array($release['files']) ? $release['files'] : [];
		foreach ($manifest as $file){
			if (!empty($file['filename'])){
				$allowed[$file['filename']] = true;
			}
		}

		$out_files = [];
		$errors = [];
		$release_dir = $this->_release_dir($area);

		foreach ($filenames as $needed_filename){

			$needed_filename = (string)$needed_filename;
			if ($needed_filename === ''){
				continue;
			}

			// Reject path traversal
			if (strpos($needed_filename, '..') !== false || strpos($needed_filename, "\0") !== false){
				$errors[$needed_filename] = 'Invalid path';
				continue;
			}

			if (empty($allowed[$needed_filename])){
				$errors[$needed_filename] = 'No such file';
				continue;
			}

			$path = $release_dir.$needed_filename;
			if (!is_file($path)){
				$errors[$needed_filename] = 'File missing from release cache';
				continue;
			}

			$out_files[$needed_filename] = base64_encode(file_get_contents($path));

		}

		return [
				'files' => $out_files,
				'errors' => $errors,
		];

	}
	
	/**
	 * Fetch multiple files from master in one request (do=file + filenames[]).
	 * @return array{files?:array<string,string>,errors?:array<string,string>,error?:string}|false
	 */
	function get_master_files_content($filenames, $area){

		if (empty($GLOBALS['config']['cms_update_url'])){
			return false;
		}

		if (!is_array($filenames) || !count($filenames)){
			return [
					'files' => [],
					'errors' => [],
			];
		}

		$decoded = $this->_master_post([
				'do' => 'file',
				'area' => $area,
				'module' => $area,
				'filenames' => array_values($filenames),
		], 60);

		// Masters serve batches (do=file + filenames[]); anything else is an error for the caller
		return $decoded;

	}
	
	/**
	 * Start an update: compare master Release list with the local tree, save it as this
	 * area's update manifest (the only files later steps may stage, copy or delete) and
	 * clear staging left by an earlier run. Returns the list, or ['error' => …].
	 */
	function get_needed_files($area){

		$area_norm = $this->normalise_area_name($area);
		if ($area_norm === false){
			return ['error' => 'Invalid module'];
		}
		$area = $area_norm;

		if (!$this->client_may_use_area($area)){
			return ['error' => 'Updates are not allowed for this module'];
		}

		// Fresh scan: compare against the tree as it is now, not the last page load
		$installed = ($area === '' || is_dir($GLOBALS['config']['base_path'].'modules/'.$area.'/'));
		if ($installed){
			$this->rebuild_area($area, $this->_update_area_folders($area));
		}

		// get master list (remote Release snapshot)
		$master_files = $this->get_master_files($area);
		if (empty($master_files['files']) || !is_array($master_files['files'])){
			$reason = (is_array($master_files) && !empty($master_files['error']))
					? $master_files['error']
					: 'Master did not return a file list';
			return ['error' => $reason];
		}

		// get local working list (not this host's release cache)
		$local_files = $this->get_files($area, false);
		if (empty($local_files['files']) || !is_array($local_files['files'])){
			$local_files['files'] = [];
		}
		$local = $this->_files_hash_map($local_files['files']);

		// Hashes of the installed version: a U/D file that differs from it was edited (or added) here
		$baseline = $installed ? $this->_load_version_files($area) : null;
		$is_local_edit = function($filename) use ($baseline, $local){
			if ($baseline === null || !isset($local[$filename])){
				return false;
			}
			return !isset($baseline[$filename]) || $baseline[$filename] !== $local[$filename];
		};

		$return = [];
		$seen = [];

		foreach ($master_files['files'] as $master_file){

			// Untrusted master list: never transfer outside this package
			if (!is_array($master_file) || !$this->_update_path_allowed($area, $master_file['filename'] ?? '')){
				error_log('cms_update: skipped master file outside package "'.$area.'": '
						.(is_array($master_file) ? json_encode($master_file['filename'] ?? '') : 'invalid entry'));
				continue;
			}

			$fn = $master_file['filename'];
			$seen[$fn] = true;

			if (!isset($local[$fn])){
				$letter = 'A';
			} else if ($local[$fn] !== ($master_file['hash'] ?? '')){
				$letter = 'U';
			} else {
				continue;
			}

			$hash = (string)($master_file['hash'] ?? '');
			if ($hash === ''){
				return ['error' => 'Master file has no hash: '.$fn];
			}

			$return[] = [
					'filename' => $fn,
					'letter' => $letter,
					'hash' => $hash,
					'fn_hash' => md5($fn),
					'local_edit' => $letter === 'U' && $is_local_edit($fn),
			];

		}

		// delete local files not in master list
		foreach ($local as $fn => $hash){

			if (isset($seen[$fn]) || !$this->_update_path_allowed($area, $fn)){
				continue;
			}

			$return[] = [
					'filename' => $fn,
					'letter' => 'D',
					'fn_hash' => md5($fn),
					'local_edit' => $is_local_edit($fn),
			];

		}

		$this->_start_update_manifest($area, $return);

		return $return;

	}

	/**
	 * Update list for the popup: files (with local_edit flags) plus the overwrite warning.
	 * local_edits — U/D files that differ from the installed version's per-file hashes.
	 * local_changes_unknown — no per-file record, but the tree differs from the stamped version
	 * (or was never stamped): the update may overwrite edits that cannot be named.
	 */
	function get_update_plan($area){

		$files = $this->get_needed_files($area);
		if (!empty($files['error'])){
			return $files;
		}

		$edits = 0;
		foreach ($files as $file){
			if (!empty($file['local_edit'])){
				$edits++;
			}
		}

		$unknown = false;
		$path = $this->_local_version_path($area);
		if (count($files) && is_file($path) && $this->_load_version_files($area) === null){
			$data = json_decode(file_get_contents($path), true);
			$version_hash = is_array($data) ? (string)($data['version_hash'] ?? '') : '';
			$current_hash = is_array($data) ? (string)($data['current_hash'] ?? '') : '';
			$installed = ($area === '' || is_dir($GLOBALS['config']['base_path'].'modules/'.$area.'/'));
			$unknown = $installed && ($version_hash === '' || $version_hash === '[unknown]' || $version_hash !== $current_hash);
		}

		return [
				'files' => $files,
				'local_edits' => $edits,
				'local_changes_unknown' => $unknown,
		];

	}

	function _update_manifest_path($area){

		return cms_path('tmp').'update_'.$this->_release_id($area).'.json';

	}

	/**
	 * Save the needed-file list for this area and drop staging left by an earlier run.
	 */
	function _start_update_manifest($area, $files){

		$this->discard_update($area);

		if (!count($files)){
			return;
		}

		$manifest = [];
		foreach ($files as $file){
			$manifest[$file['filename']] = [
					'letter' => $file['letter'],
					'hash' => (string)($file['hash'] ?? ''),
			];
		}

		file_put_contents($this->_update_manifest_path($area), json_encode([
				'area' => $area,
				'time' => time(),
				'files' => $manifest,
		], JSON_PRETTY_PRINT));

	}

	/**
	 * filename => {letter, hash} for the update in progress, or null.
	 * hash is the release md5 for U/A; D has none.
	 */
	function _load_update_manifest($area){

		$path = $this->_update_manifest_path($area);
		if (!is_file($path)){
			return null;
		}

		$data = json_decode(file_get_contents($path), true);
		if (!is_array($data) || ($data['area'] ?? null) !== $area || !is_array($data['files'] ?? null)){
			return null;
		}

		return $data['files'];

	}

	function _update_manifest_letter($entry){

		if (is_string($entry)){
			return $entry;
		}

		if (is_array($entry)){
			return (string)($entry['letter'] ?? '');
		}

		return '';

	}

	function _update_manifest_hash($entry){

		if (!is_array($entry)){
			return '';
		}

		return (string)($entry['hash'] ?? '');

	}

	/**
	 * Throw away everything staged for this area (and its manifest). The live tree is untouched.
	 */
	function discard_update($area){

		$this->_ensure_update_dirs();

		$staging = cms_path('tmp').'update/';

		foreach ($this->_update_area_folders($area) as $folder){
			$this->_rrmdir($staging.rtrim($folder, '/'));
		}

		if ($area === '' || $area === null){
			foreach (['index.php', 'LICENSE'] as $file){
				if (is_file($staging.$file)){
					unlink($staging.$file);
				}
			}
		}

		// Drop now-empty staging parents (keeps other areas' staging)
		$this->_q('rmdir', $staging.'modules');
		$this->_q('rmdir', $staging);

		$manifest = $this->_update_manifest_path($area);
		if (is_file($manifest)){
			unlink($manifest);
		}

	}

	/**
	 * Stage files of the update in progress from master in one round-trip (batch do=file).
	 * Only manifest files are accepted. D needs no download. U/A must arrive with a body
	 * (an empty file is a valid body); anything else is an error for the client to retry.
	 * @param array $filenames
	 * @return array{done:array<int,array{filename:string,fn_hash:string}>,errors:array<string,string>}
	 */
	function update_files($filenames, $area){

		if (!is_array($filenames)){
			$filenames = [];
		}

		$done = [];
		$errors = [];
		$fetch = [];

		$manifest = $this->_load_update_manifest($area);
		if ($manifest === null){
			foreach ($filenames as $filename){
				if (is_string($filename) && $filename !== ''){
					$errors[$filename] = 'No update in progress';
				}
			}
			return ['done' => $done, 'errors' => $errors];
		}

		// Posted filenames are untrusted: only this update's paths inside the package
		foreach ($filenames as $filename){
			if (!is_string($filename) || $filename === ''){
				continue;
			}
			if (!$this->_update_path_allowed($area, $filename)){
				$errors[$filename] = 'Path outside package';
				error_log('cms_update: rejected file outside package "'.$area.'": '.json_encode($filename));
				continue;
			}
			if (!isset($manifest[$filename])){
				$errors[$filename] = 'Not in this update';
				continue;
			}
			if ($this->_update_manifest_letter($manifest[$filename]) === 'D'){
				$done[] = ['filename' => $filename, 'fn_hash' => md5($filename)];
				continue;
			}
			$fetch[] = $filename;
		}

		if (!count($fetch)){
			return ['done' => $done, 'errors' => $errors];
		}

		$batch = $this->get_master_files_content($fetch, $area);
		$got = (is_array($batch) && isset($batch['files']) && is_array($batch['files'])) ? $batch['files'] : [];
		$master_errors = (is_array($batch) && isset($batch['errors']) && is_array($batch['errors'])) ? $batch['errors'] : [];
		$batch_error = !is_array($batch)
				? 'No response from master'
				: (!empty($batch['error']) ? $batch['error'] : 'Missing from master response');

		foreach ($fetch as $filename){

			if (!array_key_exists($filename, $got) || !is_string($got[$filename])){
				$errors[$filename] = $master_errors[$filename] ?? $batch_error;
				continue;
			}

			$content = base64_decode($got[$filename], true);
			if ($content === false){
				$errors[$filename] = 'Invalid file data from master';
				continue;
			}

			if (!$this->_stage_update_cache_file($filename, $content)){
				$errors[$filename] = 'Could not write staging file';
				continue;
			}

			$expected = $this->_update_manifest_hash($manifest[$filename]);
			$staged = cms_path('tmp').'update/'.$filename;
			$actual = is_file($staged) ? md5_file($staged) : false;
			if ($expected === '' || $actual === false || $actual !== $expected){
				if (is_file($staged)){
					$this->_q('unlink', $staged);
				}
				$errors[$filename] = ($expected === '') ? 'Release hash missing' : 'File does not match the release';
				continue;
			}

			$done[] = ['filename' => $filename, 'fn_hash' => md5($filename)];

		}

		return ['done' => $done, 'errors' => $errors];

	}

	function _stage_update_cache_file($filename, $content){

		$this->_ensure_update_dirs();

		$path = cms_path('tmp').'update/'.$filename;
		$dir = pathinfo($path, PATHINFO_DIRNAME);
		if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)){
			return false;
		}

		return file_put_contents($path, $content) !== false;

	}

	/**
	 * Apply the staged update to the live tree:
	 * 1. every U/A file of the manifest must be staged — if not, the download is incomplete:
	 *    staging is discarded and nothing changes;
	 * 2. pre-flight: every target must be writable — if not, nothing changes;
	 * 3. live files that are overwritten or deleted are backed up first — if that fails, nothing changes;
	 * 4. on the first failed write/delete everything attempted is rolled back from the backup
	 *    (added files and folders this update created are removed).
	 * After 2–4 the staged download is kept, so the apply can be retried without downloading.
	 * It is removed after success (or by the next update list). If the rollback itself fails,
	 * the backup folder is kept and named, and further applies are refused until it is cleared.
	 * Returns ['done' => n] or ['error' => …, 'missing'|'failed' => […], 'retry' => bool, 'backup'?].
	 */
	function update_copy($area){

		$this->_ensure_update_dirs();

		$manifest = $this->_load_update_manifest($area);
		if ($manifest === null){
			return ['error' => 'No update in progress', 'retry' => false];
		}

		$staging = cms_path('tmp').'update/';
		$base = $GLOBALS['config']['base_path'];
		$backup = $this->_update_backup_dir($area);

		// A failed rollback left originals here — never overwrite them
		if (is_dir($backup)){
			return [
					'error' => 'A previous failed update left original files in '.$backup
							.' — restore or remove that folder first',
					'retry' => false,
					'backup' => $backup,
			];
		}

		$missing = [];
		$bad_hash = [];
		foreach ($manifest as $filename => $entry){
			$letter = $this->_update_manifest_letter($entry);
			if (!$this->_update_path_allowed($area, $filename)){
				$missing[] = $filename;
				continue;
			}
			if ($letter === 'D'){
				continue;
			}
			if (!is_file($staging.$filename)){
				$missing[] = $filename;
				continue;
			}
			$expected = $this->_update_manifest_hash($entry);
			$actual = md5_file($staging.$filename);
			if ($expected === '' || $actual === false || $actual !== $expected){
				$bad_hash[$filename] = 'does not match the release';
			}
		}

		if (count($missing)){
			$this->discard_update($area);
			error_log('cms_update: download incomplete for "'.$area.'", update discarded: '.json_encode($missing));
			return [
					'error' => 'Update download incomplete ('.count($missing).' file(s)) — no files were changed',
					'missing' => $missing,
					'retry' => false,
			];
		}

		if (count($bad_hash)){
			error_log('cms_update: staged hash mismatch for "'.$area.'": '.json_encode($bad_hash));
			return [
					'error' => 'Update not applied: '.$this->_update_files_summary($bad_hash).' — no files were changed',
					'failed' => $bad_hash,
					'retry' => false,
			];
		}

		// 1. Pre-flight: every target must be writable before anything in the live tree changes
		$blocked = [];
		foreach ($manifest as $filename => $entry){
			$letter = $this->_update_manifest_letter($entry);
			$reason = $this->_update_target_problem($base.$filename, $letter);
			if ($reason !== ''){
				$blocked[$filename] = $reason;
			}
		}

		if (count($blocked)){
			error_log('cms_update: not applied for "'.$area.'", targets not writable: '.json_encode($blocked));
			return [
					'error' => 'Update not applied: '.$this->_update_files_summary($blocked).' — no files were changed',
					'failed' => $blocked,
					'retry' => true,
			];
		}

		// 2. Back up every live file the update overwrites or deletes
		foreach ($manifest as $filename => $entry){
			$to = $base.$filename;
			if (!is_file($to)){
				continue;
			}
			$backup_file = $backup.$filename;
			$backup_dir = pathinfo($backup_file, PATHINFO_DIRNAME);
			if ((!is_dir($backup_dir) && !$this->_q('mkdir', $backup_dir, 0777, true) && !is_dir($backup_dir))
					|| !$this->_q('copy', $to, $backup_file)){
				$this->_rrmdir(rtrim($backup, '/'));
				error_log('cms_update: backup failed for "'.$area.'": '.$filename);
				return [
						'error' => 'Update not applied: could not back up '.$filename.' — no files were changed',
						'failed' => [$filename => 'backup failed'],
						'retry' => true,
				];
			}
		}

		// 3. Apply; on the first failure undo everything attempted so far
		$attempted = [];
		$created_dirs = [];
		$failed = [];

		$hash_mismatch = false;

		foreach ($manifest as $filename => $entry){

			$letter = $this->_update_manifest_letter($entry);
			$to = $base.$filename;
			$attempted[] = $filename;

			if ($letter === 'D'){
				if (is_file($to) && !$this->_update_apply_delete($to)){
					$failed[$filename] = 'delete failed';
					break;
				}
				continue;
			}

			if (!$this->_update_make_dir(pathinfo($to, PATHINFO_DIRNAME), $created_dirs)
					|| !$this->_update_apply_write($staging.$filename, $to)){
				$failed[$filename] = 'write failed';
				break;
			}

		}

		if (!count($failed)){
			$expected_tree = $this->_update_release_hash($area);
			$scan = $this->scan_area_files($area, $this->_update_area_folders($area));
			$actual_tree = (string)($scan['current_hash'] ?? '');
			if ($expected_tree === '' || $actual_tree !== $expected_tree){
				$hash_mismatch = true;
				$failed['release'] = 'tree does not match the release';
			}
		}

		if (!count($failed)){
			$this->_rrmdir(rtrim($backup, '/'));
			$this->discard_update($area);
			return ['done' => count($manifest)];
		}

		// Roll back: restore originals from the backup, remove files the update added
		$unrestored = [];
		foreach (array_reverse($attempted) as $filename){
			$to = $base.$filename;
			$backup_file = $backup.$filename;
			if (is_file($backup_file)){
				// Untouched (e.g. the delete that failed) — nothing to restore. Unreadable = changed.
				$live_md5 = is_file($to) ? md5_file($to) : false;
				$backup_md5 = md5_file($backup_file);
				if ($live_md5 !== false && $backup_md5 !== false && $live_md5 === $backup_md5){
					continue;
				}
				$to_dir = pathinfo($to, PATHINFO_DIRNAME);
				if ((!is_dir($to_dir) && !$this->_q('mkdir', $to_dir, 0777, true) && !is_dir($to_dir))
						|| !$this->_q('copy', $backup_file, $to)){
					$unrestored[$filename] = 'restore failed';
				}
			} else if (is_file($to) && !$this->_q('unlink', $to)){
				$unrestored[$filename] = 'could not remove added file';
			}
		}

		// Only folders this apply created, deepest first, and only if empty again
		foreach (array_reverse($created_dirs) as $dir){
			$this->_q('rmdir', $dir);
		}

		if (count($unrestored)){
			// Keep the backup so the operator can restore by hand
			error_log('cms_update: apply failed for "'.$area.'" and rollback incomplete: '
					.json_encode(['failed' => $failed, 'unrestored' => $unrestored, 'backup' => $backup]));
			return [
					'error' => 'Update failed: '.$this->_update_files_summary($failed)
							.'. Rollback incomplete: '.$this->_update_files_summary($unrestored)
							.' — originals are kept in '.$backup,
					'failed' => $failed + $unrestored,
					'retry' => false,
					'backup' => $backup,
			];
		}

		$this->_rrmdir(rtrim($backup, '/'));
		error_log('cms_update: apply failed for "'.$area.'", rolled back: '.json_encode($failed));

		if ($hash_mismatch){
			return [
					'error' => 'Update failed: tree does not match the release — rolled back, no files were changed',
					'failed' => $failed,
					'retry' => false,
			];
		}

		return [
				'error' => 'Update failed: '.$this->_update_files_summary($failed).' — rolled back, no files were changed',
				'failed' => $failed,
				'retry' => true,
		];

	}

	function _update_release_hash($area){

		$master = $this->get_master_version($area);
		if (!is_array($master) || !empty($master['error'])){
			return '';
		}

		if (!empty($master['version_hash']) && $master['version_hash'] !== 'error'){
			return (string)$master['version_hash'];
		}

		if (!empty($master['current_hash'])){
			return (string)$master['current_hash'];
		}

		return '';

	}

	// Live-tree write / delete steps of update_copy() (separate so failures can be tested)
	function _update_apply_write($from, $to){

		return $this->_q('copy', $from, $to);

	}

	function _update_apply_delete($to){

		return $this->_q('unlink', $to);

	}

	/**
	 * mkdir -p that records each folder it creates (outermost first) for rollback.
	 */
	function _update_make_dir($dir, &$created){

		if (is_dir($dir)){
			return true;
		}

		$missing = [];
		while (!is_dir($dir)){
			$missing[] = $dir;
			$parent = pathinfo($dir, PATHINFO_DIRNAME);
			if ($parent === $dir){
				return false;
			}
			$dir = $parent;
		}

		foreach (array_reverse($missing) as $path){
			if (!$this->_q('mkdir', $path, 0777) && !is_dir($path)){
				return false;
			}
			$created[] = $path;
		}

		return true;

	}

	function _update_backup_dir($area){

		return cms_path('tmp').'update_backup_'.$this->_release_id($area).'/';

	}

	/**
	 * Why a live target cannot take this update step ('' = fine). Checked before any change.
	 */
	function _update_target_problem($to, $letter){

		if ($letter === 'D'){
			if (!is_file($to)){
				return '';
			}
			if (!is_writable(pathinfo($to, PATHINFO_DIRNAME))){
				return 'cannot delete (folder not writable)';
			}
			// Windows: a read-only file cannot be unlinked even in a writable folder
			if (DIRECTORY_SEPARATOR === '\\' && !is_writable($to)){
				return 'cannot delete (file is read-only)';
			}
			return '';
		}

		if (is_dir($to)){
			return 'cannot write (a folder has this name)';
		}

		if (is_file($to)){
			return is_writable($to) ? '' : 'cannot overwrite (file not writable)';
		}

		// New file: nearest existing parent folder must be writable, and no file may block the path
		$dir = pathinfo($to, PATHINFO_DIRNAME);
		while (!is_dir($dir)){
			if (file_exists($dir)){
				return 'cannot create (a file has the folder name '.basename($dir).')';
			}
			$parent = pathinfo($dir, PATHINFO_DIRNAME);
			if ($parent === $dir){
				return 'cannot create (no parent folder)';
			}
			$dir = $parent;
		}

		return is_writable($dir) ? '' : 'cannot create (folder not writable)';

	}

	/**
	 * "a.php (write failed), b.php (delete failed) and 3 more" — names files in operator messages.
	 */
	function _update_files_summary($reasons, $max = 5){

		$parts = [];
		foreach ($reasons as $filename => $reason){
			if (count($parts) >= $max){
				break;
			}
			$parts[] = $filename.' ('.$reason.')';
		}

		$more = count($reasons) - count($parts);

		return implode(', ', $parts).($more > 0 ? ' and '.$more.' more' : '');

	}

	function _rrmdir($dir){

		if (!is_dir($dir)){
			return;
		}

		$objects = scandir($dir);
		foreach ($objects as $object){
			if ($object === '.' || $object === '..'){
				continue;
			}
			$path = $dir.DIRECTORY_SEPARATOR.$object;
			if (is_dir($path)){
				$this->_rrmdir($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);

	}

	/**
	 * Call a filesystem/stream function without letting its warning reach the CMS error
	 * handler (which also prints @-suppressed warnings, breaking JSON answers). Failure is
	 * reported by the return value, like the @ form.
	 */
	function _q($fn, ...$args){

		set_error_handler(function(){
			return true;
		});

		try {
			return $fn(...$args);
		} finally {
			restore_error_handler();
		}

	}
	
	function update_version_cache($area, $params){

		// load cache file
		if (empty($area)){
			$filename = cms_path('tmp').'version.json';
		} else {
			$filename = cms_path('tmp').'version_'.$area.'.json';
		}

		$data = is_file($filename) ? json_decode(file_get_contents($filename), true) : null;
		if (!is_array($data)){
			$data = [];
		}

		// change values
		$data['version'] = $params['version'];
		$data['version_hash'] = $params['version_hash'];
		$data['version_time'] = $params['version_time'];
		$data['update_time'] = $params['update_time'];

		// Callers stamp only when the scanned tree equals the release (hash match), so the
		// current file list is that version's per-file baseline for local-edit detection
		$data['version_files'] = $this->_files_hash_map($data['files'] ?? []);

		// write cache file
		file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

	}

	/**
	 * filename => hash from a scan file list.
	 */
	function _files_hash_map($files){

		$map = [];
		if (!is_array($files)){
			return $map;
		}

		foreach ($files as $file){
			if (is_array($file) && isset($file['filename'], $file['hash'])){
				$map[$file['filename']] = $file['hash'];
			}
		}

		return $map;

	}

	/**
	 * Per-file hashes of the stamped (installed) version, or null when never recorded.
	 */
	function _load_version_files($area){

		$path = $this->_local_version_path($area);
		if (!is_file($path)){
			return null;
		}

		$data = json_decode(file_get_contents($path), true);
		if (!is_array($data) || empty($data['version_files']) || !is_array($data['version_files'])){
			return null;
		}

		return $data['version_files'];

	}

	/**
	 * Short calendar date for version labels (e.g. 16 Jul 2026).
	 */
	function format_version_date($time){

		if (empty($time)){
			return '';
		}

		return date('j M Y', (int)$time);

	}

	/**
	 * Local column: version + optional last-updated date + optional (local changes).
	 * Keeps 0.0.0 as a real version (never "unknown").
	 */
	function format_local_version_label($row){

		$version = $row['local_version'] ?? '';
		if ($version === '' || $version === null){
			$version = '0.0.0';
		}

		$label = $version;
		$date = $this->format_version_date($row['local_version_time'] ?? 0);
		if ($date !== ''){
			$label .= ' - '.$date;
		}

		if (!empty($row['local_changes'])){
			$label .= ' (local changes)';
		}

		return $label;

	}

	/**
	 * Client install: remote master package version (+ date).
	 */
	function format_master_version_label($version, $time){

		if ($version === '' || $version === null){
			return '';
		}

		$label = $version;
		$date = $this->format_version_date($time);
		if ($date !== ''){
			$label .= ' - '.$date;
		}

		return $label;

	}

	/**
	 * This host is master: what version is currently shared with clients.
	 * Unreleased → "Master"; released → "Master (1.1.0 - 16 Jul 2026)".
	 */
	function format_this_master_label($version, $time){

		if ($version === '' || $version === null){
			return 'Master';
		}

		$inner = $version;
		$date = $this->format_version_date($time);
		if ($date !== ''){
			$inner .= ' - '.$date;
		}

		return 'Master ('.$inner.')';

	}

	/**
	 * Build one updater table row (labels only — no hash fragments in UI fields).
	 * $area_data is a rebuild_area() result. $master_index: get_master_index() result to build
	 * module rows without a request each (null = one do=version request for this row).
	 */
	function build_area_display_row($area_data, $master_index = null){

		if (empty($GLOBALS['config']['update']['master'])){
			$GLOBALS['config']['update']['master'] = [];
		}

		$area_id = $area_data['area'] ?? '';
		$is_core = ($area_id === '');
		$is_local_master = $this->is_area_master($area_id);

		$live_hash = $area_data['local_current_hash'] ?? '';

		$may_use = $this->client_may_use_area($area_id);

		// Never remove core; master packages must leave update.master first; remove needs update.allow
		$can_remove = !$is_core && $area_id !== '' && $area_id !== 'cms' && !$is_local_master && $may_use;

		$row = [
				'area' => $area_id,
				'label' => $is_core ? 'Narrative CMS' : $area_id,
				'local_version' => $area_data['local_version'] ?? '0.0.0',
				'local_version_hash' => $area_data['local_version_hash'] ?? '',
				'local_current_hash' => $live_hash,
				'local_version_time' => !empty($area_data['local_version_time']) ? (int)$area_data['local_version_time'] : 0,
				'local_updated' => !empty($area_data['local_updated']) ? (int)$area_data['local_updated'] : 0,
				'local_changes' => false,
				'local_label' => '',
				'master_version' => '',
				'master_hash' => '',
				'master_time' => 0,
				'master_label' => '',
				'can_update' => false,
				'can_release' => false,
				'can_remove' => $can_remove,
				'may_use' => $may_use,
				// #429: master packages (bold), locally inactive modules, update blocked by update.allow
				'is_master' => $is_local_master,
				'active' => $this->_area_active($area_id),
				'not_allowed' => false,
				'error' => '',
				'local_only' => false,
				'status' => '',
		];

		// This host publishes this package — no auto-bump; optional Release button
		if ($is_local_master){

			$release = $this->get_release_meta($area_id);

			if ($release === null){
				// Config maj.min only; Release button already means not published yet
				$row['local_version'] = $this->get_config_version_string($area_id);
				$row['local_version_time'] = 0;
				$row['local_label'] = $row['local_version'];
				$row['local_changes'] = true;
				$row['can_release'] = true;
				$row['master_label'] = 'Master';
				$row['status'] = 'Master';
			} else {
				$released_hash = $release['current_hash'] ?? ($release['version_hash'] ?? '');
				$row['local_version'] = $release['version'] ?? '0.0.0';
				$row['local_version_hash'] = $released_hash;
				$row['local_version_time'] = !empty($release['version_time']) ? (int)$release['version_time'] : 0;
				$row['local_changes'] = ($live_hash !== '' && $released_hash !== '' && $live_hash !== $released_hash)
						|| ($released_hash === '');
				$row['local_label'] = $this->format_local_version_label($row);
				$row['can_release'] = !empty($row['local_changes']);
				// What clients receive from the last Release
				$row['master_label'] = $this->format_this_master_label(
						$row['local_version'], $row['local_version_time']);
				$row['status'] = $row['master_label'];
			}

			$row['can_remove'] = false;

			return $row;

		}

		$version_data = $this->_master_version_for_row($area_id, $master_index);

		if (!empty($version_data['error'])){

			// Master unreachable / bad answer: say so on the row (not "Local only")
			if ($is_core || !empty($version_data['transport_error'])){
				$row['error'] = $version_data['error'];
				$row['local_label'] = $this->format_local_version_label($row);
			} else {
				$row['local_only'] = true;
				$row['local_label'] = 'Local only';
			}

			return $row;

		}

		$master_hash = !empty($version_data['current_hash'])
				? $version_data['current_hash']
				: (!empty($version_data['version_hash']) ? $version_data['version_hash'] : '');
		$master_version = !empty($version_data['version']) ? $version_data['version'] : '';
		$master_time = !empty($version_data['version_time']) ? (int)$version_data['version_time'] : 0;

		if ($master_hash === '' && $master_version === '' && !$is_core){
			$row['local_only'] = true;
			$row['local_label'] = 'Local only';
			return $row;
		}

		// "No release" from remote master
		if (!empty($version_data['error']) || ($master_hash === '' && $master_version === '')){
			if ($is_core){
				$row['error'] = $version_data['error'] ?? 'No release on master';
				$row['local_label'] = $this->format_local_version_label($row);
			} else {
				$row['local_only'] = true;
				$row['local_label'] = 'Local only';
			}
			return $row;
		}

		$row['master_version'] = $master_version;
		$row['master_hash'] = $master_hash;
		$row['master_time'] = $master_time;

		$row['local_changes'] = !empty($row['local_current_hash'])
				&& !empty($row['local_version_hash'])
				&& $row['local_current_hash'] !== $row['local_version_hash'];

		$in_sync = ($master_hash !== '' && $row['local_current_hash'] === $master_hash);

		if ($in_sync){
			$row['local_changes'] = false;
			if ($master_version !== ''){
				$row['local_version'] = $master_version;
			}
			// Date on Local like Master (master time when the cache has no time or a different version)
			if ($master_time && (empty($row['local_version_time'])
					|| ($area_data['local_version'] ?? '') !== $master_version)){
				$row['local_version_time'] = $master_time;
			}
			if ($master_version !== ''){
				$this->_stamp_matched_release($area_data, $row, [
						'version' => $master_version,
						'current_hash' => $master_hash,
						'version_time' => $master_time,
				]);
			}
			$row['local_label'] = $this->format_local_version_label($row);
			$row['master_label'] = $this->format_master_version_label($master_version, $master_time);
			$row['can_update'] = false;
		} else {
			// Behind but identical to an older release → show that release, no local changes
			$matched = $this->_match_release_history($row['local_current_hash'], $version_data['history'] ?? []);
			if ($matched !== null){
				$row['local_version'] = $matched['version'];
				$row['local_version_hash'] = $matched['current_hash'];
				$row['local_version_time'] = $matched['version_time'];
				$row['local_changes'] = false;
				$this->_stamp_matched_release($area_data, $row, $matched);
			}
			$row['local_label'] = $this->format_local_version_label($row);
			$row['master_label'] = $this->format_master_version_label($master_version, $master_time);
			$row['can_update'] = $row['may_use'] && $master_hash !== '';
			// Master has something newer, but update.allow does not include this package
			$row['not_allowed'] = !$row['may_use'] && $master_hash !== '';
		}

		return $row;

	}

	/**
	 * Find the master release history entry whose tree hash equals the local live hash.
	 */
	function _match_release_history($live_hash, $history){

		if ($live_hash === '' || $live_hash === null){
			return null;
		}

		foreach ($this->_release_history_list($history) as $entry){
			if ($entry['version'] !== '' && $entry['current_hash'] === $live_hash){
				return $entry;
			}
		}

		return null;

	}

	/**
	 * Persist a hash-matched release into tmp/version_{area}.json so later rebuilds keep it.
	 * Writes only when the cached version / hash differ; update_time is left as it was.
	 */
	function _stamp_matched_release($area_data, $row, $release){

		$cached_version = $area_data['local_version'] ?? '';
		$cached_hash = $area_data['local_version_hash'] ?? '';

		// Also stamp once when the per-file baseline is missing (installs stamped before it existed)
		if ($cached_version === $release['version'] && $cached_hash === $release['current_hash']
				&& !empty($area_data['local_has_baseline'])){
			return;
		}

		$this->update_version_cache($row['area'], [
				'version' => $release['version'],
				'version_hash' => $release['current_hash'],
				'version_time' => !empty($release['version_time']) ? (int)$release['version_time'] : 0,
				'update_time' => !empty($row['local_updated']) ? (int)$row['local_updated'] : 0,
		]);

	}

	/**
	 * Rebuild hashes for one area and return display row for the updater table.
	 */
	function confirm_area($area){

		$folders = $this->_update_area_folders($area);
		$local = $this->rebuild_area($area, $folders);

		return $this->build_area_display_row($local);

	}

	/**
	 * Remove empty directories left after file deletes (updater step).
	 * Deepest-first until stable. $area: '' = core (system + modules/cms), else modules/<area>/.
	 */
	function update_cleanup($area = ''){

		$folders = $this->_update_area_folders($area);
		$base = str_replace('\\', '/', $GLOBALS['config']['base_path']);

		do {
			$removed = 0;

			foreach ($folders as $folder){

				$full_folder = $base.$folder;
				if (!is_dir($full_folder)){
					continue;
				}

				$dirs = [];
				$it = new \RecursiveDirectoryIterator($full_folder, \FilesystemIterator::SKIP_DOTS);
				foreach (new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::SELF_FIRST) as $path => $fileinfo){
					if ($fileinfo->isDir()){
						$dirs[] = str_replace('\\', '/', $path);
					}
				}

				// deepest paths first
				usort($dirs, function($a, $b){
					return strlen($b) - strlen($a);
				});

				foreach ($dirs as $dir){

					// never remove the area root itself
					$norm = rtrim($dir, '/').'/';
					$root_norm = rtrim($full_folder, '/').'/';
					if ($norm === $root_norm){
						continue;
					}

					if (!is_dir($dir)){
						continue;
					}

					$fi = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);
					if (!iterator_count($fi)){
						if ($this->_q('rmdir', $dir)){
							$removed++;
						}
					}

				}

			}

		} while ($removed > 0);

		return true;

	}

}