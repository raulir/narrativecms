<?php

/**
 * Play real-time developer notifications.
 * URL: {base}google/notify/
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

require_once BASEPATH.'core/cms_config.php';
require_once BASEPATH.'core/cms_bootstrap.php';
require_once BASEPATH.'core/controller.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$raw = file_get_contents('php://input');
$body = json_decode((string)$raw, true);

$ci = new Controller();
$ci->load->model('google/google_model');
$result = $ci->google_model->handle_notification(is_array($body) ? $body : []);

print(json_encode($result));
exit();
