<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Strip control / URL-encoded control characters (Input / security_helper).
 */
function remove_invisible_characters($str, $url_encoded = true){

	$non_displayables = [];

	if ($url_encoded){
		$non_displayables[] = '/%0[0-8bcef]/';
		$non_displayables[] = '/%1[0-9a-f]/';
	}

	$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';

	do {
		$str = preg_replace($non_displayables, '', $str, -1, $count);
	} while ($count);

	return $str;

}

/**
 * HTML-escape a string or array of strings.
 */
function html_escape($var){

	if (is_array($var)){
		return array_map('html_escape', $var);
	}

	$charset = $GLOBALS['config']['system']['charset'] ?? 'UTF-8';

	return htmlspecialchars($var, ENT_QUOTES, $charset);

}
