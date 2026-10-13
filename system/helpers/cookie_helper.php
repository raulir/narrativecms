<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( !function_exists('cms_cookie_create')) {

	/**
	 * Append Set-Cookie (header replace=false so PHPSESSID is not replaced).
	 * Path is site root (`/` or subdirectory base_url). SameSite=Lax; Secure on HTTPS.
	 */
	function cms_cookie_create($name, $value, $days = 0){

		$path = function_exists('cms_session_cookie_path') ? cms_session_cookie_path() : '/';
		$parts = [
				urlencode($name).'='.urlencode($value),
				'path='.$path,
				'SameSite=Lax',
		];

		if ($days){
			$date = time() + $days * 24 * 60 * 60;
			$parts[] = 'expires='.date(DATE_RFC1123, $date);
		}

		$secure = (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] !== 'off')
				|| (array_key_exists('SERVER_PORT', $_SERVER) && 443 === (int)$_SERVER['SERVER_PORT'])
				|| (array_key_exists('HTTP_X_FORWARDED_SSL', $_SERVER) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
				|| (array_key_exists('HTTP_X_FORWARDED_PROTO', $_SERVER) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

		if ($secure){
			$parts[] = 'Secure';
		}

		header('Set-Cookie: '.implode('; ', $parts), false);

	}

	function cms_cookie_clear($name){

		$paths = ['/'];
		$base = function_exists('cms_session_cookie_path') ? cms_session_cookie_path() : '/';
		if ($base !== '' && $base !== '/'){
			$paths[] = $base;
		}

		$secure = (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] !== 'off')
				|| (array_key_exists('SERVER_PORT', $_SERVER) && 443 === (int)$_SERVER['SERVER_PORT'])
				|| (array_key_exists('HTTP_X_FORWARDED_SSL', $_SERVER) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
				|| (array_key_exists('HTTP_X_FORWARDED_PROTO', $_SERVER) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
		$past = date(DATE_RFC1123, time() - 3600);

		foreach ($paths as $path){
			$variants = [false];
			if ($secure){
				$variants[] = true;
			}
			foreach ($variants as $use_secure){
				$parts = [
						urlencode($name).'=',
						'expires='.$past,
						'path='.$path,
						'SameSite=Lax',
				];
				if ($use_secure){
					$parts[] = 'Secure';
				}
				header('Set-Cookie: '.implode('; ', $parts), false);
			}
		}

	}

}
