<?php

/**
 * Public cron trigger — session-free module API (system/cms.php early include).
 * URL: {base}cms/cron/
 * Crontab or visit-triggered JS (cms_cron_run.js).
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

// Light boot left DB/modules unloaded
require_once BASEPATH.'core/cms_config.php';

// Bootstrap without CodeIgniter.php (cms.php early API die)
require_once BASEPATH.'core/cms_bootstrap.php';
require_once BASEPATH.'core/controller.php';

// Main request controller so models / run_panel_method work (no session — cms.php dies after API)
$ci = new Controller();
$ci->load->model('cms/cms_helper_model');
$ci->cms_helper_model->run_cron();
