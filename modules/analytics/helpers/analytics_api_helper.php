<?php defined('BASEPATH') OR exit('No direct script access allowed');

function analytics_beacon_enabled() {

	if (!isset($GLOBALS['config']['beacon'])) {
		return true;
	}

	return !empty($GLOBALS['config']['beacon']);

}

function analytics_get_mysqli() {

	if (empty($GLOBALS['config']['database'])) {
		return false;
	}

	$conn_hash = md5($GLOBALS['config']['database']['hostname'].$GLOBALS['config']['database']['username'].
			$GLOBALS['config']['database']['password'].$GLOBALS['config']['database']['database']);

	if (!empty($GLOBALS['dbconnections'][$conn_hash])) {
		return $GLOBALS['dbconnections'][$conn_hash];
	}

	return false;

}

function analytics_is_bot() {

	if (empty($_SERVER['HTTP_USER_AGENT'])) {
		return true;
	}

	$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

	$bot_keywords = array('bot','crawl','spider','slurp','googlebot','bingbot','baiduspider','duckduckbot','yandex',
			'facebookexternalhit','twitterbot','rogerbot','linkedinbot','embedly','quora','pinterest','whatsapp',
			'telegram','mediapartners','ahrefs','semrush','mj12bot','dotbot','petalbot','crawler','scanner','headless',
			'httpclient','python-requests','curl','wget');

	foreach ($bot_keywords as $bot) {
		if (strpos($ua, $bot) !== false) {
			return true;
		}
	}

	if (preg_match('/(bot|crawler|spider|archiver|scraper|preview|headless|httpclient|python|curl|wget|java|php)/i', $ua)) {
		return true;
	}

	return false;

}

function analytics_anonymise_ip($ip) {

	if (empty($ip)) {
		return '';
	}

	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		$parts = explode('.', $ip);
		if (count($parts) === 4) {
			$parts[3] = '0';
			return implode('.', $parts);
		}
	}

	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		$packed = inet_pton($ip);
		if ($packed !== false && strlen($packed) === 16) {
			$packed = substr($packed, 0, 8).str_repeat("\0", 8);
			$anonymised = inet_ntop($packed);
			if ($anonymised !== false) {
				return $anonymised;
			}
		}
	}

	return '';

}

function analytics_store_ip($ip) {

	if (empty($ip)) {
		return '';
	}

	if (analytics_is_local_ip($ip)) {
		return substr($ip, 0, 45);
	}

	return analytics_anonymise_ip($ip);

}

function analytics_is_local_ip($ip) {

	if ($ip === '' || $ip === '::') {
		return true;
	}

	if ($ip === '::1' || strpos($ip, '::1:') === 0) {
		return true;
	}

	if (!filter_var($ip, FILTER_VALIDATE_IP)) {
		return true;
	}

	return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

}

function analytics_local_geo($ip) {

	$ip = trim($ip);
	$area = 'private';

	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		if (strpos($ip, '127.') === 0) {
			$area = '127';
		} elseif (strpos($ip, '10.') === 0) {
			$area = '10';
		} elseif (strpos($ip, '192.168.') === 0) {
			$area = '192.168';
		} elseif (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) {
			$area = '172.16';
		} elseif (strpos($ip, '169.254.') === 0) {
			$area = '169.254';
		}
	} elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		$ip_lower = strtolower($ip);
		if ($ip_lower === '::1' || strpos($ip_lower, '::1') === 0) {
			$area = '::1';
		} elseif (strpos($ip_lower, 'fe80:') === 0) {
			$area = 'fe80';
		} elseif (strpos($ip_lower, 'fc') === 0 || strpos($ip_lower, 'fd') === 0) {
			$area = 'fc00';
		} elseif ($ip_lower === '::') {
			$area = '::';
		}
	} elseif ($ip === '::') {
		$area = '::';
	}

	return array(
		'country' => 'Localhost',
		'region' => $area,
		'city' => $ip,
	);

}

function analytics_generate_pageview_token() {

	return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

}

function analytics_beacon_cookie_name() {

	return 'beacon';

}

function analytics_is_valid_session_id($session_id) {

	return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $session_id);

}

function analytics_session_hash_display($session_id) {

	if (empty($session_id)) {
		return '';
	}

	return substr(md5($session_id), 0, 8);

}

