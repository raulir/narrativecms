<?php

$GLOBALS['timer']['start'] = round(microtime(true) * 1000);
mb_internal_encoding('UTF-8');

// Set the current directory correctly for CLI requests
if (defined('STDIN')) {
	chdir(dirname(__FILE__));
}

define('BASEPATH', str_replace('\\', '/', trim(getcwd()).'/').'system/');

require_once 'system/cms.php';
