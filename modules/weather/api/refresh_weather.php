<?php

/**
 * Background weather poll — session-free module API.
 * URL: {base}weather/refresh_weather
 */

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

require_once BASEPATH.'core/cms_config.php';
require_once BASEPATH.'core/cms_bootstrap.php';
require_once BASEPATH.'core/controller.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$ci = new Controller();
$ci->load->model('cms/cms_page_panel_model');
$ci->load->model('weather/weather_model');

$panels = $ci->cms_page_panel_model->get_cms_page_panels_by([
	'panel_name' => 'weather/weather',
	'show' => 1,
]);
if (empty($panels) || !is_array($panels)){
	$panels = $ci->cms_page_panel_model->get_cms_page_panels_by([
		'panel_name' => 'weather/weather',
	]);
}

$location = 'Cheltenham';
$lat = '51.88964722230162';
$lon = '-2.1204895339274916';
$provider = null;

if (!empty($panels) && is_array($panels)){
	$panel = reset($panels);
	if (is_array($panel)){
		if (!empty($panel['location'])){
			$location = $panel['location'];
		}
		if (isset($panel['latitude']) && $panel['latitude'] !== ''){
			$lat = $panel['latitude'];
		}
		if (isset($panel['longitude']) && $panel['longitude'] !== ''){
			$lon = $panel['longitude'];
		}
		if (!empty($panel['weather_provider'])){
			$provider = $panel['weather_provider'];
		}
	}
}

// Normal FE poll: honour provider cache_minutes (network only when TTL expired).
// force=1 bypasses TTL (dev / hard reload) — same pattern as energy/refresh_energy.
$force = !empty($_GET['force']) || !empty($_POST['force']);

try {
	$result = $ci->weather_model->get_forecast($lat, $lon, $location, $force, $provider);
} catch (\Throwable $e) {
	print(json_encode([
		'ok' => 0,
		'error' => 'Weather refresh failed',
		'days' => [],
		'later_days' => [],
		'hours_by_date' => new stdClass(),
	], JSON_UNESCAPED_UNICODE));
	exit;
}

// hours_by_date as object for JSON
$hours = !empty($result['hours_by_date']) ? $result['hours_by_date'] : new stdClass();
if (is_array($hours) && empty($hours)){
	$hours = new stdClass();
}

print(json_encode([
	'ok' => !empty($result['ok']) ? 1 : 0,
	'error' => !empty($result['error']) ? $result['error'] : '',
	'location' => !empty($result['location']) ? $result['location'] : $location,
	'fetched_at' => !empty($result['fetched_at']) ? (int)$result['fetched_at'] : 0,
	'source' => !empty($result['source']) ? $result['source'] : '',
	'days' => !empty($result['days']) ? $result['days'] : [],
	'later_days' => !empty($result['later_days']) ? $result['later_days'] : [],
	'hours_by_date' => $hours,
], JSON_UNESCAPED_UNICODE));
