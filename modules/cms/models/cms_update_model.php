<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

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
	 * Release package id under cache/master/ — core (system + modules/cms) uses "cms".
	 */
	function _release_id($area){

		if ($area === '' || $area === null){
			return 'cms';
		}

		return (string)$area;

	}

	function _release_dir($area){

		return $GLOBALS['config']['base_path'].'cache/master/'.$this->_release_id($area).'/';

	}

	function _release_version_path($area){

		return $this->_release_dir($area).'version.json';

	}

	function _local_version_path($area){

		if ($area === '' || $area === null){
			return $GLOBALS['config']['base_path'].'cache/version.json';
		}

		return $GLOBALS['config']['base_path'].'cache/version_'.$area.'.json';

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
				if (strpos($cms_filename, 'cache/master/') === 0 || strpos($cms_filename, 'cache/update/') === 0){
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
		];

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

			file_put_contents($filename, json_encode($new_data, JSON_PRETTY_PRINT));
			$old_data = $new_data;

		}

		$return['local_version'] = $old_data['version'];
		$return['local_updated'] = !empty($old_data['update_time']) ? $old_data['update_time'] : '0';
		$return['local_version_time'] = !empty($old_data['version_time']) ? $old_data['version_time'] : '0';
		$return['local_current_hash'] = $current_hash;
		$return['local_version_hash'] = !empty($old_data['version_hash']) ? $old_data['version_hash'] : '[unknown]';

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
    	
		$header = [
				'Content-type: application/x-www-form-urlencoded',
		];

		$postdata = http_build_query([
				'do' => 'version',
				'module' => $area,
				'area' => $area,
		]);
    	
    	// check url
    	if (stristr($GLOBALS['config']['cms_update_url'], 'localhost')){
    		$host = parse_url($GLOBALS['config']['cms_update_url'], PHP_URL_HOST);
    		$url = str_replace($host, 'localhost', $GLOBALS['config']['cms_update_url']);
    		$header[] = 'Host: '.$host;
    	} else {
    		$url = $GLOBALS['config']['cms_update_url'];
    	}
    	
    	$context  = stream_context_create(array('http' => array(
    			'method'  => 'POST',
    			'header'  => $header,
    			'content' => $postdata,
    			'ignore_errors' => true,
    	)));
    	
   		$master_data = file_get_contents($url, false, $context);
   		if (stristr($master_data, 'was not found')){
    		return [
    				'error' => 'Bad update URL:<br>'.$url,
    		];
    	}

    	if ($master_data === false){
			return [];
		}
		
		return json_decode($master_data, true);
		
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

			// Only packages that have been Released (cache/master/{name}/)
			$v = $this->get_release_version($name);
			if (!empty($v['error']) || empty($v['version'])){
				continue;
			}

			$out[] = [
					'name' => $name,
					'version' => $v['version'] ?? '',
					'version_hash' => $v['current_hash'] ?? $v['version_hash'] ?? '',
					'version_time' => !empty($v['version_time']) ? (int)$v['version_time'] : 0,
			];

		}

		return $out;

	}

	/**
	 * Client: packages on master not present locally, filtered by update.allow.
	 */
	function get_installable_modules(){

		if (empty($GLOBALS['config']['cms_update_url']) || empty($GLOBALS['config']['update']['allow'])){
			return [];
		}

		$master_list = $this->get_master_modules();
		if (empty($master_list) || !is_array($master_list)){
			return [];
		}

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

		if (empty($GLOBALS['config']['cms_update_url'])){
			return [];
		}

		$header = [
				'Content-type: application/x-www-form-urlencoded',
		];

		$postdata = http_build_query([
				'do' => 'modules',
		]);

		if (stristr($GLOBALS['config']['cms_update_url'], 'localhost')){
			$host = parse_url($GLOBALS['config']['cms_update_url'], PHP_URL_HOST);
			$url = str_replace($host, 'localhost', $GLOBALS['config']['cms_update_url']);
			$header[] = 'Host: '.$host;
		} else {
			$url = $GLOBALS['config']['cms_update_url'];
		}

		$context = stream_context_create(['http' => [
				'method' => 'POST',
				'header' => $header,
				'content' => $postdata,
				'ignore_errors' => true,
		]]);

		$master_data = file_get_contents($url, false, $context);
		if ($master_data === false || stristr($master_data, 'was not found')){
			return [];
		}

		$decoded = json_decode($master_data, true);
		if (empty($decoded['modules']) || !is_array($decoded['modules'])){
			return [];
		}

		return $decoded['modules'];

	}

	/**
	 * Enable module in cms/cms_settings modules list as penultimate (before last site module).
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
		$modules = $settings['modules'] ?? [];
		if (!is_array($modules)){
			$modules = [];
		}

		// Drop cms (always prepended on boot) and any existing name
		$modules = array_values(array_filter($modules, function($m) use ($name){
			return $m !== 'cms' && $m !== $name;
		}));

		if (count($modules) === 0){
			$modules = [$name];
		} else if (count($modules) === 1){
			$modules[] = $name;
		} else {
			$last = array_pop($modules);
			$modules[] = $name;
			$modules[] = $last;
		}

		$this->cms_page_panel_model->update_cms_page_panel($settings_id, [
				'modules' => $modules,
		]);

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
		$modules = $settings['modules'] ?? [];
		if (!is_array($modules)){
			$modules = [];
		}

		$modules = array_values(array_filter($modules, function($m) use ($name){
			return $m !== $name;
		}));

		$this->cms_page_panel_model->update_cms_page_panel($settings_id, [
				'modules' => $modules,
		]);

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
			@unlink($version_file);
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
		
		$header = [
				'Content-type: application/x-www-form-urlencoded',
		];
		
		$postdata = http_build_query([
				'do' => 'files',
				'module' => $area,
				'area' => $area,
		]);
		 
		// check url
		if (stristr($GLOBALS['config']['cms_update_url'], 'localhost')){
			$host = parse_url($GLOBALS['config']['cms_update_url'], PHP_URL_HOST);
			$url = str_replace($host, 'localhost', $GLOBALS['config']['cms_update_url']);
			$header[] = 'Host: '.$host;
		} else {
			$url = $GLOBALS['config']['cms_update_url'];
		}
		 
		$context  = stream_context_create(array('http' => array(
				'method'  => 'POST',
				'header'  => $header,
				'content' => $postdata
		)));
		
		$master_data = file_get_contents($url, false, $context);
		
		if (empty($master_data)){
			return false;
		}
		
		return json_decode($master_data, true);
		
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
	
	function get_master_file($needed_filename, $area){
		
		if (empty($GLOBALS['config']['cms_update_url'])){
			return false;
		}
		
		$header = [
				'Content-type: application/x-www-form-urlencoded',
		];
		
		$postdata = http_build_query([
				'do' => 'file',
				'area' => $area,
				'module' => $area,
				'filename' => $needed_filename,
		]);
			
		// check url
		if (stristr($GLOBALS['config']['cms_update_url'], 'localhost')){
			$host = parse_url($GLOBALS['config']['cms_update_url'], PHP_URL_HOST);
			$url = str_replace($host, 'localhost', $GLOBALS['config']['cms_update_url']);
			$header[] = 'Host: '.$host;
		} else {
			$url = $GLOBALS['config']['cms_update_url'];
		}
			
		$context  = stream_context_create(array('http' => array(
				'method'  => 'POST',
				'header'  => $header,
				'content' => $postdata
		)));
		
		$master_data = file_get_contents($url, false, $context);
		
		return json_decode($master_data, true);

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

		$header = [
				'Content-type: application/x-www-form-urlencoded',
		];

		$postdata = http_build_query([
				'do' => 'file',
				'area' => $area,
				'module' => $area,
				'filenames' => array_values($filenames),
		]);

		if (stristr($GLOBALS['config']['cms_update_url'], 'localhost')){
			$host = parse_url($GLOBALS['config']['cms_update_url'], PHP_URL_HOST);
			$url = str_replace($host, 'localhost', $GLOBALS['config']['cms_update_url']);
			$header[] = 'Host: '.$host;
		} else {
			$url = $GLOBALS['config']['cms_update_url'];
		}

		$context = stream_context_create(['http' => [
				'method' => 'POST',
				'header' => $header,
				'content' => $postdata,
				'ignore_errors' => true,
		]]);

		$master_data = file_get_contents($url, false, $context);
		if ($master_data === false || $master_data === ''){
			return false;
		}

		$decoded = json_decode($master_data, true);
		if (!is_array($decoded)){
			return false;
		}

		// Legacy master only understands single filename — fall back one-by-one
		if (!isset($decoded['files']) && (isset($decoded['file']) || isset($decoded['error']))){
			$out = ['files' => [], 'errors' => []];
			foreach ($filenames as $fn){
				$one = $this->get_master_file($fn, $area);
				if (!empty($one['file'])){
					$out['files'][$fn] = $one['file'];
				} else {
					$out['errors'][$fn] = $one['error'] ?? 'empty';
				}
			}
			return $out;
		}

		return $decoded;

	}
	
	function get_needed_files($area){

		$area_norm = $this->normalise_area_name($area);
		if ($area_norm === false){
			return [];
		}
		$area = $area_norm;

		if (!$this->client_may_use_area($area)){
			return [];
		}
		
		// get master list (remote Release snapshot)
		$master_files = $this->get_master_files($area);
		if (empty($master_files['files']) || !is_array($master_files['files'])){
			return [];
		}

		// get local working list (not this host's release cache)
		$local_files = $this->get_files($area, false);
		if (empty($local_files['files']) || !is_array($local_files['files'])){
			$local_files['files'] = [];
		}
		
		$return = array();

		// remove 
		foreach($master_files['files'] as $master_key => $master_file){
				
			$needs_update = false;
			$local_file_found = false;
				
			$master_hash = $master_file['hash'];
				
			// find the same local file
			foreach($local_files['files'] as $local_key => $local_file){
		
				if ($local_file['filename'] == $master_file['filename']){
					$local_file_found = true;
					$local_key_to_delete = $local_key;
					if ($local_file['hash'] != $master_file['hash']){
						$needs_update = true;
						$local_hash = $local_file['hash'];
						$letter = 'U';
					}
				}
		
			}
				
			if ($local_file_found){
				$local_hash = $local_files['files'][$local_key_to_delete]['hash'];
				unset($local_files['files'][$local_key_to_delete]);
			} else {
				$local_hash = 'no local file';
				$needs_update = true;
				$letter = 'A';
			}
				
			if ($needs_update){
				
				$return[] = array(
					'filename' => $master_file['filename'],
					'letter' => $letter,
					'fn_hash' => md5($master_file['filename']),
				);
		
			}
				
		}
		
		// delete local files not in master list
		foreach($local_files['files'] as $local_file){
				
			$return[] = array(
				'filename' => $local_file['filename'],
				'letter' => 'D',
				'fn_hash' => md5($local_file['filename']),
			);
				
		}
		
		return $return;
		
	}
	
	// Stage one file from master into cache/update/ (or _DELETE_ marker)
	function update_file($filename, $area){

		$master_file_data = $this->get_master_file($filename, $area);
		$b64 = !empty($master_file_data['file']) ? $master_file_data['file'] : '';
		$this->_stage_update_cache_file($filename, $b64);

		return $filename;

	}

	/**
	 * Stage multiple files from master in one round-trip (batch do=file).
	 * @param array $filenames
	 * @return array{done:array<int,array{filename:string,fn_hash:string}>}
	 */
	function update_files($filenames, $area){

		if (!is_array($filenames)){
			$filenames = [];
		}

		$filenames = array_values(array_filter(array_map('strval', $filenames), function($f){
			return $f !== '';
		}));

		$done = [];
		if (!count($filenames)){
			return ['done' => $done];
		}

		$batch = $this->get_master_files_content($filenames, $area);
		$got = (is_array($batch) && !empty($batch['files']) && is_array($batch['files']))
				? $batch['files']
				: [];

		foreach ($filenames as $filename){

			$b64 = isset($got[$filename]) ? $got[$filename] : '';
			$this->_stage_update_cache_file($filename, $b64);
			$done[] = [
					'filename' => $filename,
					'fn_hash' => md5($filename),
			];

		}

		return ['done' => $done];

	}

	function _stage_update_cache_file($filename, $base64_content){

		$pathinfo = pathinfo($GLOBALS['config']['base_path'].'cache/update/'.$filename);
		if (!file_exists($pathinfo['dirname'])){
			mkdir($pathinfo['dirname'], 0777, true);
		}

		if ($base64_content !== '' && $base64_content !== null){
			file_put_contents(
					$GLOBALS['config']['base_path'].'cache/update/'.$filename,
					base64_decode($base64_content)
			);
		} else {
			// Delete marker (file not on master / empty body)
			file_put_contents(
					$GLOBALS['config']['base_path'].'cache/update/'.$filename,
					'_DELETE_'
			);
		}

	}
	
	function update_copy($area){

		// go over all cache files recursively
		$folder = $GLOBALS['config']['base_path']. 'cache/update/';
		
		if (!empty($area)){
			$folder_area = $folder.'modules/'.$area.'/';
		} else {
			$folder_area = $folder;
		}

		if (file_exists($folder_area)){
			
			$it = new \RecursiveDirectoryIterator($folder);
			foreach (new \RecursiveIteratorIterator($it) as $filename => $file) {
				
				$from_filename = $folder . str_replace($folder, '', str_replace("\\", '/', $filename));

				if (!is_dir($from_filename)){
						
					$to_filename = $GLOBALS['config']['base_path'] . str_replace($folder, '', str_replace("\\", '/', $filename));

					// ensure target parent exists
					$to_dir = pathinfo($to_filename, PATHINFO_DIRNAME);
					if (!file_exists($to_dir)){
						mkdir($to_dir, 0777, true);
					}

					// check whats inside
					$contents = file_get_contents($from_filename);

					if ($contents == '_DELETE_'){
						if (file_exists($to_filename)){
							unlink($to_filename);
						}
					} else {
						copy($from_filename, $to_filename);
					}
					
					unlink($from_filename);
				
				}
	
			}

			$this->_rrmdir($folder);

		}

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
	
	function update_version_cache($area, $params){

		// load cache file
		if (empty($area)){
			$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		} else {
			$filename = $GLOBALS['config']['base_path'] . 'cache/version_'.$area.'.json';
		}
		
		$data = json_decode(file_get_contents($filename), true);
		
		// change values
		$data['version'] = $params['version'];
		$data['version_hash'] = $params['version_hash'];
		$data['version_time'] = $params['version_time'];
		$data['update_time'] = $params['update_time'];
		
		// write cache file
		file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

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
	 * $area_data is a rebuild_area() result.
	 */
	function build_area_display_row($area_data){

		if (empty($GLOBALS['config']['update']['master'])){
			$GLOBALS['config']['update']['master'] = [];
		}

		$area_id = $area_data['area'] ?? '';
		$is_core = ($area_id === '');
		$is_local_master = $this->is_area_master($area_id);

		$live_hash = $area_data['local_current_hash'] ?? '';

		// Never remove core; master packages must leave update.master first
		$can_remove = !$is_core && $area_id !== '' && $area_id !== 'cms' && !$is_local_master;

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
				'may_use' => $this->client_may_use_area($area_id),
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

		$version_data = $this->get_master_version($area_id);

		if (!empty($version_data['error'])){

			if ($is_core){
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
			// Date on Local like Master (use master time if local cache has no version_time)
			if (empty($row['local_version_time']) && $master_time){
				$row['local_version_time'] = $master_time;
			}
			$row['local_label'] = $this->format_local_version_label($row);
			$row['master_label'] = $this->format_master_version_label($master_version, $master_time);
			$row['can_update'] = false;
		} else {
			$row['local_label'] = $this->format_local_version_label($row);
			$row['master_label'] = $this->format_master_version_label($master_version, $master_time);
			$row['can_update'] = $row['may_use'] && $master_hash !== '';
		}

		return $row;

	}

	/**
	 * Rebuild hashes for one area and return display row for the updater table.
	 */
	function confirm_area($area){

		$folders = $this->_update_area_folders($area);
		$local = $this->rebuild_area($area, $folders);

		return $this->build_area_display_row($local);

	}

	function run_sql($sql){
		
		try {
			$query = $this->db->query($sql);
		} catch (\Exception $e) {
			_html_error($e->getMessage(), 0, ['backtrace' => 1]);
		}

		if ($query === false || $query === true){
			return [$query];
		}

		$result = $query->result_array();
		
		return $result;
		
	}
	
	function up(){
		
		// check if table block exists and update
		
		$sql = "select 1 from block limit 1";
		$query = $this->db->query($sql);
		
		if ($query && $query->num_rows()){
			
			$sql = "rename table `block` to `cms_page_panel`";
			$this->run_sql($sql);
			
			$sql = "ALTER TABLE `cms_page_panel` CHANGE `block_id` `cms_page_panel_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT";
			$this->run_sql($sql);
			
			$sql = "ALTER TABLE `cms_page_panel` CHANGE `page_id` `cms_page_id` INT(10) UNSIGNED NOT NULL";
			$this->run_sql($sql);
				
		}
		
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
						if (@rmdir($dir)){
							$removed++;
						}
					}

				}

			}

		} while ($removed > 0);

		return true;

	}

}