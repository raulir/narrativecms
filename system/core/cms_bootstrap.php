<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * System bootstrap globals: class registry, main controller ref, 404, PHP errors.
 * App model/library/view loading stays in Loader (load_class is not $this->load).
 * Paths use $GLOBALS['config']['base_path'] (set by cms_config_basic / full).
 */

$_cms_bp = $GLOBALS['config']['base_path'] ?? '';
require_once $_cms_bp.'system/helpers/string_helper.php';

/**
 * Singleton system class from system/{directory}/{Class}.php (Input, Output, Loader, …).
 */
function &load_class($class, $directory = 'core', $prefix = ''){

	static $_classes = [];

	if (isset($_classes[$class])){
		return $_classes[$class];
	}

	$path = $GLOBALS['config']['base_path'].'system/'.$directory.'/'.$class.'.php';
	if ( ! file_exists($path)){
		_html_error('Unable to locate the specified class: '.$class.'.php', 500);
	}

	$name = $prefix.$class;
	if ( ! class_exists($name, false)){
		require $path;
	}

	is_loaded($class);
	$_classes[$class] = new $name();

	return $_classes[$class];

}

/**
 * Map of system classes constructed via load_class (used by main Controller).
 */
function &is_loaded($class = ''){

	static $_is_loaded = [];

	if ($class !== ''){
		$_is_loaded[strtolower($class)] = $class;
	}

	return $_is_loaded;

}

/**
 * Main request Controller (after new Controller / Index / …).
 */
function &get_instance(){

	return Controller::get_instance();

}

function show_404($page = ''){

	$_error =& load_class('Exceptions');
	$_error->show_404($page);
	exit;

}

function _exception_handler($severity, $message, $filepath, $line){

	if (function_exists('cms_errors_log_init')){
		cms_errors_log_init();
	} else {
		ini_set('display_errors', '0');
	}

	if ($severity == E_STRICT){
		return true;
	}

	$word = function_exists('cms_php_severity_word') ? cms_php_severity_word($severity) : 'Error';
	if (function_exists('cms_log_php')){
		cms_log_php($word, $message, $filepath, $line);
	}

	if ( ! empty($GLOBALS['config']['errors_visible']) && function_exists('_html_error')){
		$loc = function_exists('cms_error_loc_token')
				? cms_error_loc_token($filepath, $line)
				: (basename(str_replace('\\', '/', (string)$filepath)).':'.$line);
		$error_text = "<b>A PHP Error was encountered</b>\n".
			'Severity: '.$word."\n".
			'Message: '.$message."\n";
		_html_error($error_text, 0, ['location' => $loc, 'nolog' => 1]);
	}

	return true;

}

if (function_exists('cms_errors_log_init')){
	cms_errors_log_init();
}
if (function_exists('cms_exception_handler')){
	set_exception_handler('cms_exception_handler');
}
set_error_handler('_exception_handler');
if (function_exists('cms_register_timeout_shutdown')){
	cms_register_timeout_shutdown();
}
