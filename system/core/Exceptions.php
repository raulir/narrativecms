<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Exceptions {
	var $action;
	var $severity;
	var $message;
	var $filename;
	var $line;

	/**
	 * Nesting level of the output buffering mechanism
	 *
	 * @var int
	 * @access public
	 */
	var $ob_level;

	/**
	 * List if available error levels
	 *
	 * @var array
	 * @access public
	 */
	var $levels = array(
						E_ERROR				=>	'Error',
						E_WARNING			=>	'Warning',
						E_PARSE				=>	'Parsing Error',
						E_NOTICE			=>	'Notice',
						E_CORE_ERROR		=>	'Core Error',
						E_CORE_WARNING		=>	'Core Warning',
						E_COMPILE_ERROR		=>	'Compile Error',
						E_COMPILE_WARNING	=>	'Compile Warning',
						E_USER_ERROR		=>	'User Error',
						E_USER_WARNING		=>	'User Warning',
						E_USER_NOTICE		=>	'User Notice',
						E_STRICT			=>	'Runtime Notice'
					);


	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->ob_level = ob_get_level();
		// Note:  Do not log messages from this constructor.
	}

	// --------------------------------------------------------------------

	/**
	 * Exception Logger
	 *
	 * This function logs PHP generated error messages
	 *
	 * @access	private
	 * @param	string	the error severity
	 * @param	string	the error string
	 * @param	string	the error filepath
	 * @param	string	the error line number
	 * @return	string
	 */
	function log_exception($severity, $message, $filepath, $line)
	{
		$severity = ( ! isset($this->levels[$severity])) ? $severity : $this->levels[$severity];

		//log_message('error', 'Severity: '.$severity.'  --> '.$message. ' '.$filepath.' '.$line, TRUE);
		_html_error("Unable to load the requested class: ".$class, 500);
	}

	// --------------------------------------------------------------------

	/**
	 * 404 Page Not Found Handler
	 *
	 * Prefer HTTP redirect to the CMS system page slug `not-found` so the
	 * front controller builds that page once from scratch (no nested render).
	 *
	 * @access	private
	 * @param	string	the page
	 * @param 	bool	log error yes/no
	 * @return	string
	 */
	function show_404($page = '')
	{
		$heading = "404 Page Not Found";
		$message = "The page you requested was not found.";

		// By default we log this, but allow a dev to skip it
		if (!empty($GLOBALS['config']['not_found_log'])){
			
			$ip = '[' . $_SERVER['REMOTE_ADDR'] . (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? ' ' . $_SERVER['HTTP_X_FORWARDED_FOR'] : '') . ']';
			
			file_put_contents($GLOBALS['config']['base_path'].'cache/'.$GLOBALS['config']['not_found_log'],
					date('Y-m-d H:i:s') . ' | ' . $page.' | '. 
					(!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '') . ' | ' . $ip . "\n", FILE_APPEND);
			
		}

		// Redirect to public system page — single clean page build on next request
		if ($this->_redirect_system_error_page('not-found', $page)){
			return;
		}

		_html_error(''.$heading.' - '.$message, 404, ['backtrace' => 2]);
	}

	/**
	 * Redirect to reserved system page slug (e.g. not-found, internal-error).
	 * Non-numeric slugs only — numeric would clash with cms_page_id routing.
	 *
	 * @return bool true if redirected / exit
	 */
	function _redirect_system_error_page($slug, $failed_page = ''){

		$slug = trim((string)$slug, '/');
		if ($slug === '' || ctype_digit($slug)){
			return false;
		}

		// Already on this slug — avoid loop
		$request = isset($GLOBALS['cms_request_uri']) ? trim((string)$GLOBALS['cms_request_uri'], '/') : '';
		if ($request === $slug || $failed_page === $slug){
			return false;
		}
		if (!empty($_SERVER['REQUEST_URI'])){
			$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
			$path = trim((string)$path, '/');
			$base = '';
			if (!empty($GLOBALS['config']['base_url'])){
				$base = trim((string)(parse_url($GLOBALS['config']['base_url'], PHP_URL_PATH) ?: ''), '/');
			}
			if ($base !== '' && strpos($path, $base.'/') === 0){
				$path = substr($path, strlen($base) + 1);
			} elseif ($base !== '' && $path === $base){
				$path = '';
			}
			if ($path === $slug){
				return false;
			}
		}

		// Only redirect if route cache knows the slug (page ensured + public)
		$base_path = !empty($GLOBALS['config']['base_path']) ? $GLOBALS['config']['base_path'] : '';
		$routes_file = $base_path.'cache/routes.php';
		if (is_file($routes_file)){
			$routes_src = @file_get_contents($routes_file);
			if ($routes_src === false
					|| (strpos($routes_src, "\$route['".$slug."']") === false
						&& strpos($routes_src, '$route["'.$slug.'"]') === false)){
				return false;
			}
		} else {
			// No routes file — do not guess
			return false;
		}

		if (headers_sent() || empty($GLOBALS['config']['base_url'])){
			return false;
		}

		$target = rtrim($GLOBALS['config']['base_url'], '/').'/'.$slug.'/';
		header('Location: '.$target, true, 302);
		exit;

	}

}
