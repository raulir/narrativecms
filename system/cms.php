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
				'script' => 'modules/cms/js/jquery/jquery-3.3.1.min.js',
				'no_pack' => 1,
				'sync' => (!empty($config['jquery_blocks']) ? '' : 'defer'),
		),
		array(
				'script' => 'modules/cms/js/jquery/jquery-ui.min.js',
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

// load config from db
$db = $GLOBALS['dbconnections'][md5($GLOBALS['config']['database']['hostname'].$GLOBALS['config']['database']['username'].$GLOBALS['config']['database']['password'].$config['database']['database'])];

if ($db === false){
	print('Can\'t connect database!');
	die();
}

$sql = "select b.name, b.value from cms_page_panel a join cms_page_panel_param b on a.cms_page_panel_id = b.cms_page_panel_id where (a.panel_name = 'cms_settings' or a.panel_name = 'cms/cms_settings') and b.name != ''";
$query = mysqli_query($db, $sql);

while($result = mysqli_fetch_assoc($query)){
	$GLOBALS['config'][$result['name']] = $result['value'];
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

// router - check if landing page and landing page set
if (empty($GLOBALS['config']['landing_page._value'])){
	$GLOBALS['config']['landing_page._value'] = '1';
	$GLOBALS['config']['landing_page.url'] = '/';
}

// if landing page by slug
$landing_uri = trim($GLOBALS['config']['landing_page.url'], '/');
if (!empty($landing_uri) && $landing_uri === $request_uri){
	header('Location: //'.$_SERVER['HTTP_HOST'].'/'.ltrim($GLOBALS['config']['base_url'], '/'), true, 307);
	exit();
}

if (!empty($GLOBALS['config']['landing_page._value']) && empty($request_uri)){
	$landing_route = '/index/'.$GLOBALS['config']['landing_page._value'];
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

// check for visitor target groups
if (!empty($GLOBALS['config']['targets_enabled'])){
	
	include($GLOBALS['config']['base_path'].'system/core/targets.php');
	
}

require_once BASEPATH.'core/CodeIgniter.php';
