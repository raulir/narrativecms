<?php

if (!defined('BASEPATH')){
	exit('No direct script access allowed');
}

require_once $GLOBALS['config']['base_path'].'system/core/cms_config.php';
require_once $GLOBALS['config']['base_path'].'system/core/cms_bootstrap.php';
require_once $GLOBALS['config']['base_path'].'system/core/controller.php';
require_once $GLOBALS['config']['base_path'].'system/core/session.php';

if (session_status() !== PHP_SESSION_ACTIVE){
	cms_session_boot();
}

$token = trim((string)($_GET['app_enter'] ?? ''));
$path = '/';

if ($token !== ''){

	$ci = new Controller();
	$ci->load->model('subscription/subscription_model');
	$next = $ci->subscription_model->browser_enter_consume($token);

	if ($next !== ''){
		$path = $next;
	}

}

header('Location: '.$path, true, 302);
exit();
