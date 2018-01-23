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

// The path to the "application" folder
if (is_dir('application')) {
	define('APPPATH', 'application/');
} else {
	
	if ( ! is_dir(BASEPATH.'application/')) {
		exit("Your application folder path does not appear to be set correctly. Please open the following file and correct this: ".SELF);
	}

	define('APPPATH', BASEPATH.'application/');
	
}

// And away we go...

 require_once BASEPATH.'cms.php';
