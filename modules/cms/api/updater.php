<?php

/**
 * Public master updater protocol — session-free module API.
 * URL: {base}cms/updater/  (default cms_update_url)
 *
 * POST do=version|modules|files|file
 * do=file: filename=… (legacy) or filenames[]=… (batch)
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

// Bootstrap without CodeIgniter.php (cms.php early API die)
require_once BASEPATH.'core/Common.php';
require_once BASEPATH.'core/controller.php';

// Not really needed — get_instance() lives in Common.php / controller.php.
// Only for bracing partial deploys / stale OPcache; deprecated, remove later.
if (!function_exists('get_instance')) {
	function &get_instance() {
		return Controller::get_instance();
	}
}

$ci = new Controller();
$ci->load->model('cms/cms_update_model');

$do = isset($_POST['do']) ? (string)$_POST['do'] : '';
$module = '';
if (isset($_POST['module'])) {
	$module = $_POST['module'];
} else if (isset($_POST['area'])) {
	$module = $_POST['area'];
}
// Empty string = core package
if ($module === null) {
	$module = '';
}

header('Content-Type: application/json; charset=utf-8');

if ($do === 'version') {

	print(json_encode($ci->cms_update_model->get_release_version($module)));
	exit();

}

if ($do === 'modules') {

	print(json_encode([
			'modules' => $ci->cms_update_model->get_publishable_modules(),
	]));
	exit();

}

if ($do === 'files') {

	print(json_encode($ci->cms_update_model->get_files($module)));
	exit();

}

if ($do === 'file') {

	$filenames = null;
	if (isset($_POST['filenames'])) {
		$raw = $_POST['filenames'];
		if (is_array($raw)) {
			$filenames = $raw;
		} else if (is_string($raw) && $raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				$filenames = $decoded;
			}
		}
	}

	if (is_array($filenames) && count($filenames) > 0) {

		// Batch mode
		print(json_encode($ci->cms_update_model->get_files_content($filenames, $module)));
		exit();

	}

	$filename = isset($_POST['filename']) ? (string)$_POST['filename'] : '';
	if ($filename === '') {
		print(json_encode([
				'file' => '',
				'error' => 'Missing filename',
		]));
		exit();
	}

	// Legacy single-file response
	print(json_encode($ci->cms_update_model->get_file($filename, $module)));
	exit();

}

print(json_encode([
		'error' => 'Unknown do',
]));
exit();
