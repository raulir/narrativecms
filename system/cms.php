<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

function _html_error($error, $exit = 0, $extra = []){

	if (is_array($exit)){
		$extra = $exit;
		$exit = 0;
	}

	if (!empty($extra['exit'])){
		$exit = $extra['exit'];
	}

	$formatted = str_replace(['#br#', '#b#', '#bb#'], ['<br>', '<b>', '</b>'],
			htmlentities(str_replace(['<br>', '<b>', '</b>'], ['#br#', '#b#', '#bb#'], $error)));

	if (empty($extra['location'])){
		$backtrace = debug_backtrace();
		if (empty($extra['backtrace'])){
			$extra['backtrace'] = 0;
		}
		$extra['location'] = basename($backtrace[$extra['backtrace']]['file']).':'.$backtrace[$extra['backtrace']]['line'];
	}

	$return = ('<pre style="background-color: white; color: black; display: block; border: 0.1rem solid red; white-space: normal; '.
			'font-size: 0.8rem; line-height: 0.9rem; letter-spacing: 0; font-family: monospace; text-align: left; ">');
	$return .= ('<div style="line-height: 0.6rem;  padding: 0.4rem; color: red; font-weight: bold; ">'.
			strtoupper($extra['location']??'').'</div><div style="padding: 0.6rem 1.0rem; ">');
	 
	$return .= ($formatted);
	$return .= ('</div></pre>');
		
	if(!empty($GLOBALS['config']['errors_visible']) || empty($GLOBALS['config']['base_path'])){
		print($return);
	}

	if ($exit){
		set_status_header($exit);
		exit();
	}

	return $return;
	 
}

// load config
$working_directory = str_replace('\\', '/', trim(getcwd()).'/');
include($working_directory.'system/core/config.php');

if (!empty($GLOBALS['config']['allow_api_anywhere'])){
	header('Access-Control-Allow-Origin: *');
}
   		
// check if api call
if (substr($_SERVER['REQUEST_URI'], 0, strlen($GLOBALS['config']['base_url'])) == $GLOBALS['config']['base_url']) {
	$string = substr($_SERVER['REQUEST_URI'], strlen($GLOBALS['config']['base_url']));
} else {
	$string = $_SERVER['REQUEST_URI'];
}

$request_uri = trim($string, '/');

if (stristr($request_uri, '/')){
	
	list($module, $api) = explode('/', $request_uri, 2);
	
	if (!empty($GLOBALS['config']['module'][$module]['api'])){
	
		foreach($GLOBALS['config']['module'][$module]['api'] as $capi){
			if ($capi['id'] == $api){
				$filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/api/'.$api.'.php';
				if (!file_exists($filename)){
					print('Can\'t find API main controller: '.$filename);
				} else {
					include($filename);
				}
				die();
				
			}
		}
	
	}
	
}

// router - check if landing page and landing page set
if (empty($GLOBALS['config']['landing_page']['_value'])){
	$GLOBALS['config']['landing_page']['_value'] = '1';
	$GLOBALS['config']['landing_page']['url'] = '/';
}

// if landing page by slug
$landing_uri = trim($GLOBALS['config']['landing_page']['url'], '/');
if (!empty($landing_uri) && $landing_uri === $request_uri){
	header('Location: //'.$_SERVER['HTTP_HOST'].'/'.ltrim($GLOBALS['config']['base_url'], '/'), true, 307);
	exit();
}

if (!empty($GLOBALS['config']['landing_page']['_value']) && empty($request_uri)){
	$landing_route = '/index/'.$GLOBALS['config']['landing_page']['_value'];
} else {
	$landing_route = '';
}

// check if cron needs to run
if (!empty($GLOBALS['config']['cron_trigger']) && $GLOBALS['config']['cron_trigger'] == 'visits'){
	
	$cron_data_filename = $GLOBALS['config']['base_path'].'cache/cron.json';
	if (!file_exists($cron_data_filename) || (time() - filemtime($cron_data_filename)) >= 240){
		$GLOBALS['config']['js'][] = ['script' => 'modules/cms/js/cms_cron_run.js', 'sync' => 'defer', ];
	}

}

// start session
include($GLOBALS['config']['base_path'].'system/core/session.php');

// check for visitor target groups
$_SESSION['config']['targets']['hash'] = '';
if (!empty($GLOBALS['config']['targets_enabled'])){
	
	include($GLOBALS['config']['base_path'].'system/core/targets.php');
	
}

require_once BASEPATH.'core/CodeIgniter.php';
