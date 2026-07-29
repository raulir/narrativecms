<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CMS error helpers: HTTP status, timeout handling.
 *
 * PHP max_execution_time: soft-redirect to system page /timeout/ + minimal HTML fallback.
 * Timeout shutdown registered only for front/router requests (not module API includes).
 */

/**
 * Send HTTP response status line (early boot + Output wrapper).
 */
function set_status_header($code = 200, $text = ''){

	$stati = [
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
	];

	if ($code === '' || !is_numeric($code)){
		if (function_exists('_html_error')){
			_html_error('Status codes must be numeric');
		}
		return;
	}

	$code = (int)$code;

	if (isset($stati[$code]) && $text === ''){
		$text = $stati[$code];
	}

	if ($text === ''){
		if (function_exists('_html_error')){
			_html_error('No status text available. Please check your status code number or supply your own message text.');
		}
		return;
	}

	$server_protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : false;

	if (substr(php_sapi_name(), 0, 3) === 'cgi'){
		header('Status: '.$code.' '.$text, true);
	} else if ($server_protocol === 'HTTP/1.1' || $server_protocol === 'HTTP/1.0'){
		header($server_protocol.' '.$code.' '.$text, true, $code);
	} else {
		header('HTTP/1.1 '.$code.' '.$text, true, $code);
	}

}

function cms_register_timeout_shutdown(){

	static $registered = false;
	if ($registered){
		return;
	}
	$registered = true;
	register_shutdown_function('cms_shutdown_timeout_handler');

}

/**
 * Detect max execution time fatal and respond lightly (no CMS re-bootstrap).
 */
function cms_shutdown_timeout_handler(){

	if (!empty($GLOBALS['cms_timeout_handling'])){
		return;
	}

	$error = error_get_last();
	if ($error === null){
		return;
	}

	$fatal_types = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR];
	if (!in_array($error['type'], $fatal_types, true)){
		return;
	}

	$msg = (string)($error['message'] ?? '');
	if ($msg === ''){
		return;
	}
	if (stripos($msg, 'Maximum execution time') === false
			&& stripos($msg, 'max_execution_time') === false){
		return;
	}

	$GLOBALS['cms_timeout_handling'] = 1;

	$base = !empty($GLOBALS['config']['base_url']) ? $GLOBALS['config']['base_url'] : '/';
	$base = rtrim((string)$base, '/').'/';
	$timeout_url = $base.'timeout/';

	// Loop guard: already on system timeout page → static HTML only (no meta refresh)
	$on_timeout_page = cms_request_is_timeout_slug();

	cms_timeout_output_html($base, $on_timeout_page ? null : $timeout_url);
	exit;

}

/**
 * Whether the current request is already the public /timeout/ system page.
 */
function cms_request_is_timeout_slug(){

	$req = isset($GLOBALS['cms_request_uri']) ? trim((string)$GLOBALS['cms_request_uri'], '/') : '';
	if ($req === 'timeout'){
		return true;
	}

	if (empty($_SERVER['REQUEST_URI'])){
		return false;
	}

	$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$path = trim((string)$path, '/');
	$base = '';
	if (!empty($GLOBALS['config']['base_url'])){
		$base = trim((string)(parse_url($GLOBALS['config']['base_url'], PHP_URL_PATH) ?: ''), '/');
	}
	if ($base !== '' && strpos($path, $base.'/') === 0){
		$path = substr($path, strlen($base) + 1);
	} elseif ($base !== '' && $path === $base){
		$path = '';
	}

	return ($path === 'timeout');

}

/**
 * Minimal 504 HTML. Soft-redirect via meta refresh when $timeout_url is set.
 * Home link always points at site root ($home_url).
 *
 * @param string $home_url
 * @param string|null $timeout_url soft redirect target, or null for no meta refresh
 */
function cms_timeout_output_html($home_url, $timeout_url = null){

	while (ob_get_level() > 0){
		@ob_end_clean();
	}

	if (!headers_sent()){
		http_response_code(504);
		header('Content-Type: text/html; charset=utf-8');
	}

	$home = htmlspecialchars((string)$home_url, ENT_QUOTES, 'UTF-8');
	$meta = '';
	if (!empty($timeout_url)){
		// Soft redirect (no Location header) — next request builds the CMS timeout page cleanly
		$meta = '<meta http-equiv="refresh" content="0;url='
				.htmlspecialchars((string)$timeout_url, ENT_QUOTES, 'UTF-8').'">';
	}

	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>504 - Timeout</title>'
			.$meta
			.'</head><body><p>Script timeout. <a href="'.$home.'">Click here</a></p></body></html>';

}
