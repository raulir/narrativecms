<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_console_log extends MY_Controller{
	
	function panel_action($params){

		$do = $this->input->post('do');
		
        if ($do == 'cms_console_log_write'){
        	
        	$message = $this->input->post('message');
        	
//        	if (!empty($params['filename'])){
        		
        		$file = fopen($GLOBALS['config']['base_path'].'cache/console.log', 'a'); // '.$params['filename'], 'a');

       			fwrite($file, "\n" . date('Y-m-d H:i:s') . ' ' . $message);
       			fclose($file);
        		
//        	}

        }
		
	}

}
