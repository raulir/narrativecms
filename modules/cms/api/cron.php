<?php

/**
 * Public cron trigger — session-free module API (system/cms.php early include).
 * URL: {base}cms/cron/
 * Crontab or visit-triggered JS (cms_cron_run.js).
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

if (!function_exists('load_class')) {
	require BASEPATH.'core/Common.php';
}

if (!class_exists('Controller', false)) {
	require BASEPATH.'core/controller.php';
}

// Main request controller so models / run_panel_method work (no session — cms.php dies after API)
$ci = new Controller();
$ci->load->model('cms/cms_helper_model');
$ci->cms_helper_model->run_cron();
