<?php

require_once($GLOBALS['config']['base_path'].'modules/analytics/helpers/analytics_api_helper.php');

header('Content-Type: application/json');

if (!analytics_beacon_enabled()) {
	http_response_code(204);
	die();
}

if (analytics_is_bot()) {
	http_response_code(204);
	die();
}

$_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW) ?: array();

$do = $_POST['do'] ?? '';

if ($do === 'hit') {

	$page = $_POST['page'] ?? '/';
	if (empty($page)) {
		$page = '/';
	}

	$viewport_w = $_POST['viewport_w'] ?? 0;
	$viewport_h = $_POST['viewport_h'] ?? 0;

	$pageview_token = analytics_insert_pageview($page, $viewport_w, $viewport_h);

	if ($pageview_token === false) {
		http_response_code(204);
		die();
	}

	print(json_encode(array('result' => array('pageview_token' => $pageview_token))));
	die();

}

if ($do === 'heartbeat') {

	$pageview_token = $_POST['pageview_token'] ?? '';
	$seconds = $_POST['seconds'] ?? 0;
	$scroll_pct = $_POST['scroll_pct'] ?? 0;

	analytics_update_heartbeat($pageview_token, $seconds, $scroll_pct);

	http_response_code(204);
	die();

}

http_response_code(204);
die();