<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Path normalize only — safe before full config / cms_router.
 * Needs $GLOBALS['config']['base_url'] from cms_config_basic.php.
 */
function cms_request_path() {

	$base = $GLOBALS['config']['base_url'] ?? '/';

	if (substr($_SERVER['REQUEST_URI'] ?? '', 0, strlen($base)) === $base) {
		$string = substr($_SERVER['REQUEST_URI'], strlen($base));
	} else {
		$string = $_SERVER['REQUEST_URI'] ?? '';
	}

	if (strpos($string, '?') !== false) {
		list($string, $rest) = explode('?', $string, 2);
	}

	if (($h = strpos($string, '#')) !== false) {
		$string = substr($string, 0, $h);
	}

	return trim($string, '/');

}
