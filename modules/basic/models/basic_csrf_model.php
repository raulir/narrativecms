<?php

namespace basic;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Optional CSRF tokens (moved out of system/core Security).
 * Enable with $GLOBALS['config']['csrf_protection'] and call from a panel/filter when needed.
 * Not wired into Input by default.
 */
class basic_csrf_model extends \Model {

	protected $_csrf_hash = '';
	protected $_csrf_expire = 7200;
	protected $_csrf_token_name = 'csrf_token';
	protected $_csrf_cookie_name = 'csrf_cookie';

	function __construct(){

		if (isset($GLOBALS['config']['csrf_expire']) && $GLOBALS['config']['csrf_expire'] !== ''){
			$this->_csrf_expire = (int)$GLOBALS['config']['csrf_expire'];
		}
		if ( ! empty($GLOBALS['config']['csrf_token_name'])){
			$this->_csrf_token_name = $GLOBALS['config']['csrf_token_name'];
		}
		if ( ! empty($GLOBALS['config']['csrf_cookie_name'])){
			$this->_csrf_cookie_name = $GLOBALS['config']['csrf_cookie_name'];
		}

		$this->_csrf_set_hash();

	}

	function get_csrf_hash(){

		return $this->_csrf_hash;

	}

	function get_csrf_token_name(){

		return $this->_csrf_token_name;

	}

	/**
	 * Verify POST token against cookie; regenerate on success.
	 * @return bool
	 */
	function csrf_verify(){

		if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
			return $this->csrf_set_cookie();
		}

		$cookie = $_COOKIE[$this->_csrf_cookie_name] ?? '';
		$post = $_POST[$this->_csrf_token_name] ?? '';

		if ($cookie === '' || $post === '' || $cookie !== $post){
			return false;
		}

		unset($_POST[$this->_csrf_token_name]);
		$this->_csrf_set_hash(true);
		$this->csrf_set_cookie();

		return true;

	}

	function csrf_set_cookie(){

		$expire = time() + $this->_csrf_expire;
		$path = $GLOBALS['config']['base_url'] ?? '/';
		$secure = ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| ( ! empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

		setcookie($this->_csrf_cookie_name, $this->_csrf_hash, $expire, $path, '', $secure, true);

		return true;

	}

	protected function _csrf_set_hash($force = false){

		if ( ! $force && ! empty($_COOKIE[$this->_csrf_cookie_name])
				&& preg_match('#^[0-9a-f]{32}$#i', $_COOKIE[$this->_csrf_cookie_name])){
			$this->_csrf_hash = $_COOKIE[$this->_csrf_cookie_name];
			return;
		}

		$this->_csrf_hash = md5(uniqid((string)mt_rand(), true));

	}

}
