<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Early CMS route resolve (#105 / #343).
 * Load only after cms_config_load_full() (needs DB). Not used for module API boot.
 * Path normalize: system/core/cms_path.php (cms_request_path).
 *
 * Public pages: one PK lookup on cms_route (slug, target, status).
 * Reserved controllers: ajax_api, files, module controllers — no slug table.
 */

// cms_request_path() lives in cms_path.php (loaded before API branch)
// DB: $GLOBALS['db'] from cms_config_load_full() — no separate getter

/**
 * Look up visible public route target by slug (PRIMARY KEY on cms_route.slug).
 *
 * @return string|null full target or null
 */
function cms_route_lookup_slug($slug) {

	$slug = trim((string)$slug);
	if ($slug === '') {
		return null;
	}

	$sql = 'SELECT target FROM cms_route WHERE slug = ? AND status = 0 LIMIT 1';
	$stmt = mysqli_prepare($GLOBALS['db'], $sql);
	if ($stmt === false) {
		return null;
	}
	mysqli_stmt_bind_param($stmt, 's', $slug);
	if (!mysqli_stmt_execute($stmt)) {
		mysqli_stmt_close($stmt);
		return null;
	}
	$result = mysqli_stmt_get_result($stmt);
	$row = $result ? mysqli_fetch_assoc($result) : null;
	mysqli_stmt_close($stmt);

	if (!empty($row['target'])) {
		return (string)$row['target'];
	}

	return null;

}

/**
 * Whether first URI segment is a known HTTP controller (system or module).
 */
function cms_route_is_controller_segment($name) {

	$name = str_replace(array('/', '.'), '', (string)$name);
	if ($name === '') {
		return false;
	}

	$base = $GLOBALS['config']['base_path'] ?? '';

	if (file_exists($base.'system/core/controller_'.$name.'.php')) {
		return true;
	}

	if (empty($GLOBALS['config']['modules']) || !is_array($GLOBALS['config']['modules'])) {
		return false;
	}

	foreach ($GLOBALS['config']['modules'] as $module) {
		if (file_exists($base.'modules/'.$module.'/controllers/'.$name.'.php')) {
			return true;
		}
	}

	return false;

}

/**
 * Resolve path to a structured route for dispatch.
 *
 * @param string $path cms_request_path() style
 * @return array
 */
function cms_route_resolve($path) {

	$path = trim((string)$path, '/');

	$empty = array(
		'kind' => 'not_found',
		'slug' => '',
		'target' => '',
		'controller' => 'index',
		'method' => 'index',
		'args' => array(),
	);

	// Home
	if ($path === '') {
		$page_id = !empty($GLOBALS['config']['landing_page']['_value'])
			? $GLOBALS['config']['landing_page']['_value']
			: '1';
		return array(
			'kind' => 'home',
			'slug' => '',
			'target' => (string)$page_id,
			'controller' => 'index',
			'method' => 'index',
			'args' => array($page_id),
		);
	}

	$segments = explode('/', $path);
	$first = $segments[0];

	// Explicit index/index/{target...} (internal / legacy)
	if ($first === 'index' && isset($segments[1]) && $segments[1] === 'index') {
		$target = implode('/', array_slice($segments, 2));
		$target = trim($target, '/');
		if ($target === '') {
			$page_id = !empty($GLOBALS['config']['landing_page']['_value'])
				? $GLOBALS['config']['landing_page']['_value']
				: '1';
			$target = (string)$page_id;
		}
		$kind = (strpos($target, '=') !== false) ? 'list_item' : 'page';
		return array(
			'kind' => $kind,
			'slug' => '',
			'target' => $target,
			'controller' => 'index',
			'method' => 'index',
			'args' => array($target),
		);
	}

	// Fixed front controllers
	if ($first === 'ajax_api') {
		return array(
			'kind' => 'ajax',
			'slug' => '',
			'target' => '',
			'controller' => 'ajax_api',
			'method' => isset($segments[1]) ? $segments[1] : 'get_panel',
			'args' => array_slice($segments, 2),
		);
	}

	if ($first === 'files') {
		return array(
			'kind' => 'files',
			'slug' => '',
			'target' => '',
			'controller' => 'files',
			'method' => isset($segments[1]) ? $segments[1] : 'get',
			'args' => array_slice($segments, 2),
		);
	}

	// Module / system controller by first segment (admin, cms_login, form, …)
	if (cms_route_is_controller_segment($first) && $first !== 'index') {
		return array(
			'kind' => 'module_controller',
			'slug' => '',
			'target' => '',
			'controller' => $first,
			'method' => isset($segments[1]) ? $segments[1] : 'index',
			'args' => array_slice($segments, 2),
		);
	}

	// Public slug: single path segment only (cms_route.slug has no slashes)
	if (count($segments) === 1) {
		$target = cms_route_lookup_slug($first);
		if ($target !== null && $target !== '') {
			$kind = (strpos($target, '=') !== false) ? 'list_item' : 'page';
			return array(
				'kind' => $kind,
				'slug' => $first,
				'target' => $target,
				'controller' => 'index',
				'method' => 'index',
				'args' => array($target),
			);
		}
		$empty['slug'] = $first;
		return $empty;
	}

	// Multi-segment, not a controller → not found
	$empty['slug'] = $path;
	return $empty;

}