function analytics_get_beacon_settings() {

	static $settings = null;

	if ($settings !== null) {
		return $settings;
	}

	$settings = array(
		'delay' => 0,
		'collect_engagement' => '1',
		'session_minutes' => 60,
	);

	$db = analytics_get_mysqli();
	if ($db === false) {
		return $settings;
	}

	$sql = 'SELECT b.name, b.value FROM cms_page_panel a JOIN cms_page_panel_param b ON a.cms_page_panel_id = b.cms_page_panel_id '.
			'WHERE a.panel_name = ? AND a.cms_page_id = 0 AND b.language = ?';

	$stmt = mysqli_prepare($db, $sql);
	if ($stmt === false) {
		return $settings;
	}

	$panel_name = 'analytics/analytics_settings';
	$language = '';
	mysqli_stmt_bind_param($stmt, 'ss', $panel_name, $language);

	if (!mysqli_stmt_execute($stmt)) {
		mysqli_stmt_close($stmt);
		return $settings;
	}

	$result = mysqli_stmt_get_result($stmt);
	if ($result) {
		while ($row = mysqli_fetch_assoc($result)) {
			if (!empty($row['name'])) {
				$settings[$row['name']] = $row['value'];
			}
		}
	}

	mysqli_stmt_close($stmt);

	if (!isset($settings['session_minutes']) || $settings['session_minutes'] === '') {
		$settings['session_minutes'] = 60;
	}

	return $settings;

}

function analytics_beacon_set_cookie($session_id, $minutes) {

	$name = analytics_beacon_cookie_name();
	$path = $GLOBALS['config']['base_url'] ?? '/';
	$secure = '';

	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
			|| !empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443
			|| !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
		$secure = '; Secure';
	}

	$cookie = urlencode($name).'='.urlencode($session_id).'; Path='.$path.'; SameSite=Lax'.$secure;

	$minutes = (int)$minutes;
	if ($minutes > 0) {
		$cookie .= '; Max-Age='.($minutes * 60);
	}

	header('Set-Cookie: '.$cookie, false);

}

function analytics_get_or_create_beacon_session() {

	$settings = analytics_get_beacon_settings();
	$minutes = (int)$settings['session_minutes'];
	$name = analytics_beacon_cookie_name();
	$session_id = $_COOKIE[$name] ?? '';

	if (!analytics_is_valid_session_id($session_id)) {
		$session_id = analytics_generate_pageview_token();
	}

	analytics_beacon_set_cookie($session_id, $minutes);

	return $session_id;

}

function analytics_insert_pageview($page, $viewport_w, $viewport_h) {

	$db = analytics_get_mysqli();
	if ($db === false) {
		return false;
	}

	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	$ip_anonymised = analytics_store_ip($ip);
	$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
	$pageview_token = analytics_generate_pageview_token();
	$session_id = analytics_get_or_create_beacon_session();
	$page = substr($page, 0, 500);
	$viewport_w = (int)$viewport_w;
	$viewport_h = (int)$viewport_h;

	$sql = 'INSERT INTO cms_analytics_pageview (pageview_token, session_id, page, ip_anonymised, user_agent, viewport_w, viewport_h, created, updated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

	$stmt = mysqli_prepare($db, $sql);
	if ($stmt === false) {
		return false;
	}

	mysqli_stmt_bind_param($stmt, 'sssssii', $pageview_token, $session_id, $page, $ip_anonymised, $user_agent, $viewport_w, $viewport_h);

	if (!mysqli_stmt_execute($stmt)) {
		mysqli_stmt_close($stmt);
		return false;
	}

	mysqli_stmt_close($stmt);

	return $pageview_token;

}

function analytics_update_heartbeat($pageview_token, $seconds, $scroll_pct) {

	$db = analytics_get_mysqli();
	if ($db === false || empty($pageview_token)) {
		return false;
	}

	$seconds = min(65535, max(0, (int)$seconds));
	$scroll_pct = min(100, max(0, (int)$scroll_pct));
	$pageview_token = substr($pageview_token, 0, 36);

	$sql = 'UPDATE cms_analytics_pageview SET seconds = GREATEST(seconds, ?), scroll_pct = GREATEST(scroll_pct, ?), updated = NOW() WHERE pageview_token = ? LIMIT 1';

	$stmt = mysqli_prepare($db, $sql);
	if ($stmt === false) {
		return false;
	}

	mysqli_stmt_bind_param($stmt, 'iis', $seconds, $scroll_pct, $pageview_token);
	$result = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);

	return $result;

}