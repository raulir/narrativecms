<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['base_path'] = rtrim(str_replace("\\", "/", trim(getcwd(), " \\")), '/').'/';
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

// Optimise PNG images using pngquant. Pngquant uses lossy compression to define edges and same colour areas. May affect image quality. May not be available in shared hosting.
$config['images_pngquant'] = 0;

// pngquant executable full path. If empty, uses application/libraries/pngquant/bin/pngquant.bin
// This file must be set to be executable! (chmod ugo+x application/libraries/pngquant/bin/pngquant.bin)
$config['images_pngquant_executable'] = '';

// Optimise PNG images using zopflipng. Zopflipng losslessly optimizes palette and structure of png and recompresses images.
// Additionally this removes all metadata and extra headers. May not be available in shared hosting
$config['images_zopflipng'] = 0;

// zopflipng executable full path. If empty, uses application/libraries/zopflipng/bin/zopflipng.bin
// This file must be set to be executable! (chmod ugo+x application/libraries/zopflipng/bin/zopflipng.bin)
$config['images_zopflipng_executable'] = '';

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
