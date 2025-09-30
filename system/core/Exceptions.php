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

//		log_message('error', 'Severity: '.$severity.'  --> '.$message. ' '.$filepath.' '.$line, TRUE);
		_html_error("Unable to load the requested class: ".$class, 500);
	}

	// --------------------------------------------------------------------

	/**
	 * 404 Page Not Found Handler
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

		_html_error(''.$heading.' - '.$message, 404, ['backtrace' => 2]);
	}

}
