<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/*
 * LOAD CONFIG
 *
 * Filename for example for dev site hosted at http://project.localhost/
 * should be project.localhost.php
 *
 * Change contents of the file according your project
 *
 */

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
				'script' => 'modules/cms/js/cms_site_main.js',
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
	
	if (!stristr($result['name'], '.')){
		$GLOBALS['config'][$result['name']] = $result['value'];
	} else {
		list($ra, $rb) = explode('.', $result['name']);
		$GLOBALS['config'][$ra][$rb] = $result['value'];
	}
	
}

// load module configs
if (empty($GLOBALS['config']['modules']) || !is_array($GLOBALS['config']['modules'])){
	$GLOBALS['config']['modules'] = ['cms'];
}

array_unshift($GLOBALS['config']['modules'], 'cms');

$GLOBALS['config']['modules'] = array_values(array_unique($GLOBALS['config']['modules']));

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