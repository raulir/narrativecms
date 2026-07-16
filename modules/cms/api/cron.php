<?php

/**
 * Public cron trigger — session-free module API (system/cms.php early include).
 * URL: {base}cms/cron/
 * Crontab or visit-triggered JS (cms_cron_run.js).
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

// Main request controller so models / run_panel_method work (no session — cms.php dies after API)
$ci = new Controller();
$ci->load->model('cms/cms_helper_model');
$ci->cms_helper_model->run_cron();
