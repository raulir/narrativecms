<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['base_path'] = str_replace("\\", "/", rtrim(getcwd(), " /\\")).'/';
$config['base_url']	= '/';
$config['upload_path'] = $config['base_path'].'img/';
$config['upload_url'] = $config['base_url'].'img/';
$config['errors_visible'] = 0;
$config['errors_log'] = 'cache/errors_cms.log';
$config['analytics'] = 0;

$config['cache']['force_download'] = 0;
$config['cache']['pack_js'] = 1;
$config['cache']['pack_css'] = 1;
$config['cache']['vcs_check'] = 'git'; // ''|'git'|'svn'

$config['inline_css'] = 1;

$config['update']['is_master'] = 1;
$config['update']['allow'] = ['*'];
$config['update']['master'] = [
		'',
		'acfsync',
		'basic',
		'carousel',
		'documents',
		'download',
		'emailer',
		'faq',
		'feed',
		'form',
		'gdpr',
		'lightbox',
		'localisation',
		'news',
		'onetrust',
		'search',
		'shop',
		'shopify',
		'stock',
		'stripe',
		'user',
		'video',
		'weather'
];
$config['update']['source'] = [
		'',
		'acfsync',
		'basic',
		'carousel',
		'documents',
		'download',
		'emailer',
		'faq',
		'feed',
		'form',
		'gdpr',
		'lightbox',
		'localisation',
		'news',
		'onetrust',
		'search',
		'shop',
		'shopify',
		'stock',
		'stripe',
		'user',
		'video',
		'weather'
];

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

// depending of availability: none - '', PHP - 'gd', Google cwebp - 'cwebp'
$config['images_webp'] = 'gd'; 

$config['database']['hostname'] = '127.0.0.1';
$config['database']['username'] = 'cms';
$config['database']['password'] = '3YGkfAFf';
$config['database']['database'] = 'cms';
$config['database']['dbdriver'] = 'mysqli';

// what modules to load
$config['modules'] = array('cms', 'cms_content', );

// admin superuser password
$config['admin_username'] = 'cms';
$config['admin_password'] = '1C2ywmqn';
