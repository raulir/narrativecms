<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( !function_exists('cms_cookie_create')) {

	/**
	 * set cookie
	 */
	function cms_cookie_create($name, $value, $days = 0){

		$expires = '';
		$secure = '';
		
		if ($days) {
			
			$date = time() + $days * 24 * 60 * 60;
			$expires = '; expires='.date(DATE_RFC1123, $date);
		
		}
			
		if (array_key_exists('HTTPS', $_SERVER) && 'on' === $_SERVER["HTTPS"] || 
				array_key_exists('SERVER_PORT', $_SERVER) && 443 === (int)$_SERVER['SERVER_PORT'] ||
				array_key_exists('HTTP_X_FORWARDED_SSL', $_SERVER) && 'on' === $_SERVER['HTTP_X_FORWARDED_SSL'] ||
				array_key_exists('HTTP_X_FORWARDED_PROTO', $_SERVER) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']) {
					
			$secure = '; SameSite=None; Secure';
					
		}	
		
		header('Set-Cookie: '.urlencode($name).'='.urlencode($value).'; expires='.$expires.'; path='.urlencode($GLOBALS['config']['base_url']).''.$secure);

	}

}
