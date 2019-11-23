<?php

// start timer
$GLOBALS['timer']['start'] = round(microtime(true) * 1000);

// Set the current directory correctly for CLI requests
if (defined('STDIN')) {
	chdir(dirname(__FILE__));
}
// The name of THIS file
define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));

// Path to the system folder
define('BASEPATH', realpath('system').'/');

// And away we go...
require_once BASEPATH.'cms.php';
