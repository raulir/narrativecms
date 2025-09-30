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

if ( ! function_exists('_exception_handler'))
{
	function _exception_handler($severity, $message, $filepath, $line){
		ini_set('display_errors', '0');
		
		if (!empty($GLOBALS['config']['errors_log'])){
			ini_set('error_log', ($GLOBALS['config']['base_path'].$GLOBALS['config']['errors_log']));
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

//		$_error->log_exception($severity, $message, $filepath, $line);

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