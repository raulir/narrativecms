<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CMS error helpers: HTTP status, timeout handling.
 *
 * PHP max_execution_time: redirect to /timeout/ when that system page exists;
 * otherwise stay on the current URL and print _html_error (timeout file:line).
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

function cms_errors_log_init(){

	$file = (string)($GLOBALS['config']['paths']['error_log'] ?? '');
	if ($file === '' && function_exists('cms_path')){
		$file = rtrim(cms_path('log'), '/').'/error.log';
	}
	if ($file === ''){
		return;
	}
	ini_set('error_log', $file);
	ini_set('log_errors', '0');
	ini_set('display_errors', '0');

}

function cms_project_rel_path($file){

	$file = str_replace('\\', '/', (string)$file);
	if ($file === ''){
		return '';
	}

	$base = str_replace('\\', '/', (string)($GLOBALS['config']['base_path'] ?? ''));
	$base = rtrim($base, '/').'/';
	if ($base !== '/' && strncasecmp($file, $base, strlen($base)) === 0){
		return ltrim(substr($file, strlen($base)), '/');
	}

	// Already project-relative
	if ($file !== '' && $file[0] !== '/' && !preg_match('#^[A-Za-z]:/#', $file)){
		return ltrim($file, '/');
	}

	if (strpos($file, '/') !== false){
		$parts = explode('/', $file);
		$n = count($parts);
		if ($n >= 2){
			return $parts[$n - 2].'/'.$parts[$n - 1];
		}
	}

	return basename($file);

}

function cms_error_loc_token($file, $line = 0, $column = null){

	$rel = cms_project_rel_path($file);
	if ($rel === ''){
		return '';
	}
	$line = (int)$line;
	$token = $rel.($line > 0 ? ':'.$line : '');
	if ($column !== null && $column !== '' && (int)$column > 0){
		$token .= ':'.(int)$column;
	}

	return $token;

}

function cms_request_log_uri(){

	$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
	if ($uri === ''){
		$path = function_exists('cms_request_path') ? trim((string)cms_request_path(), '/') : '';
		return $path !== '' ? '/'.$path : '/';
	}

	$base_path = (string)(parse_url((string)($GLOBALS['config']['base_url'] ?? '/'), PHP_URL_PATH) ?: '');
	$base_path = '/'.trim($base_path, '/');
	if ($base_path !== '/' && strpos($uri, $base_path) === 0){
		$uri = substr($uri, strlen($base_path));
		if ($uri === '' || $uri[0] !== '/'){
			$uri = '/'.$uri;
		}
	}

	return $uri !== '' ? $uri : '/';

}

function cms_error_one_line($text){

	$text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', (string)$text)), ENT_QUOTES, 'UTF-8');
	$text = preg_replace('/\s+/', ' ', $text);

	return trim((string)$text);

}

function cms_log_php($severity, $message, $file = '', $line = 0, $column = null){

	cms_errors_log_init();
	$severity = trim((string)$severity);
	if ($severity === ''){
		$severity = 'Error';
	}
	$msg = cms_error_one_line($message);
	$loc = cms_error_loc_token($file, $line, $column);
	$line_out = 'PHP '.$severity.($loc !== '' ? ' '.$loc : '').($msg !== '' ? ' '.$msg : '');
	error_log($line_out);

}

function error_log_user($message){

	$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
	$frame = $bt[1] ?? $bt[0] ?? [];
	cms_log_php('User', $message, (string)($frame['file'] ?? ''), (int)($frame['line'] ?? 0));

}

function cms_log_cms($code, $message, $file = '', $line = 0, $column = null){

	cms_errors_log_init();
	$code = trim((string)$code);
	if ($code === ''){
		$code = 'error';
	}
	$msg = cms_error_one_line($message);
	if ((int)$line > 0){
		$loc = cms_error_loc_token($file, $line, $column);
	} else {
		$loc = trim((string)$file);
	}
	$line_out = 'CMS '.$code.($loc !== '' ? ' '.$loc : '').($msg !== '' ? ' '.$msg : '');
	error_log($line_out);

}

