<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

function _html_error($error, $exit = 0, $extra = []){

	if (is_array($exit)){
		$extra = $exit;
		$exit = 0;
	}

	if (!empty($extra['exit'])){
		$exit = $extra['exit'];
	}

	$formatted = str_replace(['#br#', '#b#', '#bb#'], ['<br>', '<b>', '</b>'],
			htmlentities(str_replace(['<br>', '<b>', '</b>'], ['#br#', '#b#', '#bb#'], $error)));

	if (empty($extra['location'])){
		$backtrace = debug_backtrace();
		if (empty($extra['backtrace'])){
			$extra['backtrace'] = 0;
		}
		$extra['location'] = basename($backtrace[$extra['backtrace']]['file']).':'.$backtrace[$extra['backtrace']]['line'];
	}

	$return = ('<pre style="background-color: white; color: black; display: block; border: 0.1rem solid red; white-space: normal; '.
			'font-size: 0.8rem; line-height: 0.9rem; letter-spacing: 0; font-family: monospace; text-align: left; ">');
	$return .= ('<div style="line-height: 0.6rem;  padding: 0.4rem; color: red; font-weight: bold; ">'.
			strtoupper($extra['location']??'').'</div><div style="padding: 0.6rem 1.0rem; ">');

	$return .= ($formatted);
	$return .= ('</div></pre>');

	if(empty($extra['silent']) && (!empty($GLOBALS['config']['errors_visible']) || empty($GLOBALS['config']['base_path']))){
		print($return);
	}

	if ($exit){
		set_status_header($exit);
		exit();
	}

	return $return;

}

// --- Light boot: host config only (no DB, no modules, no router) ---
$working_directory = str_replace('\\', '/', trim(getcwd()).'/');
require_once $working_directory.'system/core/cms_config_basic.php';
require_once $GLOBALS['config']['base_path'].'system/core/cms_path.php';

if (!empty($GLOBALS['config']['allow_api_anywhere'])){
	header('Access-Control-Allow-Origin: *');
}

$request_uri = cms_request_path();
$GLOBALS['cms_request_uri'] = $request_uri;

// Module API short-circuit — before full config / session / cms_router
// Order: API file must exist, then module config.json must list that api id
if (strpos($request_uri, '/') !== false){

	list($module, $api) = explode('/', $request_uri, 2);
	// api id may include subpaths in theory; only first segment for file name
	if (strpos($api, '/') !== false){
		$api = explode('/', $api, 2)[0];
	}

	if (preg_match('/^[a-z0-9_]+$/i', $module) && preg_match('/^[a-z0-9_]+$/i', $api)){

		$base = $GLOBALS['config']['base_path'];
		$api_file = $base.'modules/'.$module.'/api/'.$api.'.php';
		$mod_json = $base.'modules/'.$module.'/config.json';

		// 1) file exists  2) config.json exists and declares this api id
		if (is_file($api_file) && is_file($mod_json)){

			$mod_cfg = json_decode(file_get_contents($mod_json), true);
			$api_ok = false;
			if (is_array($mod_cfg) && !empty($mod_cfg['api']) && is_array($mod_cfg['api'])){
				foreach ($mod_cfg['api'] as $capi){
					if (!empty($capi['id']) && $capi['id'] === $api){
						$api_ok = true;
						break;
					}
				}
			}

			if ($api_ok){
				include $api_file;
				die();
			}

		}

	}

}

// --- Front path: full config (DB + modules) then resolve ---
require_once $GLOBALS['config']['base_path'].'system/core/cms_config.php';
require_once $GLOBALS['config']['base_path'].'system/core/cms_router.php';

// Front/router requests only — module API includes exit above with normal fatals
require_once $GLOBALS['config']['base_path'].'system/helpers/error_helper.php';
cms_register_timeout_shutdown();

// Landing page settings
if (empty($GLOBALS['config']['landing_page']['_value'])){
	$GLOBALS['config']['landing_page']['_value'] = '1';
	$GLOBALS['config']['landing_page']['url'] = '/';
}

// Landing page by custom slug → redirect to site root
$landing_uri = trim($GLOBALS['config']['landing_page']['url'], '/');
if (!empty($landing_uri) && $landing_uri === $request_uri){
	header('Location: //'.$_SERVER['HTTP_HOST'].'/'.ltrim($GLOBALS['config']['base_url'], '/'), true, 307);
	exit();
}

// Early route resolve (DB PK for public slugs; no session) — #105 / #343
$GLOBALS['cms_route'] = cms_route_resolve($request_uri);

// Legacy CI default-controller string (Router still uses until Phase 3 dispatch)
if (!empty($GLOBALS['config']['landing_page']['_value']) && $request_uri === ''){
	$landing_route = '/index/'.$GLOBALS['config']['landing_page']['_value'];
} else {
	$landing_route = '';
}

// check if cron needs to run
if (!empty($GLOBALS['config']['cron_trigger']) && $GLOBALS['config']['cron_trigger'] == 'visits'){

	$cron_data_filename = $GLOBALS['config']['base_path'].'cache/cron.json';
	if (!file_exists($cron_data_filename) || (time() - filemtime($cron_data_filename)) >= 240){
		$GLOBALS['config']['js'][] = ['script' => 'modules/cms/js/cms_cron_run.js', 'sync' => 'defer', ];
	}

}

// start session
include($GLOBALS['config']['base_path'].'system/core/session.php');

// Visitor targets (business: AB / language / mobile…) — after session; uses global $db from full config
$_SESSION['config']['targets']['hash'] = '';
if (!empty($GLOBALS['config']['targets_enabled'])){

	include($GLOBALS['config']['base_path'].'system/core/targets.php');

}

if (is_file($GLOBALS['config']['base_path'].'cache/page_cache_registry.json')
		&& $_SERVER['REQUEST_METHOD'] === 'GET'
		&& empty($_POST)
		&& empty($_REQUEST['_ajax'])
		&& empty($_SESSION['cms_user']['cms_user_id'])
		&& empty($GLOBALS['config']['cache']['force_download'])) {
	require_once($GLOBALS['config']['base_path'].'system/helpers/json_helper.php');
	require_once($GLOBALS['config']['base_path'].'system/libraries/cache.php');
	(new cache())->try_serve();
}

require_once BASEPATH.'core/CodeIgniter.php';
