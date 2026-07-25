<?php

/**
 * Dynamic sitemap from cms_route (file cache + short HTTP max-age).
 * URL: {base}cms/sitemap  and  /sitemap.xml via .htaccess rewrite
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

require_once BASEPATH.'core/cms_config.php';
require_once BASEPATH.'core/cms_bootstrap.php';
require_once BASEPATH.'core/controller.php';

$ci = new Controller();
$ci->load->model('cms/cms_slug_model');

$xml = $ci->cms_slug_model->get_sitemap_xml_cached();
$ttl = $ci->cms_slug_model->sitemap_cache_ttl();

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age='.(int)$ttl);
header('X-Content-Type-Options: nosniff');

print($xml);
