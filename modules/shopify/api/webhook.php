<?php

/**
 * Shopify HTTPS webhooks (orders/create, orders/paid, orders/updated).
 * URL: {base}shopify/webhook/
 * Session-free. HMAC before DB. Every POST is appended to cache/webhook_debug.log.
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

function shopify_webhook_header($name){

	$key = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
	if (!empty($_SERVER[$key])){
		return (string)$_SERVER[$key];
	}
	if (function_exists('getallheaders')){
		foreach (getallheaders() as $header_name => $value){
			if (strcasecmp($header_name, $name) === 0){
				return (string)$value;
			}
		}
	}

	return '';

}

function shopify_webhook_debug_log($meta, $body){

	$base = (string)($GLOBALS['config']['base_path'] ?? '');
	if ($base === ''){
		error_log_user('CMS error [shopify/webhook]: no base_path for webhook_debug.log');
		return;
	}

	$dir = $base.'cache';
	if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)){
		error_log_user('CMS error [shopify/webhook]: cannot create cache/ for webhook_debug.log');
		return;
	}

	$block = date('Y-m-d H:i:s').' '.$meta."\n".$body."\n\n";
	$ok = @file_put_contents($dir.'/webhook_debug.log', $block, FILE_APPEND | LOCK_EX);
	if ($ok === false){
		error_log_user('CMS error [shopify/webhook]: failed to write cache/webhook_debug.log');
	}

}

$raw = file_get_contents('php://input');
if ($raw === false) {
	$raw = '';
}

$hmac = trim(shopify_webhook_header('X-Shopify-Hmac-Sha256'));
$topic = strtolower(shopify_webhook_header('X-Shopify-Topic'));
$shop = shopify_webhook_header('X-Shopify-Shop-Domain');
$webhook_id = shopify_webhook_header('X-Shopify-Webhook-Id');

$secrets = [];
$webhook_secret = trim((string)($GLOBALS['config']['shopify_webhook_secret'] ?? ''));
$api_secret = trim((string)($GLOBALS['config']['shopify_api_secret'] ?? ''));
if ($webhook_secret !== '') {
	$secrets[] = $webhook_secret;
}
if ($api_secret !== '' && $api_secret !== $webhook_secret) {
	$secrets[] = $api_secret;
}

$hmac_ok = false;
if ($hmac !== '') {
	foreach ($secrets as $secret) {
		$calculated = base64_encode(hash_hmac('sha256', $raw, $secret, true));
		if (strlen($calculated) === strlen($hmac) && hash_equals($calculated, $hmac)) {
			$hmac_ok = true;
			break;
		}
	}
}

$topic_norm = strtolower(str_replace('_', '/', $topic));
$meta = 'topic='.($topic !== '' ? $topic : '-').
		' shop='.($shop !== '' ? $shop : '-').
		' hmac='.($hmac_ok ? 'ok' : 'fail').
		' webhook_id='.($webhook_id !== '' ? $webhook_id : '-').
		' bytes='.strlen($raw);
shopify_webhook_debug_log($meta, $raw);

if (!$hmac_ok) {
	error_log_user('CMS error [shopify/webhook]: hmac mismatch or missing secret');
	http_response_code(401);
	exit();
}

$topic = $topic_norm;
$allowed = [
		'orders/create' => 1,
		'orders/paid' => 1,
		'orders/updated' => 1,
];
if (empty($allowed[$topic])) {
	http_response_code(200);
	header('Content-Type: text/plain; charset=utf-8');
	print('ok');
	exit();
}

require_once BASEPATH.'core/cms_config.php';
require_once BASEPATH.'core/cms_bootstrap.php';
require_once BASEPATH.'core/controller.php';

$payload = json_decode($raw, true);
if (!is_array($payload)) {
	error_log_user('CMS error [shopify/webhook]: invalid json');
	http_response_code(400);
	exit();
}

$ci = new Controller();
$ci->load->model('shopify/shopify_order_model');
$ci->shopify_order_model->apply_webhook_order($payload, $topic);

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
print('ok');
