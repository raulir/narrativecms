<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cache {

	function panel_is_deferred($panel_definition) {

		return isset($panel_definition['deferred']) && (string)$panel_definition['deferred'] === '1';

	}

	function page_may_cache($page) {

		$cache = trim((string)($page['cache'] ?? ''));
		return $cache !== '' && ctype_digit($cache) && (int)$cache > 0;

	}

	function try_serve($request_uri = null) {

		if (!$this->_can_serve_request()) {
			return false;
		}

		if ($request_uri === null) {
			$request_uri = $this->request_uri();
		}

		$route_target = $this->_resolve_route_target($request_uri);
		$stem = $this->_build_stem($route_target, $request_uri);

		if ($stem === '') {
			return false;
		}

		$stem_with_hash = $this->_stem_with_hash($stem);
		$meta_path = $this->_meta_path($stem_with_hash);
		$html_path = $this->_html_path($stem_with_hash);

		if (!is_file($meta_path) || !is_file($html_path)) {
			return false;
		}

		$meta = cms_json_decode(file_get_contents($meta_path), basename($meta_path));
		if (!is_array($meta) || empty($meta['ttl']) || empty($meta['written'])) {
			return false;
		}

		if (!empty($meta['access_restricted'])) {
			return false;
		}

		if (time() >= ((int)$meta['written'] + (int)$meta['ttl'])) {
			return false;
		}

		$hash = $this->_targets_hash();
		if (($meta['targets_hash'] ?? '') !== $hash) {
			return false;
		}

		header('Content-Type: text/html; charset=UTF-8');
		header('X-CMS-Page-Cache: HIT');
		print(file_get_contents($html_path));
		exit();

	}

	function should_write($page, $position_pages = []) {

		if (!$this->_can_serve_request()) {
			return 0;
		}

		if ($this->_page_has_access($page)) {
			return 0;
		}

		foreach ($position_pages as $position_page) {
			if ($this->_page_has_access($position_page)) {
				return 0;
			}
		}

		return $this->_get_effective_ttl($page, $position_pages);

	}

	function request_uri() {

		if (isset($GLOBALS['cms_request_uri'])) {
			return $GLOBALS['cms_request_uri'];
		}

		if (substr($_SERVER['REQUEST_URI'], 0, strlen($GLOBALS['config']['base_url'])) == $GLOBALS['config']['base_url']) {
			$string = substr($_SERVER['REQUEST_URI'], strlen($GLOBALS['config']['base_url']));
		} else {
			$string = $_SERVER['REQUEST_URI'];
		}

		if (stristr($string, '?')) {
			list($string,) = explode('?', $string);
		}

		return trim($string, '/');

	}

	function write($stem_with_hash, $html, $meta) {

		$base = $GLOBALS['config']['base_path'].'cache/';
		$html_path = $base.$stem_with_hash.'.html';
		$meta_path = $base.$stem_with_hash.'.meta.json';
		$html_tmp = $html_path.'.tmp';
		$meta_tmp = $meta_path.'.tmp';

		$meta['written'] = time();
		$meta['targets_hash'] = $this->_targets_hash();

		file_put_contents($html_tmp, $html);
		file_put_contents($meta_tmp, json_encode($meta, JSON_PRETTY_PRINT));
		rename($html_tmp, $html_path);
		rename($meta_tmp, $meta_path);

		$registry = $this->_load_registry();
		$registry[$stem_with_hash.'.html'] = [
			'main_page_id' => (int)($meta['main_page_id'] ?? 0),
			'header_page_id' => (int)($meta['header_page_id'] ?? 0),
			'footer_page_id' => (int)($meta['footer_page_id'] ?? 0),
			'list_panel_id' => (int)($meta['list_panel_id'] ?? 0),
			'slug' => $meta['slug'] ?? '',
			'stem' => $meta['stem'] ?? '',
		];
		$this->_save_registry($registry);

	}

	function build_stem($route_target, $request_uri = '') {

		return $this->_build_stem($route_target, $request_uri);

	}

	function stem_with_hash($stem) {

		return $this->_stem_with_hash($stem);

	}

	function build_write_meta($page, $position_pages, $route_target, $request_uri, $ttl) {

		$stem = $this->_build_stem($route_target, $request_uri);
		$meta = [
			'ttl' => (int)$ttl,
			'main_page_id' => (int)($page['cms_page_id'] ?? 0),
			'header_page_id' => 0,
			'footer_page_id' => 0,
			'list_panel_id' => 0,
			'slug' => $this->_sanitise_slug($page['slug'] ?? $request_uri),
			'stem' => $stem,
			'access_restricted' => 0,
			'targets_hash' => $this->_targets_hash(),
		];

		foreach ($position_pages as $key => $position_page) {
			if ($key === 'header') {
				$meta['header_page_id'] = (int)($position_page['cms_page_id'] ?? 0);
			}
			if ($key === 'footer') {
				$meta['footer_page_id'] = (int)($position_page['cms_page_id'] ?? 0);
			}
		}

		if (stristr((string)$route_target, '=')) {
			list(, $panel_id) = explode('=', $route_target, 2);
			$meta['list_panel_id'] = (int)$panel_id;
		}

		$deferred_meta = $GLOBALS['page_cache_deferred_meta'] ?? [];
		$meta['has_deferred'] = !empty($deferred_meta['panels']) ? 1 : 0;
		$meta['deferred_panels'] = $deferred_meta['panels'] ?? [];

		if (!empty($GLOBALS['config']['position_wrappers']) && !empty($GLOBALS['config']['position_links'])) {
			$meta['deferred_positions'] = $deferred_meta['positions'] ?? [];
		}

		return $meta;

	}

	function deferred_mount_html($panel_name) {

		$panel_name = htmlentities($panel_name, ENT_QUOTES, 'UTF-8');
		$class = str_replace('/', '_', $panel_name).'_container';

		return '<div class="'.$class.'" data-cache_ajax="'.$panel_name.'"></div>';

	}

	function write_partial_caches($page, $position_pages, $panel_data, $page_cache_ttl = 0) {

		if (empty($GLOBALS['config']['position_wrappers']) || empty($GLOBALS['config']['position_links'])) {
			return;
		}

		$deferred_meta = $GLOBALS['page_cache_deferred_meta'] ?? [];

		if ($page_cache_ttl > 0 && !empty($page['cms_page_id'])) {
			$html = $this->_collect_position_html($panel_data, 'main');
			if ($html !== '') {
				$deferred_panels = $deferred_meta['panels_by_position']['main'] ?? [];
				$meta = $this->_build_partial_meta($page, $deferred_panels);
				$meta['ttl'] = (int)$page_cache_ttl;
				$this->write_partial((int)$page['cms_page_id'], $html, $meta);
			}
		}

		foreach ($position_pages as $position_name => $position_page) {
			$ttl = $this->_position_should_write($position_page);
			if ($ttl < 1) {
				continue;
			}

			$html = $this->_collect_position_html($panel_data, $position_name);
			if ($html === '') {
				continue;
			}

			$deferred_panels = $deferred_meta['panels_by_position'][$position_name] ?? [];
			$meta = $this->_build_partial_meta($position_page, $deferred_panels);
			$meta['ttl'] = $ttl;
			$this->write_partial((int)$position_page['cms_page_id'], $html, $meta);
		}

	}

	function write_partial($cms_page_id, $html, $meta) {

		$stem = 'partial_cache_id_'.(int)$cms_page_id;
		$this->write($this->_stem_with_hash($stem), $html, $meta);

	}

	function try_serve_position($cms_page_id) {

		if (!$this->_can_serve_request()) {
			return false;
		}

		$cms_page_id = (int)$cms_page_id;
		if ($cms_page_id < 1) {
			return false;
		}

		$stem = 'partial_cache_id_'.$cms_page_id;
		$stem_with_hash = $this->_stem_with_hash($stem);
		$meta_path = $this->_meta_path($stem_with_hash);
		$html_path = $this->_html_path($stem_with_hash);

		if (!is_file($meta_path) || !is_file($html_path)) {
			return false;
		}

		$meta = cms_json_decode(file_get_contents($meta_path), basename($meta_path));
		if (!is_array($meta) || empty($meta['ttl']) || empty($meta['written'])) {
			return false;
		}

		if (!empty($meta['access_restricted'])) {
			return false;
		}

		if (time() >= ((int)$meta['written'] + (int)$meta['ttl'])) {
			return false;
		}

		$hash = $this->_targets_hash();
		if (($meta['targets_hash'] ?? '') !== $hash) {
			return false;
		}

		return [
			'html' => file_get_contents($html_path),
			'meta' => $meta,
		];

	}

	function invalidate_slug($slug) {

		$slug = $this->_sanitise_slug($slug);
		if ($slug === '') {
			return;
		}
		$this->_delete_stem_files('page_cache_'.$slug);

	}

	function invalidate_page($cms_page_id) {

		$cms_page_id = (int)$cms_page_id;
		if ($cms_page_id < 1) {
			return;
		}

		$base = $GLOBALS['config']['base_path'].'cache/';
		$this->_delete_stem_files('page_cache_id_'.$cms_page_id);
		$this->_delete_stem_files('partial_cache_id_'.$cms_page_id);

		$registry = $this->_load_registry();
		$changed = false;

		foreach ($registry as $filename => $entry) {
			if ((int)($entry['main_page_id'] ?? 0) === $cms_page_id
					|| (int)($entry['header_page_id'] ?? 0) === $cms_page_id
					|| (int)($entry['footer_page_id'] ?? 0) === $cms_page_id) {
				@unlink($base.$filename);
				@unlink($base.preg_replace('/\.html$/', '.meta.json', $filename));
				unset($registry[$filename]);
				$changed = true;
			}
		}

		if ($changed) {
			$this->_save_registry($registry);
		}

	}

	function invalidate_list_item($panel_name, $cms_page_panel_id) {

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if (stristr($panel_name, '/')) {
			list($module, $listname) = explode('/', $panel_name, 2);
		} else {
			$module = '';
			$listname = $panel_name;
		}
		$module = $this->_sanitise_slug(str_replace('/', '_', $module));
		$listname = $this->_sanitise_slug(str_replace('/', '_', $listname));
		$this->_delete_stem_files('page_cache_'.$module.'__'.$listname.'__'.$cms_page_panel_id);

	}

	function _can_serve_request() {

		if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
			return false;
		}

		if (!empty($_POST) || !empty($_REQUEST['_ajax'])) {
			return false;
		}

		if (!empty($_SESSION['cms_user']['cms_user_id'])) {
			return false;
		}

		if (!empty($GLOBALS['config']['cache']['force_download'])) {
			return false;
		}

		$request_uri = $this->request_uri();

		if ($request_uri === false) {
			return false;
		}

		$prefixes = ['admin/', 'cms_login/', 'cms_operations/'];
		foreach ($prefixes as $prefix) {
			if (strpos($request_uri, $prefix) === 0) {
				return false;
			}
		}

		if (!empty($GLOBALS['config']['module'])) {
			foreach ($GLOBALS['config']['module'] as $module => $cfg) {
				if (!empty($cfg['api'])) {
					foreach ($cfg['api'] as $api) {
						if (!empty($api['id']) && strpos($request_uri, $module.'/'.$api['id']) === 0) {
							return false;
						}
					}
				}
			}
		}

		return true;

	}

	function _registry_path() {

		return $GLOBALS['config']['base_path'].'cache/page_cache_registry.json';

	}

	function _targets_hash() {

		$hash = $_SESSION['config']['targets']['hash'] ?? '';
		$hash = preg_replace('/[^a-zA-Z0-9]/', '', (string)$hash);
		return $hash;

	}

	function _load_routes() {

		$routes = [];
		$path = $GLOBALS['config']['base_path'].'cache/routes.php';

		if (!file_exists($path)) {
			return $routes;
		}

		include $path;

		return !empty($route) && is_array($route) ? $route : [];

	}

	function _resolve_route_target($request_uri) {

		global $landing_route;

		$routes = $this->_load_routes();

		if ($request_uri === '') {
			$default = 'index'.($landing_route ?? '');
			$parts = explode('/', trim($default, '/'));
			if (count($parts) >= 3 && $parts[0] === 'index' && $parts[1] === 'index') {
				return $parts[2];
			}
			return '';
		}

		if (isset($routes[$request_uri])) {
			$parts = explode('/', trim($routes[$request_uri], '/'));
			if (count($parts) >= 3 && $parts[0] === 'index' && $parts[1] === 'index') {
				return $parts[2];
			}
		}

		foreach ($routes as $key => $val) {
			if ($key === 'default_controller' || $key === '404_override') {
				continue;
			}
			$pattern = '#^'.str_replace(':any', '.+', str_replace(':num', '[0-9]+', $key)).'$#';
			if (preg_match($pattern, $request_uri)) {
				if (strpos($val, '$') !== false && strpos($key, '(') !== false) {
					$val = preg_replace($pattern, $val, $request_uri);
				}
				$parts = explode('/', trim($val, '/'));
				if (count($parts) >= 3 && $parts[0] === 'index' && $parts[1] === 'index') {
					return $parts[2];
				}
			}
		}

		$parts = explode('/', $request_uri);
		if (count($parts) >= 2 && $parts[0] === 'index' && $parts[1] === 'index' && !empty($parts[2])) {
			return $parts[2];
		}

		return '';

	}

	function _sanitise_slug($slug) {

		$slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$slug);
		return $slug;

	}

	function _build_stem($route_target, $request_uri = '') {

		$route_target = trim((string)$route_target, '/');

		if ($route_target === '') {
			return '';
		}

		if (stristr($route_target, '=')) {
			list($panel_name, $panel_id) = explode('=', $route_target, 2);
			if (stristr($panel_name, '/')) {
				list($module, $listname) = explode('/', $panel_name, 2);
			} else {
				$module = '';
				$listname = $panel_name;
			}
			$module = $this->_sanitise_slug(str_replace('/', '_', $module));
			$listname = $this->_sanitise_slug(str_replace('/', '_', $listname));
			return 'page_cache_'.$module.'__'.$listname.'__'.(int)$panel_id;
		}

		if (ctype_digit($route_target)) {
			$slug = $this->_sanitise_slug($request_uri);
			if ($slug !== '') {
				return 'page_cache_'.$slug;
			}
			return 'page_cache_id_'.(int)$route_target;
		}

		$slug = $this->_sanitise_slug($route_target);
		if ($slug !== '') {
			return 'page_cache_'.$slug;
		}

		return '';

	}

	function _stem_with_hash($stem) {

		$hash = $this->_targets_hash();
		if ($hash !== '') {
			return $stem.'__'.$hash;
		}
		return $stem;

	}

	function _html_path($stem_with_hash) {

		return $GLOBALS['config']['base_path'].'cache/'.$stem_with_hash.'.html';

	}

	function _meta_path($stem_with_hash) {

		return $GLOBALS['config']['base_path'].'cache/'.$stem_with_hash.'.meta.json';

	}

	function _load_registry() {

		$path = $this->_registry_path();
		if (!file_exists($path)) {
			return [];
		}

		$data = cms_json_decode(file_get_contents($path), 'page_cache_registry.json');
		return is_array($data) ? $data : [];

	}

	function _save_registry($registry) {

		file_put_contents($this->_registry_path(), json_encode($registry, JSON_PRETTY_PRINT));

	}

	function _delete_stem_files($stem) {

		$base = $GLOBALS['config']['base_path'].'cache/';
		foreach (glob($base.$stem.'*.html') as $file) {
			@unlink($file);
		}
		foreach (glob($base.$stem.'*.meta.json') as $file) {
			@unlink($file);
		}

		$registry = $this->_load_registry();
		$changed = false;
		foreach ($registry as $filename => $entry) {
			if (!empty($entry['stem']) && strpos($entry['stem'], $stem) === 0) {
				unset($registry[$filename]);
				$changed = true;
			}
		}
		if ($changed) {
			$this->_save_registry($registry);
		}

	}

	function _parse_cache_seconds($value) {

		$value = trim((string)$value);
		if ($value === '' || !ctype_digit($value)) {
			return 0;
		}
		return max(0, (int)$value);

	}

	function _get_effective_ttl($main_page, $position_pages = []) {

		$ttls = [];

		if (!empty($main_page['cache'])) {
			$main_ttl = $this->_parse_cache_seconds($main_page['cache']);
			if ($main_ttl > 0) {
				$ttls[] = $main_ttl;
			}
		}

		if (empty($ttls)) {
			return 0;
		}

		foreach ($position_pages as $position_page) {
			if (empty($position_page['cache'])) {
				continue;
			}
			$ttl = $this->_parse_cache_seconds($position_page['cache']);
			if ($ttl > 0) {
				$ttls[] = $ttl;
			}
		}

		return min($ttls);

	}

	function _page_has_access($page) {

		return !empty(trim($page['access'] ?? ''));

	}

	function _position_should_write($position_page) {

		if (!$this->_can_serve_request()) {
			return 0;
		}

		if ($this->_page_has_access($position_page)) {
			return 0;
		}

		return $this->_parse_cache_seconds($position_page['cache'] ?? '');

	}

	function _collect_position_html($panel_data, $position_name) {

		$html = '';

		foreach ($panel_data as $key => $pdata) {
			$parts = explode('_', $key, 3);
			if (($parts[0] ?? '') === $position_name) {
				$html .= $pdata;
			}
		}

		return $html;

	}

	function _build_partial_meta($page, $deferred_panels = []) {

		$cms_page_id = (int)($page['cms_page_id'] ?? 0);

		return [
			'type' => 'partial',
			'ttl' => 0,
			'cms_page_id' => $cms_page_id,
			'main_page_id' => $cms_page_id,
			'stem' => 'partial_cache_id_'.$cms_page_id,
			'access_restricted' => 0,
			'targets_hash' => $this->_targets_hash(),
			'has_deferred' => !empty($deferred_panels) ? 1 : 0,
			'deferred_panels' => array_values($deferred_panels),
		];

	}

}