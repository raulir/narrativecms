<?php

/**
 * Stripe webhook module API.
 * URL: {base}stripe/webhook/
 *
 * Early short-circuit from cms.php (modules/stripe/api/webhook.php + config.json api id).
 * Not a page panel and not a first-segment module controller.
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

// Full config (DB + modules) — webhook secret + user meta
require_once BASEPATH.'core/cms_config.php';

// Bootstrap without CodeIgniter.php (cms.php API path dies after include)
require_once BASEPATH.'core/cms_bootstrap.php';
require_once BASEPATH.'core/controller.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$ci = new Controller();
$ci->load->model('stripe/stripe_model');
$ci->stripe_model->handle_webhook_request();
// handle_webhook_request() always exits