function cms_php_severity_word($severity){

	$severity = (int)$severity;
	if (defined('E_DEPRECATED') && $severity === E_DEPRECATED){
		return 'Deprecated';
	}
	if (defined('E_USER_DEPRECATED') && $severity === E_USER_DEPRECATED){
		return 'Deprecated';
	}

	$map = [
			E_ERROR => 'Error',
			E_WARNING => 'Warning',
			E_PARSE => 'Error',
			E_NOTICE => 'Notice',
			E_CORE_ERROR => 'Error',
			E_CORE_WARNING => 'Warning',
			E_COMPILE_ERROR => 'Error',
			E_COMPILE_WARNING => 'Warning',
			E_USER_ERROR => 'Error',
			E_USER_WARNING => 'Warning',
			E_USER_NOTICE => 'Notice',
			E_STRICT => 'Notice',
			E_RECOVERABLE_ERROR => 'Error',
	];

	return $map[$severity] ?? 'Error';

}

function cms_exception_handler($e){

	if (!($e instanceof \Throwable)){
		return;
	}
	cms_log_php('Error', $e->getMessage(), $e->getFile(), $e->getLine());
	if (!empty($GLOBALS['config']['errors_visible']) && function_exists('_html_error')){
		_html_error(
				'<b>Uncaught exception</b>'."\n".$e->getMessage(),
				0,
				[
						'location' => cms_error_loc_token($e->getFile(), $e->getLine()),
						'nolog' => 1,
				]
		);
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
 * First backtrace frame outside CMS error wrappers.
 */
function cms_error_caller_location(){

	$skip = [
			'cms.php' => true,
			'Exceptions.php' => true,
			'error_helper.php' => true,
			'cms_bootstrap.php' => true,
	];

	foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame){
		$file = str_replace('\\', '/', (string)($frame['file'] ?? ''));
		if ($file === ''){
			continue;
		}
		$base = basename($file);
		if (!empty($skip[$base])){
			continue;
		}
		return cms_error_loc_token($file, (int)($frame['line'] ?? 0));
	}

	return '';

}

/**
 * Reserved system page exists and has a layout file.
 */
function cms_system_error_page_is_usable($slug){

	$slug = trim((string)$slug, '/');
	if ($slug === ''){
		return false;
	}

	if (!function_exists('get_instance')){
		return false;
	}

	try {
		$CI =& get_instance();
		if (empty($CI) || empty($CI->load)){
			return false;
		}
		$CI->load->model('cms/cms_page_model');
		if (empty($CI->cms_page_model) || !method_exists($CI->cms_page_model, 'system_error_page_usable')){
			return false;
		}
		return (bool)$CI->cms_page_model->system_error_page_usable($slug);
	} catch (\Throwable $e){
		return false;
	}

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

	$is_timeout = (stripos($msg, 'Maximum execution time') !== false
			|| stripos($msg, 'max_execution_time') !== false);

	if (!$is_timeout){
		cms_log_php(
				cms_php_severity_word((int)($error['type'] ?? E_ERROR)),
				$msg,
				(string)($error['file'] ?? ''),
				(int)($error['line'] ?? 0)
		);
		return;
	}

	$GLOBALS['cms_timeout_handling'] = 1;

	$file = str_replace('\\', '/', (string)($error['file'] ?? ''));
	$line = (int)($error['line'] ?? 0);
	$loc = cms_error_loc_token($file, $line);

	cms_log_cms('Timeout', '500 Internal Server Error (timeout)', $file, $line);

	$base = !empty($GLOBALS['config']['base_url']) ? $GLOBALS['config']['base_url'] : '/';
	$base = rtrim((string)$base, '/').'/';

	$on_timeout_page = cms_request_is_timeout_slug();
	$usable = !$on_timeout_page && cms_system_error_page_is_usable('timeout');

	if ($usable && !headers_sent()){
		cms_timeout_output_html($base, $base.'timeout/');
		exit;
	}

	if (function_exists('_html_error')){
		_html_error('500 Internal Server Error (timeout)', 500, [
				'location' => $loc,
				'force' => 1,
				'nolog' => 1,
				'log_code' => 'Timeout',
		]);
	} else {
		cms_timeout_output_html($base, null);
	}
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

/**
 * Log HTTP 500 to PHP errors_log (CMS error report).
 */
function cms_log_http_500($message){

	$uri = cms_request_log_uri();
	$text = cms_error_one_line($message);
	if ($uri !== '' && $uri !== '/'){
		$text = ($text !== '' ? $text.' ' : '').$uri;
	}
	cms_log_cms('500', $text, cms_error_caller_location());

}

/**
 * HTTP 500: log, then /internal-error/ if usable; else red-frame on the current URL.
 */
function cms_show_500($message, $failed_page = ''){

	$_error =& load_class('Exceptions');
	$_error->show_500($message, $failed_page);
	exit;

}
