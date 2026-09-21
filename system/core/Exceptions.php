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

		$uri = function_exists('cms_request_log_uri') ? cms_request_log_uri() : '';
		$probe_uri = $uri !== '' ? $uri : (string)$page;
		$is_probe = function_exists('cms_404_is_probe') && cms_404_is_probe($probe_uri);
		if (function_exists('cms_404_fail2ban_log')){
			cms_404_fail2ban_log($probe_uri);
		}
		if (function_exists('cms_log_cms')){
			$ip = preg_replace('/\s+/', '', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
			$bits = ['Page Not Found'];
			if ($is_probe){
				$bits[] = '(probe)';
			}
			if ($ip !== ''){
				$bits[] = $ip;
			}
			if ($uri !== ''){
				$bits[] = $uri;
			}
			cms_log_cms('404', implode(' ', $bits));
		}

		if ($is_probe && function_exists('cms_404_probe_response')){
			cms_404_probe_response();
			return;
		}

		// Redirect to public system page — single clean page build on next request
		if ($this->_redirect_system_error_page('not-found', $page)){
			return;
		}

		// Reserved slug with no saved CMS page: stay on this URL, red frame
		if ($this->_reserved_slug_missing_page($page)){
			$slug = trim((string)$page, '/');
			$loc = function_exists('cms_error_caller_location') ? cms_error_caller_location() : '';
			_html_error('500 Internal Server Error ('.$slug.')', 500, [
					'location' => $loc,
					'force' => 1,
			]);
			return;
		}

		_html_error(''.$heading.' - '.$message, 404, ['backtrace' => 2, 'nolog' => 1, 'log_code' => '404']);
	}

	/**
	 * HTTP 500. Prefer saved system page internal-error; else red-frame HTML.
	 * Caller should already have cms_log_http_500 / cms_show_500.
	 */
	function show_500($message = '', $failed_page = ''){

		$heading = '500 Internal Server Error';
		$text = trim((string)$message);
		if ($text === ''){
			$text = 'The page could not be displayed.';
		}

		if (function_exists('cms_log_http_500')){
			cms_log_http_500($text);
		} else if (function_exists('cms_log_cms')){
			cms_log_cms('500', $text);
		} else {
			error_log('CMS 500 '.$text);
		}

		if ($this->_redirect_system_error_page('internal-error', $failed_page)){
			return;
		}

		$slug = 'internal-error';
		$failed = trim((string)$failed_page, '/');
		if ($failed === 'timeout' || $failed === 'not-found' || $failed === 'internal-error'){
			$slug = $failed;
		}

		$loc = function_exists('cms_error_caller_location') ? cms_error_caller_location() : '';
		_html_error('500 Internal Server Error ('.$slug.')', 500, [
				'location' => $loc,
				'force' => 1,
				'nolog' => 1,
				'log_code' => '500',
		]);
	}

	function _reserved_slug_missing_page($page){

		$slug = trim((string)$page, '/');
		if ($slug === '' || strpos($slug, '=') !== false){
			return false;
		}

		// Keep in sync with cms_page_model::get_system_page_defs()
		$maybe_system = ($slug === 'not-found' || $slug === 'internal-error' || $slug === 'timeout');
		if (!$maybe_system && strpos($slug, '_') === false){
			return false;
		}

		if (!function_exists('get_instance')){
			return false;
		}

		$CI =& get_instance();
		if (empty($CI) || empty($CI->load)){
			return false;
		}

		$CI->load->model('cms/cms_page_model');
		if (empty($CI->cms_page_model) || !method_exists($CI->cms_page_model, 'is_reserved_slug')){
			return false;
		}

		if (!$CI->cms_page_model->is_reserved_slug($slug)){
			return false;
		}

		return !$CI->cms_page_model->reserved_page_exists($slug);
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

		// Only redirect if a visible public route exists for this system slug (DB)
		if (!function_exists('cms_route_lookup_slug') || cms_route_lookup_slug($slug) === null){
			return false;
		}

		// Page with no layout (or missing layout file) would 500 again — show fallback instead
		if (function_exists('get_instance')){
			$CI =& get_instance();
			if (!empty($CI) && !empty($CI->load)){
				$CI->load->model('cms/cms_page_model');
				if (!empty($CI->cms_page_model) && method_exists($CI->cms_page_model, 'system_error_page_usable')
						&& !$CI->cms_page_model->system_error_page_usable($slug)){
					return false;
				}
			}
		}

		if (headers_sent() || empty($GLOBALS['config']['base_url'])){
			return false;
		}

		$target = rtrim($GLOBALS['config']['base_url'], '/').'/'.$slug.'/';
		header('Location: '.$target, true, 302);
		exit;

	}

}
