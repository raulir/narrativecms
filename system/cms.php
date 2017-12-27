<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// all the cms special stuff goes here

/*
 * LOAD CONFIG
 *
 * Filename for example for dev site hosted at http://project.dev/
 * should be project.dev.php
 *
 * Change contents of the file according your project
 *
 */

$working_directory = str_replace('\\', '/', trim(getcwd()).'/');
$config['config_file'] = $working_directory.'config/'.strtolower($_SERVER['SERVER_NAME']).'.php';

if (file_exists($config['config_file'])){

	// if config file for host exists, load config file
	include_once($config['config_file']);

} else if (file_exists($working_directory.'config/www.'.strtolower($_SERVER['SERVER_NAME']).'.php')) {

	// if config file with www exists, go to site with www
	header(trim('Location: http://www.'.strtolower($_SERVER['SERVER_NAME']).'/'.$_SERVER['REQUEST_URI'], '/ '));
	die();

} else {

	print('No config file for this host found: '.$config['config_file']);
	die();

}

// check if base_path is set correctly
if (!file_exists($config['base_path'].'config/'.strtolower($_SERVER['SERVER_NAME']).'.php')){
	print('Bad config base path: "'.$config['base_path'].'"');
	die();
}

/*
 * common config for all project environments:
 */

// what css and js to load on all pages
// TODO: to be refactored like css to cms cssjs settings
$config['js'] = array(
		array(
				'script' => 'js/jquery-3.2.1.min.js',
				'no_pack' => 1,
				'sync' => (!empty($config['jquery_blocks']) ? '' : 'defer'),
		),
		array(
				'script' => 'js/md5.js', // for caching in panels.js
				'sync' => 'defer',
		),
		array(
				'script' => 'js/panels.js',
				'sync' => 'defer',
		),
		array(
				'script' => 'js/main.js',
				'sync' => 'defer',
		),
);

$config['protocol'] = (empty($_SERVER['HTTPS']) OR strtolower($_SERVER['HTTPS']) === 'off') ? 'http' : 'https';

$GLOBALS['config'] = $config;

// connect to db (if mysqli)
if ($GLOBALS['config']['database']['dbdriver'] = 'mysqli') {
	$conn_hash = md5($GLOBALS['config']['database']['hostname'].$GLOBALS['config']['database']['username'].$GLOBALS['config']['database']['password'].$GLOBALS['config']['database']['database']);
	$GLOBALS['dbconnections'][$conn_hash] = @mysqli_connect($GLOBALS['config']['database']['hostname'], $GLOBALS['config']['database']['username'], $GLOBALS['config']['database']['password'], $GLOBALS['config']['database']['database']);
}

// load module configs
if (!is_array($GLOBALS['config']['modules'])){
	$GLOBALS['config']['modules'] = ['cms'];
} elseif (!in_array('cms', $GLOBALS['config']['modules'])){
	array_unshift($GLOBALS['config']['modules'], 'cms');
}

foreach($GLOBALS['config']['modules'] as $module_name){
	
	$filename = $GLOBALS['config']['base_path'].'modules/'.$module_name.'/config.json';
	if (file_exists($filename)){
		$GLOBALS['config']['module'][$module_name] = json_decode(file_get_contents($filename), true);
	} else {
		$GLOBALS['config']['module'][$module_name] = [];
	}
	
	if (empty($GLOBALS['config']['module'][$module_name]['panels'])){
		$GLOBALS['config']['module'][$module_name]['panels'] = [];
	}
	
}

// check if api call
if (substr($_SERVER['REQUEST_URI'], 0, strlen($GLOBALS['config']['base_url'])) == $GLOBALS['config']['base_url']) {
	$string = substr($_SERVER['REQUEST_URI'], strlen($GLOBALS['config']['base_url']));
} else {
	$string = $_SERVER['REQUEST_URI'];
}

$request_uri = trim($string, '/');

if (stristr($request_uri, '/')){
	
	list($module, $api) = explode('/', $request_uri, 2);
	
	if (!empty($GLOBALS['config']['module'][$module]['api'])){
	
		foreach($GLOBALS['config']['module'][$module]['api'] as $capi){
			if ($capi['id'] == $api){
				
				include($GLOBALS['config']['base_path'].'modules/'.$module.'/api/'.$api.'.php');
				die();
				
			}
		}
	
	}
	
}

require_once BASEPATH.'core/CodeIgniter.php';
