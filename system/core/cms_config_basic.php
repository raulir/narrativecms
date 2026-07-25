<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Minimal host config for early boot (API branch).
 * No DB connect, no module graph, no settings SELECT.
 * Full site config: cms_config_load_full() in cms_config.php.
 */

// static system config (CI heritage, deprecated)
$config['system']['charset'] = 'UTF-8';
$config['system']['log_path'] = '';
$config['system']['log_date_format'] = 'Y-m-d H:i:s';

if (empty($working_directory)){
	$working_directory = str_replace('\\', '/', trim(getcwd()).'/');
}

require_once($working_directory.'system/helpers/json_helper.php');

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

	if (substr($config['upload_path'], 0, 1) !== '/' && substr($config['upload_path'], 1, 1) !== ':'){
		$config['upload_path'] = $config['base_path'].$config['upload_path'];
	}

	if (substr($config['upload_url'], 0, 2) !== '//' && substr($config['upload_path'], 1, 4) !== 'http'){
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
$GLOBALS['cms_config_full'] = false;
