<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CMS error helpers (timeout, and future error-related utilities).
 *
 * PHP max_execution_time: soft-redirect to system page /timeout/ + minimal HTML fallback.
 * Registered only for front/router requests (not module API includes).
 */

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
