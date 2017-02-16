<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['base_path'] = str_replace("\\", "/", trim(getcwd(), " /\\")).'/';
$config['base_url']	= '/';
$config['upload_path'] = $config['base_path'].'img/';
$config['upload_url'] = $config['base_url'].'img/';
$config['environment'] = 'DEV';
$config['title'] = (!empty($config['environment']) ? '['.$config['environment'].'] ' : '').'CMS';
$config['errors_visible'] = 1;
$config['analytics'] = 0;
$config['cache']['force_download'] = 1;
$config['cache']['pack_js'] = 0;
$config['cache']['pack_css'] = 0;
$config['update']['allow_updates'] = 1;

$config['database']['hostname'] = 'localhost';
$config['database']['username'] = 'root';
$config['database']['password'] = '';
$config['database']['database'] = 'cms';
$config['database']['dbdriver'] = 'mysqli';

// what modules to load
$config['modules'] = array('cms', 'cms_content', );

// admin superuser password
$config['admin_username'] = 'cms';
$config['admin_password'] = 'cms';

// static panels - position => panel_name
$config['static_panels'] = array(
);
