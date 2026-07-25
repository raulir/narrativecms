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

	ini_set('display_errors', '0');

	if ( ! empty($GLOBALS['config']['errors_log'])){
		ini_set('error_log', $GLOBALS['config']['base_path'].$GLOBALS['config']['errors_log']);
	}

	if ($severity == E_STRICT){
		return false;
	}

	$levels = [
		E_ERROR => 'Error',
		E_WARNING => 'Warning',
		E_PARSE => 'Parsing Error',
		E_NOTICE => 'Notice',
		E_CORE_ERROR => 'Core Error',
		E_CORE_WARNING => 'Core Warning',
		E_COMPILE_ERROR => 'Compile Error',
		E_COMPILE_WARNING => 'Compile Warning',
		E_USER_ERROR => 'User Error',
		E_USER_WARNING => 'User Warning',
		E_USER_NOTICE => 'User Notice',
		E_STRICT => 'Runtime Notice',
	];

	$severity = $levels[$severity] ?? $severity;

	$filepath = str_replace('\\', '/', $filepath);
	if (stristr($filepath, '/')){
		$x = explode('/', $filepath);
		$filepath = $x[count($x) - 2].'/'.end($x);
	}

	if ( ! empty($GLOBALS['config']['errors_visible'])){
		$error_text = "<b>A PHP Error was encountered</b>\n".
			'Severity: '.$severity."\n".
			'Message: '.$message."\n";
		_html_error($error_text, 0, ['location' => $filepath.':'.$line]);
	}

	return false;

}
