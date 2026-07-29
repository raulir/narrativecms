<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Real UTF-8 for storage / API / JSON — never HTML entities.
 * Invalid sequences are dropped or re-decoded from Windows-1252; valid UTF-8 (incl. emoji/mb4) is left as-is.
 */
function cms_utf8_string($value){

	if (!is_string($value)){
		return is_scalar($value) || $value === null ? (string)$value : '';
	}
	if ($value === ''){
		return '';
	}
	if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')){
		return $value;
	}
	if (function_exists('iconv')){
		$fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
		if ($fixed !== false && $fixed !== ''){
			return $fixed;
		}
	}
	if (function_exists('mb_convert_encoding')){
		$fixed = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
		if (is_string($fixed) && $fixed !== '' && (!function_exists('mb_check_encoding') || mb_check_encoding($fixed, 'UTF-8'))){
			return $fixed;
		}
		$fixed = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
		if (is_string($fixed) && (!function_exists('mb_check_encoding') || mb_check_encoding($fixed, 'UTF-8'))){
			return $fixed;
		}
	}
	return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

}

/**
 * Recursively apply cms_utf8_string to all strings in arrays (panel params, AI trees).
 */
function cms_utf8_tree($data){

	if (is_string($data)){
		return cms_utf8_string($data);
	}
	if (!is_array($data)){
		return $data;
	}
	$out = [];
	foreach ($data as $key => $value){
		$safe_key = is_string($key) ? cms_utf8_string($key) : $key;
		$out[$safe_key] = cms_utf8_tree($value);
	}
	return $out;

}

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
