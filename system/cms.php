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

/*
 * common config for all project environments:
 */

// what css and js to load on all pages
// TODO: to be refactored like css to cms cssjs settings
$config['js'] = array(
		array(
				'script' => 'js/jquery-3.2.1.min.js',
				'no_pack' => 1,
				'sync' => 'defer',		// removed - may be needed on page while loading
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
if ($config['database']['dbdriver'] = 'mysqli') {
	$conn_hash = md5($config['database']['hostname'].$config['database']['username'].$config['database']['password'].$config['database']['database']);
	$GLOBALS['dbconnections'][$conn_hash] = @mysqli_connect($config['database']['hostname'], $config['database']['username'], $config['database']['password'], $config['database']['database']);
}

require_once BASEPATH.'core/CodeIgniter.php';
