<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_operations extends CI_Controller {
		
    public function __construct() {
    	
        parent::__construct();        

        $GLOBALS['_panel_js'][] = array('script' => 'modules/cms/js/jquery/jquery-ui.min.js', );

		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_preloader.js';
        $GLOBALS['_panel_js'][] = 'modules/cms/js/cms.js';
  
        $GLOBALS['_panel_titles'][] = 'ADMIN';
   	}
	
   	// recreate routes
   	function update_routes(){
   		
   		$this->load->model('cms/cms_slug_model');
   		$this->cms_slug_model->_regenerate_cache();
   		
   		header('Location: '.$_SERVER['REQUEST_URI']);
   		
   	}
   	
   	function cron(){
   		
   		$this->load->model('cms/cms_helper_model');
   		
   		$this->cms_helper_model->run_cron();
   		
   	}

}
