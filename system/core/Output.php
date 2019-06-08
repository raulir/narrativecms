<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Output Class
 *
 * Responsible for sending final output to browser
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Output
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/output.html
 */
class CI_Output {

	/**
	 * Current output string
	 *
	 * @var string
	 * @access 	protected
	 */
	protected $final_output;
	/**
	 * Cache expiration time
	 *
	 * @var int
	 * @access 	protected
	 */
	protected $cache_expiration	= 0;
	/**
	 * List of server headers
	 *
	 * @var array
	 * @access 	protected
	 */
	protected $headers			= array();

	/**
	 * Determines if output compression is enabled
	 *
	 * @var bool
	 * @access 	protected
	 */
	protected $_zlib_oc			= FALSE;

	/**
	 * Whether or not to parse variables like {elapsed_time} and {memory_usage}
	 *
	 * @var bool
	 * @access 	protected
	 */
	protected $parse_exec_vars	= TRUE;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		$this->_zlib_oc = @ini_get('zlib.output_compression');

		log_message('debug', "Output Class Initialized");
	}

	// --------------------------------------------------------------------

	/**
	 * Get Output
	 *
	 * Returns the current output string
	 *
	 * @access	public
	 * @return	string
	 */
	function get_output()
	{
		return $this->final_output;
	}

	// --------------------------------------------------------------------

	/**
	 * Set Output
	 *
	 * Sets the output string
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function set_output($output)
	{
		$this->final_output = $output;

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Append Output
	 *
	 * Appends data onto the output string
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function append_output($output)
	{
		if ($this->final_output == '')
		{
			$this->final_output = $output;
		}
		else
		{
			$this->final_output .= $output;
		}

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Set Header
	 *
	 * Lets you set a server header which will be outputted with the final display.
	 *
	 * Note:  If a file is cached, headers will not be sent.  We need to figure out
	 * how to permit header data to be saved with the cache data...
	 *
	 * @access	public
	 * @param	string
	 * @param 	bool
	 * @return	void
	 */
	function set_header($header, $replace = TRUE)
	{
		// If zlib.output_compression is enabled it will compress the output,
		// but it will not modify the content-length header to compensate for
		// the reduction, causing the browser to hang waiting for more data.
		// We'll just skip content-length in those cases.

		if ($this->_zlib_oc && strncasecmp($header, 'content-length', 14) == 0)
		{
			return;
		}

		$this->headers[] = array($header, $replace);

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Set HTTP Status Header
	 * moved to Common procedural functions in 1.7.2
	 *
	 * @access	public
	 * @param	int		the status code
	 * @param	string
	 * @return	void
	 */
	function set_status_header($code = 200, $text = '')
	{
		set_status_header($code, $text);

		return $this;
	}

	/**
	 * Set Cache
	 *
	 * @access	public
	 * @param	integer
	 * @return	void
	 */
	function cache($time)
	{
		$this->cache_expiration = ( ! is_numeric($time)) ? 0 : $time;

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Display Output
	 *
	 * All "view" data is automatically put into this variable by the controller class:
	 *
	 * $this->final_output
	 *
	 * This function sends the finalized output data to the browser along
	 * with any server headers and profile data.
	 *
	 * @access	public
	 * @param 	string
	 * @return	mixed
	 */
	function _display($output = '')
	{
		// Note:  We use globals because we can't use $CI =& get_instance()
		// since this function is sometimes called by the caching mechanism,
		// which happens before the CI super object is available.
		global $CFG;

		// Grab the super object if we can.
		if (class_exists('CI_Controller'))
		{
			$CI =& get_instance();
		}

		// --------------------------------------------------------------------

		// Set the output data
		if ($output == '')
		{
			$output =& $this->final_output;
		}

		// Are there any server headers to send?
		if (count($this->headers) > 0)
		{
			foreach ($this->headers as $header)
			{
				@header($header[0], $header[1]);
			}
		}

		// --------------------------------------------------------------------

		// Does the $CI object exist?
		// If not we know we are dealing with a cache file so we'll
		// simply echo out the data and exit.
		if ( ! isset($CI))
		{
			echo $output;
			log_message('debug', "Final output sent to browser");
			log_message('debug', "Total execution time: ".$elapsed);
			return TRUE;
		}

		// --------------------------------------------------------------------

		echo $output;  // Send it to the browser!

	}

	// --------------------------------------------------------------------

	/**
	 * Update/serve a cached file
	 *
	 * @access	public
	 * @param 	object	config class
	 * @param 	object	uri class
	 * @return	void
	 * 
	 * deprecated!
	 * 
	 */
	function _display_cache(&$CFG, &$URI) {
		
		return false;

	}


}
// END Output Class

/* End of file Output.php */
/* Location: ./system/core/Output.php */