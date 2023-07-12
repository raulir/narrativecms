<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if ( ! function_exists('config_item')) {
	function config_item($item)	{

		if (empty($GLOBALS['config']['system'][$item])){
			return false;
		}

		return $GLOBALS['config']['system'][$item];

	}
}

/**
* Class registry
*
* This function acts as a singleton.  If the requested class does not
* exist it is instantiated and set to a static variable.  If it has
* previously been instantiated the variable is returned.
*
* @access	public
* @param	string	the class name being requested
* @param	string	the directory where the class should be found
* @param	string	the class name prefix
* @return	object
*/
if ( ! function_exists('load_class'))
{
	function &load_class($class, $directory = 'core', $prefix = '')
	{
		static $_classes = array();

		// Does the class exist?  If so, we're done...
		if (isset($_classes[$class]))
		{
			return $_classes[$class];
		}

		$name = FALSE;

		if (file_exists(BASEPATH.$directory.'/'.$class.'.php'))
		{
			$name = $prefix.$class;

			if (class_exists($name) === FALSE)
			{
				require(BASEPATH.$directory.'/'.$class.'.php');
			}
		}

		// Did we find the class?
		if ($name === FALSE){
			_html_error('Unable to locate the specified class: '.$class.'.php', 500);
		}

		// Keep track of what we just loaded
		is_loaded($class);

		$_classes[$class] = new $name();
		return $_classes[$class];
	}
}

// --------------------------------------------------------------------

/**
* Keeps track of which libraries have been loaded.  This function is
* called by the load_class() function above
*
* @access	public
* @return	array
*/
if ( ! function_exists('is_loaded'))
{
	function &is_loaded($class = '')
	{
		static $_is_loaded = array();

		if ($class != '')
		{
			$_is_loaded[strtolower($class)] = $class;
		}

		return $_is_loaded;
	}
}

// ------------------------------------------------------------------------

/**
* 404 Page Handler
*
* This function is similar to the show_ error() function above
* However, instead of the standard error template it displays
* 404 errors.
*
* @access	public
* @return	void
*/
if ( ! function_exists('show_404'))
{
	function show_404($page = '')
	{
		$_error =& load_class('Exceptions');
		$_error->show_404($page);
		exit;
	}
}

// ------------------------------------------------------------------------

/**
* Error Logging Interface
*
* We use this as a simple mechanism to access the logging
* class and send messages to be logged.
*
* @access	public
* @return	void
*/
if ( ! function_exists('log_message'))
{
	function log_message($level = 'error', $message, $php_error = FALSE)
	{
		static $_log;

		$_log =& load_class('Log', 'libraries', 'CI_');
		$_log->write_log($level, $message, $php_error);
	}
}

// ------------------------------------------------------------------------

/**
 * Set HTTP Status Header
 *
 * @access	public
 * @param	int		the status code
 * @param	string
 * @return	void
 */
if ( ! function_exists('set_status_header'))
{
	function set_status_header($code = 200, $text = '')
	{
		$stati = array(
							200	=> 'OK',
							201	=> 'Created',
							202	=> 'Accepted',
							203	=> 'Non-Authoritative Information',
							204	=> 'No Content',
							205	=> 'Reset Content',
							206	=> 'Partial Content',

							300	=> 'Multiple Choices',
							301	=> 'Moved Permanently',
							302	=> 'Found',
							304	=> 'Not Modified',
							305	=> 'Use Proxy',
							307	=> 'Temporary Redirect',

							400	=> 'Bad Request',
							401	=> 'Unauthorized',
							403	=> 'Forbidden',
							404	=> 'Not Found',
							405	=> 'Method Not Allowed',
							406	=> 'Not Acceptable',
							407	=> 'Proxy Authentication Required',
							408	=> 'Request Timeout',
							409	=> 'Conflict',
							410	=> 'Gone',
							411	=> 'Length Required',
							412	=> 'Precondition Failed',
							413	=> 'Request Entity Too Large',
							414	=> 'Request-URI Too Long',
							415	=> 'Unsupported Media Type',
							416	=> 'Requested Range Not Satisfiable',
							417	=> 'Expectation Failed',

							500	=> 'Internal Server Error',
							501	=> 'Not Implemented',
							502	=> 'Bad Gateway',
							503	=> 'Service Unavailable',
							504	=> 'Gateway Timeout',
							505	=> 'HTTP Version Not Supported'
						);

		if ($code == '' OR ! is_numeric($code)){
			_html_error('Status codes must be numeric');
		}

		if (isset($stati[$code]) AND $text == ''){
			$text = $stati[$code];
		}

		if ($text == ''){
			_html_error('No status text available. Please check your status code number or supply your own message text.');
		}

		$server_protocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : FALSE;

		if (substr(php_sapi_name(), 0, 3) == 'cgi')
		{
			header("Status: {$code} {$text}", TRUE);
		}
		elseif ($server_protocol == 'HTTP/1.1' OR $server_protocol == 'HTTP/1.0')
		{
			header($server_protocol." {$code} {$text}", TRUE, $code);
		}
		else
		{
			header("HTTP/1.1 {$code} {$text}", TRUE, $code);
		}
	}
}

