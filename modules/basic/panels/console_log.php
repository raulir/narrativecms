<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class console_log extends MY_Controller{
	
	function panel_action($params){

		$do = $this->input->post('do');
		
        if ($do == 'console_log_write'){

        	$message = $this->input->post('message');

        	$file = fopen($GLOBALS['config']['base_path'].'cache/console.log', 'a');

       		fwrite($file, "\n" . date('Y-m-d H:i:s') . ' ' . $message);
       		
       		fclose($file);

        }
		
	}

}
