<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Minimal host config for early boot (API branch).
 * No DB connect, no module graph, no settings SELECT.
 * Full site config: cms_config_load_full() in cms_config.php.
 */

if (!function_exists('cms_path')){

	function cms_path($key){

		if (empty($GLOBALS['config']['paths']['cache'])){
			$c = &$GLOBALS['config'];
			$slash = function ($p){
				$p = str_replace('\\', '/', (string)$p);
				return ($p === '') ? '' : rtrim($p, '/').'/';
			};
			$project = $slash($c['base_path'] ?? '');
			$dir = (isset($c['dir']) && is_array($c['dir'])) ? $c['dir'] : [];
			$base_raw = trim(str_replace('\\', '/', (string)($dir['base'] ?? '')));
			$parent = $project;
			if ($base_raw !== ''){
				$abs = ($base_raw[0] === '/' || preg_match('/^[A-Za-z]:/', $base_raw));
				$parent = $abs ? $slash($base_raw) : $slash($project.ltrim($base_raw, '/'));
			}
			$resolve = function ($raw, $default) use ($parent, $slash){
				$raw = trim(str_replace('\\', '/', (string)$raw));
				if ($raw === ''){
					$raw = $default;
				}
				if ($raw !== '' && ($raw[0] === '/' || preg_match('/^[A-Za-z]:/', $raw))){
					return $slash($raw);
				}

				return $slash($parent.ltrim($raw, '/'));
			};
			$cache = $resolve($dir['cache'] ?? '', 'cache');
			$tmp = $resolve($dir['tmp'] ?? '', 'tmp');
			$log = $resolve($dir['log'] ?? '', 'log');
			$session_raw = trim(str_replace('\\', '/', (string)($dir['session'] ?? '')));
			if ($session_raw === ''){
				$session = $tmp.'sessions/';
			} else if ($session_raw[0] === '/' || preg_match('/^[A-Za-z]:/', $session_raw)){
				$session = $slash($session_raw);
			} else {
				$session = $slash($parent.ltrim($session_raw, '/'));
			}
			$upload_raw = trim(str_replace('\\', '/', (string)($dir['upload'] ?? 'img/')));
			if ($upload_raw === ''){
				$upload_raw = 'img/';
			}
			$upload = ($upload_raw[0] === '/' || preg_match('/^[A-Za-z]:/', $upload_raw))
					? $slash($upload_raw) : $slash($parent.ltrim($upload_raw, '/'));
			$cache_url = 'cache/';
			if ($project !== '' && strpos($cache, $project) === 0){
				$cache_url = substr($cache, strlen($project));
			}
			$c['paths'] = [
					'cache' => $cache,
					'tmp' => $tmp,
					'log' => $log,
					'session' => $session,
					'upload' => $upload,
					'error_log' => $log.'error.log',
			];
			$c['paths_url'] = ['cache' => $cache_url];
		}

		$val = $GLOBALS['config']['paths'][$key] ?? '';
		if ($key === 'error_log'){
			return str_replace('\\', '/', (string)$val);
		}
		$val = str_replace('\\', '/', (string)$val);

		return ($val === '') ? '' : rtrim($val, '/').'/';

	}

	function cms_cache_rel($filename){

		$rel = str_replace('\\', '/', (string)($GLOBALS['config']['paths_url']['cache'] ?? 'cache/'));
		$rel = trim($rel, '/');

		return $rel.'/'.ltrim(str_replace('\\', '/', (string)$filename), '/');

	}

	function cms_path_url($key){

		if ($key !== 'cache'){
			return '';
		}
		$rel = str_replace('\\', '/', (string)($GLOBALS['config']['paths_url']['cache'] ?? 'cache/'));
		$rel = trim($rel, '/');
		$base_url = (string)($GLOBALS['config']['base_url'] ?? '/');
		if ($base_url === '' || $base_url === '/'){
			return '/'.$rel.'/';
		}

		return rtrim($base_url, '/').'/'.$rel.'/';

	}

}

if (defined('CMS_INSTALL_SCHEMA') && CMS_INSTALL_SCHEMA){
	return;
}

// static system config (CI heritage, deprecated)
$config['system']['charset'] = 'UTF-8';
$config['system']['log_path'] = '';
$config['system']['log_date_format'] = 'Y-m-d H:i:s';

if (empty($working_directory)){
	$working_directory = str_replace('\\', '/', trim(getcwd()).'/');
}

// Early helpers (BASEPATH is set in index.php before cms.php)
require_once($working_directory.'system/helpers/json_helper.php');
require_once($working_directory.'system/helpers/error_helper.php');
require_once($working_directory.'system/helpers/string_helper.php');

/*
 * LOAD HOST CONFIG
 */

$config['config_file'] = $working_directory.'config/'.strtolower($_SERVER['SERVER_NAME']).'.json';

if (file_exists($config['config_file'])){

	$json = file_get_contents($config['config_file']);
	$config_file = cms_json_decode($json, $config['config_file']);

	if (empty($config_file)){
		die();
	}

	$config = array_merge($config, $config_file);

	if ($config['base_path'] == '_auto_'){
		$config['base_path'] = rtrim(str_replace("\\", "/", trim(getcwd(), " \\")), '/').'/';
	}

	$GLOBALS['config'] = $config;
	$config['upload_path'] = cms_path('upload');
	$config['session_path'] = rtrim(cms_path('session'), '/');
	$config['paths'] = $GLOBALS['config']['paths'] ?? [];
	$config['paths_url'] = $GLOBALS['config']['paths_url'] ?? ['cache' => 'cache/'];

	if (substr($config['upload_url'], 0, 2) !== '//' && substr($config['upload_url'], 0, 4) !== 'http'){
		$config['upload_url'] = $config['base_url'].$config['upload_url'];
	}

} else {

	$config['config_file'] = $working_directory.'config/'.strtolower($_SERVER['SERVER_NAME']).'.php';
	if (file_exists($config['config_file'])){

		include_once($config['config_file']);

		if (!file_exists($config['base_path'].'config/'.strtolower($_SERVER['SERVER_NAME']).'.php')){
			print('Bad config base path: "'.$config['base_path'].'"');
			die();
		}

	} else {

		if (file_exists($working_directory.'_install/install.php')){
			include($working_directory.'_install/install.php');
		} else {
			print('No config file for this host found: '.$working_directory.'config/'.strtolower($_SERVER['SERVER_NAME']).'.json or '.$config['config_file']);
		}

		die();

	}

}

if (empty($config['base_host'])){
	$config['base_host'] = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
			? 'https://' : 'http://' ).$_SERVER['HTTP_HOST'];
}

$config['protocol'] = (empty($_SERVER['HTTPS']) OR strtolower($_SERVER['HTTPS']) === 'off') ? 'http' : 'https';

$GLOBALS['config'] = $config;
if (empty($config['paths']['cache'])){
	$config['upload_path'] = cms_path('upload');
	$config['session_path'] = rtrim(cms_path('session'), '/');
	$config['paths'] = $GLOBALS['config']['paths'] ?? [];
	$config['paths_url'] = $GLOBALS['config']['paths_url'] ?? ['cache' => 'cache/'];
}

$GLOBALS['config'] = $config;
$GLOBALS['cms_config_full'] = false;
