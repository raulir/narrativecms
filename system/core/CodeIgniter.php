<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * System Initialization File
 *
 * Loads the base classes and executes the request.
 *
 * @package		CodeIgniter
 * @subpackage	codeigniter
 * @category	Front-controller
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/
 */

/*
 * ------------------------------------------------------
 *  Load the global functions
 * ------------------------------------------------------
 */
	require BASEPATH.'core/cms_bootstrap.php';

/*
 * ------------------------------------------------------
 *  Define a custom error handler so we can log PHP errors
 * ------------------------------------------------------
 */
	set_error_handler('_exception_handler');

/*
 * ------------------------------------------------------
 *  Instantiate the URI class
 * ------------------------------------------------------
 */
	$URI =& load_class('URI');

/*
 * ------------------------------------------------------
 *  Instantiate the routing class and set the routing
 * ------------------------------------------------------
 */
	$RTR =& load_class('Router');

	// Early cms_route_resolve() result from cms.php (DB-backed public slugs).
	$cms_route = !empty($GLOBALS['cms_route']) && is_array($GLOBALS['cms_route'])
		? $GLOBALS['cms_route']
		: null;
	$cms_route_ready = $cms_route
		&& !empty($cms_route['controller'])
		&& !empty($cms_route['method']);

	if ($cms_route_ready)
	{
		$RTR->set_class($cms_route['controller']);
		$RTR->set_method($cms_route['method']);
		// 1-based rsegments: class, method, …args
		$rseg = array(
			1 => $cms_route['controller'],
			2 => $cms_route['method'],
		);
		$argi = 3;
		foreach (($cms_route['args'] ?? array()) as $arg)
		{
			$rseg[$argi++] = $arg;
		}
		$URI->rsegments = $rseg;
		$URI->uri_string = isset($GLOBALS['cms_request_uri']) ? $GLOBALS['cms_request_uri'] : '';
	}
	else
	{
		$RTR->_set_routing();

		// Set any routing overrides that may exist in the main index file
		if (isset($routing))
		{
			$RTR->_set_overrides($routing);
		}
	}

/*
 * ------------------------------------------------------
 *  Instantiate the output class
 * ------------------------------------------------------
 */
	$OUT =& load_class('Output');

/*
 * ------------------------------------------------------
 *  Load the Input class and sanitize globals
 * ------------------------------------------------------
 */
	$IN	=& load_class('Input');

/*
 * ------------------------------------------------------
 *  Load the app controller and local controller
 * ------------------------------------------------------
 *
 */	
	
	// Load the base controller class
	require BASEPATH.'core/controller.php';
	
	// Load the local application controller
	// Note: The Router class automatically validates the controller path using the router->_validate_request().
	// If this include fails it means that the default controller in the Routes.php file is not resolving to something valid.
	$class = $RTR->fetch_class();
	// Instantiable class name (may become module\class after namespaced controller load)
	$controller_class = $class;

	if (!file_exists($GLOBALS['config']['base_path'].'system/core/controller_'.$RTR->fetch_directory().$class.'.php')) {
		
		$not_found = true;
		
		foreach($GLOBALS['config']['modules'] as $module){
			if (file_exists($GLOBALS['config']['base_path'].'modules/'.$module.'/controllers/'.$class.'.php')){
				
				$not_found = false;
				
				include($GLOBALS['config']['base_path'].'modules/'.$module.'/controllers/'.$class.'.php');

				// Prefer namespaced class module\controller when present
				if (class_exists($module.'\\'.$class, false)){
					$controller_class = $module.'\\'.$class;
				}
				
				break;
			
			}
		}
		
		if ($not_found){
			_html_error('Unable to load your default controller. Please make sure the controller specified in your Routes.php file is valid.', 500);
		}
		
	} else {

		include($GLOBALS['config']['base_path'].'system/core/controller_'.$RTR->fetch_directory().$class.'.php');
	
	}
	
/*
 * ------------------------------------------------------
 *  Security check
 * ------------------------------------------------------
 *
 *  None of the functions in the app controller or the
 *  loader class can be called via the URI, nor can
 *  controller functions that begin with an underscore
 */
	$method = $RTR->fetch_method();

	if ( ! class_exists($controller_class)
		OR strncmp($method, '_', 1) == 0
		OR in_array(strtolower($method), array_map('strtolower', get_class_methods('Controller')))
		)
	{
		if ( ! empty($RTR->routes['404_override']))
		{
			$x = explode('/', $RTR->routes['404_override']);
			$class = $x[0];
			$controller_class = $class;
			$method = (isset($x[1]) ? $x[1] : 'index');
			if ( ! class_exists($controller_class))
			{
				if ( ! file_exists($GLOBALS['config']['base_path'].'system/core/controller_'.$class.'.php'))
				{
					show_404("{$class}/{$method}");
				}

				include_once($GLOBALS['config']['base_path'].'system/core/controller_'.$class.'.php');
			}
		}
		else
		{
			show_404("{$class}/{$method}");
		}
	}

	$CI = new $controller_class();

	if (defined('CMS_CLI_BOOTSTRAP')){
		return;
	}

	// Public slug not in DB — soft 404
	if ($cms_route_ready && ($cms_route['kind'] ?? '') === 'not_found')
	{
		$nf = !empty($cms_route['slug']) ? $cms_route['slug'] : ($URI->uri_string ?? '');
		show_404($nf);
	}

/*
 * ------------------------------------------------------
 *  Call the requested method
 * ------------------------------------------------------
 */
	// Is there a "remap" function? If so, we call it instead
	if (method_exists($CI, '_remap'))
	{
		$remap_args = ($cms_route_ready && array_key_exists('args', $cms_route))
			? $cms_route['args']
			: array_slice($URI->rsegments, 2);
		$CI->_remap($method, $remap_args);
	}
	else
	{
		// is_callable() returns TRUE on some versions of PHP 5 for private and protected
		// methods, so we'll use this workaround for consistent behavior
		if ( ! in_array(strtolower($method), array_map('strtolower', get_class_methods($CI))))
		{
			// Check and see if we are using a 404 override and use it.
			if ( ! empty($RTR->routes['404_override']))
			{
				$x = explode('/', $RTR->routes['404_override']);
				$class = $x[0];
				$method = (isset($x[1]) ? $x[1] : 'index');
				if ( ! class_exists($class))
				{
					if ( ! file_exists($GLOBALS['config']['base_path'].'system/core/controller_'.$class.'.php'))
					{
						show_404("{$class}/{$method}");
					}

					include_once($GLOBALS['config']['base_path'].'system/core/controller_'.$class.'.php');
					unset($CI);
					$CI = new $class();
				}
			}
			else
			{
				show_404("{$class}/{$method}");
			}
		}

		// Call the requested method.
		// Prefer early cms_route args when present (single target string for Index)
		if ($cms_route_ready && array_key_exists('args', $cms_route))
		{
			call_user_func_array(array(&$CI, $method), $cms_route['args']);
		}
		else
		{
			// Any URI segments present (besides the class/function) will be passed to the method for convenience
			call_user_func_array(array(&$CI, $method), array_slice($URI->rsegments, 2));
		}
	}

/*
 * ------------------------------------------------------
 *  Send the final rendered output to the browser
 * ------------------------------------------------------
 */

	$OUT->_display();

/*
 * ------------------------------------------------------
 *  Close the DB connection if one exists
 * ------------------------------------------------------
 */
	if (class_exists('CI_DB') AND isset($CI->db))
	{
		$CI->db->close();
	}


/* End of file CodeIgniter.php */
/* Location: ./system/core/CodeIgniter.php */