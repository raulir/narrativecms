<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 * Session
 *
 */

if (!session_id()){

	session_start();

	if (!empty($_SESSION['timezone'])){
		date_default_timezone_set($_SESSION['timezone']);
	}
	
	// detect webp support
	if (empty($_SESSION['webp'])){
		
		$_SESSION['webp'] = false;
		if (!empty($GLOBALS['config']['images_webp'])){
			if (empty($_SESSION['webp']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false){
				$_SESSION['webp'] = true;
			}
		}
		
	}

}
