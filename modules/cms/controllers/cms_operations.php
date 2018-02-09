<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_operations extends MY_Controller {
		
    public function __construct() {
    	
        parent::__construct();        

        $this->js[] = array('script' => 'modules/cms/js/jquery/jquery-ui.min.js', );

        $this->js[] = 'js/preloader.js';

        $this->js[] = 'modules/cms/js/cms.js';
  
        $GLOBALS['_panel_titles'][] = 'ADMIN';
   	}
	
   	// recreate routes
   	function update_routes(){
   		
   		$this->load->model('cms_slug_model');
   		$this->cms_slug_model->_regenerate_cache();
   		
   		header('Location: '.$_SERVER['REQUEST_URI']);
   		
   	}
   	
   	function cron(){
   		
   		$this->load->model('cms/cms_helper_model');
   		
   		$this->cms_helper_model->run_cron();
   		
   	}

}
