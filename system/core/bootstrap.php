<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

	set_error_handler('_exception_handler');

/*
 * ------------------------------------------------------
 *  Instantiate the UTF-8 class
 * ------------------------------------------------------
 *
 * Note: Order here is rather important as the UTF-8
 * class needs to be used very early on, but it cannot
 * properly determine if UTf-8 can be supported until
 * after the  class is instantiated.
 *
 */

	$UNI =& load_class('Utf8');

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
	$RTR->_set_routing();


	$OUT =& load_class('Output');

/*
 * ------------------------------------------------------
 *	Is there a valid cache file?  If so, we're done...
 * ------------------------------------------------------
 */

	if ($OUT->_display_cache($CFG, $URI) == TRUE)
		{
			exit;
		}

/*
 * ------------------------------------------------------
 *  Load the Input class and sanitize globals
 * ------------------------------------------------------
 */
	$IN	=& load_class('Input');

	$class = $RTR->class;
	$method = $RTR->method;
	$params = $RTR->params;
	
	include($GLOBALS['config']['base_path'].'system/core/page.php');
		
	if ($class == 'index' && $method == 'index'){
		
		$GLOBALS['page'] = new page();
		
	} else {

		$not_found = true;
		
		foreach($GLOBALS['config']['modules'] as $module){
			if (file_exists($GLOBALS['config']['base_path'].'modules/'.$module.'/controllers/'.$class.'.php')){
				
				$not_found = false;
				
				include($GLOBALS['config']['base_path'].'modules/'.$module.'/controllers/'.$class.'.php');
				
				if ( !class_exists($class) OR strncmp($method, '_', 1) == 0
						OR in_array(strtolower($method), array_map('strtolower', get_class_methods('page')))){
					
					show_404("{$class}/{$method}");
				
				}
				
				$GLOBALS['page'] = new $class();

				continue;
			
			}
		}
		
		if ($not_found){
			show_error('Unable to load your controller: '.$class.'/'.$method);
		}
		
	}
	
	$GLOBALS['page']->$method($params);

	print('x');
	
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