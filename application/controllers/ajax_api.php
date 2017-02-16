<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ajax_api extends MY_Controller {
	
    public function __construct() {
    	
        parent::__construct();
        $this->params = $this->input->post();
   	}
   	
   	function get_panel(){
   		if (empty($this->params['panel_id'])){
    		print(json_encode(array('result' => array(), 'error' => array('message' => 'Missing panel_id', ))));
    		exit();
   		}
   		
    	if (extension_loaded('newrelic')) {
    		
			newrelic_set_appname($GLOBALS['config']['title']);
			newrelic_name_transaction('/ajax_api/'.$this->params['panel_id']);

		}
   		
		$data = $this->ajax_panel($this->params['panel_id'], $this->params);
		print(json_encode(array('result' => $data, )));
   	}
    
}
