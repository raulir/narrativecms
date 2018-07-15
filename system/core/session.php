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

}
