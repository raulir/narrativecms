<?php

namespace basic;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class console_log extends \Controller {
	
	function panel_action($params){

		$do = $params['cms_do'] ?? false;
		
        if ($do == 'console_log_write'){

        	$message = $params['message'] ?? false;

        	$file = fopen(cms_path('log').'console.log', 'a');

       		fwrite($file, "\n" . date('Y-m-d H:i:s') . ' ' . $message);
       		
       		fclose($file);

        }
		
	}

}
