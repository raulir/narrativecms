<?php

/**
 * CMS files (cms_file: file inputs, form uploads) as downloads: the only way to them. They are kept in the data
 * folder (dir.data), which the web server never serves.
 * URL: {base}cms/file/<year>/<month>/<name>  (cms_file_url(), _lf(), _lfd(), _lfs())
 * A file with a cms_user_id is sent only to that logged-in site user; the session starts only then.
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

require_once BASEPATH.'core/cms_config.php';
require_once BASEPATH.'core/cms_bootstrap.php';
require_once BASEPATH.'core/controller.php';

$cms_file_not_found = function(){
	http_response_code(404);
	header('Content-Type: text/plain; charset=UTF-8');
	header('X-Content-Type-Options: nosniff');
	print('File not found');
	exit();
};

$filename = rawurldecode((string)substr((string)($GLOBALS['cms_request_uri'] ?? ''), strlen('cms/file/')));

$ci = new Controller();
$ci->load->model('cms/cms_file_model');

$file = $filename !== '' ? $ci->cms_file_model->get_cms_file_by_filename($filename) : [];
$path = !empty($file['filename']) ? $ci->cms_file_model->stored_file_path($file['filename']) : '';

if ($path === ''){
	$cms_file_not_found();
}

if (!empty($file['cms_user_id'])){
	require_once BASEPATH.'core/session.php';
	if (session_status() !== PHP_SESSION_ACTIVE){
		cms_session_boot();
	}
	if ((int)($_SESSION['user']['user_id'] ?? 0) !== (int)$file['cms_user_id']){
		$cms_file_not_found();
	}
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'png' => 'image/png', 'gif' => 'image/gif', ];

header('Content-Disposition: attachment; filename="'.str_replace(['"', "\r", "\n"], '', $file['name']).'"');
header('Content-Type: '.($types[$extension] ?? 'application/octet-stream'));
header('Content-Length: '.filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($path);