// --------------------------------------------------------------------

/**
* Exception Handler
*
* This is the custom exception handler that is declaired at the top
* of Codeigniter.php.  The main reason we use this is to permit
* PHP errors to be logged in our own log files since the user may
* not have access to server logs. Since this function
* effectively intercepts PHP errors, however, we also need
* to display errors based on the current error_reporting level.
* We do that with the use of a PHP error template.
*
* @access	private
* @return	void
*/
if ( ! function_exists('_exception_handler'))
{
	function _exception_handler($severity, $message, $filepath, $line){
		ini_set('display_errors', '0');
		
		if (!empty($GLOBALS['config']['errors_log'])){
			ini_set('error_log', $GLOBALS['config']['errors_log']);
		}

		// We don't bother with "strict" notices
		if ($severity == E_STRICT) {
			return false;
		}

		$_error =& load_class('Exceptions');
		
		$levels = [
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
				E_STRICT			=>	'Runtime Notice',
		];
		
		$severity = $levels[$severity] ?? $severity;
		
		$filepath = str_replace('\\', '/', $filepath);
		if (stristr($filepath, '/')){
			$x = explode('/', $filepath);
			$filepath = $x[count($x)-2].'/'.end($x);
		}
		
		if (!empty($GLOBALS['config']['errors_visible'])){
			
			$error_text = 	'<b>A PHP Error was encountered</b>'."\n".
							'Severity: '.$severity."\n".
							'Message: '.$message."\n";

			_html_error($error_text, 0, ['location' => $filepath.':'.$line]);
		
		}

		$_error->log_exception($severity, $message, $filepath, $line);

		return false;
		
	}
}

// --------------------------------------------------------------------

/**
 * Remove Invisible Characters
 *
 * This prevents sandwiching null characters
 * between ascii characters, like Java\0script.
 *
 * @access	public
 * @param	string
 * @return	string
 */
if ( ! function_exists('remove_invisible_characters'))
{
	function remove_invisible_characters($str, $url_encoded = TRUE)
	{
		$non_displayables = array();
		
		// every control character except newline (dec 10)
		// carriage return (dec 13), and horizontal tab (dec 09)
		
		if ($url_encoded)
		{
			$non_displayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
			$non_displayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
		}
		
		$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

		do
		{
			$str = preg_replace($non_displayables, '', $str, -1, $count);
		}
		while ($count);

		return $str;
	}
}

// ------------------------------------------------------------------------

/**
* Returns HTML escaped variable
*
* @access	public
* @param	mixed
* @return	mixed
*/
if ( ! function_exists('html_escape'))
{
	function html_escape($var)
	{
		if (is_array($var))
		{
			return array_map('html_escape', $var);
		}
		else
		{
			return htmlspecialchars($var, ENT_QUOTES, config_item('charset'));
		}
	}
}

/* End of file Common.php */
/* Location: ./system/core/Common.php */